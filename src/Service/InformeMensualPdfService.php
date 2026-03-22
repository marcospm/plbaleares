<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ExamenRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\PreguntaRepository;
use App\Repository\TareaAsignadaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\TemaRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class InformeMensualPdfService
{
    public function __construct(
        private Environment $twig,
        private ExamenRepository $examenRepository,
        private TareaAsignadaRepository $tareaAsignadaRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private TemaRepository $temaRepository,
        private TemaMunicipalRepository $temaMunicipalRepository,
        private PreguntaRiesgoService $preguntaRiesgoService
    ) {
    }

    public function generatePdf(User $alumno, int $mes, int $anio): Response
    {
        $primerDiaMes = new \DateTime("$anio-$mes-01");
        $ultimoDiaMes = clone $primerDiaMes;
        $ultimoDiaMes->modify('last day of this month');
        $ultimoDiaMes->setTime(23, 59, 59);

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

        $totalExamenes = count($examenes);

        $totalAciertos = array_sum(array_map(function ($examen) {
            return $examen->getAciertos();
        }, $examenes));

        $totalErrores = array_sum(array_map(function ($examen) {
            return $examen->getErrores();
        }, $examenes));

        $totalEnBlanco = array_sum(array_map(function ($examen) {
            return $examen->getEnBlanco();
        }, $examenes));

        $notaMedia = $this->examenRepository->calcularNotaMediaDesdeExamenes($examenes) ?? 0;

        $notas = array_map(function ($examen) {
            return (float) $examen->getNota();
        }, $examenes);

        $mejorNota = $totalExamenes > 0 ? max($notas) : 0;
        $peorNota = $totalExamenes > 0 ? min($notas) : 0;

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

        $tareasCompletadas = array_filter($tareas, function ($tarea) {
            return $tarea->isCompletada();
        });
        $tareasPendientes = array_filter($tareas, function ($tarea) {
            return !$tarea->isCompletada();
        });

        $temasConMasFallos = $this->calcularTemasConMasFallos($alumno);

        $estadisticasPorDificultad = $this->calcularEstadisticasPorDificultad($examenes);

        $porcentajesPorTemaRaw = $this->preguntaRiesgoService->calcularPorcentajesPorTema($alumno);
        $porcentajesPorTemaMunicipalRaw = $this->preguntaRiesgoService->calcularPorcentajesPorTemaMunicipal($alumno);

        $temaIds = array_keys($porcentajesPorTemaRaw);
        $temasMap = [];
        if (!empty($temaIds)) {
            $temas = $this->temaRepository->findBy(['id' => $temaIds]);
            foreach ($temas as $tema) {
                $temasMap[$tema->getId()] = $tema;
            }
        }

        $porcentajesPorTema = [];
        foreach ($porcentajesPorTemaRaw as $temaId => $porcentaje) {
            if (isset($temasMap[$temaId])) {
                $tema = $temasMap[$temaId];
                $porcentajesPorTema[] = [
                    'tema' => $tema,
                    'nombre' => $tema->getNombre(),
                    'porcentaje' => $porcentaje,
                ];
            }
        }
        usort($porcentajesPorTema, fn ($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);

        $temaMunicipalIds = array_keys($porcentajesPorTemaMunicipalRaw);
        $temasMunicipalesMap = [];
        if (!empty($temaMunicipalIds)) {
            $temasMunicipales = $this->temaMunicipalRepository->findBy(['id' => $temaMunicipalIds]);
            foreach ($temasMunicipales as $temaMunicipal) {
                $temasMunicipalesMap[$temaMunicipal->getId()] = $temaMunicipal;
            }
        }

        $porcentajesPorTemaMunicipal = [];
        foreach ($porcentajesPorTemaMunicipalRaw as $temaMunicipalId => $porcentaje) {
            if (isset($temasMunicipalesMap[$temaMunicipalId])) {
                $temaMunicipal = $temasMunicipalesMap[$temaMunicipalId];
                $porcentajesPorTemaMunicipal[] = [
                    'tema' => $temaMunicipal,
                    'nombre' => $temaMunicipal->getNombre(),
                    'porcentaje' => $porcentaje,
                ];
            }
        }
        usort($porcentajesPorTemaMunicipal, fn ($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);

        $examenesGenerales = array_filter($examenes, function ($examen) {
            return $examen->getMunicipio() === null && $examen->getConvocatoria() === null && $examen->getExamenSemanal() === null;
        });

        $examenesMunicipales = array_filter($examenes, function ($examen) {
            return $examen->getMunicipio() !== null && $examen->getExamenSemanal() === null;
        });

        $examenesConvocatoria = array_filter($examenes, function ($examen) {
            return $examen->getConvocatoria() !== null && $examen->getExamenSemanal() === null;
        });

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $nombreMes = $meses[$mes] ?? 'Mes';

        $html = $this->twig->render('informe_mensual/pdf.html.twig', [
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
            'temasConMasFallos' => $temasConMasFallos,
            'estadisticasPorDificultad' => $estadisticasPorDificultad,
            'porcentajesPorTema' => $porcentajesPorTema,
            'porcentajesPorTemaMunicipal' => $porcentajesPorTemaMunicipal,
        ]);

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

        $nombreArchivo = "Informe_Mensual_{$alumno->getUsername()}_{$nombreMes}_{$anio}.pdf";

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
            ]
        );
    }

    /**
     * @return array<int, array{tema: mixed, nombre: string, fallos: int}>
     */
    private function calcularTemasConMasFallos(User $alumno): array
    {
        $todosExamenes = $this->examenRepository->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->setParameter('usuario', $alumno)
            ->getQuery()
            ->getResult();

        $fallosPorTema = [];

        $preguntasIdsGenerales = [];
        $preguntasIdsMunicipales = [];
        $mapaRespuestas = [];

        foreach ($todosExamenes as $examen) {
            $preguntasIds = $examen->getPreguntasIds() ?? [];
            $respuestas = $examen->getRespuestas() ?? [];
            $municipio = $examen->getMunicipio();
            $esMunicipal = $municipio !== null;

            if (empty($preguntasIds)) {
                continue;
            }

            foreach ($preguntasIds as $preguntaId) {
                $mapaRespuestas[$preguntaId] = [
                    'examen' => $examen,
                    'respuestas' => $respuestas,
                    'esMunicipal' => $esMunicipal,
                ];

                if ($esMunicipal) {
                    if (!in_array($preguntaId, $preguntasIdsMunicipales)) {
                        $preguntasIdsMunicipales[] = $preguntaId;
                    }
                } else {
                    if (!in_array($preguntaId, $preguntasIdsGenerales)) {
                        $preguntasIdsGenerales[] = $preguntaId;
                    }
                }
            }
        }

        $preguntasGeneralesMap = [];
        if (!empty($preguntasIdsGenerales)) {
            $preguntasGenerales = $this->preguntaRepository->findByIds($preguntasIdsGenerales);
            foreach ($preguntasGenerales as $pregunta) {
                $preguntasGeneralesMap[$pregunta->getId()] = $pregunta;
            }
        }

        $preguntasMunicipalesMap = [];
        if (!empty($preguntasIdsMunicipales)) {
            $preguntasMunicipales = $this->preguntaMunicipalRepository->findByIds($preguntasIdsMunicipales);
            foreach ($preguntasMunicipales as $pregunta) {
                $preguntasMunicipalesMap[$pregunta->getId()] = $pregunta;
            }
        }

        foreach ($mapaRespuestas as $preguntaId => $datos) {
            $respuestas = $datos['respuestas'];
            $esMunicipal = $datos['esMunicipal'];

            if ($esMunicipal) {
                $pregunta = $preguntasMunicipalesMap[$preguntaId] ?? null;
            } else {
                $pregunta = $preguntasGeneralesMap[$preguntaId] ?? null;
            }

            if (!$pregunta) {
                continue;
            }

            if ($esMunicipal) {
                $tema = $pregunta->getTemaMunicipal();
                $temaId = $tema ? $tema->getId() : null;
                $temaNombre = $tema ? $tema->getNombre() : 'Sin tema';
            } else {
                $tema = $pregunta->getTema();
                $temaId = $tema ? $tema->getId() : null;
                $temaNombre = $tema ? $tema->getNombre() : 'Sin tema';
            }

            if (!$temaId) {
                continue;
            }

            $respuestaAlumno = $respuestas[$preguntaId] ?? null;

            if ($respuestaAlumno !== null && $respuestaAlumno !== '') {
                $respuestaCorrecta = $pregunta->getRespuestaCorrecta();
                if (strtoupper(trim($respuestaAlumno)) !== strtoupper(trim($respuestaCorrecta ?? ''))) {
                    $claveTema = ($esMunicipal ? 'm_' : 'g_') . $temaId;

                    if (!isset($fallosPorTema[$claveTema])) {
                        $fallosPorTema[$claveTema] = [
                            'tema' => $tema,
                            'nombre' => $temaNombre,
                            'fallos' => 0,
                        ];
                    }
                    $fallosPorTema[$claveTema]['fallos']++;
                }
            }
        }

        usort($fallosPorTema, function ($a, $b) {
            return $b['fallos'] <=> $a['fallos'];
        });

        return array_slice($fallosPorTema, 0, 3);
    }

    /**
     * @param array<int, \App\Entity\Examen> $examenes
     */
    private function calcularEstadisticasPorDificultad(array $examenes): array
    {
        $estadisticas = [
            'facil' => ['total' => 0, 'notaMedia' => 0, 'totalAciertos' => 0, 'totalErrores' => 0, 'totalEnBlanco' => 0],
            'moderada' => ['total' => 0, 'notaMedia' => 0, 'totalAciertos' => 0, 'totalErrores' => 0, 'totalEnBlanco' => 0],
            'dificil' => ['total' => 0, 'notaMedia' => 0, 'totalAciertos' => 0, 'totalErrores' => 0, 'totalEnBlanco' => 0],
        ];

        foreach ($examenes as $examen) {
            $dificultad = strtolower($examen->getDificultad() ?? '');

            if (!isset($estadisticas[$dificultad])) {
                continue;
            }

            $estadisticas[$dificultad]['total']++;
            $estadisticas[$dificultad]['totalAciertos'] += $examen->getAciertos();
            $estadisticas[$dificultad]['totalErrores'] += $examen->getErrores();
            $estadisticas[$dificultad]['totalEnBlanco'] += $examen->getEnBlanco();
        }

        foreach (['facil', 'moderada', 'dificil'] as $dificultad) {
            $examenesDificultad = array_filter($examenes, function ($examen) use ($dificultad) {
                return strtolower($examen->getDificultad() ?? '') === $dificultad;
            });

            if (!empty($examenesDificultad)) {
                $notaMedia = $this->examenRepository->calcularNotaMediaDesdeExamenes(array_values($examenesDificultad)) ?? 0;
                $estadisticas[$dificultad]['notaMedia'] = round($notaMedia, 2);
            }
        }

        return $estadisticas;
    }
}
