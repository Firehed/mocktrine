<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Entity
 * @Table(name="nodes")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'nodes')]
class Node
{
    /**
     * @Id
     * @Column
     * @var string
     */
    #[Mapping\Id]
    #[Mapping\Column]
    private $nodeId;

    public function __construct()
    {
        $this->nodeId = 'node_' . md5((string)random_int(0, PHP_INT_MAX));
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }
}
