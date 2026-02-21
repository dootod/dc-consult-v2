<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du document',
                // ✅ FIX : contraintes côté serveur — le required HTML5 peut être contourné
                'constraints' => [
                    new NotBlank(message: 'Le nom du document est obligatoire.'),
                    new Length(
                        max: 255,
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('fichierFile', FileType::class, [
                'label'    => 'Fichier',
                'mapped'   => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                        mimeTypesMessage: 'Format non autorisé. Formats acceptés : PDF, images, Word, Excel.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'is_new'     => true,
        ]);

        $resolver->setAllowedTypes('is_new', 'bool');
    }
}