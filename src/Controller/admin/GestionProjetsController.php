<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GestionProjetsController extends AbstractController
{
    #[Route('/admin/gestion-projets', name: 'app_gestion_projets')]
    public function gestionProjets(): Response
    {
        return $this->render('admin/gestion_projets.html.twig');
    }
}
