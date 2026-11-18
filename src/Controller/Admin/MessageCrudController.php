<?php

namespace App\Controller\Admin;

use App\Entity\Message;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class MessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('conversation', 'Conversation'),
            AssociationField::new('sender', 'Expéditeur'),
            TextareaField::new('content', 'Contenu'),
            DateTimeField::new('sentAt', 'Envoyé le')->onlyOnIndex(),
            DateTimeField::new('deletedAt', 'Supprimé le')->onlyOnIndex(),
        ];
    }
}