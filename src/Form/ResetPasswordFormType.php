<?php

namespace App\Form;

use App\Validator\TextRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Choisir un nouveau mot de passe apres avoir clique sur le lien recu par e-mail. */
class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('newPassword', RepeatedType::class, [
            'type'            => PasswordType::class,
            'mapped'          => false,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'first_options'   => ['attr' => ['autocomplete' => 'new-password'], 'constraints' => TextRules::password()],
            'second_options'  => ['attr' => ['autocomplete' => 'new-password']],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
