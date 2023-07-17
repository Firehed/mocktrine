<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Mapping\Entity
 * @Mapping\Table(name="readonly_generated_ids")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'readonly_generated_ids')]
class ReadonlyGeneratedId
{
    /**
     * @Mapping\Id
     * @Mapping\Column
     * @Mapping\GeneratedValue
     */
    #[Mapping\Id]
    #[Mapping\Column]
    #[Mapping\GeneratedValue]
    public readonly int $id;
}
