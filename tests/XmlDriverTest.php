<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\ORM\{
    EntityManagerInterface,
    Mapping,
};

class XmlDriverTest extends InMemoryEntityManagerTest
{
    protected function getEntityManager(): InMemoryEntityManager
    {
        $driver = new Mapping\Driver\XmlDriver([__DIR__ . '/Entities/XmlMappings']);
        return new InMemoryEntityManager($driver);
    }
}
