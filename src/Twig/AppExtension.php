<?php

namespace App\Twig;

use App\Repository\MessageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly MessageRepository $messageRepo,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', [$this, 'getUnreadCount']),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if ($user === null) {
            return 0;
        }

        return $this->messageRepo->countUnreadFor($user);
    }
}