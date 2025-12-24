<?php

namespace App\Controller;

use App\Entity\Examen;
use App\Entity\ExamenSemanal;
use App\Entity\Pregunta;
use App\Repository\ExamenSemanalRepository;
use App\Repository\ExamenRepository;
use App\Repository\PreguntaRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Service\NotificacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/examen-semanal-alumno')]
#[IsGranted('ROLE_USER')]
class ExamenSemanalAlumnoController extends AbstractController
{
    public function __construct(
        private ExamenSemanalRepository $examenSemanalRepository,
        private ExamenRepository $examenRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private MunicipioRepository $municipioRepository,
        private EntityManagerInterface $entityManager,
        private NotificacionService $notificacionService
    ) {
    }

    #[Route('/', name: 'app_examen_semanal_alumno_index', methods: ['GET'])]
    public function index(): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();
        $ahora = new \DateTime();

        // Obtener todos los exámenes semanales activos que aún no han cerrado
        $todosExamenes = $this->examenSemanalRepository->createQueryBuilder('e')
            ->where('e.activo = :activo')
            ->andWhere('e.fechaCierre >= :ahora')
            ->setParameter('activo', true)
            ->setParameter('ahora', $ahora)
            ->orderBy('e.fechaApertura', 'DESC')
            ->getQuery()
            ->getResult();

        // Obtener IDs de exámenes semanales ya realizados por el alumno
        $examenesCompletados = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal IS NOT NULL')
            ->setParameter('usuario', $alumno)
            ->getQuery()
            ->getResult();
        
        $examenesRealizadosIds = [];
        foreach ($examenesCompletados as $examen) {
            if ($examen->getExamenSemanal()) {
                $examenesRealizadosIds[] = $examen->getExamenSemanal()->getId();
            }
        }

        // Filtrar solo exámenes pendientes (no realizados)
        $examenesDisponibles = array_filter($todosExamenes, function($examen) use ($examenesRealizadosIds) {
            return !in_array($examen->getId(), $examenesRealizadosIds);
        });

        // Separar en examen general y municipal
        $examenGeneral = null;
        $examenMunicipal = null;

        foreach ($examenesDisponibles as $examen) {
            if ($examen->getMunicipio() === null) {
                // Es examen general
                if ($examenGeneral === null || $examen->getFechaApertura() > $examenGeneral->getFechaApertura()) {
                    $examenGeneral = $examen;
                }
            } else {
                // Es examen municipal
                if ($examenMunicipal === null || $examen->getFechaApertura() > $examenMunicipal->getFechaApertura()) {
                    $examenMunicipal = $examen;
                }
            }
        }

