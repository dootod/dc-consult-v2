<?php

namespace App\Controller\utilisateur;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/mon-espace/mes-documents')]
final class DocumentController extends AbstractController
{
    #[Route(name: 'app_mes_documents', methods: ['GET'])]
    public function index(DocumentRepository $documentRepository): Response
    {
        /** @var \App\Entity\Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        return $this->render('utilisateur/document/index.html.twig', [
            'documents' => $documentRepository->findBy(
                ['utilisateur' => $utilisateur],
                ['date' => 'DESC']
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

    #[Route('/nouveau', name: 'app_new_mes_documents', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var \App\Entity\Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Fichier uploadé
            $fichierFile = $form->get('fichierFile')->getData();

            if ($fichierFile) {
                $originalFilename = pathinfo($fichierFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $fichierFile->guessExtension();

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
                $ancienFichier = $this->getParameter('documents_directory') . '/' . $document->getFichier();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }

                $originalFilename = pathinfo($fichierFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $fichierFile->guessExtension();

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
        }

        return $this->redirectToRoute('app_mes_documents', [], Response::HTTP_SEE_OTHER);
    }
}