<?php

namespace App\Entity;

use App\Repository\ExamenRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExamenRepository::class)]
#[ORM\Index(columns: ['usuario_id', 'fecha'], name: 'idx_examen_usuario_fecha')]
#[ORM\Index(columns: ['dificultad', 'municipio_id'], name: 'idx_examen_dificultad_municipio')]
#[ORM\Index(columns: ['convocatoria_id', 'fecha'], name: 'idx_examen_convocatoria_fecha')]
#[ORM\Index(columns: ['fecha'], name: 'idx_examen_fecha')]
class Examen
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $usuario = null;

    #[ORM\Column(length: 20)]
    private ?string $dificultad = null;

    #[ORM\Column]
    private ?int $numeroPreguntas = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private ?string $nota = null;

    #[ORM\Column]
    private ?int $aciertos = null;

    #[ORM\Column]
    private ?int $errores = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $enBlanco = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false], name: 'realizado_en_pdf')]
    private bool $realizadoEnPDF = false;

    #[ORM\Column(type: Types::JSON)]
    private array $respuestas = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $preguntasIds = null;

    #[ORM\ManyToMany(targetEntity: Tema::class)]
    #[ORM\JoinTable(name: 'examen_tema')]
    private Collection $temas;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Municipio $municipio = null;

    #[ORM\ManyToMany(targetEntity: TemaMunicipal::class)]
    #[ORM\JoinTable(name: 'examen_tema_municipal')]
    private Collection $temasMunicipales;

    #[ORM\ManyToOne(inversedBy: 'examenes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ExamenSemanal $examenSemanal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Convocatoria $convocatoria = null;

    public function __construct()
    {
        $this->temas = new ArrayCollection();
        $this->temasMunicipales = new ArrayCollection();
        $this->fecha = new \DateTime();
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

    public function setNumeroPreguntas(int $numeroPreguntas): static
    {
        $this->numeroPreguntas = $numeroPreguntas;

        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getNota(): ?string
    {
        return $this->nota;
    }

    public function setNota(string $nota): static
    {
        $this->nota = $nota;

        return $this;
    }

    public function getAciertos(): ?int
    {
        return $this->aciertos;
    }

    public function setAciertos(int $aciertos): static
    {
        $this->aciertos = $aciertos;

        return $this;
    }

    public function getErrores(): ?int
    {
        return $this->errores;
    }

    public function setErrores(int $errores): static
    {
        $this->errores = $errores;

        return $this;
    }

    public function getEnBlanco(): int
    {
        return $this->enBlanco;
    }

    public function setEnBlanco(int $enBlanco): static
    {
        $this->enBlanco = $enBlanco;

        return $this;
    }

    public function isRealizadoEnPDF(): bool
    {
        return $this->realizadoEnPDF;
    }

    public function setRealizadoEnPDF(bool $realizadoEnPDF): static
    {
        $this->realizadoEnPDF = $realizadoEnPDF;

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

    public function getPreguntasIds(): ?array
    {
        return $this->preguntasIds;
    }

    public function setPreguntasIds(?array $preguntasIds): static
    {
        $this->preguntasIds = $preguntasIds;

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

    public function getDificultadLabel(): string
    {
        return match($this->dificultad) {
            'facil' => 'Fácil',
            'moderada' => 'Moderada',
            'dificil' => 'Difícil',
            default => $this->dificultad ?? '',
        };
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

    public function getConvocatoria(): ?Convocatoria
    {
        return $this->convocatoria;
    }

    public function setConvocatoria(?Convocatoria $convocatoria): static
    {
        $this->convocatoria = $convocatoria;

        return $this;
    }
}

