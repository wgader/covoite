<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\TextRules;
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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => false,
                'constraints' => TextRules::name('le prenom'),
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'constraints' => TextRules::name('le nom'),
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(message: 'Merci de renseigner votre adresse e-mail.'),
                    new Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                    new Length(max: 180, maxMessage: 'Adresse e-mail trop longue.'),
                ],
            ])
            ->add('dateOfBirth', BirthdayType::class, [
                'label' => false,
                'widget' => 'single_text',
                'invalid_message' => 'Date de naissance invalide.',
                'constraints' => [
                    new NotBlank(message: 'Indiquez votre date de naissance.'),
                    new LessThanOrEqual(value: '-18 years', message: 'Vous devez avoir au moins 18 ans pour vous inscrire.'),
                    new GreaterThan(value: '-100 years', message: 'Date de naissance invalide.'),
                ],
            ])
            ->add('phone', TelType::class, [
                'label'    => false,
                'required' => false,
                'mapped'   => false, // à câbler sur l'entité User si tu veux le sauvegarder
                'constraints' => [
                    new Regex(
                        pattern: '/^(\+33|0033|0)\s?[1-9](?:[\s.-]?\d{2}){4}$/', // espaces, points ou tirets acceptes
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