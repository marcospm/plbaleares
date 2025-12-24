<?php

namespace App\Entity;

use App\Repository\ExamenSemanalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenSemanalRepository::class)]
class ExamenSemanal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaApertura = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCierre = null;

    #[ORM\Column(length: 20)]
    private ?string $dificultad = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $numeroPreguntas = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creadoPor = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaCreacion = null;

    #[ORM\ManyToMany(targetEntity: Tema::class)]
    #[ORM\JoinTable(name: 'examen_semanal_tema')]
    private Collection $temas;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Municipio $municipio = null;

    #[ORM\ManyToMany(targetEntity: TemaMunicipal::class)]
    #[ORM\JoinTable(name: 'examen_semanal_tema_municipal')]
    private Collection $temasMunicipales;

    #[ORM\OneToMany(targetEntity: Examen::class, mappedBy: 'examenSemanal')]
    private Collection $examenes;

    #[ORM\Column(length: 50, nullable: true, options: ['default' => 'temas'])]
    private ?string $modoCreacion = 'temas';

    #[ORM\ManyToMany(targetEntity: Pregunta::class)]
    #[ORM\JoinTable(name: 'examen_semanal_pregunta')]
    private Collection $preguntas;

    #[ORM\ManyToMany(targetEntity: PreguntaMunicipal::class)]
    #[ORM\JoinTable(name: 'examen_semanal_pregunta_municipal')]
    private Collection $preguntasMunicipales;

    public function __construct()
    {
        $this->temas = new ArrayCollection();
        $this->temasMunicipales = new ArrayCollection();
        $this->examenes = new ArrayCollection();
        $this->preguntas = new ArrayCollection();
        $this->preguntasMunicipales = new ArrayCollection();
        $this->fechaCreacion = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getFechaApertura(): ?\DateTimeInterface
    {
        return $this->fechaApertura;
    }

    public function setFechaApertura(\DateTimeInterface $fechaApertura): static
    {
        $this->fechaApertura = $fechaApertura;

        return $this;
    }

    public function getFechaCierre(): ?\DateTimeInterface
    {
        return $this->fechaCierre;
    }

    public function setFechaCierre(\DateTimeInterface $fechaCierre): static
    {
        $this->fechaCierre = $fechaCierre;

        return $this;
    }

    public function getDificultad(): ?string
    {
        return $this->dificultad;
    }

    public function setDificultad(string $dificultad): static
    {
        $this->dificultad = $dificultad;

        return $this;
    }

    public function getNumeroPreguntas(): ?int
    {
        return $this->numeroPreguntas;
    }

    public function setNumeroPreguntas(?int $numeroPreguntas): static
    {
        $this->numeroPreguntas = $numeroPreguntas;

        return $this;
    }

    public function getCreadoPor(): ?User
    {
        return $this->creadoPor;
    }

    public function setCreadoPor(?User $creadoPor): static
    {
        $this->creadoPor = $creadoPor;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeImmutable
    {
        return $this->fechaCreacion;
    }

    public function setFechaCreacion(\DateTimeImmutable $fechaCreacion): static
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }

    /**
     * @return Collection<int, Tema>
     */
    public function getTemas(): Collection
    {
        return $this->temas;
    }

    public function addTema(Tema $tema): static
    {
        if (!$this->temas->contains($tema)) {
            $this->temas->add($tema);
        }

        return $this;
    }

    public function removeTema(Tema $tema): static
    {
        $this->temas->removeElement($tema);

        return $this;
    }

    public function getMunicipio(): ?Municipio
    {
        return $this->municipio;
    }

    public function setMunicipio(?Municipio $municipio): static
    {
        $this->municipio = $municipio;

        return $this;
    }

    /**
     * @return Collection<int, TemaMunicipal>
     */
    public function getTemasMunicipales(): Collection
    {
        return $this->temasMunicipales;
    }

    public function addTemasMunicipale(TemaMunicipal $temasMunicipale): static
    {
        if (!$this->temasMunicipales->contains($temasMunicipale)) {
            $this->temasMunicipales->add($temasMunicipale);
        }

        return $this;
    }

    public function removeTemasMunicipale(TemaMunicipal $temasMunicipale): static
    {
        $this->temasMunicipales->removeElement($temasMunicipale);

        return $this;
    }

    /**
     * @return Collection<int, Examen>
     */
    public function getExamenes(): Collection
    {
        return $this->examenes;
    }

    public function addExamen(Examen $examen): static
    {
        if (!$this->examenes->contains($examen)) {
            $this->examenes->add($examen);
            $examen->setExamenSemanal($this);
        }

        return $this;
    }

    public function removeExamen(Examen $examen): static
    {
        if ($this->examenes->removeElement($examen)) {
            if ($examen->getExamenSemanal() === $this) {
                $examen->setExamenSemanal(null);
            }
        }

        return $this;
    }

    public function getDificultadLabel(): string
    {
        return match($this->dificultad) {
            'facil' => 'Fácil',
            'moderada' => 'Moderada',
            'dificil' => 'Difícil',
            default => $this->dificultad ?? '',
        };
    }

    public function estaDisponible(): bool
    {
        $ahora = new \DateTime();
        return $this->activo 
            && $this->fechaApertura <= $ahora 
            && $this->fechaCierre >= $ahora;
    }

    public function estaCerrado(): bool
    {
        $ahora = new \DateTime();
        return $this->fechaCierre < $ahora;
    }

    public function estaPendiente(): bool
    {
        $ahora = new \DateTime();
        return $this->activo && $this->fechaApertura > $ahora;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    public function getModoCreacion(): ?string
    {
        return $this->modoCreacion;
    }

    public function setModoCreacion(?string $modoCreacion): static
    {
        $this->modoCreacion = $modoCreacion;

        return $this;
    }

    /**
     * @return Collection<int, Pregunta>
     */
    public function getPreguntas(): Collection
    {
        return $this->preguntas;
    }

    public function addPregunta(Pregunta $pregunta): static
    {
        if (!$this->preguntas->contains($pregunta)) {
            $this->preguntas->add($pregunta);
        }

        return $this;
    }

    public function removePregunta(Pregunta $pregunta): static
    {
        $this->preguntas->removeElement($pregunta);

        return $this;
    }

    /**
     * @return Collection<int, PreguntaMunicipal>
     */
    public function getPreguntasMunicipales(): Collection
    {
        return $this->preguntasMunicipales;
    }

    public function addPreguntasMunicipale(PreguntaMunicipal $preguntasMunicipale): static
    {
        if (!$this->preguntasMunicipales->contains($preguntasMunicipale)) {
            $this->preguntasMunicipales->add($preguntasMunicipale);
        }

        return $this;
    }

    public function removePreguntasMunicipale(PreguntaMunicipal $preguntasMunicipale): static
    {
        $this->preguntasMunicipales->removeElement($preguntasMunicipale);

        return $this;
    }
}
