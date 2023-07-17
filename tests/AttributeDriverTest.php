<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\ORM\{
    EntityManagerInterface,
    Mapping,
};

/**
 * @covers Firehed\Mocktrine\InMemoryEntityManager
 */
class AttributeDriverTest extends InMemoryEntityManagerTest
{
    protected function getEntityManager(): InMemoryEntityManager
    {
        $driver = new Mapping\Driver\AttributeDriver([__DIR__ . '/Entities']);
        return new InMemoryEntityManager($driver);
    }
}
