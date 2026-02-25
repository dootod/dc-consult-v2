<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Gestionnaire de succès d'authentification.
 *
 * OWASP A01:2021 – Broken Access Control
 * OWASP A10:2021 – Server-Side Request Forgery (Open Redirect)
 *
 * Redirige l'utilisateur vers la bonne section après login selon son rôle.
 *
 * Sécurité Open Redirect :
 * ────────────────────────
 * Les redirections après login sont une cible classique d'open redirect :
 * un attaquant construit un lien vers /connexion?_target_path=https://evil.com
 * et l'utilisateur est redirigé vers le site malveillant après s'être connecté.
 *
 * Mitigation appliquée :
 *  - Les redirections sont UNIQUEMENT générées via RouterInterface::generate(),
 *    qui ne peut produire que des routes internes à l'application.
 *  - Le paramètre _target_path n'est JAMAIS lu ni utilisé ici.
 *  - Aucune URL externe ne peut donc être injectée via ce handler.
 *
 * Si vous devez un jour autoriser une redirection post-login vers une URL
 * variable (ex: retour vers la page d'origine), utilisez obligatoirement
 * UrlHelper::isAbsoluteUrl() + vérification que le host appartient au domaine
 * autorisé avant toute redirection.
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router
    ) {}

    /**
     * Redirige vers le dashboard approprié selon le rôle de l'utilisateur.
     *
     * OWASP Open Redirect : toutes les URLs de redirection sont générées
     * en interne via le router Symfony — jamais depuis l'input utilisateur.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $roles = $token->getRoleNames();

        // ── Administrateur → dashboard admin ─────────────────────────────────
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse(
                $this->router->generate('app_dashboard_admin')
            );
        }

        // ── Utilisateur standard → dashboard utilisateur ──────────────────────
        if (in_array('ROLE_USER', $roles, true)) {
            return new RedirectResponse(
                $this->router->generate('app_dashboard')
            );
        }

        // ── Fallback → page d'accueil publique ───────────────────────────────
        // Ne devrait pas arriver (tout utilisateur authentifié a au moins ROLE_USER),
        // mais sert de filet de sécurité.
        return new RedirectResponse(
            $this->router->generate('app_home')
        );
    }
}