        // Obtener histórico de exámenes semanales realizados
        $examenesRealizados = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal IS NOT NULL')
            ->setParameter('usuario', $alumno)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('examen_semanal_alumno/index.html.twig', [
            'examenGeneral' => $examenGeneral,
            'examenMunicipal' => $examenMunicipal,
            'yaRealizadoGeneral' => false, // Ya no se muestran los realizados aquí
            'yaRealizadoMunicipal' => false, // Ya no se muestran los realizados aquí
            'examenesRealizados' => $examenesRealizados,
        ]);
    }

    #[Route('/{id}/realizar', name: 'app_examen_semanal_alumno_realizar', methods: ['GET', 'POST'])]
    public function realizar(Request $request, ExamenSemanal $examenSemanal, SessionInterface $session): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        $alumno = $this->getUser();

        // Verificar que el examen esté disponible
        if (!$examenSemanal->estaDisponible()) {
            $this->addFlash('error', 'Este examen no está disponible en este momento.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Verificar que el alumno no haya realizado ya este examen
        $examenRealizado = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal = :examenSemanal')
            ->setParameter('usuario', $alumno)
            ->setParameter('examenSemanal', $examenSemanal)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($examenRealizado) {
            $this->addFlash('error', 'Ya has realizado este examen semanal.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Limpiar sesión de examen anterior si existe
        $session->remove('examen_preguntas');
        $session->remove('examen_respuestas');
        $session->remove('examen_config');
        $session->remove('examen_pregunta_actual');

        // Obtener preguntas según el modo de creación del examen
        $preguntas = [];
        $esMunicipal = $examenSemanal->getMunicipio() !== null;

        if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            // Examen con preguntas específicas (creadas al vuelo)
            if ($esMunicipal) {
                $preguntas = $examenSemanal->getPreguntasMunicipales()->toArray();
            } else {
                $preguntas = $examenSemanal->getPreguntas()->toArray();
            }
        } else {
            // Examen con preguntas por temas (método tradicional)
            if ($esMunicipal) {
                $temasMunicipales = $examenSemanal->getTemasMunicipales();
                foreach ($temasMunicipales as $temaMunicipal) {
                    $preguntasTema = $this->preguntaMunicipalRepository->findBy([
                        'temaMunicipal' => $temaMunicipal,
                        'dificultad' => $examenSemanal->getDificultad(),
                        'activo' => true,
                    ]);
                    $preguntas = array_merge($preguntas, $preguntasTema);
                }
            } else {
                $temas = $examenSemanal->getTemas();
                foreach ($temas as $tema) {
                    $preguntasTema = $this->preguntaRepository->findBy([
                        'tema' => $tema,
                        'dificultad' => $examenSemanal->getDificultad(),
                        'activo' => true,
                    ]);
                    $preguntas = array_merge($preguntas, $preguntasTema);
                }
            }
        }

        if (empty($preguntas)) {
            $this->addFlash('error', 'No hay preguntas disponibles para este examen.');
            return $this->redirectToRoute('app_examen_semanal_alumno_index');
        }

        // Mezclar preguntas aleatoriamente
        shuffle($preguntas);

        // Limitar el número de preguntas si está especificado
        $numeroPreguntas = $examenSemanal->getNumeroPreguntas();
        if ($numeroPreguntas !== null && $numeroPreguntas > 0) {
            // Seleccionar preguntas sin repetir artículos, respetando el límite
            $preguntas = $this->seleccionarPreguntasSinRepetirArticulos($preguntas, $numeroPreguntas);
            
            // Si hay menos preguntas disponibles que el límite solicitado, informar al usuario
            if (count($preguntas) < $numeroPreguntas) {
                $this->addFlash('info', 'Solo hay ' . count($preguntas) . ' preguntas disponibles sin repetir artículos. El examen se realizará con todas las preguntas disponibles.');
            }
        } else {
            // Si no hay límite, aplicar solo la restricción de no repetir artículos
            // pero usar todas las preguntas disponibles
            $preguntas = $this->seleccionarPreguntasSinRepetirArticulos($preguntas, count($preguntas));
        }

        $preguntasIds = array_map(fn($p) => $p->getId(), $preguntas);

        // Guardar en sesión
        $config = [
            'dificultad' => $examenSemanal->getDificultad(),
            'numero_preguntas' => count($preguntasIds),
            'es_municipal' => $esMunicipal,
            'examen_semanal_id' => $examenSemanal->getId(),
        ];
        
        if ($esMunicipal) {
            $config['municipio_id'] = $examenSemanal->getMunicipio()->getId();
            $config['temas_municipales'] = array_map(fn($t) => $t->getId(), $examenSemanal->getTemasMunicipales()->toArray());
        } else {
            $config['temas'] = array_map(fn($t) => $t->getId(), $examenSemanal->getTemas()->toArray());
        }
        
        $session->set('examen_preguntas', $preguntasIds);
        $session->set('examen_respuestas', []);
        $session->set('examen_config', $config);
        $session->set('examen_pregunta_actual', 0);

        return $this->redirectToRoute('app_examen_pregunta', ['numero' => 1]);
    }

    #[Route('/{id}/ranking', name: 'app_examen_semanal_alumno_ranking', methods: ['GET'])]
    public function ranking(ExamenSemanal $examenSemanal): Response
    {
        // Verificar que no sea profesor ni admin
        if ($this->isGranted('ROLE_PROFESOR') || $this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Esta ruta es solo para alumnos.');
        }

        // Obtener todos los exámenes realizados de este examen semanal
        $examenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.examenSemanal = :examenSemanal')
            ->setParameter('examenSemanal', $examenSemanal)
            ->orderBy('e.nota', 'DESC')
            ->addOrderBy('e.fecha', 'ASC')
            ->getQuery()
            ->getResult();

        // Calcular posición del alumno actual
        $alumno = $this->getUser();
        $posicion = null;
        $miExamen = null;
        
        foreach ($examenes as $index => $examen) {
            if ($examen->getUsuario()->getId() === $alumno->getId()) {
                $posicion = $index + 1;
                $miExamen = $examen;
                break;
            }
        }

        return $this->render('examen_semanal_alumno/ranking.html.twig', [
            'examenSemanal' => $examenSemanal,
            'examenes' => $examenes,
            'posicion' => $posicion,
            'miExamen' => $miExamen,
        ]);
    }

    /**
     * Selecciona preguntas asegurándose de que no haya dos preguntas del mismo artículo
     * 
     * @param array $preguntas Array de preguntas disponibles
     * @param int $cantidad Cantidad de preguntas a seleccionar
     * @return array Array de preguntas seleccionadas sin repetir artículos
     */
    private function seleccionarPreguntasSinRepetirArticulos(array $preguntas, int $cantidad): array
    {
        $preguntasSeleccionadas = [];
        $articulosUsados = [];
        
        foreach ($preguntas as $pregunta) {
            // Si ya tenemos suficientes preguntas, parar
            if (count($preguntasSeleccionadas) >= $cantidad) {
                break;
            }
            
            // Solo verificar artículos si la pregunta tiene el método getArticulo()
            // (las preguntas municipales no tienen artículo)
            $articuloId = null;
            if (method_exists($pregunta, 'getArticulo')) {
                $articulo = $pregunta->getArticulo();
                $articuloId = $articulo ? $articulo->getId() : null;
            }
            
            // Si el artículo ya fue usado, saltar esta pregunta
            if ($articuloId !== null && in_array($articuloId, $articulosUsados)) {
                continue;
            }
            
            // Agregar la pregunta a las seleccionadas
            $preguntasSeleccionadas[] = $pregunta;
            
            // Marcar el artículo como usado
            if ($articuloId !== null) {
                $articulosUsados[] = $articuloId;
            }
        }
        
        return $preguntasSeleccionadas;
    }
}

