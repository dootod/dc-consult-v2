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

#[Route('/admin/gestion-utilisateurs')]
final class GestionUtilisateursController extends AbstractController
{
    #[Route(name: 'app_gestion_utilisateurs', methods: ['GET'])]
    public function gestionUtilisateurs(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('admin/gestion_utilisateurs/gestion_utilisateurs.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
        ]);
    }

    #[Route('/voir/{id}', name: 'app_show_gestion_utilisateurs', methods: ['GET'])]
    function show(Utilisateur $utilisateur): Response
    {
        return $this->render('admin/gestion_utilisateurs/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

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
            
            $this->addFlash(
                'success',
                'Utilisateur ajouté avec succès !'
            );

            return $this->redirectToRoute('app_show_gestion_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/new.html.twig', [
            'form' => $form,
        ]);
    }

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
            
            $this->addFlash(
                'success',
                'Utilisateur modifié avec succès !'
            );

            return $this->redirectToRoute('app_show_gestion_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/supprimer/{id}', name: 'app_delete_gestion_utilisateurs', methods: ['POST'])]
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