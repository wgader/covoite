<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Merci de renseigner votre prénom.'),
                    new Length(max: 255),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Merci de renseigner votre nom.'),
                    new Length(max: 255),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Merci de renseigner votre adresse e-mail.'),
                    new Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                ],
            ])
            ->add('dateOfBirth', BirthdayType::class, [
                'label' => false,
                'widget' => 'single_text',
            ])
            ->add('phone', TelType::class, [
                'label'    => false,
                'required' => false,
                'mapped'   => false, // à câbler sur l'entité User si tu veux le sauvegarder
                'constraints' => [
                    new Regex(
                        pattern: '/^(\+33|0033|0)[1-9](\d{2}){4}$/',
                        message: 'Numéro de téléphone français invalide (ex : 06 12 34 56 78).',
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options'   => [
                    'label' => false,
                    'attr'  => ['autocomplete' => 'new-password'],
                    'constraints' => [
                        new NotBlank(message: 'Merci de choisir un mot de passe.'),
                        new Length(
                            min: 8,
                            minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                            max: 4096,
                        ),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])/',
                            message: 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.',
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr'  => ['autocomplete' => 'new-password'],
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped'      => false,
                'label'       => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions d\'utilisation.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}