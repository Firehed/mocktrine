<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Mapping\Entity
 * @Mapping\Table(name="unspecified_ids")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'unspecified_ids')]
class UnspecifiedId
{
    /**
     * @Mapping\Id
     * @Mapping\Column
     * @Mapping\GeneratedValue
     * @var ?string
     */
    #[Mapping\Id]
    #[Mapping\Column]
    #[Mapping\GeneratedValue]
    private $id;

    public function getId(): ?string
    {
        return $this->id;
    }
}
