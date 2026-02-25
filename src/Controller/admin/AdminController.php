<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentAdminRepository;
use App\Repository\ProjetRepository;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard_admin')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        DocumentRepository $documentRepository,
        DocumentAdminRepository $documentAdminRepository,
        ProjetRepository $projetRepository
    ): Response {
        $email = $this->getUser()?->getUserIdentifier();
        $utilisateur = $utilisateurRepository->findOneBy(['email' => $email]);

        // ── Statistiques ──
        $allUtilisateurs   = $utilisateurRepository->findAll();
        $totalUtilisateurs = count($allUtilisateurs);

        // Compte les admins en PHP (liste déjà chargée pour l'affichage)
        $totalAdmins = count(array_filter($allUtilisateurs, fn($u) => in_array('ROLE_ADMIN', $u->getRoles())));

        // ✅ FIX : COUNT SQL au lieu de findAll() + count() — évite de charger tous les documents en mémoire
        $totalDocuments = $documentRepository->count([]) + $documentAdminRepository->count([]);

        $stats = [
            'utilisateurs' => $totalUtilisateurs,
            'projets'      => $projetRepository->count([]),
            'documents'    => $totalDocuments,
            'admins'       => $totalAdmins,
        ];

        // ── Activité récente ──
        // ✅ FIX : requête paginée directement en BDD (findRecentOrderedByDate)
        // au lieu de findAll() + usort() + array_slice() en PHP
        $recentDocs = $documentAdminRepository->findRecentOrderedByDate(5);

        $activites = [];
        foreach ($recentDocs as $doc) {
            $date = $doc->getDeposeLe();
            $activites[] = [
                'type'    => 'blue',
                'message' => sprintf(
                    'Document "%s" déposé pour %s %s',
                    $doc->getNom(),
                    $doc->getDestinataire()->getPrenom(),
                    $doc->getDestinataire()->getNom()
                ),
                'date' => $date ? $date->format('d/m/Y à H:i') : '',
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'utilisateur' => $utilisateur,
            'prenom'      => $utilisateur?->getPrenom() ?? 'Admin',
            'stats'       => $stats,
            'activites'   => $activites,
        ]);
    }
}