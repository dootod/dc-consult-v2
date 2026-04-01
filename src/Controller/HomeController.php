<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Form\ContactType;
use App\Repository\ProjetRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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

    #[Route('/projets', name: 'app_projets')]
    public function projets(ProjetRepository $projetRepository): Response
    {
        $total   = $projetRepository->countAll();
        $projets = $projetRepository->findPageWithImages(0, self::PROJETS_PER_PAGE);

        return $this->render('projets/index.html.twig', [
            'projets'  => $projets,
            'total'    => $total,
            'loaded'   => count($projets),
            'per_page' => self::PROJETS_PER_PAGE,
        ]);
    }

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

    // ── CONTACT ───────────────────────────────────────────────────────────────

    /**
     * Deux emails envoyés à chaque soumission valide :
     *  1. dc.consult@orange.fr  — notification interne (emails/contact.html.twig)
     *  2. Expéditeur            — accusé de réception  (emails/contact_accuse_reception.html.twig)
     *
     * NOTE : 'email' est réservé par TemplatedEmail → on utilise 'senderEmail'.
     */
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request            $request,
        MailerInterface    $mailer,
        RateLimiterFactory $contactFormLimiter,
    ): Response {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── Rate limiting : 5 soumissions / 10 min par IP (OWASP A04) ──
            $limiter = $contactFormLimiter->create($request->getClientIp());
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('danger', 'Trop de messages envoyés. Veuillez patienter quelques minutes avant de réessayer.');
                return $this->redirectToRoute('app_contact');
            }

            $data   = $form->getData();
            $sentAt = new \DateTimeImmutable();

            // Contexte partagé entre les deux templates email
            $context = [
                'nom'         => $data['nom'],
                'prenom'      => $data['prenom'],
                'senderEmail' => $data['email'],   // 'email' est réservé par TemplatedEmail
                'telephone'   => $data['telephone'] ?? null,
                'sujet'       => $data['sujet'],
                'message'     => $data['message'],
                'sentAt'      => $sentAt,
            ];

            try {
                // ── 1. Notification interne à DC Consult ─────────────────────
                $mailer->send(
                    (new TemplatedEmail())
                        ->to(new Address('dc.consult@orange.fr', 'DC Consult'))
                        ->replyTo(new Address($data['email'], $data['prenom'] . ' ' . $data['nom']))
                        ->subject('[Contact] ' . $data['sujet'])
                        ->htmlTemplate('emails/contact.html.twig')
                        ->context($context)
                );

                // ── 2. Accusé de réception à l'expéditeur ────────────────────
                $mailer->send(
                    (new TemplatedEmail())
                        ->to(new Address($data['email'], $data['prenom'] . ' ' . $data['nom']))
                        ->subject('Votre message a bien été reçu – DC Consult')
                        ->htmlTemplate('emails/contact_accuse_reception.html.twig')
                        ->context($context)
                );

                $this->addFlash('success', 'Votre message a bien été envoyé ! Un accusé de réception vous a été adressé par email.');
                return $this->redirectToRoute('app_contact');

            } catch (TransportExceptionInterface $e) {
                $this->addFlash('danger', 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer ou nous contacter directement par email.');
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('home/mentions_legales.html.twig');
    }
}