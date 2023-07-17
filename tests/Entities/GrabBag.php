<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use DateTimeInterface;
use Doctrine\ORM\Mapping;

/**
 * @Entity
 * @Table(name="grab_bags")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'grab_bags')]
class GrabBag
{
    /**
     * @Id
     * @Column
     */
    #[Mapping\Id]
    #[Mapping\Column]
    private int $id;

    /**
     * @Column(name="bool_field", type="boolean")
     */
    #[Mapping\Column]
    private bool $boolField;

    /**
     * @Column(name="float_field", type="float")
     */
    #[Mapping\Column]
    private float $floatField;

    /**
     * @Column(name="str_field")
     */
    #[Mapping\Column]
    private string $strField;

    /**
     * @Column(name="date_field", type="date")
     */
    #[Mapping\Column]
    private DateTimeInterface $dateField;

    public function __construct(
        bool $boolField,
        float $floatField,
        string $strField,
        DateTimeInterface $dateField
    ) {
        $this->boolField = $boolField;
        $this->floatField = $floatField;
        $this->strField = $strField;
        $this->dateField = $dateField;
    }
}
