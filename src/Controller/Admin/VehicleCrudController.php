<?php

namespace App\Controller\Admin;

use App\Entity\Vehicle;
use App\Enum\LuggageSize;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class VehicleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Vehicle::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('owner', 'Propriétaire'),
            TextField::new('brand', 'Marque'),
            TextField::new('model', 'Modèle'),
            TextField::new('color', 'Couleur'),
            IntegerField::new('seats', 'Places'),
            ChoiceField::new('trunkSize', 'Coffre')->setChoices([
                'Aucun' => LuggageSize::None,
                'Petit' => LuggageSize::Small,
                'Moyen' => LuggageSize::Medium,
                'Grand' => LuggageSize::Large,
                'XL' => LuggageSize::Xl,
            ]),
            BooleanField::new('isElectric', 'Électrique'),
            BooleanField::new('hasAc', 'Climatisation'),
            BooleanField::new('hasUsb', 'USB'),
            BooleanField::new('isDefault', 'Véhicule par défaut'),
        ];
    }
}