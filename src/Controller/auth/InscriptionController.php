<?php

namespace App\Controller\auth;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion de l'inscription utilisateur.
 *
 * Renforcements de sécurité appliqués (OWASP) :
 *  - Rate limiting par IP : max 5 inscriptions / 15 min (A04, A07)
 *  - CSRF natif via Symfony Forms (A01)
 *  - Validation et sanitisation des données via RegistrationFormType (A03)
 *  - Hachage du mot de passe via UserPasswordHasherInterface (A02)
 *  - Redirection POST-Redirect-GET après succès (évite la double soumission)
 */
class InscriptionController extends AbstractController
{
    public function __construct(
        // Injection du rate limiter nommé 'inscription' (défini dans rate_limiter.yaml)
        private readonly RateLimiterFactory $inscriptionLimiter,
    ) {}

    #[Route('/inscription', name: 'app_inscription', methods: ['GET', 'POST'])]
    public function inscription(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        // ── Redirection si déjà connecté ──────────────────────────────────────
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // ── Rate limiting par IP (OWASP A04 : Insecure Design) ────────────────
        // Limite : 5 inscriptions par IP toutes les 15 minutes
        // Prévient la création de masse de comptes (spam, fake accounts, etc.)
        if ($request->isMethod('POST')) {
            $limiter = $this->inscriptionLimiter->create($request->getClientIp() ?? 'unknown');
            $limit   = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                // 429 Too Many Requests avec header Retry-After
                $response = new Response(
                    $this->renderView('auth/inscription.html.twig', [
                        'registrationForm' => $this->createForm(RegistrationFormType::class, new Utilisateur()),
                        'rate_limit_error' => true,
                    ]),
                    Response::HTTP_TOO_MANY_REQUESTS
                );
                $response->headers->set(
                    'Retry-After',
                    (string) $limit->getRetryAfter()->getTimestamp()
                );

                return $response;
            }
        }

        // ── Traitement du formulaire ──────────────────────────────────────────
        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hachage sécurisé du mot de passe (bcrypt/argon2id selon config)
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Les rôles par défaut sont [] (ROLE_USER est implicite dans Symfony)
            $user->setRoles([]);

            $entityManager->persist($user);
            $entityManager->flush();

            // Message générique — ne confirme pas l'existence de l'email (anti-énumération)
            $this->addFlash(
                'success',
                'Merci pour votre inscription ! Vous pouvez maintenant vous connecter.'
            );

            // PRG : évite la double soumission du formulaire en cas de rechargement
            return $this->redirectToRoute('app_connexion');
        }

        return $this->render('auth/inscription.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}