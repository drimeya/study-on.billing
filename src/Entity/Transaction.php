<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'billing_transaction')]
class Transaction
{
    public const TYPE_DEPOSIT = 0;
    public const TYPE_PAYMENT = 1;

    public const TYPES = [
        self::TYPE_DEPOSIT => 'deposit',
        self::TYPE_PAYMENT => 'payment',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Course $course = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [self::TYPE_DEPOSIT, self::TYPE_PAYMENT])]
    private ?int $type = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotNull]
    private ?float $value = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Срок действия — заполняется только для арендованных курсов (TYPE_PAYMENT + Course::TYPE_RENT)
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeName(): string
    {
        return self::TYPES[$this->type] ?? 'unknown';
    }

    public function isDeposit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    public function isPayment(): bool
    {
        return $this->type === self::TYPE_PAYMENT;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;

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

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function isActive(): bool
    {
        if ($this->validUntil === null) {
            return true;
        }

        return $this->validUntil > new \DateTimeImmutable();
    }
}
