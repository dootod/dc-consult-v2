<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard_admin')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/admin/gestion-projets', name: 'app_gestion_projets')]
    public function gestionProjets(): Response
    {
        return $this->render('admin/gestion_projets.html.twig');
    }

    #[Route('/admin/gestion-documents', name: 'app_gestion_documents')]
    public function gestionDocuments(): Response
    {
        return $this->render('admin/gestion_documents.html.twig');
    }

    #[Route('/admin/gestion-utilisateurs', name: 'app_gestion_utilisateurs')]
    public function gestionUtilisateurs(): Response
    {
        return $this->render('admin/gestion_utilisateurs.html.twig');
    }
}
