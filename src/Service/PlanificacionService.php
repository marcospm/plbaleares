<?php

namespace App\Service;

use App\Entity\PlanificacionPersonalizada;
use App\Entity\FranjaHorariaPersonalizada;
use App\Entity\User;
use App\Repository\FranjaHorariaPersonalizadaRepository;
use App\Repository\TareaAsignadaRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlanificacionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FranjaHorariaPersonalizadaRepository $franjaRepository,
        private TareaAsignadaRepository $tareaAsignadaRepository
    ) {
    }

    /**
     * Crea una nueva planificación con fechas específicas
     */
    public function crearPlanificacionConFechas(
        User $usuario,
        User $creadoPor,
        string $nombre,
        ?string $descripcion,
        \DateTime $fechaInicio,
        \DateTime $fechaFin,
        array $actividades
    ): PlanificacionPersonalizada {
        $planificacion = new PlanificacionPersonalizada();
        $planificacion->setUsuario($usuario);
        $planificacion->setCreadoPor($creadoPor);
        $planificacion->setNombre($nombre);
        $planificacion->setDescripcion($descripcion);
        $planificacion->setFechaInicio($fechaInicio);
        $planificacion->setFechaFin($fechaFin);

        $orden = 1;
        foreach ($actividades as $actividad) {
            $franja = new FranjaHorariaPersonalizada();
            $franja->setPlanificacion($planificacion);
            $franja->setFechaEspecifica($actividad['fecha']);
            $franja->setHoraInicio($actividad['horaInicio']);
            $franja->setHoraFin($actividad['horaFin']);
            $franja->setTipoActividad($actividad['tipoActividad'] ?? 'repaso_basico');
            $franja->setDescripcionRepaso($actividad['descripcionRepaso'] ?? null);
            $franja->setTemas($actividad['temas'] ?? null);
            $franja->setRecursos($actividad['recursos'] ?? null);
            $franja->setEnlaces($actividad['enlaces'] ?? null);
            $franja->setNotas($actividad['notas'] ?? null);
            $franja->setOrden($orden++);

            $planificacion->addFranjaHoraria($franja);
        }

        $this->entityManager->persist($planificacion);
        $this->entityManager->flush();

        return $planificacion;
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function obtenerFranjasDelDia(User $usuario, \DateTime $fecha): array
    {
        return $this->franjaRepository->findByUsuarioYFecha($usuario, $fecha);
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function obtenerFranjasDeSemana(User $usuario, \DateTime $lunesSemana): array
    {
        $domingoSemana = clone $lunesSemana;
        $domingoSemana->modify('+6 days');
        
        return $this->franjaRepository->findByUsuarioYRangoFechas($usuario, $lunesSemana, $domingoSemana);
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function obtenerFranjasPorRangoFechas(User $usuario, \DateTime $fechaInicio, \DateTime $fechaFin): array
    {
        return $this->franjaRepository->findByUsuarioYRangoFechas($usuario, $fechaInicio, $fechaFin);
    }

    /**
     * @return \App\Entity\TareaAsignada[]
     */
    public function obtenerTareasDeFranja(FranjaHorariaPersonalizada $franja, \DateTime $lunesSemana): array
    {
        $tareas = [];
        foreach ($franja->getTareasAsignadas() as $tareaAsignada) {
            $semanaTarea = $tareaAsignada->getTarea()->getSemanaAsignacion();
            $lunesTarea = clone $semanaTarea;
            if ($lunesTarea->format('N') != '1') {
                $lunesTarea->modify('monday this week');
            }
            
            if ($lunesTarea->format('Y-m-d') === $lunesSemana->format('Y-m-d')) {
                $tareas[] = $tareaAsignada;
            }
        }
        
        return $tareas;
    }

    /**
     * @return \App\Entity\TareaAsignada[]
     */
    public function obtenerTareasSinFranja(User $usuario, \DateTime $lunesSemana): array
    {
        $tareasAsignadas = $this->tareaAsignadaRepository->findByUsuarioYsemana($usuario, $lunesSemana);
        
        return array_filter($tareasAsignadas, function($tareaAsignada) {
            return $tareaAsignada->getFranjaHoraria() === null;
        });
    }

    /**
     * @return \App\Entity\TareaAsignada[]
     */
    public function obtenerTareasPendientes(User $usuario): array
    {
        return $this->tareaAsignadaRepository->findPendientesByUsuario($usuario);
    }

    /**
     * Valida las franjas de una planificación
     */
    public function validarFranjas(array $franjas, User $usuario): array
    {
        $errores = [];
        $franjasPorFecha = [];

        // Agrupar franjas por fecha
        foreach ($franjas as $franja) {
            $fecha = $franja->getFechaEspecifica();
            if ($fecha === null) {
                $errores[] = "Todas las franjas deben tener una fecha específica.";
                continue;
            }
            
            $fechaKey = $fecha->format('Y-m-d');
            if (!isset($franjasPorFecha[$fechaKey])) {
                $franjasPorFecha[$fechaKey] = [];
            }
            $franjasPorFecha[$fechaKey][] = $franja;
        }

        // Validar cada fecha
        foreach ($franjasPorFecha as $fechaKey => $franjasDelDia) {
            $fecha = $franjasDelDia[0]->getFechaEspecifica();
            
            // Verificar solapamientos
            usort($franjasDelDia, function($a, $b) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            });

            for ($i = 0; $i < count($franjasDelDia) - 1; $i++) {
                $franja1 = $franjasDelDia[$i];
                $franja2 = $franjasDelDia[$i + 1];

                if ($franja1->getHoraFin() > $franja2->getHoraInicio()) {
                    $errores[] = sprintf(
                        "Hay solapamiento el %s entre las franjas %s-%s y %s-%s",
                        $fecha->format('d/m/Y'),
                        $franja1->getHoraInicio()->format('H:i'),
                        $franja1->getHoraFin()->format('H:i'),
                        $franja2->getHoraInicio()->format('H:i'),
                        $franja2->getHoraFin()->format('H:i')
                    );
                }
            }

            // Verificar solapamientos con otras planificaciones del usuario
            foreach ($franjasDelDia as $franja) {
                if ($this->franjaRepository->tieneSolapamiento(
                    $usuario,
                    $franja->getFechaEspecifica(),
                    $franja->getHoraInicio(),
                    $franja->getHoraFin(),
                    $franja->getId()
                )) {
                    $errores[] = sprintf(
                        "La franja del %s (%s-%s) se solapa con otra planificación existente.",
                        $fecha->format('d/m/Y'),
                        $franja->getHoraInicio()->format('H:i'),
                        $franja->getHoraFin()->format('H:i')
                    );
                }
            }
        }

        return $errores;
    }

    /**
     * Valida que las fechas de las franjas estén dentro del rango de la planificación
     */
    public function validarFechasEnRango(PlanificacionPersonalizada $planificacion): array
    {
        $errores = [];
        
        foreach ($planificacion->getFranjasHorarias() as $franja) {
            $fechaFranja = $franja->getFechaEspecifica();
            if ($fechaFranja === null) {
                $errores[] = "Todas las franjas deben tener una fecha específica.";
                continue;
            }
            
            if ($fechaFranja < $planificacion->getFechaInicio() || $fechaFranja > $planificacion->getFechaFin()) {
                $errores[] = sprintf(
                    "La fecha %s está fuera del rango de la planificación (%s - %s).",
                    $fechaFranja->format('d/m/Y'),
                    $planificacion->getFechaInicio()->format('d/m/Y'),
                    $planificacion->getFechaFin()->format('d/m/Y')
                );
            }
        }
        
        return $errores;
    }

    public function calcularResumenSemanal(User $usuario, \DateTime $lunesSemana): array
    {
        $tareasAsignadas = $this->tareaAsignadaRepository->findByUsuarioYsemana($usuario, $lunesSemana);
        
        $total = count($tareasAsignadas);
        $completadas = 0;
        $pendientes = 0;
        $vencidas = 0;

        $hoy = new \DateTime();
        $domingoSemana = clone $lunesSemana;
        $domingoSemana->modify('+6 days');

        foreach ($tareasAsignadas as $tareaAsignada) {
            if ($tareaAsignada->isCompletada()) {
                $completadas++;
            } else {
                $pendientes++;
                if ($hoy > $domingoSemana) {
                    $vencidas++;
                }
            }
        }

        return [
            'total' => $total,
            'completadas' => $completadas,
            'pendientes' => $pendientes,
            'vencidas' => $vencidas,
        ];
    }

}

