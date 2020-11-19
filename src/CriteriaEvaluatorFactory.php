<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

/**
 * @template Entity of object
 */
class CriteriaEvaluatorFactory
{
    /** @var CriteriaEvaluator[] */
    private static array $instances = [];

    /**
     * @param class-string<Entity> $className
     * @return CriteriaEvaluator<Entity>
     */
    public static function getInstance(string $className): CriteriaEvaluator
    {
        if (!array_key_exists($className, self::$instances)) {
            self::$instances[$className] = new CriteriaEvaluator($className);
        }
        return self::$instances[$className];
    }
}
