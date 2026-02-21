<?php

namespace App\Controller\utilisateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentAdminRepository;

final class UtilisateurController extends AbstractController
{
    #[Route('/mon-espace/dashboard', name: 'app_dashboard')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        DocumentRepository $documentRepository,
        DocumentAdminRepository $documentAdminRepository
    ): Response {
        /** @var \App\Entity\Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        // ── Documents personnels (uploadés par l'utilisateur) ──
        $documentsPerso = $documentRepository->findBy(
            ['utilisateur' => $utilisateur],
            ['date' => 'DESC']
        );

        // ── Documents envoyés par l'admin ──
        $documentsAdmin = $documentAdminRepository->findBy(
            ['destinataire' => $utilisateur],
            ['deposeLe' => 'DESC']
        );

        // ── Derniers documents reçus (activité récente) ──
        $recentDocumentsAdmin = array_slice($documentsAdmin, 0, 5);

        // ── Statistiques ──
        $stats = [
            'documents_perso'  => count($documentsPerso),
            'documents_recus'  => count($documentsAdmin),
            'total_documents'  => count($documentsPerso) + count($documentsAdmin),
        ];

        // ── Dernier document perso ──
        $dernierDocPerso = !empty($documentsPerso) ? $documentsPerso[0] : null;

        // ── Date du jour en français ──
        $mois = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $now = new \DateTimeImmutable();
        $dateAujourdhui = $now->format('d') . ' ' . $mois[(int)$now->format('n')] . ' ' . $now->format('Y');

        return $this->render('utilisateur/dashboard.html.twig', [
            'utilisateur'           => $utilisateur,
            'stats'                 => $stats,
            'recentDocumentsAdmin'  => $recentDocumentsAdmin,
            'dernierDocPerso'       => $dernierDocPerso,
            'documentsAdmin'        => array_slice($documentsAdmin, 0, 3),
            'dateAujourdhui'        => $dateAujourdhui,
        ]);
    }
}