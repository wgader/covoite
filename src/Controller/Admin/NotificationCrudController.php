<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Enum\NotificationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('user', 'Utilisateur'),
            ChoiceField::new('type', 'Type')->setChoices([
                'Retard' => NotificationType::Delay,
                'Réservation confirmée' => NotificationType::BookingConfirmed,
                'Réservation annulée' => NotificationType::BookingCancelled,
                'Trajet annulé' => NotificationType::TripCancelled,
                'Nouveau message' => NotificationType::NewMessage,
                'Nouvel avis' => NotificationType::NewReview,
            ]),
            TextareaField::new('content', 'Contenu')->onlyOnForms(),
            BooleanField::new('isRead', 'Lu')->onlyOnIndex(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex(),
        ];
    }
}