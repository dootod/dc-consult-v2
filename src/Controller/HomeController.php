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
        return $this->render('home/index.html.twig');
    }

    #[Route('/projets', name: 'app_projets')]
    public function projets(): Response
    {
        return $this->render('projets/index.html.twig');
    }

    #[Route('/clients', name: 'app_clients')]
    public function clients(): Response
    {
        return $this->render('home/clients.html.twig');
    }

    #[Route('/logiciels', name: 'app_logiciels')]
    public function logiciels(): Response
    {
        return $this->render('home/logiciels.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('contact/index.html.twig');
    }
}
