<?php

namespace App\Entity;

use App\Repository\FranjaHorariaPersonalizadaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FranjaHorariaPersonalizadaRepository::class)]
class FranjaHorariaPersonalizada
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'franjasHorarias')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanificacionPersonalizada $planificacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FranjaHoraria $franjaBase = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fechaEspecifica = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $horaInicio = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $horaFin = null;

    #[ORM\Column(length: 50)]
    private ?string $tipoActividad = null; // 'repaso_basico' o 'estudio_tareas'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcionRepaso = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $temas = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $recursos = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $enlaces = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notas = null;

    #[ORM\Column]
    private ?int $orden = null;

    #[ORM\OneToMany(targetEntity: TareaAsignada::class, mappedBy: 'franjaHoraria')]
    private Collection $tareasAsignadas;

    public function __construct()
    {
        $this->tareasAsignadas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanificacion(): ?PlanificacionPersonalizada
    {
        return $this->planificacion;
    }

    public function setPlanificacion(?PlanificacionPersonalizada $planificacion): static
    {
        $this->planificacion = $planificacion;

        return $this;
    }

    public function getFranjaBase(): ?FranjaHoraria
    {
        return $this->franjaBase;
    }

    public function setFranjaBase(?FranjaHoraria $franjaBase): static
    {
        $this->franjaBase = $franjaBase;

        return $this;
    }

    public function getFechaEspecifica(): ?\DateTimeInterface
    {
        return $this->fechaEspecifica;
    }

    public function setFechaEspecifica(?\DateTimeInterface $fechaEspecifica): static
    {
        $this->fechaEspecifica = $fechaEspecifica;

        return $this;
    }

    public function getHoraInicio(): ?\DateTimeInterface
    {
        return $this->horaInicio;
    }

    public function setHoraInicio(\DateTimeInterface $horaInicio): static
    {
        $this->horaInicio = $horaInicio;

        return $this;
    }

    public function getHoraFin(): ?\DateTimeInterface
    {
        return $this->horaFin;
    }

    public function setHoraFin(\DateTimeInterface $horaFin): static
    {
        $this->horaFin = $horaFin;

        return $this;
    }

    public function getTipoActividad(): ?string
    {
        return $this->tipoActividad;
    }

    public function setTipoActividad(string $tipoActividad): static
    {
        $this->tipoActividad = $tipoActividad;

        return $this;
    }

    public function getDescripcionRepaso(): ?string
    {
        return $this->descripcionRepaso;
    }

    public function setDescripcionRepaso(?string $descripcionRepaso): static
    {
        $this->descripcionRepaso = $descripcionRepaso;

        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    /**
     * @return Collection<int, TareaAsignada>
     */
    public function getTareasAsignadas(): Collection
    {
        return $this->tareasAsignadas;
    }

    public function addTareaAsignada(TareaAsignada $tareaAsignada): static
    {
        if (!$this->tareasAsignadas->contains($tareaAsignada)) {
            $this->tareasAsignadas->add($tareaAsignada);
            $tareaAsignada->setFranjaHoraria($this);
        }

        return $this;
    }

    public function removeTareaAsignada(TareaAsignada $tareaAsignada): static
    {
        if ($this->tareasAsignadas->removeElement($tareaAsignada)) {
            if ($tareaAsignada->getFranjaHoraria() === $this) {
                $tareaAsignada->setFranjaHoraria(null);
            }
        }

        return $this;
    }

    public function getTemas(): ?string
    {
        return $this->temas;
    }

    public function setTemas(?string $temas): static
    {
        $this->temas = $temas;

        return $this;
    }

    public function getRecursos(): ?string
    {
        return $this->recursos;
    }

    public function setRecursos(?string $recursos): static
    {
        $this->recursos = $recursos;

        return $this;
    }

    public function getEnlaces(): ?string
    {
        return $this->enlaces;
    }

    public function setEnlaces(?string $enlaces): static
    {
        $this->enlaces = $enlaces;

        return $this;
    }

    public function getNotas(): ?string
    {
        return $this->notas;
    }

    public function setNotas(?string $notas): static
    {
        $this->notas = $notas;

        return $this;
    }

    public function getNombreDia(): string
    {
        if ($this->fechaEspecifica) {
            $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            return $dias[(int)$this->fechaEspecifica->format('w')];
        }
        return '';
    }
}

