<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Mapping\Entity
 * @Mapping\Table(name="groups")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'groups')]
class Group
{
}
