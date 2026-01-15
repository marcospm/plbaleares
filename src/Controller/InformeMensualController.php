<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ExamenRepository;
use App\Repository\ExamenSemanalRepository;
use App\Repository\TareaAsignadaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/informe-mensual')]
#[IsGranted('ROLE_PROFESOR')]
class InformeMensualController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private ExamenRepository $examenRepository,
        private ExamenSemanalRepository $examenSemanalRepository,
        private TareaAsignadaRepository $tareaAsignadaRepository
    ) {
    }

    #[Route('/', name: 'app_informe_mensual_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Obtener alumnos asignados si no es admin
        $alumnosIds = [];
        if (!$esAdmin) {
            $alumnosIds = array_map(function($alumno) {
                return $alumno->getId();
            }, $usuarioActual->getAlumnos()->toArray());
            
            if (empty($alumnosIds)) {
                $alumnosIds = [-1]; // No mostrar nada si no tiene alumnos
            }
        }

        // Parámetros de paginación
        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('search', '');

        // Construir query para alumnos
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%');

        // Filtrar por alumnos asignados si no es admin
        if (!$esAdmin && !empty($alumnosIds)) {
            $qb->andWhere('u.id IN (:alumnosIds)')
               ->setParameter('alumnosIds', $alumnosIds);
        }

        // Filtro de búsqueda
        if (!empty($search)) {
            $qb->andWhere('(u.username LIKE :search OR u.nombre LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        // Contar total
        $totalQb = clone $qb;
        $totalItems = (int) $totalQb->select('COUNT(u.id)')
                                   ->getQuery()
                                   ->getSingleScalarResult();

        // Aplicar paginación
        $offset = ($page - 1) * $itemsPerPage;
        $alumnos = $qb->orderBy('u.username', 'ASC')
                     ->setFirstResult($offset)
                     ->setMaxResults($itemsPerPage)
                     ->getQuery()
                     ->getResult();

        // Calcular total de páginas
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        // Obtener año actual para el selector de mes
        $anioActual = (int) date('Y');
        $mesActual = (int) date('m');

        return $this->render('informe_mensual/index.html.twig', [
            'alumnos' => $alumnos,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
            'anioActual' => $anioActual,
            'mesActual' => $mesActual,
        ]);
    }

    #[Route('/{id}/generar', name: 'app_informe_mensual_generar', methods: ['GET'])]
    public function generarInforme(User $alumno, Request $request): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');

        // Verificar que el profesor tiene acceso a este alumno
        if (!$esAdmin) {
            $alumnosIds = array_map(function($a) {
                return $a->getId();
            }, $usuarioActual->getAlumnos()->toArray());
            
            if (!in_array($alumno->getId(), $alumnosIds)) {
                $this->addFlash('error', 'No tienes acceso a este alumno.');
                return $this->redirectToRoute('app_informe_mensual_index');
            }
        }

        // Obtener mes y año (por defecto mes actual)
        $mes = $request->query->getInt('mes', (int) date('m'));
        $anio = $request->query->getInt('anio', (int) date('Y'));

        // Validar mes y año
        if ($mes < 1 || $mes > 12) {
            $this->addFlash('error', 'Mes inválido.');
            return $this->redirectToRoute('app_informe_mensual_index');
        }

        if ($anio < 2000 || $anio > 2100) {
            $this->addFlash('error', 'Año inválido.');
            return $this->redirectToRoute('app_informe_mensual_index');
        }

        // Obtener primer y último día del mes
        $primerDiaMes = new \DateTime("$anio-$mes-01");
        $ultimoDiaMes = clone $primerDiaMes;
        $ultimoDiaMes->modify('last day of this month');
        $ultimoDiaMes->setTime(23, 59, 59);

        // Obtener exámenes del mes
        $examenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.fecha >= :fechaInicio')
            ->andWhere('e.fecha <= :fechaFin')
            ->setParameter('usuario', $alumno)
            ->setParameter('fechaInicio', $primerDiaMes)
            ->setParameter('fechaFin', $ultimoDiaMes)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        // Calcular estadísticas de exámenes
        $totalExamenes = count($examenes);
        
        // Sumar todos los aciertos, errores y en blanco para calcular nota media
        $totalAciertos = array_sum(array_map(function($examen) {
            return $examen->getAciertos();
        }, $examenes));
        
        $totalErrores = array_sum(array_map(function($examen) {
            return $examen->getErrores();
        }, $examenes));
        
        $totalEnBlanco = array_sum(array_map(function($examen) {
            return $examen->getEnBlanco();
        }, $examenes));
        
        // Calcular nota media sumando aciertos, errores y en blanco (mismo método que rankings)
        $notaMedia = $this->examenRepository->calcularNotaMediaDesdeExamenes($examenes) ?? 0;
        
        // Para mejor y peor nota, usar las notas individuales de los exámenes
        $notas = array_map(function($examen) {
            return (float) $examen->getNota();
        }, $examenes);
        
        $mejorNota = $totalExamenes > 0 ? max($notas) : 0;
        $peorNota = $totalExamenes > 0 ? min($notas) : 0;

        // Obtener exámenes semanales realizados en el mes
        $examenesSemanales = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal IS NOT NULL')
            ->andWhere('e.fecha >= :fechaInicio')
            ->andWhere('e.fecha <= :fechaFin')
            ->setParameter('usuario', $alumno)
            ->setParameter('fechaInicio', $primerDiaMes)
            ->setParameter('fechaFin', $ultimoDiaMes)
            ->orderBy('e.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        // Obtener tareas del mes
        $tareas = $this->tareaAsignadaRepository->createQueryBuilder('ta')
            ->join('ta.tarea', 't')
            ->where('ta.usuario = :usuario')
            ->andWhere('t.semanaAsignacion >= :fechaInicio')
            ->andWhere('t.semanaAsignacion <= :fechaFin')
            ->setParameter('usuario', $alumno)
            ->setParameter('fechaInicio', $primerDiaMes)
            ->setParameter('fechaFin', $ultimoDiaMes)
            ->orderBy('t.semanaAsignacion', 'DESC')
            ->getQuery()
            ->getResult();

        $tareasCompletadas = array_filter($tareas, function($tarea) {
            return $tarea->isCompletada();
        });
        $tareasPendientes = array_filter($tareas, function($tarea) {
            return !$tarea->isCompletada();
        });

        // Agrupar exámenes por tipo
        $examenesGenerales = array_filter($examenes, function($examen) {
            return $examen->getMunicipio() === null && $examen->getConvocatoria() === null && $examen->getExamenSemanal() === null;
        });
        
        $examenesMunicipales = array_filter($examenes, function($examen) {
            return $examen->getMunicipio() !== null && $examen->getExamenSemanal() === null;
        });
        
        $examenesConvocatoria = array_filter($examenes, function($examen) {
            return $examen->getConvocatoria() !== null && $examen->getExamenSemanal() === null;
        });

        // Generar HTML para el PDF
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $nombreMes = $meses[$mes] ?? 'Mes';

        $html = $this->renderView('informe_mensual/pdf.html.twig', [
            'alumno' => $alumno,
            'mes' => $mes,
            'anio' => $anio,
            'nombreMes' => $nombreMes,
            'primerDiaMes' => $primerDiaMes,
            'ultimoDiaMes' => $ultimoDiaMes,
            'examenes' => $examenes,
            'totalExamenes' => $totalExamenes,
            'notaMedia' => $notaMedia,
            'mejorNota' => $mejorNota,
            'peorNota' => $peorNota,
            'totalAciertos' => $totalAciertos,
            'totalErrores' => $totalErrores,
            'totalEnBlanco' => $totalEnBlanco,
            'examenesSemanales' => $examenesSemanales,
            'examenesGenerales' => $examenesGenerales,
            'examenesMunicipales' => $examenesMunicipales,
            'examenesConvocatoria' => $examenesConvocatoria,
            'tareas' => $tareas,
            'tareasCompletadas' => count($tareasCompletadas),
            'tareasPendientes' => count($tareasPendientes),
        ]);

        // Configurar DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('isPhpEnabled', true);
        $options->set('isFontSubsettingEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generar nombre del archivo
        $nombreArchivo = "Informe_Mensual_{$alumno->getUsername()}_{$nombreMes}_{$anio}.pdf";

        // Retornar PDF
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
            ]
        );
    }
}
