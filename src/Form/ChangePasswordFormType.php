<?php

namespace App\Form;

use App\Validator\TextRules;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Changer SON mot de passe.
 * Pas d'id d'utilisateur dans ce formulaire : le controleur travaille toujours sur l'utilisateur
 * connecte, et l'ancien mot de passe est obligatoire (UserPassword le compare au hash en base).
 */
class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'mapped'      => false,
                'attr'        => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Indiquez votre mot de passe actuel.'),
                    new UserPassword(message: 'Mot de passe actuel incorrect.'),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'invalid_message' => 'Les deux nouveaux mots de passe ne correspondent pas.',
                'first_options'   => [
                    'attr'        => ['autocomplete' => 'new-password'],
                    'constraints' => TextRules::password(),
                ],
                'second_options'  => [
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
