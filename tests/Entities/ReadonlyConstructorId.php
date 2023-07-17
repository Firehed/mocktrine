<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Entity
 * @Table(name="readonly_constructor_ids")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'readonly_constructor_ids')]
class ReadonlyConstructorId
{
    /**
     * @Id
     * @Column
     */
    #[Mapping\Id]
    #[Mapping\Column]
    public readonly string $id;

    public function __construct()
    {
        // Normally this would be a UUID, ULID, etc.
        $this->id = bin2hex(random_bytes(10));
    }
}
