<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/clients', name: 'app_clients')]
    public function clients(): Response
    {
        return $this->render('clients/index.html.twig', [
            'controller_name' => 'ClientsController',
        ]);
    }

    #[Route('/logiciels', name: 'app_logiciels')]
    public function logiciels(): Response
    {
        return $this->render('logiciels/index.html.twig', [
            'controller_name' => 'LogicielsController',
        ]);
    }
}
