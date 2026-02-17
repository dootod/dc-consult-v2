<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Entity\Utilisateur;
use App\Form\NewUtilisateurType;
use App\Form\EditUtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GestionUtilisateursController extends AbstractController
{
    #[Route('/admin/gestion-utilisateurs', name: 'app_gestion_utilisateurs', methods: ['GET'])]
    public function gestionUtilisateurs(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('admin/gestion_utilisateurs/gestion_utilisateurs.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
        ]);
    }

    #[Route('/admin/gestion-utilisateurs/{id<\d+>}', name: 'app_show_utilisateurs', methods: ['GET'])]
    function show(Utilisateur $utilisateur): Response
    {
        return $this->render('admin/gestion_utilisateurs/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/admin/gestion-utilisateurs/nouveau', name: 'app_new_utilisateurs', methods: ['GET', 'POST'])]
    public function new(
        Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $utilisateur = new Utilisateur();

        $form = $this->createForm(NewUtilisateurType::class, $utilisateur);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            
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
            
            $this->addFlash(
                'success',
                'Utilisateur ajouté avec succès !'
            );

            return $this->redirectToRoute('app_show_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/gestion-utilisateurs/modifier/{id<\d+>}', name: 'app_edit_utilisateurs', methods: ['GET', 'POST'])]
    public function edit(Utilisateur $utilisateur, Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $passwordHasher): Response 
    {
        $form = $this->createForm(EditUtilisateurType::class, $utilisateur);

        $currentRole = in_array('ROLE_ADMIN', $utilisateur->getRoles()) ? 'ROLE_ADMIN' : 'ROLE_USER';
        $form->get('role_choice')->setData($currentRole);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // Si un nouveau mot de passe est fourni, le hasher
            $plainPassword = $form->get('password')->getData();
            if (!empty($plainPassword)) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $utilisateur,
                    $plainPassword
                );
                $utilisateur->setPassword($hashedPassword);
            }
            
            // Récupérer le choix de rôle et définir les rôles appropriés
            $roleChoice = $form->get('role_choice')->getData();
            
            if ($roleChoice === 'ROLE_ADMIN') {
                $utilisateur->setRoles(['ROLE_ADMIN']);
            } else {
                $utilisateur->setRoles([]);
            }
            
            $manager->flush();
            
            $this->addFlash(
                'success',
                'Utilisateur modifié avec succès !'
            );

            return $this->redirectToRoute('app_show_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/gestion-utilisateurs/supprimer/{id<\d+>}', name: 'app_delete_utilisateurs', methods: ['POST'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $manager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$utilisateur->getId(), $request->getPayload()->getString('_token'))) {
            $manager->remove($utilisateur);
            $manager->flush();

            $this->addFlash(
                'success',
                'Utilisateur supprimé avec succès !'
            );
        }

        return $this->redirectToRoute('app_gestion_utilisateurs', [], Response::HTTP_SEE_OTHER);
    }
}