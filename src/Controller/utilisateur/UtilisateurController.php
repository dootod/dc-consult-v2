<?php

namespace App\Controller\utilisateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UtilisateurController extends AbstractController
{
    #[Route('dashboard', name: 'app_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('utilisateur/dashboard.html.twig');
    }
}
