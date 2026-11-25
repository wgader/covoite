<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\MusicGenre;
use App\Validator\TextRules;
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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label'       => 'Prenom',
                'constraints' => TextRules::name('le prenom'),
            ])
            ->add('lastName', TextType::class, [
                'label'       => 'Nom',
                'constraints' => TextRules::name('le nom'),
            ])
            ->add('phone', TelType::class, [
                'label'    => 'Telephone',
                'required' => false,
                'attr'     => ['placeholder' => '06 12 34 56 78'],
                'constraints' => [
                    new Length(max: 20),
                    new Regex(
                        pattern: '/^(\+33|0033|0)\s?[1-9](?:[\s.-]?\d{2}){4}$/',
                        message: 'Numero de telephone francais invalide (ex : 06 12 34 56 78).',
                    ),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label'    => 'A propos de vous',
                'required' => false,
                'attr'     => ['placeholder' => 'Etudiant a Limoges, je fais souvent Angouleme - Limoges...', 'rows' => 4],
                'constraints' => TextRules::text('la description', max: 500),
            ])
            // L'entite User stocke un \DateTime (mutable) -> input 'datetime'
            ->add('dateOfBirth', DateType::class, [
                'label'       => 'Date de naissance',
                'widget'      => 'single_text',
                'input'       => 'datetime',
                'invalid_message' => 'Date de naissance invalide.',
                'constraints' => [
                    new GreaterThan(value: '-100 years', message: 'Date de naissance invalide.'),
                    new NotBlank(message: 'La date de naissance est obligatoire.'),
                    new LessThan(value: '-18 years', message: 'Vous devez avoir au moins 18 ans.'),
                ],
            ])
            ->add('avatar', UrlType::class, [
                'label'    => 'Photo de profil (lien URL)',
                'required' => false,
                'default_protocol' => 'https',
                // Uniquement un lien http(s) vers une image : pas de "javascript:" ni d'autre protocole
                'constraints' => [
                    new Length(max: 255, maxMessage: 'Lien trop long (255 caracteres maximum).'),
                    new Url(protocols: ['https', 'http'], requireTld: true, message: 'Ce lien n\'est pas une adresse web valide.'),
                ],
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
