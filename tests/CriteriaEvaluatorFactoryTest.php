<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Uses throwaway classes: the factory caches per class for the whole process,
 * so stubbing a real fixture's metadata would leak into other tests.
 */
#[CoversClass(CriteriaEvaluatorFactory::class)]
#[Small]
class CriteriaEvaluatorFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return ClassMetadata<object>
     */
    private static function metadataFor(object $entity): ClassMetadata
    {
        $md = self::createStub(ClassMetadata::class);
        $md->method('getName')->willReturn($entity::class);
        $md->method('getFieldNames')->willReturn([]);
        return $md;
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $md = self::metadataFor(new class {
        });

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
        $md1 = self::metadataFor(new class {
        });
        $md2 = self::metadataFor(new class {
        });

        $ce1 = CriteriaEvaluatorFactory::getInstance($md1);
        $ce2 = CriteriaEvaluatorFactory::getInstance($md2);

        $this->assertNotSame(
            $ce1,
            $ce2,
            'getInstance should not return the same instance for different classes'
        );
    }
}
