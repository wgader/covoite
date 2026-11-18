<?php

namespace App\Form;

use App\Entity\Trip;
use App\Entity\Vehicle;
use App\Enum\LuggageSize;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TripFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $driver = $options['driver'];

        $builder
            // ── Départ
            ->add('departureAddress', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Adresse complète de départ', 'autocomplete' => 'off'],
            ])
            ->add('departureCity', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Ville'],
            ])
            ->add('departurePostalCode', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Code postal', 'maxlength' => 10],
            ])
            ->add('departureLat', HiddenType::class, ['required' => false])
            ->add('departureLng', HiddenType::class, ['required' => false])

            // ── Arrivée
            ->add('arrivalAddress', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Adresse complète d\'arrivée', 'autocomplete' => 'off'],
            ])
            ->add('arrivalCity', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Ville'],
            ])
            ->add('arrivalPostalCode', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Code postal', 'maxlength' => 10],
            ])
            ->add('arrivalLat', HiddenType::class, ['required' => false])
            ->add('arrivalLng', HiddenType::class, ['required' => false])

            // ── Date
            ->add('departureAt', DateTimeType::class, [
                'label'  => false,
                'widget' => 'single_text',
                'html5'  => true,
            ])

            // ── Tarif & places
            ->add('price', NumberType::class, [
                'label' => false,
                'scale' => 2,
                'attr'  => ['placeholder' => '0.00', 'min' => '0', 'step' => '0.50'],
            ])
            ->add('availableSeats', IntegerType::class, [
                'label' => false,
                'attr'  => ['min' => 1, 'max' => 8],
            ])

            // ── Options
            ->add('luggageAllowed', ChoiceType::class, [
                'label'       => false,
                'required'    => false,
                'placeholder' => 'Bagages autorisés...',
                'choices'     => [
                    'Aucun bagage'       => LuggageSize::None,
                    'Petit bagage'       => LuggageSize::Small,
                    'Bagage moyen'       => LuggageSize::Medium,
                    'Grand bagage'       => LuggageSize::Large,
                    'Très grand bagage'  => LuggageSize::Xl,
                ],
            ])
            ->add('avoidHighway', CheckboxType::class, [
                'label'    => 'Éviter les autoroutes',
                'required' => false,
            ])
            ->add('delayMinutes', IntegerType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['placeholder' => '0', 'min' => 0, 'max' => 60],
            ])
            ->add('bio', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Un mot pour les passagers... (optionnel)', 'rows' => 3],
            ])
        ;

        if ($driver !== null) {
            $builder->add('vehicle', EntityType::class, [
                'label'         => false,
                'class'         => Vehicle::class,
                'required'      => false,
                'placeholder'   => 'Sélectionner un véhicule (optionnel)',
                'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('v')
                    ->where('v.owner = :driver')
                    ->setParameter('driver', $driver),
                'choice_label'  => fn(Vehicle $v) => $v->getBrand() . ' ' . $v->getModel() . ' — ' . $v->getColor(),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trip::class,
            'driver'     => null,
        ]);
        $resolver->setAllowedTypes('driver', ['null', \App\Entity\User::class]);
    }
}