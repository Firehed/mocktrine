<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Persistence\Mapping\ClassMetadata;

/**
 * @covers Firehed\Mocktrine\CriteriaEvaluatorFactory
 */
class CriteriaEvaluatorFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetInstanceReturnsSingleton(): void
    {
        $md = $this->createMock(ClassMetadata::class);
        $md->method('getName')->willReturn(Entities\GrabBag::class);
        $md->method('getFieldNames')->willReturn([]);

        $ce1 = CriteriaEvaluatorFactory::getInstance($md);
        $ce2 = CriteriaEvaluatorFactory::getInstance($md);

        $this->assertSame(
            $ce1,
            $ce2,
            'getInstance should return the same instance for a given class',
        );
    }

    public function testGetInstanceReturnsDifferentInstancesForDifferentClasses(): void
    {
        $md1 = $this->createMock(ClassMetadata::class);
        $md1->method('getName')->willReturn(Entities\GrabBag::class);
        $md1->method('getFieldNames')->willReturn([]);

        $md2 = $this->createMock(ClassMetadata::class);
        $md2->method('getName')->willReturn(Entities\User::class);
        $md2->method('getFieldNames')->willReturn([]);

        $ce1 = CriteriaEvaluatorFactory::getInstance($md1);
        $ce2 = CriteriaEvaluatorFactory::getInstance($md2);

        $this->assertNotSame(
            $ce1,
            $ce2,
            'getInstance should not return the same instance for different classes'
        );
    }
}
