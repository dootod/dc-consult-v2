<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GestionDocumentsController extends AbstractController
{
    #[Route('/admin/gestion-documents', name: 'app_gestion_documents')]
    public function gestionDocuments(): Response
    {
        return $this->render('admin/gestion_documents.html.twig');
    }
}
