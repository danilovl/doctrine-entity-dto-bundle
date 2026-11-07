<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Entity;

use Doctrine\Common\Collections\{
    ArrayCollection,
    Collection
};
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Cheque
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $number;

    #[ORM\ManyToOne(targetEntity: Shop::class, inversedBy: 'cheques')]
    private ?Shop $shop = null;

    /**
     * @var Collection<int, ChequeItem>
     */
    #[ORM\OneToMany(targetEntity: ChequeItem::class, mappedBy: 'cheque')]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): void
    {
        $this->shop = $shop;
    }

    /**
     * @return Collection<int, ChequeItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }
}
