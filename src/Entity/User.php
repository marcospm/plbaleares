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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codigoPostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ciudad = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $provincia = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $fechaNacimiento = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $sexo = null; // M, F, O (Masculino, Femenino, Otro)

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $dni = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $banco = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

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

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * Obtiene el nombre a mostrar: nombre si existe, username si no
     */
    public function getNombreDisplay(): string
    {
        return $this->nombre ?? $this->username ?? '';
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getCodigoPostal(): ?string
    {
        return $this->codigoPostal;
    }

    public function setCodigoPostal(?string $codigoPostal): static
    {
        $this->codigoPostal = $codigoPostal;

        return $this;
    }

    public function getCiudad(): ?string
    {
        return $this->ciudad;
    }

    public function setCiudad(?string $ciudad): static
    {
        $this->ciudad = $ciudad;

        return $this;
    }

    public function getProvincia(): ?string
    {
        return $this->provincia;
    }

    public function setProvincia(?string $provincia): static
    {
        $this->provincia = $provincia;

        return $this;
    }

    public function getFechaNacimiento(): ?\DateTimeInterface
    {
        return $this->fechaNacimiento;
    }

    public function setFechaNacimiento(?\DateTimeInterface $fechaNacimiento): static
    {
        $this->fechaNacimiento = $fechaNacimiento;

        return $this;
    }

    public function getEdad(): ?int
    {
        if (!$this->fechaNacimiento) {
            return null;
        }
        
        $hoy = new \DateTime();
        $edad = $hoy->diff($this->fechaNacimiento);
        return $edad->y;
    }

    public function getSexo(): ?string
    {
        return $this->sexo;
    }

    public function setSexo(?string $sexo): static
    {
        $this->sexo = $sexo;

        return $this;
    }

    public function getSexoLabel(): string
    {
        return match($this->sexo) {
            'M' => 'Masculino',
            'F' => 'Femenino',
            'O' => 'Otro',
            default => 'No especificado'
        };
    }

    public function getDni(): ?string
    {
        return $this->dni;
    }

    public function setDni(?string $dni): static
    {
        $this->dni = $dni;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;

        return $this;
    }

    public function getBanco(): ?string
    {
        return $this->banco;
    }

    public function setBanco(?string $banco): static
    {
        $this->banco = $banco;

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
