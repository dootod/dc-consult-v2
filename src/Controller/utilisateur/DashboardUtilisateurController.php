<?php

namespace App\Controller\utilisateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardUtilisateurController extends AbstractController
{
    #[Route('/utilisateur/dashboard', name: 'app_dashboard_utilisateur')]
    public function index(): Response
    {
        return $this->render('utilisateur/dashboard_utilisateur/index.html.twig', [
            'controller_name' => 'DashboardUtilisateurController',
        ]);
    }
}
