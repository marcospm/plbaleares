<?php

namespace App\Service;

use App\Entity\PlanificacionSemanal;
use App\Entity\PlanificacionPersonalizada;
use App\Entity\FranjaHoraria;
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

    public function crearDesdePlantilla(PlanificacionSemanal $plantilla, User $usuario): PlanificacionPersonalizada
    {
        $planificacionPersonalizada = new PlanificacionPersonalizada();
        $planificacionPersonalizada->setUsuario($usuario);
        $planificacionPersonalizada->setPlanificacionBase($plantilla);

        // Copiar todas las franjas de la plantilla
        foreach ($plantilla->getFranjasHorarias() as $franjaBase) {
            $franjaPersonalizada = new FranjaHorariaPersonalizada();
            $franjaPersonalizada->setFranjaBase($franjaBase);
            $franjaPersonalizada->setDiaSemana($franjaBase->getDiaSemana());
            $franjaPersonalizada->setHoraInicio($franjaBase->getHoraInicio());
            $franjaPersonalizada->setHoraFin($franjaBase->getHoraFin());
            $franjaPersonalizada->setTipoActividad($franjaBase->getTipoActividad());
            $franjaPersonalizada->setDescripcionRepaso($franjaBase->getDescripcionRepaso());
            $franjaPersonalizada->setOrden($franjaBase->getOrden());
            $franjaPersonalizada->setPlanificacion($planificacionPersonalizada);

            $planificacionPersonalizada->addFranjaHoraria($franjaPersonalizada);
        }

        $this->entityManager->persist($planificacionPersonalizada);
        $this->entityManager->flush();

        return $planificacionPersonalizada;
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function obtenerFranjasDelDia(User $usuario, \DateTime $fecha): array
    {
        $diaSemana = (int) $fecha->format('N'); // 1=Lunes, 7=Domingo
        
        // Pasar la fecha como referencia para filtrar por fechaFin de la planificación
        return $this->franjaRepository->findByUsuarioYdia($usuario, $diaSemana, $fecha);
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function obtenerFranjasDeSemana(User $usuario, \DateTime $lunesSemana): array
    {
        $franjas = [];
        for ($dia = 1; $dia <= 7; $dia++) {
            $fechaDia = clone $lunesSemana;
            $fechaDia->modify('+' . ($dia - 1) . ' days');
            $franjasDelDia = $this->franjaRepository->findByUsuarioYdia($usuario, $dia, $fechaDia);
            foreach ($franjasDelDia as $franja) {
                $franjas[] = $franja;
            }
        }
        
        return $franjas;
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

    public function validarFranjas(array $franjas): array
    {
        $errores = [];
        $franjasPorDia = [];

        // Agrupar franjas por día
        foreach ($franjas as $franja) {
            $dia = $franja->getDiaSemana();
            if (!isset($franjasPorDia[$dia])) {
                $franjasPorDia[$dia] = [];
            }
            $franjasPorDia[$dia][] = $franja;
        }

        // Validar cada día
        foreach ($franjasPorDia as $dia => $franjasDelDia) {
            // Verificar que hay al menos una franja de repaso_basico
            $tieneRepaso = false;
            foreach ($franjasDelDia as $franja) {
                if ($franja->getTipoActividad() === 'repaso_basico') {
                    $tieneRepaso = true;
                    break;
                }
            }
            if (!$tieneRepaso) {
                $errores[] = "El día " . $this->getNombreDia($dia) . " debe tener al menos una franja de repaso básico.";
            }

            // Verificar solapamientos
            usort($franjasDelDia, function($a, $b) {
                return $a->getHoraInicio() <=> $b->getHoraInicio();
            });

            for ($i = 0; $i < count($franjasDelDia) - 1; $i++) {
                $franja1 = $franjasDelDia[$i];
                $franja2 = $franjasDelDia[$i + 1];

                if ($franja1->getHoraFin() > $franja2->getHoraInicio()) {
                    $errores[] = "Hay solapamiento en el día " . $this->getNombreDia($dia) . 
                                " entre las franjas " . $franja1->getHoraInicio()->format('H:i') . 
                                "-" . $franja1->getHoraFin()->format('H:i') . 
                                " y " . $franja2->getHoraInicio()->format('H:i') . 
                                "-" . $franja2->getHoraFin()->format('H:i');
                }
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

    private function getNombreDia(int $dia): string
    {
        $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        return $dias[$dia] ?? '';
    }
}

