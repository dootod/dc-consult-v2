<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Repository\ProjetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur public — pages statiques et vitrine projets.
 *
 * Toutes ces routes sont publiques (pas d'authentification requise).
 * La protection XSS est assurée par l'auto-escape Twig + les headers CSP.
 */
final class HomeController extends AbstractController
{
    private const PROJETS_PER_PAGE = 6;

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    // ── LISTE PROJETS ─────────────────────────────────────────────────────────

    /**
     * Page principale "Nos projets" — affiche les N premiers projets.
     * La pagination "Voir plus" est gérée via XHR sur app_projets_load_more.
     */
    #[Route('/projets', name: 'app_projets')]
    public function projets(ProjetRepository $projetRepository): Response
    {
        $total   = $projetRepository->countAll();
        $projets = $projetRepository->findPageWithImages(0, self::PROJETS_PER_PAGE);

        return $this->render('projets/index.html.twig', [
            'projets'      => $projets,
            'total'        => $total,
            'loaded'       => count($projets),
            'per_page'     => self::PROJETS_PER_PAGE,
        ]);
    }

    /**
     * Endpoint XHR "Voir plus" — retourne le fragment HTML des projets suivants.
     * Accepte uniquement les requêtes XMLHttpRequest pour éviter l'accès direct.
     */
    #[Route('/projets/voir-plus', name: 'app_projets_load_more', methods: ['GET'])]
    public function projetsLoadMore(Request $request, ProjetRepository $projetRepository): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('app_projets');
        }

        $offset  = max(0, (int) $request->query->get('offset', 0));
        $projets = $projetRepository->findPageWithImages($offset, self::PROJETS_PER_PAGE);
        $total   = $projetRepository->countAll();

        return $this->render('projets/_cards.html.twig', [
            'projets'  => $projets,
            'total'    => $total,
            'loaded'   => $offset + count($projets),
            'per_page' => self::PROJETS_PER_PAGE,
        ]);
    }

    // ── DETAIL PROJET ─────────────────────────────────────────────────────────

    /**
     * Page de détail d'un projet — accessible publiquement.
     * Utilise le ParamConverter Doctrine pour charger l'entité via l'ID.
     */
    #[Route('/projets/{id}', name: 'app_projet_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function projetShow(Projet $projet): Response
    {
        return $this->render('projets/show.html.twig', [
            'projet' => $projet,
        ]);
    }

    // ── PAGES STATIQUES ───────────────────────────────────────────────────────

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