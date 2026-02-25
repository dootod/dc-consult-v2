<?php

namespace App\Controller\utilisateur;

use App\Entity\Document;
use App\Entity\DocumentAdmin;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Repository\DocumentAdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/mon-espace/mes-documents')]
final class DocumentController extends AbstractController
{
    /**
     * Extensions de fichier autorisées pour le mapping MIME → extension.
     * Utilisé pour sécuriser le nommage des fichiers uploadés contre les cas
     * où guessExtension() retourne null ou une extension inattendue.
     *
     * OWASP A03 : Validation des entrées — on n'accepte que des extensions connues.
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

    #[Route(name: 'app_mes_documents', methods: ['GET'])]
    public function index(DocumentRepository $documentRepository, DocumentAdminRepository $documentAdminRepository): Response
    {
        /** @var \App\Entity\Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        return $this->render('utilisateur/document/index.html.twig', [
            'documents'      => $documentRepository->findBy(
                ['utilisateur' => $utilisateur],
                ['date' => 'DESC']
            ),
            'documentsAdmin' => $documentAdminRepository->findBy(
                ['destinataire' => $utilisateur],
                ['deposeLe' => 'DESC']
            ),
        ]);
    }

    #[Route('/voir/{id}', name: 'app_show_mes_documents', methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        return $this->render('utilisateur/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    /**
     * Téléchargement sécurisé d'un document personnel.
     * Vérifié via le DocumentVoter — seul le propriétaire (ou admin) y a accès.
     */
    #[Route('/telecharger/{id}', name: 'app_download_mes_documents', methods: ['GET'])]
    public function download(Document $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

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
     * Téléchargement sécurisé d'un document reçu de l'admin (DocumentAdmin).
     * Seul le destinataire peut y accéder.
     */
    #[Route('/telecharger-recu/{id}', name: 'app_download_document_admin', methods: ['GET'])]
    public function downloadDocumentAdmin(DocumentAdmin $documentAdmin): BinaryFileResponse
    {
        /** @var \App\Entity\Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        // IDOR : seul le destinataire du document peut le télécharger
        if ($documentAdmin->getDestinataire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Accès interdit à ce document.');
        }

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

    #[Route('/nouveau', name: 'app_new_mes_documents', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $document = new Document();
        $form     = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\Utilisateur $utilisateur */
            $utilisateur = $this->getUser();

            $fichierFile = $form->get('fichierFile')->getData();

            if ($fichierFile) {
                // ── Validation du type MIME réel via finfo (OWASP A03) ─────────
                // guessExtension() utilise déjà finfo internement dans Symfony,
                // mais on double la validation avec finfo_file() pour s'assurer
                // que l'extension correspondante est dans la liste blanche.
                // Cela protège contre les fichiers dont le contenu ne correspond
                // pas à l'extension déclarée par le client.
                $realMime = $this->getRealMimeType($fichierFile->getPathname());

                if (!isset(self::ALLOWED_EXTENSIONS[$realMime])) {
                    $this->addFlash('danger', 'Type de fichier non autorisé. Formats acceptés : PDF, images, Word, Excel.');
                    return $this->redirectToRoute('app_new_mes_documents');
                }

                // Extension déterminée depuis la liste blanche MIME → extension
                // Evite les cas où guessExtension() retourne null ou une extension inattendue
                $safeExtension = self::ALLOWED_EXTENSIONS[$realMime];

                $originalFilename = pathinfo($fichierFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $safeExtension;

                $fichierFile->move(
                    $this->getParameter('documents_directory'),
                    $newFilename
                );

                $document->setFichier($newFilename);
            }

            $document->setDate(new \DateTime());
            $document->setUtilisateur($utilisateur);

            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', 'Document ajouté avec succès !');

            return $this->redirectToRoute('app_mes_documents', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('utilisateur/document/new.html.twig', [
            'document' => $document,
            'form'     => $form,
        ]);
    }

    #[Route('/modifier/{id}', name: 'app_edit_mes_documents', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('DOCUMENT_EDIT', $document);

        $form = $this->createForm(DocumentType::class, $document, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichierFile = $form->get('fichierFile')->getData();

            if ($fichierFile) {
                // ── Validation du type MIME réel (même protection que new()) ──
                $realMime = $this->getRealMimeType($fichierFile->getPathname());

                if (!isset(self::ALLOWED_EXTENSIONS[$realMime])) {
                    $this->addFlash('danger', 'Type de fichier non autorisé. Formats acceptés : PDF, images, Word, Excel.');
                    return $this->redirectToRoute('app_edit_mes_documents', ['id' => $document->getId()]);
                }

                $safeExtension = self::ALLOWED_EXTENSIONS[$realMime];

                // Suppression de l'ancien fichier physique
                $ancienFichier = $this->getParameter('documents_directory') . '/' . $document->getFichier();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }

                $originalFilename = pathinfo($fichierFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                // ✅ bin2hex(random_bytes(8)) cryptographiquement aléatoire
                $newFilename = $safeFilename . '-' . bin2hex(random_bytes(8)) . '.' . $safeExtension;

                $fichierFile->move(
                    $this->getParameter('documents_directory'),
                    $newFilename
                );

                $document->setFichier($newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Document modifié avec succès !');

            return $this->redirectToRoute('app_mes_documents', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('utilisateur/document/edit.html.twig', [
            'document' => $document,
            'form'     => $form,
        ]);
    }

    #[Route('/supprimer/{id}', name: 'app_delete_mes_documents', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('DOCUMENT_DELETE', $document);

        if ($this->isCsrfTokenValid('delete' . $document->getId(), $request->getPayload()->getString('_token'))) {
            $cheminFichier = $this->getParameter('documents_directory') . '/' . $document->getFichier();
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }

            $entityManager->remove($document);
            $entityManager->flush();

            $this->addFlash('success', 'Document supprimé avec succès !');
        } else {
            $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_mes_documents', [], Response::HTTP_SEE_OTHER);
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