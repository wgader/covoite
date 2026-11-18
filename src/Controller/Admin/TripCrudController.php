<?php

namespace App\Controller\Admin;

use App\Entity\Trip;
use App\Enum\TripStatus;
use App\Enum\LuggageSize;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class TripCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Trip::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('driver', 'Conducteur'),
            AssociationField::new('vehicle', 'Véhicule')->onlyOnForms(),
            TextField::new('departureCity', 'Départ'),
            TextField::new('arrivalCity', 'Arrivée'),
            DateTimeField::new('departureAt', 'Date de départ'),
            MoneyField::new('price', 'Prix')->setCurrency('EUR'),
            IntegerField::new('availableSeats', 'Places dispo')->onlyOnIndex(),
            ChoiceField::new('status', 'Statut')->setChoices([
                'Actif' => TripStatus::Active,
                'Complet' => TripStatus::Full,
                'Annulé' => TripStatus::Cancelled,
                'Terminé' => TripStatus::Completed,
            ]),
            ChoiceField::new('luggageAllowed', 'Bagages')->setChoices([
                'Aucun' => LuggageSize::None,
                'Petit' => LuggageSize::Small,
                'Moyen' => LuggageSize::Medium,
                'Grand' => LuggageSize::Large,
                'XL' => LuggageSize::Xl,
            ])->onlyOnForms(),
            BooleanField::new('avoidHighway', 'Sans autoroute')->onlyOnForms(),
            DateTimeField::new('suspendedAt', 'Suspendu le')->onlyOnIndex(),
            DateTimeField::new('deletedAt', 'Supprimé le')->onlyOnIndex(),
        ];
    }
}