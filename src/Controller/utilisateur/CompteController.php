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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/mon-espace/mon-compte')]
final class CompteController extends AbstractController
{
    // ----------------------------------------------------------------
    // Page principale
    // ----------------------------------------------------------------
    #[Route('', name: 'app_compte', methods: ['GET', 'POST'])]
    public function compte(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        // ---- Modification nom / prénom ----
        if ($request->isMethod('POST') && $request->request->has('_action_identite')) {
            if (!$this->isCsrfTokenValid('identite', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_compte');
            }

            $nom    = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));

            if (empty($nom) || empty($prenom)) {
                $this->addFlash('danger', 'Le nom et le prénom ne peuvent pas être vides.');
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

    // ----------------------------------------------------------------
    // Demande de changement de mot de passe
    // ----------------------------------------------------------------
    #[Route('/demande-changement-mdp', name: 'app_compte_demande_mdp', methods: ['POST'])]
    public function demandeChangementMdp(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        if (!$this->isCsrfTokenValid('demande_mdp', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_compte');
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $utilisateur->setPasswordChangeToken($token);
        $utilisateur->setPasswordChangeTokenExpiresAt($expiresAt);
        $em->flush();

        $lien = $this->generateUrl('app_compte_confirmer_mdp', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

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

    // ----------------------------------------------------------------
    // Confirmation + formulaire nouveau mot de passe
    // ----------------------------------------------------------------
    #[Route('/confirmer-mdp/{token}', name: 'app_compte_confirmer_mdp', methods: ['GET', 'POST'])]
    public function confirmerChangementMdp(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        /** @var Utilisateur|null $utilisateur */
        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['passwordChangeToken' => $token]);

        if (
            !$utilisateur
            || !$utilisateur->getPasswordChangeTokenExpiresAt()
            || $utilisateur->getPasswordChangeTokenExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_compte');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('nouveau_mdp', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_compte_confirmer_mdp', ['token' => $token]);
            }

            $nouveauMdp    = $request->request->get('nouveau_mdp', '');
            $confirmationMdp = $request->request->get('confirmation_mdp', '');

            if (strlen($nouveauMdp) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_compte_confirmer_mdp', ['token' => $token]);
            }

            if ($nouveauMdp !== $confirmationMdp) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_compte_confirmer_mdp', ['token' => $token]);
            }

            $utilisateur->setPassword($hasher->hashPassword($utilisateur, $nouveauMdp));
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

    // ----------------------------------------------------------------
    // Demande de changement d'email — étape 1 : confirmation ancien email
    // ----------------------------------------------------------------
    #[Route('/demande-changement-email', name: 'app_compte_demande_email', methods: ['POST'])]
    public function demandeChangementEmail(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        if (!$this->isCsrfTokenValid('demande_email', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_compte');
        }

        $nouvelEmail = trim($request->request->get('nouvel_email', ''));

        if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse email invalide.');
            return $this->redirectToRoute('app_compte');
        }

        if ($nouvelEmail === $utilisateur->getEmail()) {
            $this->addFlash('danger', 'Le nouvel email est identique à l\'actuel.');
            return $this->redirectToRoute('app_compte');
        }

        // Vérifier que l'email n'est pas déjà utilisé
        $existant = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $nouvelEmail]);
        if ($existant) {
            $this->addFlash('danger', 'Cet email est déjà utilisé par un autre compte.');
            return $this->redirectToRoute('app_compte');
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $utilisateur->setPendingEmail($nouvelEmail);
        $utilisateur->setEmailChangeToken($token);
        $utilisateur->setEmailChangeTokenExpiresAt($expiresAt);
        $em->flush();

        // Email envoyé à l'ANCIEN email pour confirmer la demande
        $lien = $this->generateUrl('app_compte_confirmer_email_ancien', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->to($utilisateur->getEmail())
            ->subject('Confirmation de changement d\'adresse email')
            ->html($this->renderView('emails/confirmer_changement_email_ancien.html.twig', [
                'utilisateur' => $utilisateur,
                'nouvelEmail' => $nouvelEmail,
                'lien'        => $lien,
            ]));

        $mailer->send($email);

        $this->addFlash('info', 'Un lien de confirmation a été envoyé à votre adresse email actuelle. Il est valable 1 heure.');
        return $this->redirectToRoute('app_compte');
    }

    // ----------------------------------------------------------------
    // Étape 2 : L'utilisateur clique le lien depuis l'ancien email
    //           → on envoie un lien de validation au NOUVEL email
    // ----------------------------------------------------------------
    #[Route('/confirmer-email-ancien/{token}', name: 'app_compte_confirmer_email_ancien', methods: ['GET'])]
    public function confirmerEmailAncien(
        string $token,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        /** @var Utilisateur|null $utilisateur */
        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['emailChangeToken' => $token]);

        if (
            !$utilisateur
            || !$utilisateur->getEmailChangeTokenExpiresAt()
            || $utilisateur->getEmailChangeTokenExpiresAt() < new \DateTimeImmutable()
            || !$utilisateur->getPendingEmail()
        ) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_compte');
        }

        // Générer un nouveau token pour valider le nouvel email
        $nouveauToken = bin2hex(random_bytes(32));
        $expiresAt    = new \DateTimeImmutable('+1 hour');

        $utilisateur->setEmailChangeToken($nouveauToken);
        $utilisateur->setEmailChangeTokenExpiresAt($expiresAt);
        $em->flush();

        // Email envoyé au NOUVEL email
        $lien = $this->generateUrl('app_compte_confirmer_email_nouveau', ['token' => $nouveauToken], UrlGeneratorInterface::ABSOLUTE_URL);

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

    // ----------------------------------------------------------------
    // Étape 3 : L'utilisateur clique le lien depuis le NOUVEL email
    //           → on applique le changement
    // ----------------------------------------------------------------
    #[Route('/confirmer-email-nouveau/{token}', name: 'app_compte_confirmer_email_nouveau', methods: ['GET'])]
    public function confirmerEmailNouveau(
        string $token,
        EntityManagerInterface $em,
    ): Response {
        /** @var Utilisateur|null $utilisateur */
        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['emailChangeToken' => $token]);

        if (
            !$utilisateur
            || !$utilisateur->getEmailChangeTokenExpiresAt()
            || $utilisateur->getEmailChangeTokenExpiresAt() < new \DateTimeImmutable()
            || !$utilisateur->getPendingEmail()
        ) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expiré.');
            return $this->redirectToRoute('app_compte');
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