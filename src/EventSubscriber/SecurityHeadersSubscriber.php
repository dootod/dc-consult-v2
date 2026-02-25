<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * OWASP Security Headers Subscriber
 *
 * Ajoute automatiquement les headers HTTP de sécurité sur toutes les réponses.
 * Conformément aux recommandations OWASP A05:2021 – Security Misconfiguration.
 *
 * Headers couverts :
 *  - Content-Security-Policy    : Prévient XSS et injection de ressources
 *  - X-Frame-Options            : Prévient le clickjacking
 *  - X-Content-Type-Options     : Prévient le MIME-type sniffing
 *  - Strict-Transport-Security  : Force HTTPS (HSTS)
 *  - Referrer-Policy            : Contrôle les informations dans le header Referer
 *  - Permissions-Policy         : Désactive les APIs navigateur non nécessaires
 *  - Cache-Control: no-store    : Sur les routes avec tokens sensibles dans l'URL
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    /**
     * Routes contenant des tokens sensibles dans l'URL (path parameters).
     *
     * OWASP A02 – Cryptographic Failures :
     * Les tokens dans les URLs apparaissent dans :
     *  - Les logs serveur (access.log Apache/Nginx)
     *  - L'historique du navigateur
     *  - Le header Referer des requêtes suivantes
     *  - Les proxies/CDN intermédiaires
     *
     * Pour ces routes, on impose Cache-Control: no-store pour éviter que
     * les caches intermédiaires ou le navigateur ne conservent la page
     * (et donc le token dans l'URL historique du cache).
     *
     * Note : Le Referrer-Policy 'strict-origin-when-cross-origin' déjà appliqué
     * limite la fuite via Referer. Cache-Control: no-store ajoute une couche
     * supplémentaire de protection.
     */
    private const TOKEN_ROUTES = [
        '/confirmer-mdp/',
        '/confirmer-email-ancien/',
        '/confirmer-email-nouveau/',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // N'agir que sur la requête principale (pas les sous-requêtes)
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers  = $response->headers;

        // ── Content-Security-Policy ──────────────────────────────────────────
        // Politique stricte : seules les ressources du même domaine et CDNs
        // approuvés sont autorisées. Les scripts inline utilisent un nonce
        // généré côté serveur (voir SecurityNonceExtension).
        // 'strict-dynamic' permet aux scripts approuvés de charger d'autres scripts.
        //
        // IMPORTANT : Si vous ajoutez de nouveaux CDNs ou sources, mettez-les ici.
        // Pour les scripts inline Symfony/Turbo, 'unsafe-inline' est nécessaire
        // uniquement si vous ne pouvez pas utiliser de nonce.
        $csp = implode('; ', [
            "default-src 'self'",

            // Scripts : domaine courant + Bootstrap CDN + FontAwesome Kit + cdnjs
            // 'unsafe-inline' requis pour les scripts inline Symfony (importmap, Stimulus bootstrap)
            // data: requis pour l'importmap de Symfony AssetMapper qui génère des modules data: URI
            "script-src 'self' 'unsafe-inline' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://kit.fontawesome.com",

            // Styles : domaine courant + Bootstrap CDN + FontAwesome + Google Fonts
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://ka-f.fontawesome.com",

            // Polices : Google Fonts + FontAwesome (kit ET cdnjs — les deux sont utilisés)
            "font-src 'self' https://fonts.gstatic.com https://ka-f.fontawesome.com https://cdnjs.cloudflare.com data:",

            // Images : domaine courant + données inline (avatars, icônes base64, etc.)
            "img-src 'self' data: https:",

            // Connexions XHR/Fetch (Stimulus, Turbo, FontAwesome Kit)
            "connect-src 'self' https://ka-f.fontawesome.com",

            // Frames : aucune frame externe autorisée
            "frame-src 'none'",

            // Objets (Flash, etc.) : interdits
            "object-src 'none'",

            // Base URI : restreinte au domaine courant
            "base-uri 'self'",

            // Formulaires : soumission vers le domaine courant uniquement
            // OWASP A01 : Empêche les soumissions vers des sites externes
            "form-action 'self'",

            // Empêche le chargement dans des frames (renforce X-Frame-Options)
            "frame-ancestors 'none'",

            // Upgrade les requêtes HTTP vers HTTPS
            "upgrade-insecure-requests",
        ]);
        $headers->set('Content-Security-Policy', $csp);

        // ── X-Frame-Options ─────────────────────────────────────────────────
        // Empêche le clickjacking en interdisant l'intégration dans des iframes.
        // 'DENY' est plus restrictif que 'SAMEORIGIN' — à adapter si vous avez
        // besoin d'iframes (ex: embed de cartes).
        $headers->set('X-Frame-Options', 'DENY');

        // ── X-Content-Type-Options ───────────────────────────────────────────
        // Empêche le MIME-type sniffing : le navigateur respecte exactement
        // le Content-Type déclaré, réduisant les risques de drive-by downloads.
        $headers->set('X-Content-Type-Options', 'nosniff');

        // ── Strict-Transport-Security ────────────────────────────────────────
        // Force HTTPS pour 1 an (31 536 000 secondes) avec includeSubDomains.
        // ATTENTION : N'activez preload que si vous êtes inscrit dans la HSTS preload list.
        // max-age=31536000 correspond à 1 an, valeur recommandée par OWASP.
        $headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );

        // ── Referrer-Policy ──────────────────────────────────────────────────
        // Envoie uniquement l'origine (sans chemin) pour les requêtes cross-origin,
        // et le referrer complet pour les requêtes same-origin.
        // Évite de fuiter des URLs internes sensibles (tokens dans les URLs, etc.).
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Permissions-Policy ───────────────────────────────────────────────
        // Désactive les APIs navigateur non utilisées par l'application.
        // Réduit la surface d'attaque en cas de compromission d'un script tiers.
        $headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        // ── X-XSS-Protection ────────────────────────────────────────────────
        // Obsolète dans les navigateurs modernes mais maintenu pour les anciens.
        // La vraie protection XSS est assurée par la CSP ci-dessus.
        $headers->set('X-XSS-Protection', '1; mode=block');

        // ── Cache-Control: no-store sur les routes avec tokens sensibles ─────
        // OWASP A02 – Cryptographic Failures :
        // Les tokens dans les URLs (path parameters) des routes de confirmation
        // ne doivent jamais être mis en cache par le navigateur ou des proxies.
        // 'no-store' est plus fort que 'no-cache' : interdit tout stockage.
        // Combiné avec Referrer-Policy, cela limite fortement les fuites de tokens.
        $requestPath = $event->getRequest()->getPathInfo();
        foreach (self::TOKEN_ROUTES as $tokenRoute) {
            if (str_contains($requestPath, $tokenRoute)) {
                // no-store : le navigateur ne stocke aucune version de la réponse
                // no-cache : force la revalidation avant utilisation (redondant avec no-store)
                // must-revalidate : les proxies ne peuvent pas servir une version périmée
                // private : interdit aux caches partagés (proxies, CDN) de stocker la réponse
                $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
                $headers->set('Pragma', 'no-cache'); // Compatibilité HTTP/1.0
                break;
            }
        }

        // ── Suppression de l'en-tête Server ─────────────────────────────────
        // Évite de divulguer la technologie serveur (security through obscurity).
        // Note : Symfony ne définit pas ce header, mais le serveur web (Apache/Nginx) peut.
        // Cela se configure côté serveur web, pas PHP.
    }
}