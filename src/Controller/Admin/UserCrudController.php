<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            EmailField::new('email'),
            TextField::new('firstName', 'Prénom'),
            TextField::new('lastName', 'Nom'),
            TelephoneField::new('phone', 'Téléphone'),
            ChoiceField::new('roles', 'Rôles')
                ->allowMultipleChoices()
                ->setChoices([
                    'Utilisateur' => 'ROLE_USER',
                    'Admin' => 'ROLE_ADMIN',
                ])
                ->onlyOnForms(),
            TextareaField::new('bio', 'Bio')->onlyOnForms(),
            ImageField::new('avatar', 'Avatar')->setBasePath('')->onlyOnIndex(),
            DateField::new('dateOfBirth', 'Date de naissance')->onlyOnForms(),
            BooleanField::new('smokingAllowed', 'Fumeur')->onlyOnForms(),
            BooleanField::new('petsAllowed', 'Animaux')->onlyOnForms(),
            MoneyField::new('balancePending', 'Solde en attente')
                ->setCurrency('EUR')
                ->onlyOnIndex(),
            MoneyField::new('balanceAvailable', 'Solde disponible')
                ->setCurrency('EUR')
                ->onlyOnIndex(),
            DateTimeField::new('verifiedAt', 'Vérifié le')->onlyOnIndex(),
            DateTimeField::new('suspendedAt', 'Suspendu le')->onlyOnIndex(),
            DateTimeField::new('deletedAt', 'Supprimé le')->onlyOnIndex(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex(),
        ];
    }
}