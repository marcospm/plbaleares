<?php

namespace App\Entity;

use App\Repository\ExamenBorradorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenBorradorRepository::class)]
class ExamenBorrador
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    #[ORM\Column(length: 50)]
    private ?string $tipoExamen = null; // 'general', 'municipal', 'convocatoria', 'semanal'

    #[ORM\Column(type: Types::JSON)]
    private array $config = []; // Configuración del examen (dificultad, temas, etc.)

    #[ORM\Column(type: Types::JSON)]
    private array $preguntasIds = []; // IDs de las preguntas del examen

    #[ORM\Column(type: Types::JSON)]
    private array $respuestas = []; // Respuestas guardadas {preguntaId: 'A'|'B'|'C'|'D'}

    #[ORM\Column]
    private ?int $preguntaActual = null; // Número de pregunta actual (1-indexed)

    #[ORM\Column(nullable: true)]
    private ?int $tiempoRestante = null; // Tiempo restante en segundos

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaActualizacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?ExamenSemanal $examenSemanal = null; // Si es examen semanal

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
        $this->fechaActualizacion = new \DateTime();
        $this->preguntaActual = 1;
        $this->respuestas = [];
        $this->config = [];
        $this->preguntasIds = [];
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

    public function getTipoExamen(): ?string
    {
        return $this->tipoExamen;
    }

    public function setTipoExamen(string $tipoExamen): static
    {
        $this->tipoExamen = $tipoExamen;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getPreguntasIds(): array
    {
        return $this->preguntasIds;
    }

    public function setPreguntasIds(array $preguntasIds): static
    {
        $this->preguntasIds = $preguntasIds;
        return $this;
    }

    public function getRespuestas(): array
    {
        return $this->respuestas;
    }

    public function setRespuestas(array $respuestas): static
    {
        $this->respuestas = $respuestas;
        return $this;
    }

    public function getPreguntaActual(): ?int
    {
        return $this->preguntaActual;
    }

    public function setPreguntaActual(int $preguntaActual): static
    {
        $this->preguntaActual = $preguntaActual;
        return $this;
    }

    public function getTiempoRestante(): ?int
    {
        return $this->tiempoRestante;
    }

    public function setTiempoRestante(?int $tiempoRestante): static
    {
        $this->tiempoRestante = $tiempoRestante;
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

    public function getFechaActualizacion(): ?\DateTimeInterface
    {
        return $this->fechaActualizacion;
    }

    public function setFechaActualizacion(\DateTimeInterface $fechaActualizacion): static
    {
        $this->fechaActualizacion = $fechaActualizacion;
        return $this;
    }

    public function getExamenSemanal(): ?ExamenSemanal
    {
        return $this->examenSemanal;
    }

    public function setExamenSemanal(?ExamenSemanal $examenSemanal): static
    {
        $this->examenSemanal = $examenSemanal;
        return $this;
    }

    /**
     * Actualiza la fecha de actualización automáticamente
     */
    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->fechaActualizacion = new \DateTime();
    }
}
