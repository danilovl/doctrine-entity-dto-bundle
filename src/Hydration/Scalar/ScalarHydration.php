<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Hydration\Scalar;

use Doctrine\ORM\EntityManagerInterface;

class ScalarHydration extends AbstractScalarHydration
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }
}
