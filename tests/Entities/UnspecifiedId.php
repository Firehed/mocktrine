<?php
declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

/**
 * @Entity
 * @Table(name="unspecified_ids")
 */
class UnspecifiedId
{
    /**
     * @Id
     * @Column
     * @GeneratedValue
     * @var ?string
     */
    private $id;

    public function getId(): ?string
    {
        return $this->id;
    }
}
