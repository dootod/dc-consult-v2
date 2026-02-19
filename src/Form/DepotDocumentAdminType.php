<?php

namespace App\Form;

use App\Entity\DocumentAdmin;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class DepotDocumentAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du document',
                'constraints' => [
                    new NotBlank(message: 'Le nom du document est obligatoire.'),
                ],
            ])
            ->add('destinataire', EntityType::class, [
                'class'        => Utilisateur::class,
                'label'        => 'Destinataire',
                'choice_label' => fn(Utilisateur $u) => $u->getPrenom() . ' ' . $u->getNom() . ' (' . $u->getEmail() . ')',
                'placeholder'  => '-- Sélectionner un utilisateur --',
                'constraints'  => [
                    new NotBlank(message: 'Veuillez sélectionner un destinataire.'),
                ],
                // On exclut les admins : seuls les ROLE_USER sont destinataires
                'query_builder' => fn(UtilisateurRepository $repo) => $repo
                    ->createQueryBuilder('u')
                    ->orderBy('u.nom', 'ASC'),
            ])
            ->add('fichierFile', FileType::class, [
                'label'    => 'Fichier (PDF, Word, etc.)',
                'mapped'   => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un fichier.'),
                    new File(
                        maxSize: '10M',
                        maxSizeMessage: 'Le fichier ne doit pas dépasser 10 Mo.',
                        mimeTypesMessage: 'Format de fichier non autorisé.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentAdmin::class,
        ]);
    }
}