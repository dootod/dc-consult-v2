<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard_admin')]
    public function dashboard(UtilisateurRepository $utilisateurRepository): Response
    {
        $email = $this->getUser()?->getUserIdentifier();

        $utilisateur = $utilisateurRepository->findOneBy(['email' => $email]);

        return $this->render('admin/dashboard.html.twig', [
            'prenom' => $utilisateur?->getPrenom(),
        ]);
    }
}
