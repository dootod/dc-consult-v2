<?php

namespace App\Controller\utilisateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;

final class UtilisateurController extends AbstractController
{
    #[Route('/mon-espace/dashboard', name: 'app_dashboard')]
    public function dashboard(UtilisateurRepository $utilisateurRepository): Response
    {
        $email = $this->getUser()?->getUserIdentifier();

        $utilisateur = $utilisateurRepository->findOneBy(['email' => $email]);

        return $this->render('utilisateur/dashboard.html.twig', [
            'prenom' => $utilisateur?->getPrenom(),
        ]);
    }
}
