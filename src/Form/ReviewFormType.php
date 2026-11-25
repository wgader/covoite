<?php

namespace App\Form;

use App\Validator\TextRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class ReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label'    => 'Note',
                'expanded' => true,
                'invalid_message' => 'Choisissez une note entre 1 et 5 etoiles.',
                'multiple' => false,
                // Dans l'ordre des etoiles (de gauche a droite). Le libelle sert d'info-bulle et aux lecteurs d'ecran.
                'choices'  => [
                    '1 etoile - Decevant'   => 1,
                    '2 etoiles - Passable'  => 2,
                    '3 etoiles - Bien'      => 3,
                    '4 etoiles - Tres bien' => 4,
                    '5 etoiles - Excellent' => 5,
                ],
                'constraints' => [
                    new NotBlank(message: 'Choisissez une note.'),
                    new Range(min: 1, max: 5),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label'       => 'Votre commentaire (optionnel)',
                'required'    => false,
                'constraints' => TextRules::text('le commentaire', max: 1000),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
