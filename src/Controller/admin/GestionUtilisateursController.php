<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Repository\DocumentAdminRepository;
use App\Repository\DocumentRepository;
use App\Entity\Document;
use App\Entity\DocumentAdmin;
use App\Entity\Utilisateur;
use App\Form\NewUtilisateurType;
use App\Form\EditUtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Route('/admin/gestion-utilisateurs')]
final class GestionUtilisateursController extends AbstractController
{
    // ── MODIFIÉ : calcul du nb de docs reçus par utilisateur pour afficher le total dans le tableau ──
    #[Route(name: 'app_gestion_utilisateurs', methods: ['GET'])]
    public function gestionUtilisateurs(UtilisateurRepository $utilisateurRepository, DocumentAdminRepository $documentAdminRepository): Response
    {
        $utilisateurs = $utilisateurRepository->findAll();

        // Précalcul en PHP pour éviter les N+1 queries dans Twig
        $documentsRecusParUtilisateur = [];
        foreach ($documentAdminRepository->findAll() as $docAdmin) {
            $destId = $docAdmin->getDestinataire()?->getId();
            if ($destId !== null) {
                $documentsRecusParUtilisateur[$destId] = ($documentsRecusParUtilisateur[$destId] ?? 0) + 1;
            }
        }
        
        return $this->render('admin/gestion_utilisateurs/gestion_utilisateurs.html.twig', [
            'utilisateurs'                 => $utilisateurs,
            'documentsRecusParUtilisateur' => $documentsRecusParUtilisateur,
        ]);
    }

    // ── MODIFIÉ : on récupère aussi les documents déposés par l'utilisateur ──
    #[Route('/voir/{id}', name: 'app_show_gestion_utilisateurs', methods: ['GET'])]
    public function show(Utilisateur $utilisateur, DocumentAdminRepository $documentAdminRepository, DocumentRepository $documentRepository): Response
    {
        $documentsRecus   = $documentAdminRepository->findBy(['destinataire' => $utilisateur], ['deposeLe' => 'DESC']);
        $documentsDeposes = $documentRepository->findBy(['utilisateur' => $utilisateur], ['date' => 'DESC']);
        
        return $this->render('admin/gestion_utilisateurs/show.html.twig', [
            'utilisateur'      => $utilisateur,
            'documentsRecus'   => $documentsRecus,
            'documentsDeposes' => $documentsDeposes,
        ]);
    }

