<?php

namespace App\Controller\Admin;

use App\Entity\Conversation;
use App\Enum\ConversationType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class ConversationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Conversation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            ChoiceField::new('type', 'Type')->setChoices([
                'Direct' => ConversationType::Direct,
                'Groupe' => ConversationType::Group,
            ]),
            AssociationField::new('trip', 'Trajet lié'),
            DateTimeField::new('suspendedAt', 'Suspendu le')->onlyOnIndex(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex(),
        ];
    }
}