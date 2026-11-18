<?php

namespace App\Form;

use App\Entity\Vehicle;
use App\Enum\LuggageSize;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class VehicleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // IMPORTANT Symfony 8 : les contraintes prennent des arguments nommes
        // new Length(max: 100)  et plus  new Length(['max' => 100])
        $builder
            ->add('brand', TextType::class, [
                'label'       => 'Marque',
                'constraints' => [
                    new NotBlank(message: 'Indiquez la marque.'),
                    new Length(max: 100),
                ],
                'attr' => ['placeholder' => 'Peugeot, Renault...'],
            ])
            ->add('model', TextType::class, [
                'label'       => 'Modele',
                'constraints' => [
                    new NotBlank(message: 'Indiquez le modele.'),
                    new Length(max: 100),
                ],
                'attr' => ['placeholder' => '208, Clio...'],
            ])
            ->add('color', TextType::class, [
                'label'       => 'Couleur',
                'constraints' => [
                    new NotBlank(message: 'Indiquez la couleur.'),
                    new Length(max: 50),
                ],
                'attr' => ['placeholder' => 'Gris, Blanc...'],
            ])
            ->add('seats', IntegerType::class, [
                'label'       => 'Places (conducteur inclus)',
                'constraints' => [
                    new NotBlank(message: 'Indiquez le nombre de places.'),
                    new Range(min: 2, max: 9),
                ],
                'attr' => ['min' => 2, 'max' => 9],
            ])
            ->add('trunkSize', EnumType::class, [
                'class'        => LuggageSize::class,
                'label'        => 'Taille du coffre',
                'expanded'     => true,
                'choice_label' => fn (LuggageSize $s) => match ($s) {
                    LuggageSize::None   => 'Aucun',
                    LuggageSize::Small  => 'Petit',
                    LuggageSize::Medium => 'Moyen',
                    LuggageSize::Large  => 'Grand',
                    LuggageSize::Xl     => 'Tres grand',
                },
            ])
            ->add('isElectric', CheckboxType::class, [
                'label'    => 'Vehicule electrique',
                'required' => false,
            ])
            ->add('hasAc', CheckboxType::class, [
                'label'    => 'Climatisation',
                'required' => false,
            ])
            ->add('hasUsb', CheckboxType::class, [
                'label'    => 'Prises USB',
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label'    => 'Vehicule par defaut',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicle::class,
        ]);
    }
}
