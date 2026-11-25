<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** "Mot de passe oublie" : on demande juste l'adresse e-mail. */
class ForgotPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'attr'        => ['autocomplete' => 'email', 'placeholder' => 'vous@exemple.fr', 'maxlength' => 180],
            'constraints' => [
                new NotBlank(message: 'Indiquez votre adresse e-mail.'),
                new Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                new Length(max: 180),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
