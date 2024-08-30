<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\ORM\{
    EntityManagerInterface,
    Mapping,
};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryEntityManager::class)]
#[Small]
class AttributeDriverTest extends InMemoryEntityManagerTest
{
    public function setUp(): void
    {
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->markTestSkipped('Attribute tests require PHP8+');
        }
    }

    protected function getEntityManager(): InMemoryEntityManager
    {
        $driver = new Mapping\Driver\AttributeDriver([__DIR__ . '/Entities']);
        return new InMemoryEntityManager($driver);
    }
}
