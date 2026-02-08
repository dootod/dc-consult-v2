<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Entity\Utilisateur;
use App\Form\NewUtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GestionUtilisateursController extends AbstractController
{
    #[Route('/admin/gestion-utilisateurs', name: 'app_gestion_utilisateurs')]
    public function gestionUtilisateurs(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('admin/gestion_utilisateurs/gestion_utilisateurs.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
        ]);
    }

    #[Route('/admin/gestion-utilisateurs/{id<\d+>}', name: 'app_show_utilisateurs')]
    function show(Utilisateur $utilisateur): Response
    {
        return $this->render('admin/gestion_utilisateurs/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/admin/gestion-utilisateurs/nouveau', name: 'app_new_utilisateurs')]
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

            return $this->redirectToRoute('app_show_utilisateurs', [
                'id' => $utilisateur->getId(),
            ]);
        }

        return $this->render('admin/gestion_utilisateurs/new.html.twig', [
            'form' => $form,
        ]);
    }
}