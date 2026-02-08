<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GestionUtilisateursController extends AbstractController
{
    #[Route('/admin/gestion-utilisateurs', name: 'app_gestion_utilisateurs')]
    public function gestionUtilisateurs(): Response
    {
        return $this->render('admin/gestion_utilisateurs.html.twig');
    }
}
