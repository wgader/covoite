<?php

namespace App\Form;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\LuggageSize;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Formulaire de publication / modification d'un trajet.
 * Il est affiche en plusieurs etapes par templates/trip/_wizard.html.twig,
 * mais cote Symfony c'est un seul formulaire, envoye en une fois a la fin.
 */
class TripFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $driver = $options['driver'];

        $builder
            // ── Depart (rempli automatiquement par l'autocompletion d'adresse)
            ->add('departureAddress', TextType::class, [
                'constraints' => [new NotBlank(message: 'Indiquez votre point de depart.'), new Length(max: 255)],
            ])
            ->add('departureCity', TextType::class, [
                'constraints' => [new NotBlank(message: 'La ville de depart est obligatoire.'), new Length(max: 100)],
            ])
            ->add('departurePostalCode', TextType::class, [
                'constraints' => [new NotBlank(message: 'Le code postal est obligatoire.'), new Length(max: 10)],
            ])
            ->add('departureLat', HiddenType::class, ['required' => false])
            ->add('departureLng', HiddenType::class, ['required' => false])

            // ── Arrivee
            ->add('arrivalAddress', TextType::class, [
                'constraints' => [new NotBlank(message: 'Indiquez votre destination.'), new Length(max: 255)],
            ])
            ->add('arrivalCity', TextType::class, [
                'constraints' => [new NotBlank(message: 'La ville d\'arrivee est obligatoire.'), new Length(max: 100)],
            ])
            ->add('arrivalPostalCode', TextType::class, [
                'constraints' => [new NotBlank(message: 'Le code postal est obligatoire.'), new Length(max: 10)],
            ])
            ->add('arrivalLat', HiddenType::class, ['required' => false])
            ->add('arrivalLng', HiddenType::class, ['required' => false])

            // ── Date et heure (un seul champ, rempli par les selecteurs date + heure en JS)
            ->add('departureAt', DateTimeType::class, [
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Choisissez une date et une heure de depart.')],
            ])

            // ── Places et prix
            ->add('availableSeats', IntegerType::class, [
                'constraints' => [new NotBlank(), new Range(min: 1, max: 8)],
            ])
            ->add('price', NumberType::class, [
                'scale'       => 2,
                'constraints' => [new NotBlank(message: 'Indiquez un prix.'), new Range(min: 0, max: 500)],
            ])

            // ── Confort
            ->add('luggageAllowed', EnumType::class, [
                'class'        => LuggageSize::class,
                'required'     => false,
                'expanded'     => true,   // boutons "pastilles" au lieu d'une liste deroulante
                'placeholder'  => false,
                'choice_label' => fn (LuggageSize $s) => match ($s) {
                    LuggageSize::None   => 'Aucun',
                    LuggageSize::Small  => 'Petit sac',
                    LuggageSize::Medium => 'Valise cabine',
                    LuggageSize::Large  => 'Grande valise',
                    LuggageSize::Xl     => 'Volumineux',
                },
            ])
            ->add('avoidHighway', CheckboxType::class, [
                'required' => false,
            ])
            ->add('bio', TextareaType::class, [
                'required'    => false,
                'constraints' => [new Length(max: 1000)],
            ]);

        if ($driver !== null) {
            $builder->add('vehicle', EntityType::class, [
                'class'         => Vehicle::class,
                'required'      => false,
                'expanded'      => true,  // cartes cliquables
                'placeholder'   => false,
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('v')
                    ->where('v.owner = :driver')
                    ->setParameter('driver', $driver)
                    ->orderBy('v.isDefault', 'DESC'),
                'choice_label'  => fn (Vehicle $v) => $v->getBrand() . ' ' . $v->getModel(),
                // Infos lues par le JavaScript (places max, couleur affichee sur la carte)
                'choice_attr'   => fn (Vehicle $v) => [
                    'data-seats' => $v->getSeats(),
                    'data-color' => $v->getColor(),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trip::class,
            'driver'     => null,
        ]);
        $resolver->setAllowedTypes('driver', ['null', User::class]);
    }
}
