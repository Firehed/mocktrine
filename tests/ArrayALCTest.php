<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use BadMethodCallException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayALC::class)]
#[Small]
class ArrayALCTest extends TestCase
{
    public function testWrappedCollectionIsAccessible(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $items = [$a, $b];
        $inner = new ArrayCollection($items);
        $alc = new ArrayALC($inner);

        self::assertSame(
            $items,
            $alc->toArray(),
            'Wrapped collection contents should be accessible',
        );
    }

    public function testMatchingThrowsBadMethodCallException(): void
    {
        /** @var ArrayCollection<int, object> $inner */
        $inner = new ArrayCollection([]);
        $alc = new ArrayALC($inner);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Nested matching not supported');

        $alc->matching(Criteria::create());
    }
}
