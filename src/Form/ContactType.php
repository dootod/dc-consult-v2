<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => false,
                'attr'        => [
                    'placeholder' => 'Nom *',
                    'class'       => 'dc-form-control',
                    'autocomplete'=> 'family-name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre nom.']),
                    new Length(['max' => 100, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label'       => false,
                'attr'        => [
                    'placeholder' => 'Prénom *',
                    'class'       => 'dc-form-control',
                    'autocomplete'=> 'given-name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre prénom.']),
                    new Length(['max' => 100, 'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères.']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label'       => false,
                'attr'        => [
                    'placeholder' => 'Adresse email *',
                    'class'       => 'dc-form-control',
                    'autocomplete'=> 'email',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre adresse email.']),
                    new Email(['message' => 'L\'adresse email « {{ value }} » n\'est pas valide.']),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label'       => false,
                'required'    => false,
                'attr'        => [
                    'placeholder' => 'Numéro de téléphone',
                    'class'       => 'dc-form-control',
                    'autocomplete'=> 'tel',
                ],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[\d\s\+\-\(\)\.]{6,20}$/',
                        'message' => 'Le numéro de téléphone n\'est pas valide.',
                        'match'   => true,
                    ]),
                ],
            ])
            ->add('sujet', TextType::class, [
                'label'       => false,
                'attr'        => [
                    'placeholder' => 'Sujet *',
                    'class'       => 'dc-form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un sujet.']),
                    new Length(['max' => 200, 'maxMessage' => 'Le sujet ne peut pas dépasser {{ limit }} caractères.']),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label'       => false,
                'attr'        => [
                    'placeholder' => 'Votre message *',
                    'class'       => 'dc-form-control',
                    'rows'        => 6,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre message.']),
                    new Length([
                        'min'        => 10,
                        'minMessage' => 'Votre message doit contenir au moins {{ limit }} caractères.',
                        'max'        => 3000,
                        'maxMessage' => 'Votre message ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'contact_form',
        ]);
    }
}