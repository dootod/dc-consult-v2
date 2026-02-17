<?php

namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentVoter extends Voter
{
    public const VIEW   = 'DOCUMENT_VIEW';
    public const EDIT   = 'DOCUMENT_EDIT';
    public const DELETE = 'DOCUMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Utilisateur) {
            return false;
        }

        /** @var Document $document */
        $document = $subject;

        return $document->getUtilisateur() === $user;
    }
}