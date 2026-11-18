<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\MusicGenre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label'       => 'Prénom',
                'constraints' => [new NotBlank(), new Length(['min' => 2, 'max' => 50])],
                'attr'        => ['placeholder' => 'Votre prénom'],
            ])
            ->add('lastName', TextType::class, [
                'label'       => 'Nom',
                'constraints' => [new NotBlank(), new Length(['min' => 2, 'max' => 50])],
                'attr'        => ['placeholder' => 'Votre nom'],
            ])
            ->add('bio', TextareaType::class, [
                'label'    => 'Bio',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Quelques mots sur vous...',
                    'rows'        => 4,
                ],
                'constraints' => [new Length(['max' => 500])],
            ])
            ->add('dateOfBirth', DateType::class, [
                'label'    => 'Date de naissance',
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'required' => false,
            ])
            ->add('avatar', UrlType::class, [
                'label'    => 'URL de votre photo de profil',
                'required' => false,
                'attr'     => ['placeholder' => 'https://exemple.com/photo.jpg'],
            ])
            ->add('musicGenre', EnumType::class, [
                'class'        => MusicGenre::class,
                'label'        => 'Genre musical en voiture',
                'required'     => false,
                'placeholder'  => 'Pas de préférence',
                'choice_label' => fn(MusicGenre $m) => ucfirst($m->value),
            ])
            ->add('smokingAllowed', ChoiceType::class, [
                'label'    => 'Tabac',
                'required' => false,
                'choices'  => [
                    'Autorisé'     => true,
                    'Non autorisé' => false,
                ],
                'placeholder' => 'Non spécifié',
            ])
            ->add('petsAllowed', ChoiceType::class, [
                'label'    => 'Animaux',
                'required' => false,
                'choices'  => [
                    'Autorisé'     => true,
                    'Non autorisé' => false,
                ],
                'placeholder' => 'Non spécifié',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}