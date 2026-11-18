<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
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
                'multiple' => false,
                'choices'  => [
                    '5 — Excellent'    => 5,
                    '4 — Tres bien'    => 4,
                    '3 — Bien'         => 3,
                    '2 — Passable'     => 2,
                    '1 — Decevant'     => 1,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Choisissez une note.']),
                    new Range(['min' => 1, 'max' => 5]),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label'       => 'Votre commentaire (optionnel)',
                'required'    => false,
                'attr'        => ['rows' => 4, 'placeholder' => 'Decrivez votre experience...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}