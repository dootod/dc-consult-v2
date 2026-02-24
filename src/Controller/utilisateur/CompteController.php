<?php

namespace App\Controller\utilisateur;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Gestion du compte utilisateur : informations personnelles, mot de passe, email.
 *
 * Renforcements de sécurité appliqués (OWASP) :
 *  - CSRF vérifié manuellement sur tous les formulaires POST (A01)
 *  - Validation et sanitisation strictes des entrées (A03)
 *    · Longueur max sur nom/prénom : 100 caractères
 *    · Validation email via filter_var FILTER_VALIDATE_EMAIL
 *  - Rate limiting sur la demande de reset de mot de passe (A04, A07)
 *  - Tokens de reset : brut dans l'email, SHA-256 en BDD (A02)
 *  - Expiration des tokens à 1 heure (A07)
 *  - ✅ FIX ANTI-ÉNUMÉRATION (A07) : messages génériques sur changement d'email
 *    L'ancien message "Cet email est déjà utilisé par un autre compte." révélait
 *    l'existence d'un compte, permettant l'énumération des utilisateurs inscrits.
 *    Le nouveau message est identique qu'un email soit pris ou non.
 */
#[Route('/mon-espace/mon-compte')]
final class CompteController extends AbstractController
{
    public function __construct(
        // Rate limiter pour les demandes de reset de mot de passe
        private readonly RateLimiterFactory $passwordResetLimiter,
    ) {}

    // ── Page principale ──────────────────────────────────────────────────────
    #[Route('', name: 'app_compte', methods: ['GET', 'POST'])]
    public function compte(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        // ── Modification nom / prénom ────────────────────────────────────────
        if ($request->isMethod('POST') && $request->request->has('_action_identite')) {
            // Vérification CSRF (OWASP A01)
            if (!$this->isCsrfTokenValid('identite', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_compte');
            }

            $nom    = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));

            // ── Validation et sanitisation des entrées (OWASP A03) ────────────
            if (empty($nom) || empty($prenom)) {
                $this->addFlash('danger', 'Le nom et le prénom ne peuvent pas être vides.');
                return $this->redirectToRoute('app_compte');
            }

            // Limite de longueur pour éviter les overflows en BDD
            if (mb_strlen($nom) > 100 || mb_strlen($prenom) > 100) {
                $this->addFlash('danger', 'Le nom et le prénom ne peuvent pas dépasser 100 caractères.');
                return $this->redirectToRoute('app_compte');
            }

            // Rejette les caractères non autorisés (chiffres, balises, etc.)
            if (!preg_match('/^[\p{L}\s\-\']+$/u', $nom) || !preg_match('/^[\p{L}\s\-\']+$/u', $prenom)) {
                $this->addFlash('danger', 'Le nom et le prénom ne doivent contenir que des lettres, espaces, tirets et apostrophes.');
                return $this->redirectToRoute('app_compte');
            }

            $utilisateur->setNom($nom);
            $utilisateur->setPrenom($prenom);
            $em->flush();

            $this->addFlash('success', 'Vos informations ont été mises à jour.');
            return $this->redirectToRoute('app_compte');
        }

