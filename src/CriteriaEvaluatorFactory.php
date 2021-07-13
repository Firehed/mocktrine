<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Persistence\Mapping\ClassMetadata;

use function array_key_exists;

/**
 * @template Entity of object
 *
 * @internal
 */
class CriteriaEvaluatorFactory
{
    /**
     * @var array<class-string<Entity>, CriteriaEvaluator<Entity>>
     */
    private static array $instances = [];

    /**
     * @param ClassMetadata<Entity> $metadata
     * @return CriteriaEvaluator<Entity>
     */
    public static function getInstance(ClassMetadata $metadata): CriteriaEvaluator
    {
        $className = $metadata->getName();
        if (!array_key_exists($className, self::$instances)) {
            self::$instances[$className] = new CriteriaEvaluator($metadata);
        }
        return self::$instances[$className];
    }
}
