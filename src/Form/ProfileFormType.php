<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\MusicGenre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label'       => 'Prenom',
                'constraints' => [new NotBlank(message: 'Le prenom est obligatoire.'), new Length(min: 2, max: 50)],
            ])
            ->add('lastName', TextType::class, [
                'label'       => 'Nom',
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.'), new Length(min: 2, max: 50)],
            ])
            ->add('phone', TelType::class, [
                'label'    => 'Telephone',
                'required' => false,
                'attr'     => ['placeholder' => '06 12 34 56 78'],
                'constraints' => [new Length(max: 20)],
            ])
            ->add('bio', TextareaType::class, [
                'label'    => 'A propos de vous',
                'required' => false,
                'attr'     => ['placeholder' => 'Etudiant a Limoges, je fais souvent Angouleme - Limoges...', 'rows' => 4],
                'constraints' => [new Length(max: 500)],
            ])
            // L'entite User stocke un \DateTime (mutable) -> input 'datetime'
            ->add('dateOfBirth', DateType::class, [
                'label'       => 'Date de naissance',
                'widget'      => 'single_text',
                'input'       => 'datetime',
                'constraints' => [
                    new NotBlank(message: 'La date de naissance est obligatoire.'),
                    new LessThan(value: '-18 years', message: 'Vous devez avoir au moins 18 ans.'),
                ],
            ])
            ->add('avatar', UrlType::class, [
                'label'    => 'Photo de profil (lien URL)',
                'required' => false,
                'default_protocol' => 'https',
                'attr'     => ['placeholder' => 'https://...'],
            ])
            ->add('musicGenre', EnumType::class, [
                'class'        => MusicGenre::class,
                'label'        => 'Musique en voiture',
                'required'     => false,
                'placeholder'  => 'Pas de preference',
                'choice_label' => fn (MusicGenre $m) => match ($m) {
                    MusicGenre::Rap       => 'Rap',
                    MusicGenre::Rnb       => 'RnB',
                    MusicGenre::Rock      => 'Rock',
                    MusicGenre::Pop       => 'Pop',
                    MusicGenre::Electro   => 'Electro',
                    MusicGenre::Classique => 'Classique',
                    MusicGenre::Jazz      => 'Jazz',
                    MusicGenre::Variete   => 'Variete',
                    MusicGenre::Silence   => 'Plutot le silence',
                    MusicGenre::Any       => 'Tout me va',
                },
            ])
            // Les champs sont des bool non-nullables -> une case a cocher, pas un select avec "vide"
            ->add('smokingAllowed', CheckboxType::class, [
                'label'    => 'Cigarette acceptee',
                'required' => false,
            ])
            ->add('petsAllowed', CheckboxType::class, [
                'label'    => 'Animaux acceptes',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
