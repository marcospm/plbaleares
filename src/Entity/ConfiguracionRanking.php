<?php

namespace App\Entity;

use App\Repository\ConfiguracionRankingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfiguracionRankingRepository::class)]
class ConfiguracionRanking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Solo cuentan para ranking los exámenes/partidas con fecha >= esta (inicio del día).
     * Null = sin corte (ranking histórico completo).
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaCorte = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualizadoEn = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaCorte(): ?\DateTimeInterface
    {
        return $this->fechaCorte;
    }

    public function setFechaCorte(?\DateTimeInterface $fechaCorte): static
    {
        $this->fechaCorte = $fechaCorte;
        $this->actualizadoEn = new \DateTime();

        return $this;
    }

    public function getActualizadoEn(): ?\DateTimeInterface
    {
        return $this->actualizadoEn;
    }
}
