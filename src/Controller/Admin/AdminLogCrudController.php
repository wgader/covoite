<?php

namespace App\Controller\Admin;

use App\Entity\AdminLog;
use App\Enum\AdminActionType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class AdminLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AdminLog::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('adminUser', 'Admin'),
            ChoiceField::new('action', 'Action')->setChoices(
                array_combine(
                    array_map(fn($case) => $case->value, AdminActionType::cases()),
                    AdminActionType::cases()
                )
            ),
            TextField::new('targetType', 'Cible'),
            IntegerField::new('targetId', 'ID cible'),
            TextareaField::new('reason', 'Raison')->onlyOnForms(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex(),
        ];
    }
}