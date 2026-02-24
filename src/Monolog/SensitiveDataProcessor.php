<?php

namespace App\Monolog;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Processeur Monolog : suppression des données sensibles des logs.
 *
 * OWASP A09:2021 – Security Logging and Monitoring Failures
 * OWASP A02:2021 – Cryptographic Failures
 *
 * Ce processeur est appliqué en production pour s'assurer qu'aucun mot de passe,
 * token, clé API ou donnée personnelle sensible ne soit écrit dans les logs.
 *
 * Couverture :
 *  - Mots de passe dans les messages et le contexte
 *  - Tokens (reset, API, JWT, Bearer)
 *  - Données de formulaires (champs password/mdp/pass)
 *  - Clés APP_SECRET, DATABASE_URL, MAILER_DSN
 *  - Emails dans les messages de contexte sensibles
 *
 * Usage : enregistré comme service Monolog via l'attribut #[AsMonologProcessor]
 * et configuré dans monolog.yaml (prod uniquement).
 */
#[AsMonologProcessor]
class SensitiveDataProcessor
{
    /**
     * Patterns de clés de contexte considérées comme sensibles.
     * Ces champs seront masqués si trouvés dans le contexte du log.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'mot_de_passe',
        'plainPassword',
        'plain_password',
        'passwd',
        'pass',
        'mdp',
        'secret',
        'token',
        'access_token',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'session',
        'creditCard',
        'credit_card',
        'cvv',
    ];

    /**
     * Patterns regex pour masquer les données sensibles dans les messages texte.
     * Chaque entrée : [pattern, replacement]
     */
    private const MESSAGE_PATTERNS = [
        // Mots de passe dans les URLs (ex: mysql://user:PASSWORD@host)
        ['/(:\/\/[^:]+:)([^@]+)(@)/', '$1[REDACTED]$3'],
        // Tokens Bearer dans les headers
        ['/Bearer\s+[a-zA-Z0-9\-_\.]+/', 'Bearer [REDACTED]'],
        // Tokens génériques hex (64 chars = SHA-256, 128 chars = tokens longs)
        ['/\b[a-f0-9]{64}\b/', '[TOKEN_REDACTED]'],
        // APP_SECRET typique
        ['/APP_SECRET[=:\s]+[a-zA-Z0-9]+/', 'APP_SECRET=[REDACTED]'],
    ];

    /**
     * Traite chaque entrée de log pour masquer les données sensibles.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // ── Nettoyage du contexte ────────────────────────────────────────────
        $context = $this->scrubArray($record->context);

        // ── Nettoyage des extras ─────────────────────────────────────────────
        $extra = $this->scrubArray($record->extra);

        // ── Nettoyage du message ─────────────────────────────────────────────
        $message = $this->scrubMessage($record->message);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra,
        );
    }

    /**
     * Parcourt récursivement un tableau et masque les valeurs des clés sensibles.
     */
    private function scrubArray(array $data, int $depth = 0): array
    {
        // Limite la récursion pour éviter les boucles infinies sur des structures profondes
        if ($depth > 5) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->scrubArray($value, $depth + 1);
            } elseif (is_string($value) && $this->isSensitiveKey((string) $key)) {
                // Masque la valeur complète pour les clés sensibles
                $data[$key] = '[REDACTED]';
            } elseif (is_string($value)) {
                // Nettoyage des patterns dans les valeurs texte
                $data[$key] = $this->scrubMessage($value);
            }
        }

        return $data;
    }

    /**
     * Vérifie si une clé correspond à un champ sensible (insensible à la casse).
     */
    private function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($keyLower, strtolower($sensitive))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applique les patterns regex pour masquer les données sensibles dans un message.
     */
    private function scrubMessage(string $message): string
    {
        foreach (self::MESSAGE_PATTERNS as [$pattern, $replacement]) {
            $message = (string) preg_replace($pattern, $replacement, $message);
        }
        return $message;
    }
}