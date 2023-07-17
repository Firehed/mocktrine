<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping;

/**
 * @Entity
 * @Table(name="users")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'users')]
class User
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var ?int
     */
    #[Mapping\Id]
    #[Mapping\Column(type: Types::INTEGER)]
    #[Mapping\GeneratedValue]
    private $id;

    /**
     * @Column
     * @var string
     */
    #[Mapping\Column]
    private $email;

    /**
     * @Column(type="boolean")
     * @var bool
     */
    #[Mapping\Column(type: Types::BOOLEAN)]
    private $active = false;

    /**
     * @Column(name="last_name")
     * @var string
     */
    #[Mapping\Column]
    private $lastName;

    /**
     * @var int
     */
    private $notAColumn = 100;

    public function __construct(
        string $email,
        string $lastName,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->lastName = $lastName;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }
}
