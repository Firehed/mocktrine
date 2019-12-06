<?php
declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

/**
 * @Entity
 * @Table(name="nodes")
 */
class Node
{
    /**
     * @Id
     * @Column
     * @var string
     */
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
