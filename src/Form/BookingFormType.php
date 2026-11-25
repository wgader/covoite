<?php

namespace App\Form;

use App\Validator\TextRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxSeats = $options['max_seats'];

        $seatsChoices = [];
        for ($i = 1; $i <= $maxSeats; $i++) {
            $seatsChoices[$i . ' place' . ($i > 1 ? 's' : '')] = $i;
        }

        $builder
            ->add('seats', ChoiceType::class, [
                'label'   => false,
                'choices' => $seatsChoices,
                // Un nombre de places hors de la liste (ex : 99 envoye a la main) est refuse
                'invalid_message' => 'Nombre de places invalide.',
            ])
            ->add('message', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Un mot pour le conducteur ? (optionnel)',
                    'rows'        => 3,
                    'maxlength'   => 500,
                ],
                'constraints' => TextRules::text('le message', max: 500),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'max_seats' => 1,
        ]);

        $resolver->setAllowedTypes('max_seats', 'int');
    }
}