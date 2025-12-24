<?php

namespace App\Entity;

use App\Repository\MunicipioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MunicipioRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_NOMBRE_MUNICIPIO', fields: ['nombre'])]
class Municipio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaCreacion = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'municipios')]
    #[ORM\JoinTable(name: 'user_municipio')]
    private Collection $usuarios;

    #[ORM\OneToMany(targetEntity: TemaMunicipal::class, mappedBy: 'municipio', cascade: ['persist', 'remove'])]
    private Collection $temasMunicipales;

    #[ORM\OneToMany(targetEntity: PreguntaMunicipal::class, mappedBy: 'municipio', cascade: ['persist', 'remove'])]
    private Collection $preguntasMunicipales;

    public function __construct()
    {
        $this->usuarios = new ArrayCollection();
        $this->temasMunicipales = new ArrayCollection();
        $this->preguntasMunicipales = new ArrayCollection();
        $this->fechaCreacion = new \DateTime();
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

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

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
     * @return Collection<int, User>
     */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    public function addUsuario(User $usuario): static
    {
        if (!$this->usuarios->contains($usuario)) {
            $this->usuarios->add($usuario);
        }

        return $this;
    }

    public function removeUsuario(User $usuario): static
    {
        $this->usuarios->removeElement($usuario);

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
            $temasMunicipale->setMunicipio($this);
        }

        return $this;
    }

    public function removeTemasMunicipale(TemaMunicipal $temasMunicipale): static
    {
        if ($this->temasMunicipales->removeElement($temasMunicipale)) {
            if ($temasMunicipale->getMunicipio() === $this) {
                $temasMunicipale->setMunicipio(null);
            }
        }

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
            $preguntasMunicipale->setMunicipio($this);
        }

        return $this;
    }

    public function removePreguntasMunicipale(PreguntaMunicipal $preguntasMunicipale): static
    {
        if ($this->preguntasMunicipales->removeElement($preguntasMunicipale)) {
            if ($preguntasMunicipale->getMunicipio() === $this) {
                $preguntasMunicipale->setMunicipio(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}







