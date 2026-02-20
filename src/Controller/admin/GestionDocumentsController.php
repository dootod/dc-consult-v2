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
                $originalFilename = pathinfo($fichierFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $fichierFile->guessExtension();

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
        }

        return $this->redirectToRoute('app_gestion_documents');
    }
}