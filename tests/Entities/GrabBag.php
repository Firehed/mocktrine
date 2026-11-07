<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping;

/**
 * @Mapping\Entity
 * @Mapping\Table(name="grab_bags")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'grab_bags')]
class GrabBag
{
    /**
     * @Mapping\Id
     * @Mapping\Column
     */
    #[Mapping\Id]
    #[Mapping\Column]
    private int $id;

    /**
     * @Mapping\Column(name="bool_field", type="boolean")
     */
    #[Mapping\Column]
    private bool $boolField;

    /**
     * @Mapping\Column(name="float_field", type="float")
     */
    #[Mapping\Column]
    private float $floatField;

    /**
     * @Mapping\Column(name="str_field")
     */
    #[Mapping\Column]
    private string $strField;

    /**
     * @Mapping\Column(name="date_field", type="date")
     */
    #[Mapping\Column]
    private DateTimeInterface $dateField;

    /**
     * @Mapping\Column(name="nullable_int_field", type="integer", nullable=true)
     */
    #[Mapping\Column(nullable: true)]
    private ?int $nullableIntField; // @phpstan-ignore property.onlyWritten (read via reflection)

    /**
     * @Mapping\Column(name="nullable_date_field", type="datetime_immutable", nullable=true)
     */
    #[Mapping\Column(nullable: true)]
    private ?DateTimeImmutable $nullableDateField; // @phpstan-ignore property.onlyWritten (read via reflection)

    public function __construct(
        bool $boolField,
        float $floatField,
        string $strField,
        DateTimeInterface $dateField,
        ?int $nullableIntField = null,
        ?DateTimeImmutable $nullableDateField = null,
    ) {
        $this->boolField = $boolField;
        $this->floatField = $floatField;
        $this->strField = $strField;
        $this->dateField = $dateField;
        $this->nullableIntField = $nullableIntField;
        $this->nullableDateField = $nullableDateField;
    }
}
