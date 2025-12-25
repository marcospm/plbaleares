<?php

namespace App\Entity;

use App\Repository\NotificacionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificacionRepository::class)]
class Notificacion
{
    public const TIPO_EXAMEN = 'examen';
    public const TIPO_TAREA = 'tarea';
    public const TIPO_ERROR_ARTICULO = 'error_articulo';
    public const TIPO_MENSAJE_ALUMNO = 'mensaje_alumno';
    public const TIPO_PLANIFICACION_CREADA = 'planificacion_creada';
    public const TIPO_PLANIFICACION_EDITADA = 'planificacion_editada';
    public const TIPO_PLANIFICACION_ELIMINADA = 'planificacion_eliminada';
    public const TIPO_TAREA_CREADA = 'tarea_creada';
    public const TIPO_TAREA_EDITADA = 'tarea_editada';
    public const TIPO_TAREA_ELIMINADA = 'tarea_eliminada';
    public const TIPO_EXAMEN_SEMANAL = 'examen_semanal';
    public const TIPO_RESPUESTA_ARTICULO = 'respuesta_articulo';
    public const TIPO_ERROR_PREGUNTA = 'error_pregunta';
    public const TIPO_RESPUESTA_PREGUNTA = 'respuesta_pregunta';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $tipo = null;

    #[ORM\Column(length: 255)]
    private ?string $titulo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mensaje = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $profesor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $alumno = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Examen $examen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TareaAsignada $tareaAsignada = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Articulo $articulo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PlanificacionSemanal $planificacionSemanal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tarea $tarea = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ExamenSemanal $examenSemanal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Pregunta $pregunta = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $leida = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTime();
        $this->leida = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getTitulo(): ?string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function getMensaje(): ?string
    {
        return $this->mensaje;
    }

    public function setMensaje(?string $mensaje): static
    {
        $this->mensaje = $mensaje;

        return $this;
    }

    public function getProfesor(): ?User
    {
        return $this->profesor;
    }

    public function setProfesor(?User $profesor): static
    {
        $this->profesor = $profesor;

        return $this;
    }

    public function getAlumno(): ?User
    {
        return $this->alumno;
    }

    public function setAlumno(?User $alumno): static
    {
        $this->alumno = $alumno;

        return $this;
    }

    public function getExamen(): ?Examen
    {
        return $this->examen;
    }

    public function setExamen(?Examen $examen): static
    {
        $this->examen = $examen;

        return $this;
    }

    public function getTareaAsignada(): ?TareaAsignada
    {
        return $this->tareaAsignada;
    }

    public function setTareaAsignada(?TareaAsignada $tareaAsignada): static
    {
        $this->tareaAsignada = $tareaAsignada;

        return $this;
    }

    public function getArticulo(): ?Articulo
    {
        return $this->articulo;
    }

    public function setArticulo(?Articulo $articulo): static
    {
        $this->articulo = $articulo;

        return $this;
    }

    public function getPlanificacionSemanal(): ?\App\Entity\PlanificacionSemanal
    {
        return $this->planificacionSemanal;
    }

    public function setPlanificacionSemanal(?\App\Entity\PlanificacionSemanal $planificacionSemanal): static
    {
        $this->planificacionSemanal = $planificacionSemanal;

        return $this;
    }

    public function getTarea(): ?\App\Entity\Tarea
    {
        return $this->tarea;
    }

    public function setTarea(?\App\Entity\Tarea $tarea): static
    {
        $this->tarea = $tarea;

        return $this;
    }

    public function getExamenSemanal(): ?\App\Entity\ExamenSemanal
    {
        return $this->examenSemanal;
    }

    public function setExamenSemanal(?\App\Entity\ExamenSemanal $examenSemanal): static
    {
        $this->examenSemanal = $examenSemanal;

        return $this;
    }

    public function getPregunta(): ?\App\Entity\Pregunta
    {
        return $this->pregunta;
    }

    public function setPregunta(?\App\Entity\Pregunta $pregunta): static
    {
        $this->pregunta = $pregunta;

        return $this;
    }

    public function isLeida(): bool
    {
        return $this->leida;
    }

    public function setLeida(bool $leida): static
    {
        $this->leida = $leida;

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
}



