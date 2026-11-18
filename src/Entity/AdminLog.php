<?php

namespace App\Entity;

use App\Repository\AdminLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\AdminActionType;

#[ORM\Entity(repositoryClass: AdminLogRepository::class)]
class AdminLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: AdminActionType::class)]
    private AdminActionType $action;

    #[ORM\Column(length: 50)]
    private ?string $targetType = null;

    #[ORM\Column]
    private ?int $targetId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'adminLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $adminUser = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): AdminActionType
    {
        return $this->action;
    }

    public function setAction(AdminActionType $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function setTargetId(int $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAdminUser(): ?User
    {
        return $this->adminUser;
    }

    public function setAdminUser(?User $adminUser): static
    {
        $this->adminUser = $adminUser;

        return $this;
    }
}