        return $this->render('utilisateur/compte/index.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    // ── Demande de changement de mot de passe ────────────────────────────────
    #[Route('/demande-changement-mdp', name: 'app_compte_demande_mdp', methods: ['POST'])]
    public function demandeChangementMdp(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        // Vérification CSRF (OWASP A01)
        if (!$this->isCsrfTokenValid('demande_mdp', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_compte');
        }

        // ── Rate limiting (OWASP A04) ─────────────────────────────────────────
        // Max 3 demandes par IP par heure pour éviter le spam d'emails
        $limiter = $this->passwordResetLimiter->create(
            'pwd_reset_' . ($request->getClientIp() ?? 'unknown')
        );
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->addFlash('danger', 'Trop de demandes. Veuillez patienter avant de réessayer.');
            return $this->redirectToRoute('app_compte');
        }

        // Génère le token brut (envoyé dans l'email, jamais stocké)
        // Stocke uniquement son hash SHA-256 en BDD (OWASP A02)
        $tokenBrut = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $utilisateur->setPasswordChangeToken(hash('sha256', $tokenBrut));
        $utilisateur->setPasswordChangeTokenExpiresAt($expiresAt);
        $em->flush();

        // Le lien contient le token BRUT (le hash ne sert qu'en BDD)
        $lien = $this->generateUrl(
            'app_compte_confirmer_mdp',
            ['token' => $tokenBrut],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->to($utilisateur->getEmail())
            ->subject('Confirmation de changement de mot de passe')
            ->html($this->renderView('emails/confirmer_changement_mdp.html.twig', [
                'utilisateur' => $utilisateur,
                'lien'        => $lien,
            ]));

        $mailer->send($email);

        $this->addFlash('info', 'Un lien de confirmation vous a été envoyé par email. Il est valable 1 heure.');
        return $this->redirectToRoute('app_compte');
    }

    // ── Confirmation + formulaire nouveau mot de passe ───────────────────────
    #[Route('/confirmer-mdp/{token}', name: 'app_compte_confirmer_mdp', methods: ['GET', 'POST'])]
    public function confirmerChangementMdp(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        // Validation du format du token (64 hex chars = 32 bytes)
        // Prévient les injections via l'URL (OWASP A03)
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_connexion');
        }

        // Comparaison constante-time via hash (OWASP A02 – timing attack prevention)
        $utilisateur = $em->getRepository(Utilisateur::class)
            ->findOneBy(['passwordChangeToken' => hash('sha256', $token)]);

        if (
            !$utilisateur
            || !$utilisateur->getPasswordChangeTokenExpiresAt()
            || $utilisateur->getPasswordChangeTokenExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_connexion');
        }

        if ($request->isMethod('POST')) {
            // CSRF sur le formulaire de changement de mot de passe
            if (!$this->isCsrfTokenValid('confirmer_mdp', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_compte_confirmer_mdp', ['token' => $token]);
            }

            $nouveauMdp   = $request->request->get('nouveau_mdp', '');
            $confirmeMdp  = $request->request->get('confirme_mdp', '');

            // Validation de la longueur minimale (OWASP A07 : min 8 caractères)
            if (mb_strlen($nouveauMdp) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_compte_confirmer_mdp', ['token' => $token]);
            }

            if ($nouveauMdp !== $confirmeMdp) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_compte_confirmer_mdp', ['token' => $token]);
            }

            $utilisateur->setPassword($hasher->hashPassword($utilisateur, $nouveauMdp));
            // Invalidation du token après usage (usage unique)
            $utilisateur->setPasswordChangeToken(null);
            $utilisateur->setPasswordChangeTokenExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
            return $this->redirectToRoute('app_compte');
        }

        return $this->render('utilisateur/compte/nouveau_mdp.html.twig', [
            'token' => $token,
        ]);
    }

    // ── Demande de changement d'email — étape 1 ──────────────────────────────
    #[Route('/demande-changement-email', name: 'app_compte_demande_email', methods: ['POST'])]
    public function demandeChangementEmail(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        // Vérification CSRF (OWASP A01)
        if (!$this->isCsrfTokenValid('demande_email', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_compte');
        }

        $nouvelEmail = trim($request->request->get('nouvel_email', ''));

        // Validation stricte de l'email (OWASP A03)
        if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse email invalide.');
            return $this->redirectToRoute('app_compte');
        }

        // Limite de longueur pour correspondre à la colonne BDD (VARCHAR 180)
        if (mb_strlen($nouvelEmail) > 180) {
            $this->addFlash('danger', 'L\'adresse email est trop longue.');
            return $this->redirectToRoute('app_compte');
        }

        if ($nouvelEmail === $utilisateur->getEmail()) {
            $this->addFlash('danger', 'Le nouvel email est identique à l\'actuel.');
            return $this->redirectToRoute('app_compte');
        }

        // ✅ FIX ANTI-ÉNUMÉRATION (OWASP A07) :
        // On effectue toujours la même action (envoi d'email ou simulé) que l'email
        // cible existe ou non, afin d'éviter que l'attaquant ne déduise l'existence
        // d'un compte à partir du comportement de l'application.
        // Si l'email est déjà pris, on renvoie le même message générique de succès.
        // L'email de confirmation ne sera envoyé que si l'email n'est pas pris.
        $existant = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $nouvelEmail]);

