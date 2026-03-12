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
     * Page de contact avec formulaire.
     *
     * NOTE : 'email' est un nom réservé par TemplatedEmail (Symfony Bridge).
     * On utilise donc 'senderEmail' dans le contexte Twig.
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

            $data = $form->getData();

            try {
                $emailMessage = (new TemplatedEmail())
                    ->to(new Address('t.dumont1809@gmail.com', 'DC Consult'))
                    ->replyTo(new Address($data['email'], $data['prenom'] . ' ' . $data['nom']))
                    ->subject('[Contact] ' . $data['sujet'])
                    ->htmlTemplate('emails/contact.html.twig')
                    ->context([
                        'nom'         => $data['nom'],
                        'prenom'      => $data['prenom'],
                        'senderEmail' => $data['email'],   // 'email' est réservé par TemplatedEmail
                        'telephone'   => $data['telephone'] ?? null,
                        'sujet'       => $data['sujet'],
                        'message'     => $data['message'],
                        'sentAt'      => new \DateTimeImmutable(),
                    ]);

                $mailer->send($emailMessage);

                $this->addFlash('success', 'Votre message a bien été envoyé ! Nous vous répondrons dans les plus brefs délais.');
                return $this->redirectToRoute('app_contact');

            } catch (TransportExceptionInterface $e) {
                $this->addFlash('danger', 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer ou nous contacter directement par email.');
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}