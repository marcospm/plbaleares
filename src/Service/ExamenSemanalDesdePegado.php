<?php

namespace App\Service;

use App\Entity\ExamenSemanal;
use App\Entity\Municipio;
use App\Entity\Pregunta;
use App\Entity\PreguntaMunicipal;
use App\Entity\User;
use App\Repository\GrupoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ExamenSemanalDesdePegado
{
    public const CANTIDAD = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrupoRepository $grupoRepository,
        private NotificacionService $notificacionService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private DificultadExamenService $dificultadExamenService,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param array{nombre?: string, descripcion?: string, fechaApertura?: string, fechaCierre?: string, grupoId?: int, dificultad?: string} $entrada
     * @return array{error: ?string, datos: array<string, mixed>}
     */
    public function validar(string $tipo, array $entrada): array
    {
        $nombre = trim((string) ($entrada['nombre'] ?? ''));
        $descripcion = trim((string) ($entrada['descripcion'] ?? ''));
        $apertura = $this->fecha((string) ($entrada['fechaApertura'] ?? ''));
        $cierre = $this->fecha((string) ($entrada['fechaCierre'] ?? ''));
        $grupoId = (int) ($entrada['grupoId'] ?? 0);
        $dificultad = (string) ($entrada['dificultad'] ?? '');
        $grupoNombre = null;
        $grupoInvalido = false;

        if ($grupoId > 0) {
            $grupo = $this->grupoRepository->find($grupoId);
            if ($grupo === null) {
                $grupoInvalido = true;
            } else {
                $grupoNombre = (string) $grupo->getNombre();
            }
        }

        $datos = [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'fechaAperturaIso' => $apertura?->format('Y-m-d\TH:i') ?? '',
            'fechaCierreIso' => $cierre?->format('Y-m-d\TH:i') ?? '',
            'fechaApertura' => $apertura?->format('d/m/Y H:i') ?? '',
            'fechaCierre' => $cierre?->format('d/m/Y H:i') ?? '',
            'grupoId' => $grupoInvalido ? 0 : $grupoId,
            'grupoNombre' => $grupoNombre,
            'dificultad' => $dificultad,
        ];

        $error = null;
        if ($nombre === '') {
            $error = 'Escribe el nombre del examen semanal.';
        } elseif (mb_strlen($nombre) > 255) {
            $error = 'El nombre del examen es demasiado largo.';
        } elseif ($apertura === null || $cierre === null) {
            $error = 'Indica la fecha de apertura y la de cierre.';
        } elseif ($cierre <= $apertura) {
            $error = 'La fecha de cierre debe ser posterior a la fecha de apertura.';
        } elseif (!$this->dificultadExamenService->acepta($dificultad, true)) {
            $error = 'Esa dificultad no está disponible.';
        } elseif ($grupoInvalido) {
            $error = 'El grupo seleccionado no es válido.';
        }

        return ['error' => $error, 'datos' => $datos];
    }

    /**
     * @param list<Pregunta> $preguntas
     * @param list<PreguntaMunicipal> $preguntasMunicipales
     * @param array<string, mixed> $datos
     */
    public function crear(User $creadoPor, array $datos, array $preguntas, array $preguntasMunicipales): ExamenSemanal
    {
        $apertura = $this->fecha((string) ($datos['fechaAperturaIso'] ?? ''));
        $cierre = $this->fecha((string) ($datos['fechaCierreIso'] ?? ''));
        if ($apertura === null || $cierre === null || $cierre <= $apertura) {
            throw new \RuntimeException('Las fechas del examen semanal no son válidas.');
        }

        $examen = $this->tomarBorrador($datos) ?? new ExamenSemanal();
        if ($examen->getId() !== null) {
            $this->vaciarAsignaciones($examen);
        }
        $examen->setNombre((string) $datos['nombre']);
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $examen->setDescripcion($descripcion !== '' ? $descripcion : null);
        $examen->setFechaApertura($apertura);
        $examen->setFechaCierre($cierre);
        $examen->setDificultad((string) $datos['dificultad']);
        if ($examen->getCreadoPor() === null) {
            $examen->setCreadoPor($creadoPor);
        }
        $examen->setModoCreacion('preguntas_especificas');
        $examen->setActivo(true);
        $examen->setBorrador(false);
        $examen->setBloqueBorrador(null);
        $examen->setBorradorMeta(null);

        $grupoId = (int) ($datos['grupoId'] ?? 0);
        $examen->setGrupo($grupoId > 0 ? $this->grupoRepository->find($grupoId) : null);

        if (($datos['tipo'] ?? '') === 'municipal') {
            if ($preguntasMunicipales === []) {
                throw new \RuntimeException('No hay preguntas válidas para crear el examen.');
            }
            $municipio = $preguntasMunicipales[0]->getMunicipio();
            if ($municipio === null) {
                throw new \RuntimeException('Las preguntas municipales no tienen municipio.');
            }
            $examen->setMunicipio($municipio);
            foreach ($preguntasMunicipales as $pregunta) {
                $examen->addPreguntasMunicipale($pregunta);
                $tema = $pregunta->getTemaMunicipal();
                if ($tema !== null) {
                    $examen->addTemasMunicipale($tema);
                }
            }
            $examen->setNumeroPreguntas(count($preguntasMunicipales));
        } else {
            if ($preguntas === []) {
                throw new \RuntimeException('No hay preguntas válidas para crear el examen.');
            }
            foreach ($preguntas as $pregunta) {
                $examen->addPregunta($pregunta);
                $tema = $pregunta->getTema();
                if ($tema !== null) {
                    $examen->addTema($tema);
                }
            }
            $examen->setNumeroPreguntas(count($preguntas));
            $examen->setMunicipio(null);
            $examen->setConvocatoria(null);
        }

        $this->entityManager->persist($examen);
        $this->entityManager->flush();

        return $examen;
    }

    /**
     * Guarda el examen sin publicarlo. El bloque es la última versión de las
     * preguntas, ya con las ediciones hechas en la previsualización.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $meta
     */
    public function guardarBorrador(User $creadoPor, array $datos, string $bloque, array $meta, int $preguntasValidas): ExamenSemanal
    {
        $apertura = $this->fecha((string) ($datos['fechaAperturaIso'] ?? ''));
        $cierre = $this->fecha((string) ($datos['fechaCierreIso'] ?? ''));
        if ($apertura === null || $cierre === null || $cierre <= $apertura) {
            throw new \RuntimeException('Las fechas del examen semanal no son válidas.');
        }
        if (trim($bloque) === '') {
            throw new \RuntimeException('No hay preguntas para guardar en el borrador.');
        }

        $examen = $this->tomarBorrador($datos) ?? new ExamenSemanal();
        $examen->setNombre((string) $datos['nombre']);
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $examen->setDescripcion($descripcion !== '' ? $descripcion : null);
        $examen->setFechaApertura($apertura);
        $examen->setFechaCierre($cierre);
        $examen->setDificultad((string) ($datos['dificultad'] ?? 'indeterminado'));
        if ($examen->getCreadoPor() === null) {
            $examen->setCreadoPor($creadoPor);
        }
        $examen->setModoCreacion('borrador');
        $examen->setActivo(false);
        $examen->setBorrador(true);
        $examen->setBloqueBorrador($bloque);
        $meta['tipo'] = (string) ($datos['tipo'] ?? ($meta['tipo'] ?? 'general'));
        $examen->setBorradorMeta($meta);
        $examen->setNumeroPreguntas($preguntasValidas);

        $grupoId = (int) ($datos['grupoId'] ?? 0);
        $examen->setGrupo($grupoId > 0 ? $this->grupoRepository->find($grupoId) : null);

        if (($datos['tipo'] ?? '') === 'municipal') {
            $municipioId = (int) ($meta['municipioId'] ?? 0);
            $municipio = $municipioId > 0 ? $this->entityManager->find(Municipio::class, $municipioId) : null;
            $examen->setMunicipio($municipio instanceof Municipio ? $municipio : null);
        } else {
            $examen->setMunicipio(null);
        }
        $examen->setConvocatoria(null);

        $this->entityManager->persist($examen);
        $this->entityManager->flush();

        return $examen;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeExamen(ExamenSemanal $examen): array
    {
        $apertura = $examen->getFechaApertura();
        $cierre = $examen->getFechaCierre();
        $meta = $examen->getBorradorMeta() ?? [];

        return [
            'tipo' => (string) ($meta['tipo'] ?? 'general'),
            'nombre' => (string) $examen->getNombre(),
            'descripcion' => (string) ($examen->getDescripcion() ?? ''),
            'fechaAperturaIso' => $apertura?->format('Y-m-d\TH:i') ?? '',
            'fechaCierreIso' => $cierre?->format('Y-m-d\TH:i') ?? '',
            'fechaApertura' => $apertura?->format('d/m/Y H:i') ?? '',
            'fechaCierre' => $cierre?->format('d/m/Y H:i') ?? '',
            'grupoId' => (int) ($examen->getGrupo()?->getId() ?? 0),
            'grupoNombre' => $examen->getGrupo()?->getNombre(),
            'dificultad' => (string) $examen->getDificultad(),
            'borradorId' => (int) $examen->getId(),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function tomarBorrador(array $datos): ?ExamenSemanal
    {
        $id = (int) ($datos['borradorId'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $examen = $this->entityManager->find(ExamenSemanal::class, $id);
        if (!$examen instanceof ExamenSemanal || !$examen->isBorrador()) {
            return null;
        }

        $tipoGuardado = (string) (($examen->getBorradorMeta() ?? [])['tipo'] ?? '');
        $tipo = (string) ($datos['tipo'] ?? '');
        if ($tipoGuardado !== '' && $tipo !== '' && $tipoGuardado !== $tipo) {
            return null;
        }

        return $examen;
    }

    private function vaciarAsignaciones(ExamenSemanal $examen): void
    {
        foreach ($examen->getPreguntas()->toArray() as $pregunta) {
            $examen->removePregunta($pregunta);
        }
        foreach ($examen->getPreguntasMunicipales()->toArray() as $pregunta) {
            $examen->removePreguntasMunicipale($pregunta);
        }
        foreach ($examen->getTemas()->toArray() as $tema) {
            $examen->removeTema($tema);
        }
        foreach ($examen->getTemasMunicipales()->toArray() as $tema) {
            $examen->removeTemasMunicipale($tema);
        }
    }

    public function notificar(ExamenSemanal $examenSemanal): void
    {
        $grupoExamen = $examenSemanal->getGrupo();
        $profesor = $examenSemanal->getCreadoPor();
        if ($profesor === null) {
            return;
        }

        $alumnos = $grupoExamen
            ? $grupoExamen->getAlumnos()
            : $this->userRepository->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->andWhere('u.activo = :activo')
                ->setParameter('role', '%ROLE_USER%')
                ->setParameter('activo', true)
                ->getQuery()
                ->getResult();

        foreach ($alumnos as $alumno) {
            if (!$alumno instanceof User) {
                continue;
            }
            if (in_array('ROLE_PROFESOR', $alumno->getRoles(), true)
                || in_array('ROLE_ADMIN', $alumno->getRoles(), true)
                || $alumno->getId() === $profesor->getId()) {
                continue;
            }
            $this->notificacionService->crearNotificacionExamenSemanal($examenSemanal, $alumno, $profesor);
        }
        $this->entityManager->flush();
    }

    public function invalidarCache(): void
    {
        try {
            $versionItem = $this->cache->getItem('examenes_semanales_version');
            $version = $versionItem->get() ?? 0;
            $versionItem->set($version + 1);
            $versionItem->expiresAfter(86400);
            $this->cache->save($versionItem);
        } catch (\Throwable $e) {
            $this->logger->error('No se pudo invalidar la caché de exámenes semanales: ' . $e->getMessage());
        }
    }

    private function fecha(string $valor): ?\DateTime
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        try {
            return new \DateTime($valor);
        } catch (\Exception) {
            return null;
        }
    }
}