    // ── NOUVEAU : suppression d'un document reçu (DocumentAdmin) depuis la fiche utilisateur ──
    #[Route('/voir/{utilisateurId}/supprimer-recu/{id}', name: 'app_delete_document_recu_utilisateur', methods: ['POST'])]
    public function deleteDocumentRecu(
        Request $request,
        int $utilisateurId,
        DocumentAdmin $documentAdmin,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Vérification IDOR : le document doit appartenir à l'utilisateur de l'URL
        if ($documentAdmin->getDestinataire()?->getId() !== $utilisateurId) {
            throw $this->createAccessDeniedException('Ce document n\'appartient pas à cet utilisateur.');
        }

        if ($this->isCsrfTokenValid('delete-recu' . $documentAdmin->getId(), $request->getPayload()->getString('_token'))) {
            $chemin = $this->getParameter('documents_directory') . '/' . $documentAdmin->getFichier();
            if (file_exists($chemin)) {
                unlink($chemin);
            }

            $em->remove($documentAdmin);
            $em->flush();

            $this->addFlash('success', 'Document reçu supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_show_gestion_utilisateurs', ['id' => $utilisateurId]);
    }

    // ── NOUVEAU : suppression d'un document déposé (Document) depuis la fiche utilisateur ──
    #[Route('/voir/{utilisateurId}/supprimer-depose/{id}', name: 'app_delete_document_depose_utilisateur', methods: ['POST'])]
    public function deleteDocumentDepose(
        Request $request,
        int $utilisateurId,
        Document $document,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Vérification IDOR : le document doit appartenir à l'utilisateur de l'URL
        if ($document->getUtilisateur()?->getId() !== $utilisateurId) {
            throw $this->createAccessDeniedException('Ce document n\'appartient pas à cet utilisateur.');
        }

        if ($this->isCsrfTokenValid('delete-depose' . $document->getId(), $request->getPayload()->getString('_token'))) {
            $chemin = $this->getParameter('documents_directory') . '/' . $document->getFichier();
            if (file_exists($chemin)) {
                unlink($chemin);
            }

            $em->remove($document);
            $em->flush();

            $this->addFlash('success', 'Document déposé supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_show_gestion_utilisateurs', ['id' => $utilisateurId]);
    }

    // ── IDENTIQUE à l'original ──
    #[Route('/nouveau', name: 'app_new_gestion_utilisateurs', methods: ['GET', 'POST'])]
    public function new(
        Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $utilisateur = new Utilisateur();

        $form = $this->createForm(NewUtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $plainPassword = $form->get('password')->getData();
            
            $hashedPassword = $passwordHasher->hashPassword(
                $utilisateur,
                $plainPassword
            );
            $utilisateur->setPassword($hashedPassword);
            
            $roleChoice = $form->get('role_choice')->getData();
            
            if ($roleChoice === 'ROLE_ADMIN') {
                $utilisateur->setRoles(['ROLE_ADMIN']);
            } else {
                $utilisateur->setRoles([]);
            }
            
            $manager->persist($utilisateur);
            $manager->flush();
            
            $this->addFlash('success', 'Utilisateur ajouté avec succès !');

            return $this->redirectToRoute('app_show_gestion_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/new.html.twig', [
            'form' => $form,
            'utilisateur' => $utilisateur,
        ]);
    }

    // ── IDENTIQUE à l'original ──
    #[Route('/modifier/{id}', name: 'app_edit_gestion_utilisateurs', methods: ['GET', 'POST'])]
    public function edit(Utilisateur $utilisateur, Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $passwordHasher): Response 
    {
        $form = $this->createForm(EditUtilisateurType::class, $utilisateur);

        $currentRole = in_array('ROLE_ADMIN', $utilisateur->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_USER';
        $form->get('role_choice')->setData($currentRole);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $plainPassword = $form->get('password')->getData();
            if (!empty($plainPassword)) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $utilisateur,
                    $plainPassword
                );
                $utilisateur->setPassword($hashedPassword);
            }
            
            $roleChoice = $form->get('role_choice')->getData();
            
            if ($roleChoice === 'ROLE_ADMIN') {
                $utilisateur->setRoles(['ROLE_ADMIN']);
            } else {
                $utilisateur->setRoles([]);
            }
            
            $manager->flush();
            
            $this->addFlash('success', 'Utilisateur modifié avec succès !');

            return $this->redirectToRoute('app_show_gestion_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/edit.html.twig', [
            'form' => $form,
            'utilisateur' => $utilisateur,
        ]);
    }

    // ── MODIFIÉ : suppression des fichiers physiques avant remove() ──
    #[Route('/supprimer/{id}', name: 'app_delete_gestion_utilisateurs', methods: ['POST'])]
    public function delete(
        Request $request, 
        Utilisateur $utilisateur, 
        EntityManagerInterface $manager,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $dispatcher,
        DocumentRepository $documentRepository,
        DocumentAdminRepository $documentAdminRepository
    ): Response
    {
        if ($this->isCsrfTokenValid('delete'.$utilisateur->getId(), $request->getPayload()->getString('_token'))) {

            // ── Suppression physique des fichiers des documents déposés par l'utilisateur ──
            foreach ($documentRepository->findBy(['utilisateur' => $utilisateur]) as $document) {
                $chemin = $this->getParameter('documents_directory') . '/' . $document->getFichier();
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
            }

            // ── Suppression physique des fichiers des documents reçus par l'utilisateur ──
            foreach ($documentAdminRepository->findBy(['destinataire' => $utilisateur]) as $docAdmin) {
                $chemin = $this->getParameter('documents_directory') . '/' . $docAdmin->getFichier();
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
            }

            $currentUser = $this->getUser();
            $isSelfDelete = $currentUser instanceof Utilisateur && $currentUser->getId() === $utilisateur->getId();
            
            $manager->remove($utilisateur);
            $manager->flush();

            // ✅ CORRECTIF conservé : Si l'admin supprime son propre compte, on le déconnecte
            if ($isSelfDelete) {
                $tokenStorage->setToken(null);
                $dispatcher->dispatch(new LogoutEvent($request, null));
                return $this->redirectToRoute('app_connexion');
            }

            $this->addFlash('success', 'Utilisateur et ses documents supprimés avec succès !');
        }

        return $this->redirectToRoute('app_gestion_utilisateurs', [], Response::HTTP_SEE_OTHER);
    }
}