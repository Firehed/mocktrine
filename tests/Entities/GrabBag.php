<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use DateTimeInterface;

/**
 * @Entity
 * @Table(name="grab_bags")
 */
class GrabBag
{
    /**
     * @Column(name="bool_field", type="boolean")
     */
    private bool $boolField;

    /**
     * @Column(name="float_field", type="float")
     */
    private float $floatField;

    /**
     * @Column(name="str_field")
     */
    private string $strField;

    /**
     * @Column(name="date_field", type="date")
     */
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
