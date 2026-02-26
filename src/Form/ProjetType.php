<?php

namespace App\Form;

use App\Entity\Projet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjetType extends AbstractType
{
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const IMAGE_MAX_SIZE   = '5M';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre',
                'attr'        => ['placeholder' => 'Ex : Résidence Les Acacias'],
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['placeholder' => 'Description du projet…', 'rows' => 5],
            ])
            ->add('localisation', TextType::class, [
                'label'    => 'Localisation',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : Paris 15e'],
            ])
            ->add('date', DateType::class, [
                'label'    => 'Date',
                'required' => false,
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
            ])
            ->add('taille', TextType::class, [
                'label'    => 'Taille',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : R+5 1 100 m²'],
            ])
            ->add('maitreOuvrage', TextType::class, [
                'label'    => 'Maître d\'ouvrage',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : SCI Patrimoine'],
            ])
            ->add('maitreOeuvre', TextType::class, [
                'label'    => 'Maître d\'œuvre',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : Cabinet DC Architectes'],
            ])

            // ── Image de couverture (1 seule) ─────────────────────────────────
            // Obligatoire à la création. Sera automatiquement affichée en tête du carousel.
            ->add('coverFile', FileType::class, [
                'label'       => 'Image de couverture',
                'mapped'      => false,
                'required'    => $isNew,
                'multiple'    => false,
                'attr'        => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => $isNew ? [
                    new NotBlank(message: 'Une image de couverture est obligatoire.'),
                    new File(
                        maxSize: self::IMAGE_MAX_SIZE,
                        maxSizeMessage: 'L\'image ne doit pas dépasser 5 Mo.',
                        mimeTypes: self::IMAGE_MIME_TYPES,
                        mimeTypesMessage: 'Seules les images JPEG, PNG et WebP sont acceptées.',
                    ),
                ] : [
                    new File(
                        maxSize: self::IMAGE_MAX_SIZE,
                        maxSizeMessage: 'L\'image ne doit pas dépasser 5 Mo.',
                        mimeTypes: self::IMAGE_MIME_TYPES,
                        mimeTypesMessage: 'Seules les images JPEG, PNG et WebP sont acceptées.',
                    ),
                ],
            ])

            // ── Images du carousel (0 à N images) ─────────────────────────────
            // Optionnelles. La cover est toujours affichée en premier dans le carousel.
            ->add('carouselFiles', FileType::class, [
                'label'       => 'Images du carousel',
                'mapped'      => false,
                'required'    => false,
                'multiple'    => true,
                'attr'        => [
                    'accept'   => 'image/jpeg,image/png,image/webp',
                    'multiple' => 'multiple',
                ],
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File(
                                maxSize: self::IMAGE_MAX_SIZE,
                                maxSizeMessage: 'Chaque image ne doit pas dépasser 5 Mo.',
                                mimeTypes: self::IMAGE_MIME_TYPES,
                                mimeTypesMessage: 'Seules les images JPEG, PNG et WebP sont acceptées.',
                            ),
                        ],
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projet::class,
            'is_new'     => false,
        ]);

        $resolver->setAllowedTypes('is_new', 'bool');
    }
}