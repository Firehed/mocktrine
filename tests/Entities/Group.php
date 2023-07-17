<?php

declare(strict_types=1);

namespace Firehed\Mocktrine\Entities;

use Doctrine\ORM\Mapping;

/**
 * @Entity
 * @Table(name="groups")
 */
#[Mapping\Entity]
#[Mapping\Table(name: 'groups')]
class Group
{
}
