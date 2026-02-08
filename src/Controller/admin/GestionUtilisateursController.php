<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Entity\Utilisateur;

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
    public function new(): Response
    {
        return $this->render('admin/gestion_utilisateurs/new.html.twig');
    }
}
