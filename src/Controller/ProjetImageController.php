<?php

namespace App\Controller;

use App\Entity\ProjetImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de diffusion des images de projets.
 *
 * Les images sont stockées hors public/ (dans var/projets/) et servies via ce
 * contrôleur pour éviter tout accès direct par URL au système de fichiers.
 *
 * Deux niveaux d'accès :
 *  - Route publique  : /projets/image/{id}       → accessible à tous (page vitrine)
 *  - Route admin     : /admin/gestion-projets/image/{id}/voir → ROLE_ADMIN uniquement
 *                      (définie dans GestionProjetsController)
 *
 * Sécurité :
 *  - Path traversal impossible : on résout le chemin depuis l'ID en BDD,
 *    jamais depuis un paramètre de chemin contrôlable par l'utilisateur.
 *  - Content-Type forcé depuis les magic bytes réels (finfo).
 *  - Cache navigateur 24h pour les performances (images vitrine statiques).
 */
final class ProjetImageController extends AbstractController
{
    #[Route('/projets/image/{id}', name: 'app_image_projet_publique', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function servir(ProjetImage $projetImage): BinaryFileResponse
    {
        $filePath = $this->getParameter('projets_images_directory') . '/' . $projetImage->getNomFichier();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Image introuvable.');
        }

        // Content-Type depuis les magic bytes réels (ne fait JAMAIS confiance à l'extension)
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($filePath) ?: 'application/octet-stream';

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Content-Type', $mime);

        // Cache 24h côté navigateur — les images vitrine ne changent pas souvent
        // et ne contiennent aucune donnée sensible
        $response->setMaxAge(86400);
        $response->setPublic();

        return $response;
    }
}