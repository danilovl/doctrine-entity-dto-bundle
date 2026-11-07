<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Entity;

use Doctrine\Common\Collections\{
    ArrayCollection,
    Collection
};
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?City $city = null;

    /**
     * @var Collection<int, Cheque>
     */
    #[ORM\OneToMany(targetEntity: Cheque::class, mappedBy: 'shop')]
    private Collection $cheques;

    public function __construct()
    {
        $this->cheques = new ArrayCollection;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): void
    {
        $this->city = $city;
    }

    /**
     * @return Collection<int, Cheque>
     */
    public function getCheques(): Collection
    {
        return $this->cheques;
    }
}
