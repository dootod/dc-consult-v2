<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogicielsController extends AbstractController
{
    #[Route('/logiciels', name: 'app_logiciels')]
    public function index(): Response
    {
        return $this->render('logiciels/index.html.twig', [
            'controller_name' => 'LogicielsController',
        ]);
    }
}
