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
        $builder
            ->add('brand', TextType::class, [
                'label'       => 'Marque',
                'constraints' => [new NotBlank(), new Length(['max' => 100])],
                'attr'        => ['placeholder' => 'Toyota, Renault...'],
            ])
            ->add('model', TextType::class, [
                'label'       => 'Modèle',
                'constraints' => [new NotBlank(), new Length(['max' => 100])],
                'attr'        => ['placeholder' => 'Prius, Clio...'],
            ])
            ->add('color', TextType::class, [
                'label'       => 'Couleur',
                'constraints' => [new NotBlank(), new Length(['max' => 50])],
                'attr'        => ['placeholder' => 'Gris, Blanc...'],
            ])
            ->add('seats', IntegerType::class, [
                'label'       => 'Nombre de places (total)',
                'constraints' => [new NotBlank(), new Range(['min' => 2, 'max' => 9])],
                'attr'        => ['min' => 2, 'max' => 9],
                'help'        => 'Places totales, conducteur inclus.',
            ])
            ->add('trunkSize', EnumType::class, [
                'class'        => LuggageSize::class,
                'label'        => 'Taille du coffre',
                'choice_label' => fn(LuggageSize $s) => $s->name,
            ])
            ->add('isElectric', CheckboxType::class, [
                'label'    => 'Véhicule électrique',
                'required' => false,
            ])
            ->add('hasAc', CheckboxType::class, [
                'label'    => 'Climatisation',
                'required' => false,
            ])
            ->add('hasUsb', CheckboxType::class, [
                'label'    => 'Ports USB',
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label'    => 'Véhicule par défaut pour mes trajets',
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