<?php

namespace App\Entity;

use App\Repository\PartidaJuegoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartidaJuegoRepository::class)]
#[ORM\Index(columns: ['usuario_id'], name: 'idx_partida_juego_usuario')]
#[ORM\Index(columns: ['usuario_id', 'tipo_juego'], name: 'idx_partida_juego_usuario_tipo')]
#[ORM\Index(columns: ['tipo_juego'], name: 'idx_partida_juego_tipo')]
#[ORM\Index(columns: ['fecha_creacion'], name: 'idx_partida_juego_fecha')]
class PartidaJuego
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    #[ORM\Column(length: 50)]
    private ?string $tipoJuego = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?User
    {
        return $this->usuario;
    }

    public function setUsuario(?User $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getTipoJuego(): ?string
    {
        return $this->tipoJuego;
    }

    public function setTipoJuego(string $tipoJuego): static
    {
        $this->tipoJuego = $tipoJuego;

        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeInterface
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTimeInterface $fechaCreacion): static
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }

    /**
     * Obtiene el nombre legible del tipo de juego
     */
    public function getNombreJuego(): string
    {
        return match($this->tipoJuego) {
            'adivina_numero_articulo' => '¿Qué Número Tiene el Artículo?',
            'adivina_nombre_articulo' => '¿Cómo se Llama el Artículo?',
            'completa_fecha_ley' => '¿Cuándo se Publicó la Ley?',
            'completa_texto_legal' => 'Completa el Artículo',
            default => $this->tipoJuego,
        };
    }
}
