<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $activo = false;

    #[ORM\ManyToMany(targetEntity: Municipio::class, mappedBy: 'usuarios')]
    private Collection $municipios;

    #[ORM\ManyToMany(targetEntity: Convocatoria::class, mappedBy: 'usuarios')]
    private Collection $convocatorias;

    /**
     * Profesores asignados a este alumno (solo para alumnos)
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'alumnos')]
    #[ORM\JoinTable(name: 'user_profesor_alumno')]
    private Collection $profesores;

    /**
     * Alumnos asignados a este profesor (solo para profesores)
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'profesores')]
    private Collection $alumnos;

    public function __construct()
    {
        $this->municipios = new ArrayCollection();
        $this->convocatorias = new ArrayCollection();
        $this->profesores = new ArrayCollection();
        $this->alumnos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

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

    /**
     * @return Collection<int, Municipio>
     */
    public function getMunicipios(): Collection
    {
        return $this->municipios;
    }

    public function addMunicipio(Municipio $municipio): static
    {
        if (!$this->municipios->contains($municipio)) {
            $this->municipios->add($municipio);
            $municipio->addUsuario($this);
        }

        return $this;
    }

    public function removeMunicipio(Municipio $municipio): static
    {
        if ($this->municipios->removeElement($municipio)) {
            $municipio->removeUsuario($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Convocatoria>
     */
    public function getConvocatorias(): Collection
    {
        return $this->convocatorias;
    }

    public function addConvocatoria(Convocatoria $convocatoria): static
    {
        if (!$this->convocatorias->contains($convocatoria)) {
            $this->convocatorias->add($convocatoria);
            $convocatoria->addUsuario($this);
        }

        return $this;
    }

    public function removeConvocatoria(Convocatoria $convocatoria): static
    {
        if ($this->convocatorias->removeElement($convocatoria)) {
            $convocatoria->removeUsuario($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getProfesores(): Collection
    {
        return $this->profesores;
    }

    public function addProfesore(User $profesore): static
    {
        if (!$this->profesores->contains($profesore)) {
            $this->profesores->add($profesore);
            $profesore->addAlumno($this);
        }

        return $this;
    }

    public function removeProfesore(User $profesore): static
    {
        if ($this->profesores->removeElement($profesore)) {
            $profesore->removeAlumno($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAlumnos(): Collection
    {
        return $this->alumnos;
    }

    public function addAlumno(User $alumno): static
    {
        if (!$this->alumnos->contains($alumno)) {
            $this->alumnos->add($alumno);
            $alumno->addProfesore($this);
        }

        return $this;
    }

    public function removeAlumno(User $alumno): static
    {
        if ($this->alumnos->removeElement($alumno)) {
            $alumno->removeProfesore($this);
        }

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