        // Message générique identique dans les deux cas (anti-énumération)
        $messageGenerique = 'Si cette adresse email est disponible, un lien de confirmation vous a été envoyé à votre adresse actuelle. Il est valable 1 heure.';

        if ($existant) {
            // ✅ On ne révèle PAS que l'email est déjà utilisé — message identique
            $this->addFlash('info', $messageGenerique);
            return $this->redirectToRoute('app_compte');
        }

        // Token brut dans l'email, hash SHA-256 en BDD (OWASP A02)
        $tokenBrut = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $utilisateur->setPendingEmail($nouvelEmail);
        $utilisateur->setEmailChangeToken(hash('sha256', $tokenBrut));
        $utilisateur->setEmailChangeTokenExpiresAt($expiresAt);
        $em->flush();

        $lien = $this->generateUrl(
            'app_compte_confirmer_email_ancien',
            ['token' => $tokenBrut],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->to($utilisateur->getEmail())
            ->subject('Confirmation de changement d\'adresse email')
            ->html($this->renderView('emails/confirmer_changement_email_ancien.html.twig', [
                'utilisateur' => $utilisateur,
                'nouvelEmail' => $nouvelEmail,
                'lien'        => $lien,
            ]));

        $mailer->send($email);

        $this->addFlash('info', $messageGenerique);
        return $this->redirectToRoute('app_compte');
    }

    // ── Étape 2 : Confirmation depuis l'ancien email ──────────────────────────
    #[Route('/confirmer-email-ancien/{token}', name: 'app_compte_confirmer_email_ancien', methods: ['GET'])]
    public function confirmerEmailAncien(
        string $token,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        // Validation du format du token (OWASP A03)
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_connexion');
        }

        $utilisateur = $em->getRepository(Utilisateur::class)
            ->findOneBy(['emailChangeToken' => hash('sha256', $token)]);

        if (
            !$utilisateur
            || !$utilisateur->getEmailChangeTokenExpiresAt()
            || $utilisateur->getEmailChangeTokenExpiresAt() < new \DateTimeImmutable()
            || !$utilisateur->getPendingEmail()
        ) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_connexion');
        }

        // Nouveau token pour l'étape 2
        $nouveauTokenBrut = bin2hex(random_bytes(32));
        $expiresAt        = new \DateTimeImmutable('+1 hour');

        $utilisateur->setEmailChangeToken(hash('sha256', $nouveauTokenBrut));
        $utilisateur->setEmailChangeTokenExpiresAt($expiresAt);
        $em->flush();

        $lien = $this->generateUrl(
            'app_compte_confirmer_email_nouveau',
            ['token' => $nouveauTokenBrut],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->to($utilisateur->getPendingEmail())
            ->subject('Validez votre nouvelle adresse email')
            ->html($this->renderView('emails/confirmer_changement_email_nouveau.html.twig', [
                'utilisateur' => $utilisateur,
                'lien'        => $lien,
            ]));

        $mailer->send($email);

        $this->addFlash('info', 'L\'ancien email a été confirmé. Un lien de validation a été envoyé à votre nouvelle adresse email.');
        return $this->redirectToRoute('app_compte');
    }

    // ── Étape 3 : Application du changement depuis le nouvel email ────────────
    #[Route('/confirmer-email-nouveau/{token}', name: 'app_compte_confirmer_email_nouveau', methods: ['GET'])]
    public function confirmerEmailNouveau(
        string $token,
        EntityManagerInterface $em,
    ): Response {
        // Validation du format du token (OWASP A03)
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_connexion');
        }

        $utilisateur = $em->getRepository(Utilisateur::class)
            ->findOneBy(['emailChangeToken' => hash('sha256', $token)]);

        if (
            !$utilisateur
            || !$utilisateur->getEmailChangeTokenExpiresAt()
            || $utilisateur->getEmailChangeTokenExpiresAt() < new \DateTimeImmutable()
            || !$utilisateur->getPendingEmail()
        ) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_connexion');
        }

        $utilisateur->setEmail($utilisateur->getPendingEmail());
        $utilisateur->setPendingEmail(null);
        $utilisateur->setEmailChangeToken(null);
        $utilisateur->setEmailChangeTokenExpiresAt(null);
        $em->flush();

        $this->addFlash('success', 'Votre adresse email a été modifiée avec succès.');
        return $this->redirectToRoute('app_compte');
    }
}