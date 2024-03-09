<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Hydration\Entity;

use Doctrine\ORM\EntityManagerInterface;

class EntityHydration extends AbstractEntityHydration
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }
}
