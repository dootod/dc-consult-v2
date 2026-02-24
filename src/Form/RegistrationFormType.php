<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Formulaire d'inscription utilisateur.
 *
 * Renforcements de sécurité (OWASP A03 : Injection / Validation) :
 *  - Contraintes NotBlank + Length (min ET max) sur tous les champs texte
 *  - Regex sur nom/prénom pour rejeter les caractères non alphabétiques
 *  - PasswordStrength : force minimale de mot de passe
 *  - CSRF automatiquement géré par Symfony Forms (AbstractType)
 *  - Longueur max sur l'email pour éviter les overflows
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Nom ──────────────────────────────────────────────────────────
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Entrez un nom.'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    // Accepte les lettres (avec accents), tirets, espaces et apostrophes
                    // Prévient l'injection de caractères de contrôle ou de scripts
                    new Regex(
                        pattern: '/^[\p{L}\s\-\']+$/u',
                        message: 'Le nom ne doit contenir que des lettres, espaces, tirets et apostrophes.',
                    ),
                ],
            ])

            // ── Prénom ────────────────────────────────────────────────────────
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Entrez un prénom.'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                    new Regex(
                        pattern: '/^[\p{L}\s\-\']+$/u',
                        message: 'Le prénom ne doit contenir que des lettres, espaces, tirets et apostrophes.',
                    ),
                ],
            ])

            // ── Email ─────────────────────────────────────────────────────────
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(message: 'Entrez une adresse email.'),
                    new Email(
                        message: 'Entrez une adresse email valide.',
                        // 'html5' utilise la validation HTML5 (mode par défaut)
                        // 'strict' utilise une validation RFC plus stricte
                        mode: 'html5',
                    ),
                    new Length(
                        max: 180,
                        maxMessage: 'L\'adresse email ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])

            // ── Mot de passe ──────────────────────────────────────────────────
            ->add('plainPassword', PasswordType::class, [
                // Non mappé : sera haché manuellement dans le controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Entrez un mot de passe.'),
                    new Length(
                        min: 8,
                        minMessage: 'Votre mot de passe doit avoir au moins {{ limit }} caractères.',
                        // Limite Symfony pour éviter les attaques DoS par hachage long
                        max: 4096,
                    ),
                    // OWASP recommande de mesurer la force réelle du mot de passe
                    // plutôt que d'imposer des règles arbitraires de complexité
                    // PasswordStrength disponible depuis Symfony 6.3
                    new PasswordStrength(
                        minScore: PasswordStrength::STRENGTH_WEAK,
                        message: 'Ce mot de passe est trop faible. Essayez une phrase ou ajoutez des chiffres et symboles.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}