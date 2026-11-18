<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Enum\ReviewType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class ReviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('author', 'Auteur'),
            AssociationField::new('target', 'Cible'),
            AssociationField::new('booking', 'Réservation')->onlyOnForms(),
            IntegerField::new('rating', 'Note'),
            TextareaField::new('comment', 'Commentaire')->onlyOnForms(),
            ChoiceField::new('type', 'Type')->setChoices([
                'Conducteur' => ReviewType::Driver,
                'Passager' => ReviewType::Passenger,
            ]),
            BooleanField::new('isAutomatic', 'Automatique')->onlyOnIndex(),
            DateTimeField::new('suspendedAt', 'Suspendu le')->onlyOnIndex(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex(),
        ];
    }
}