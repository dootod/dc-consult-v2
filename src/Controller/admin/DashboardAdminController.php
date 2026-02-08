<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardAdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard_admin')]
    public function index(): Response
    {
        return $this->render('admin/dashboard_admin/index.html.twig', [
            'controller_name' => 'DashboardAdminController',
        ]);
    }
}
