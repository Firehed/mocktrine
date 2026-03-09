<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Mapping\Entity
 * @Mapping\Table(name="string_ids")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'string_ids')]
class StringId
{
    /**
     * @Mapping\Id
     * @Mapping\Column(type="string")
     * @Mapping\GeneratedValue
     * @var ?string
     */
    #[Mapping\Id]
    #[Mapping\Column]
    #[Mapping\GeneratedValue]
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
}
