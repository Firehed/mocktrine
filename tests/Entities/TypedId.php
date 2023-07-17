<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Mapping\Entity
 * @Mapping\Table(name="typed_ids")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'typed_ids')]
class TypedId
{
    /**
     * @Mapping\Id
     * @Mapping\Column
     * @Mapping\GeneratedValue
     */
    #[Mapping\Id]
    #[Mapping\Column]
    #[Mapping\GeneratedValue]
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
