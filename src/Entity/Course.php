<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'billing_course')]
#[ORM\UniqueConstraint(name: 'UNIQ_COURSE_CODE', fields: ['code'])]
class Course
{
    public const TYPE_FREE = 0;
    public const TYPE_RENT = 1;
    public const TYPE_FULL = 2;

    public const TYPES = [
        self::TYPE_FREE => 'free',
        self::TYPE_RENT => 'rent',
        self::TYPE_FULL => 'buy',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $code = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [self::TYPE_FREE, self::TYPE_RENT, self::TYPE_FULL])]
    private ?int $type = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $price = null;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'course')]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function isFree(): bool
    {
        return $this->type === self::TYPE_FREE;
    }

    public function isRent(): bool
    {
        return $this->type === self::TYPE_RENT;
    }

    public function isFull(): bool
    {
        return $this->type === self::TYPE_FULL;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }
}
