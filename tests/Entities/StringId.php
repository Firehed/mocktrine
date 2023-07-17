<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Entity
 * @Table(name="string_ids")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'string_ids')]
class StringId
{
    /**
     * @Id
     * @Column(type="string")
     * @GeneratedValue
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
