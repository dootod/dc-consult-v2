<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du document',
            ])
            ->add('fichierFile', FileType::class, [
                'label'       => 'Fichier',
                'mapped'      => false, 
                'required'    => $options['is_new'],
                'constraints' => $options['is_new'] ? [
                    new File([
                        'maxSize'          => '10M',
                        'mimeTypesMessage' => 'Veuillez téléverser un fichier valide.',
                    ]),
                ] : [],
                'attr' => $options['is_new'] ? [] : ['placeholder' => 'Laisser vide pour ne pas changer le fichier'],
            ])
        ;
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