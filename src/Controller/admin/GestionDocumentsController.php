<?php

namespace App\Controller\admin;

use App\Entity\Document;
use App\Entity\DocumentAdmin;
use App\Form\DepotDocumentAdminType;
use App\Repository\DocumentAdminRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/gestion-documents')]
final class GestionDocumentsController extends AbstractController
{
    /**
     * Extensions de fichier autorisées pour le mapping MIME → extension.
     *
     * OWASP A03 : Validation des entrées — on n'accepte que des extensions connues
     * et on détermine l'extension depuis le MIME réel, pas depuis le nom du fichier.
     */
    private const ALLOWED_EXTENSIONS = [
        'application/pdf'                                                          => 'pdf',
        'image/jpeg'                                                               => 'jpg',
        'image/png'                                                                => 'png',
        'image/webp'                                                               => 'webp',
        'application/msword'                                                       => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                                 => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    ];

    /**
     * Page principale : formulaire de dépôt + documents admins + documents utilisateurs
     */
    #[Route('', name: 'app_gestion_documents', methods: ['GET', 'POST'])]
    public function gestionDocuments(
        Request $request,
        EntityManagerInterface $em,
        DocumentAdminRepository $documentAdminRepository,
        DocumentRepository $documentRepository,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $documentAdmin = new DocumentAdmin();
        $form = $this->createForm(DepotDocumentAdminType::class, $documentAdmin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichierFile = $form->get('fichierFile')->getData();

            if ($fichierFile) {
                // ── Validation du type MIME réel via finfo (OWASP A03) ─────────
                // On lit les "magic bytes" du fichier pour déterminer son type réel,
                // indépendamment du Content-Type envoyé par le navigateur.
                // Protège contre les fichiers malveillants renommés (ex: shell.php → doc.pdf).
                $realMime = $this->getRealMimeType($fichierFile->getPathname());

                if (!isset(self::ALLOWED_EXTENSIONS[$realMime])) {
                    $this->addFlash('danger', 'Type de fichier non autorisé. Formats acceptés : PDF, images, Word, Excel.');
                    return $this->redirectToRoute('app_gestion_documents');
                }

                // Extension déterminée depuis la liste blanche MIME → extension
                // Evite les cas où guessExtension() retourne null ou une valeur inattendue
                $safeExtension    = self::ALLOWED_EXTENSIONS[$realMime];
                $originalFilename = pathinfo($fichierFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $safeExtension;

                $fichierFile->move(
                    $this->getParameter('documents_directory'),
                    $newFilename
                );

                $documentAdmin->setFichier($newFilename);
            }

            /** @var \App\Entity\Utilisateur $admin */
            $admin = $this->getUser();

            $documentAdmin->setDeposeLe(new \DateTimeImmutable());
            $documentAdmin->setDeposePar($admin);

            $em->persist($documentAdmin);
            $em->flush();

            $destinataire = $documentAdmin->getDestinataire();
            $this->addFlash(
                'success',
                sprintf(
                    'Document « %s » déposé pour %s %s.',
                    $documentAdmin->getNom(),
                    $destinataire->getPrenom(),
                    $destinataire->getNom()
                )
            );

            return $this->redirectToRoute('app_gestion_documents');
        }

        return $this->render('admin/gestion_documents/gestion_documents.html.twig', [
            'form'                  => $form,
            'logs'                  => $documentAdminRepository->findBy([], ['deposeLe' => 'DESC']),
            'documentsUtilisateurs' => $documentRepository->findAllWithUtilisateur(),
        ]);
    }

    /**
     * Téléchargement sécurisé d'un document admin (envoyé à un utilisateur).
     */
    #[Route('/telecharger/{id}', name: 'app_download_gestion_documents', methods: ['GET'])]
    public function download(DocumentAdmin $documentAdmin): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filePath = $this->getParameter('documents_directory') . '/' . $documentAdmin->getFichier();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file(
            $filePath,
            $documentAdmin->getNom() . '.' . pathinfo($filePath, PATHINFO_EXTENSION),
            ResponseHeaderBag::DISPOSITION_INLINE
        );
    }

    /**
     * Téléchargement sécurisé d'un document déposé par un utilisateur.
     */
    #[Route('/telecharger-utilisateur/{id}', name: 'app_download_utilisateur_document', methods: ['GET'])]
    public function downloadUtilisateurDocument(Document $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filePath = $this->getParameter('documents_directory') . '/' . $document->getFichier();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file(
            $filePath,
            $document->getNom() . '.' . pathinfo($filePath, PATHINFO_EXTENSION),
            ResponseHeaderBag::DISPOSITION_INLINE
        );
    }

    /**
     * Suppression d'un document envoyé par l'admin à un utilisateur (DocumentAdmin).
     */
    #[Route('/annuler/{id}', name: 'app_annuler_gestion_documents', methods: ['POST'])]
    public function annuler(
        Request $request,
        DocumentAdmin $documentAdmin,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('annuler' . $documentAdmin->getId(), $request->request->get('_token'))) {
            $cheminFichier = $this->getParameter('documents_directory') . '/' . $documentAdmin->getFichier();
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }

            $em->remove($documentAdmin);
            $em->flush();

            $this->addFlash('success', 'Document supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_gestion_documents');
    }

    /**
     * Suppression d'un document déposé par un utilisateur (Document).
     */
    #[Route('/supprimer-document-utilisateur/{id}', name: 'app_supprimer_document_utilisateur', methods: ['POST'])]
    public function supprimerDocumentUtilisateur(
        Request $request,
        Document $document,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('supprimer-doc-utilisateur' . $document->getId(), $request->request->get('_token'))) {
            $cheminFichier = $this->getParameter('documents_directory') . '/' . $document->getFichier();
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }

            $em->remove($document);
            $em->flush();

            $this->addFlash('success', 'Document utilisateur supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_gestion_documents');
    }

    /**
     * Détecte le type MIME réel du fichier en lisant son contenu via finfo.
     *
     * OWASP A03 : Validation des entrées — ne pas faire confiance au Content-Type
     * déclaré par le navigateur ni à l'extension du fichier. finfo lit les "magic bytes"
     * pour déterminer le type réel du fichier, indépendamment de son nom.
     *
     * @param string $filePath chemin absolu vers le fichier temporaire
     * @return string type MIME réel (ex: 'application/pdf')
     */
    private function getRealMimeType(string $filePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath) ?: 'application/octet-stream';
    }
}