<?php

namespace App\Controller\admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UtilisateurRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentAdminRepository;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard_admin')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        DocumentRepository $documentRepository,
        DocumentAdminRepository $documentAdminRepository
    ): Response {
        $email = $this->getUser()?->getUserIdentifier();
        $utilisateur = $utilisateurRepository->findOneBy(['email' => $email]);

        // ── Statistiques ──
        $allUtilisateurs = $utilisateurRepository->findAll();
        $totalUtilisateurs = count($allUtilisateurs);
        
        // Compte les admins (utilisateurs avec ROLE_ADMIN)
        $admins = array_filter($allUtilisateurs, fn($u) => in_array('ROLE_ADMIN', $u->getRoles()));
        $totalAdmins = count($admins);
        
        // Compte les documents (perso + admin)
        $documentsPerso = $documentRepository->findAll();
        $documentsAdmin = $documentAdminRepository->findAll();
        $totalDocuments = count($documentsPerso) + count($documentsAdmin);
        
        $stats = [
            'utilisateurs' => $totalUtilisateurs,
            'projets' => 0, // À implémenter plus tard
            'documents' => $totalDocuments,
            'admins' => $totalAdmins,
        ];

        // ── Activité récente ──
        // Récupère les 5 derniers documents déposés par les admins
        $activites = [];
        
        if (!empty($documentsAdmin)) {
            // Trier les documents admin par date décroissante
            usort($documentsAdmin, function($a, $b) {
                $dateA = $a->getDeposeLe();
                $dateB = $b->getDeposeLe();
                if ($dateA === null || $dateB === null) {
                    return 0;
                }
                return $dateB <=> $dateA;
            });
            
            // Garder seulement les 5 premiers
            $recentDocs = array_slice($documentsAdmin, 0, 5);
            
            foreach ($recentDocs as $doc) {
                $date = $doc->getDeposeLe();
                $activites[] = [
                    'type' => 'blue',
                    'message' => sprintf(
                        'Document "%s" déposé pour %s %s',
                        $doc->getNom(),
                        $doc->getDestinataire()->getPrenom(),
                        $doc->getDestinataire()->getNom()
                    ),
                    'date' => $date ? $date->format('d/m/Y H:i') : 'N/A',
                ];
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'prenom' => $utilisateur?->getPrenom(),
            'stats' => $stats,
            'activites' => $activites,
        ]);
    }
}
