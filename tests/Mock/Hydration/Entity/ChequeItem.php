<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ChequeItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Cheque::class, inversedBy: 'items')]
    private ?Cheque $cheque = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCheque(): ?Cheque
    {
        return $this->cheque;
    }

    public function setCheque(?Cheque $cheque): void
    {
        $this->cheque = $cheque;
    }
}
