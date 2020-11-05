<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

/**
 * @Entity
 * @Table(name="string_ids")
 */
class StringId
{
    /**
     * @Id
     * @Column(type="string")
     * @GeneratedValue
     * @var ?string
     */
    private $id;

    public function getId(): ?string
    {
        return $this->id;
    }
}
