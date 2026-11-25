<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Regex;

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
                    'constraints' => [
                        new NotBlank(message: 'Choisissez un nouveau mot de passe.'),
                        new Length(min: 8, max: 4096, minMessage: 'Au moins {{ limit }} caracteres.'),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])/',
                            message: 'Il faut une majuscule, une minuscule, un chiffre et un caractere special.',
                        ),
                        // Refuse les mots de passe deja apparus dans des fuites de donnees (service haveibeenpwned,
                        // seuls les 5 premiers caracteres du hash sont envoyes). Si le service est injoignable, on laisse passer.
                        new NotCompromisedPassword(message: 'Ce mot de passe a deja fuite sur Internet. Choisissez-en un autre.', skipOnError: true),
                    ],
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
