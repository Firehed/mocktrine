<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

/**
 * @coversDefaultClass Firehed\Mocktrine\CriteriaEvaluatorFactory
 * @covers ::<protected>
 * @covers ::<private>
 */
class CriteriaEvaluatorFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetInstanceReturnsSingleton(): void
    {
        $ce1 = CriteriaEvaluatorFactory::getInstance(Entities\GrabBag::class);
        $ce2 = CriteriaEvaluatorFactory::getInstance(Entities\GrabBag::class);

        $this->assertSame(
            $ce1,
            $ce2,
            'getInstance should return the same instance for a given class',
        );
    }

    public function testGetInstanceReturnsDifferentInstancesForDifferentClasses(): void
    {
        $ce1 = CriteriaEvaluatorFactory::getInstance(Entities\GrabBag::class);
        $ce2 = CriteriaEvaluatorFactory::getInstance(Entities\User::class);

        $this->assertNotSame(
            $ce1,
            $ce2,
            'getInstance should not return the same instance for different classes'
        );
    }
}
