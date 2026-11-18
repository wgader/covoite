<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class BookingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('trip', 'Trajet'),
            AssociationField::new('passenger', 'Passager'),
            IntegerField::new('seats', 'Places réservées'),
            MoneyField::new('amount', 'Montant')->setCurrency('EUR'),
            MoneyField::new('platformFee', 'Commission')->setCurrency('EUR')->onlyOnIndex(),
            MoneyField::new('driverEarns', 'Gains conducteur')->setCurrency('EUR')->onlyOnIndex(),
            ChoiceField::new('status', 'Statut')->setChoices([
                'En attente' => BookingStatus::Pending,
                'Confirmé' => BookingStatus::Confirmed,
                'Annulé' => BookingStatus::Cancelled,
                'Terminé' => BookingStatus::Completed,
            ]),
            TextareaField::new('message', 'Message')->onlyOnForms(),
            DateTimeField::new('paidToDriverAt', 'Versé au conducteur')->onlyOnIndex(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex(),
        ];
    }
}