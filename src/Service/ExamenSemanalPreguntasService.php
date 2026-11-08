<?php

namespace App\Service;

use App\Entity\ExamenSemanal;
use App\Repository\ConfiguracionExamenRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\PreguntaRepository;

/**
 * Selecciona las preguntas de un examen semanal con un orden fijo.
 * El examen en pantalla, el PDF y la plantilla de respuestas usan esta misma lista.
 */
class ExamenSemanalPreguntasService
{
    public function __construct(
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private ConfiguracionExamenRepository $configuracionExamenRepository,
        private ConfiguracionExamenService $configuracionExamenService,
    ) {
    }

    /**
     * @return array<int, object>
     */
    public function paraExamen(ExamenSemanal $examenSemanal): array
    {
        return $this->ordenar($examenSemanal, $this->cargar($examenSemanal));
    }

    /**
     * @return array<int, object>
     */
    public function cargar(ExamenSemanal $examenSemanal): array
    {
        $esMunicipal = $examenSemanal->getMunicipio() !== null;
        $esConvocatoria = $examenSemanal->getConvocatoria() !== null;

        if ($examenSemanal->getModoCreacion() === 'preguntas_especificas') {
            if ($esMunicipal || $esConvocatoria) {
                $preguntas = array_filter(
                    $examenSemanal->getPreguntasMunicipales()->toArray(),
                    static fn ($pregunta) => $pregunta->isActivo()
                );
            } else {
                $preguntas = array_filter(
                    $examenSemanal->getPreguntas()->toArray(),
                    static fn ($pregunta) => $pregunta->isActivo()
                );
            }

            return array_values($preguntas);
        }

        $preguntas = [];
        if ($esMunicipal || $esConvocatoria) {
            foreach ($examenSemanal->getTemasMunicipales() as $temaMunicipal) {
                $preguntas = array_merge($preguntas, $this->preguntaMunicipalRepository->findBy([
                    'temaMunicipal' => $temaMunicipal,
                    'dificultad' => $examenSemanal->getDificultad(),
                    'activo' => true,
                ]));
            }
        } else {
            foreach ($examenSemanal->getTemas() as $tema) {
                $preguntas = array_merge($preguntas, $this->preguntaRepository->findBy([
                    'tema' => $tema,
                    'dificultad' => $examenSemanal->getDificultad(),
                    'activo' => true,
                ]));
            }
        }

        return $preguntas;
    }

    /**
     * @param array<int, object> $preguntas
     * @return array<int, object>
     */
    public function ordenar(ExamenSemanal $examenSemanal, array $preguntas): array
    {
        if ($preguntas === []) {
            return [];
        }

        $esMunicipal = $examenSemanal->getMunicipio() !== null;
        $esConvocatoria = $examenSemanal->getConvocatoria() !== null;
        $numeroPreguntas = $examenSemanal->getNumeroPreguntas();
        $semilla = $examenSemanal->getId() ?? 0;

        if ($numeroPreguntas !== null && $numeroPreguntas > 0) {
            if (!$esMunicipal && !$esConvocatoria && $examenSemanal->getModoCreacion() !== 'preguntas_especificas') {
                $temas = $examenSemanal->getTemas()->toArray();
                if ($temas !== []) {
                    return $this->distribuirPreguntasPorPorcentajesDeterministico($preguntas, $temas, $numeroPreguntas, $semilla);
                }

                $preguntas = $this->mezclarDeterministico($preguntas, $semilla);

                return $this->seleccionarPreguntasSinRepetirArticulosDeterministico($preguntas, $numeroPreguntas, $semilla);
            }

            $preguntas = $this->mezclarDeterministico($preguntas, $semilla);

            return $this->seleccionarPreguntasSinRepetirArticulosDeterministico($preguntas, $numeroPreguntas, $semilla);
        }

        $preguntas = $this->mezclarDeterministico($preguntas, $semilla);

        return $this->seleccionarPreguntasSinRepetirArticulosDeterministico($preguntas, count($preguntas), $semilla);
    }

    private function mezclarDeterministico(array $array, int $semilla): array
    {
        usort($array, function ($a, $b) {
            return $a->getId() <=> $b->getId();
        });

        mt_srand($semilla);

        $n = count($array);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }

        mt_srand();

        return $array;
    }

    private function distribuirPreguntasPorPorcentajesDeterministico(array $preguntas, array $temas, int $cantidadTotal, int $semilla): array
    {
        $configuraciones = $this->configuracionExamenRepository->findByTemas($temas);

        $preguntasPorTema = [];
        foreach ($preguntas as $pregunta) {
            $temaId = $pregunta->getTema()->getId();
            if (!isset($preguntasPorTema[$temaId])) {
                $preguntasPorTema[$temaId] = [];
            }
            $preguntasPorTema[$temaId][] = $pregunta;
        }

        $distribucionPorTema = [];
        $totalTemas = count($temas);
        $temasConPorcentaje = [];
        $porcentajesTemas = [];

        foreach ($temas as $tema) {
            $temaId = $tema->getId();
            $porcentaje = $this->configuracionExamenService->obtenerPorcentajeParaTema($configuraciones, $temaId, $totalTemas);
            $porcentajesTemas[$temaId] = $porcentaje;

            if ($porcentaje > 0 && isset($preguntasPorTema[$temaId]) && count($preguntasPorTema[$temaId]) > 0) {
                $temasConPorcentaje[] = $temaId;
                $cantidadIdeal = ($porcentaje / 100) * $cantidadTotal;
                $cantidadParaTema = max(1, (int) round($cantidadIdeal));
            } else {
                $cantidadParaTema = 0;
            }

            $distribucionPorTema[$temaId] = $cantidadParaTema;
        }

        $sumaDistribucion = array_sum($distribucionPorTema);
        $diferencia = $cantidadTotal - $sumaDistribucion;

        if ($diferencia != 0) {
            $temasAjustables = [];
            foreach ($temasConPorcentaje as $temaId) {
                $porcentaje = $porcentajesTemas[$temaId];
                $cantidadIdeal = ($porcentaje / 100) * $cantidadTotal;
                $cantidadActual = $distribucionPorTema[$temaId];
                $preguntasDisponibles = count($preguntasPorTema[$temaId] ?? []);

                $temasAjustables[] = [
                    'temaId' => $temaId,
                    'porcentaje' => $porcentaje,
                    'cantidadIdeal' => $cantidadIdeal,
                    'cantidadActual' => $cantidadActual,
                    'diferencia' => $cantidadIdeal - $cantidadActual,
                    'preguntasDisponibles' => $preguntasDisponibles,
                ];
            }

            if ($diferencia > 0) {
                usort($temasAjustables, function ($a, $b) {
                    return $b['diferencia'] <=> $a['diferencia'];
                });

                foreach ($temasAjustables as $temaAjustable) {
                    if ($diferencia <= 0) {
                        break;
                    }
                    $temaId = $temaAjustable['temaId'];
                    if ($temaAjustable['preguntasDisponibles'] > $distribucionPorTema[$temaId]) {
                        $distribucionPorTema[$temaId]++;
                        $diferencia--;
                    }
                }

                while ($diferencia > 0) {
                    $asignado = false;
                    foreach ($temasAjustables as $temaAjustable) {
                        if ($diferencia <= 0) {
                            break;
                        }
                        $temaId = $temaAjustable['temaId'];
                        if ($temaAjustable['preguntasDisponibles'] > $distribucionPorTema[$temaId]) {
                            $distribucionPorTema[$temaId]++;
                            $diferencia--;
                            $asignado = true;
                        }
                    }
                    if (!$asignado) {
                        break;
                    }
                }
            } else {
                usort($temasAjustables, function ($a, $b) {
                    return $a['diferencia'] <=> $b['diferencia'];
                });

                while ($diferencia < 0) {
                    $quitado = false;
                    foreach ($temasAjustables as $temaAjustable) {
                        if ($diferencia >= 0) {
                            break;
                        }
                        $temaId = $temaAjustable['temaId'];
                        if ($distribucionPorTema[$temaId] > 1) {
                            $distribucionPorTema[$temaId]--;
                            $diferencia++;
                            $quitado = true;
                        }
                    }
                    if (!$quitado) {
                        break;
                    }
                }
            }
        }

        $preguntasSeleccionadas = [];
        $articulosUsados = [];
        $preguntasIdsUsadas = [];

        foreach ($distribucionPorTema as $temaId => $cantidad) {
            if ($cantidad <= 0 || !isset($preguntasPorTema[$temaId])) {
                continue;
            }

            $preguntasTema = $this->mezclarDeterministico($preguntasPorTema[$temaId], $semilla + $temaId);

            $preguntasSeleccionadasTema = [];
            foreach ($preguntasTema as $pregunta) {
                if (count($preguntasSeleccionadasTema) >= $cantidad) {
                    break;
                }

                $articuloId = null;
                if (method_exists($pregunta, 'getArticulo')) {
                    $articulo = $pregunta->getArticulo();
                    $articuloId = $articulo ? $articulo->getId() : null;
                }

                if ($articuloId !== null && in_array($articuloId, $articulosUsados)) {
                    continue;
                }

                if (in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                    continue;
                }

                $preguntasSeleccionadasTema[] = $pregunta;
                $preguntasIdsUsadas[] = $pregunta->getId();

                if ($articuloId !== null) {
                    $articulosUsados[] = $articuloId;
                }
            }

            $preguntasSeleccionadas = array_merge($preguntasSeleccionadas, $preguntasSeleccionadasTema);
        }

        if (count($preguntasSeleccionadas) < $cantidadTotal) {
            $preguntasRestantes = array_filter($preguntas, function ($p) use ($preguntasIdsUsadas) {
                return !in_array($p->getId(), $preguntasIdsUsadas);
            });
            $preguntasRestantes = $this->mezclarDeterministico($preguntasRestantes, $semilla + 1000);

            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidadTotal) {
                    break;
                }

                $articuloId = null;
                if (method_exists($pregunta, 'getArticulo')) {
                    $articulo = $pregunta->getArticulo();
                    $articuloId = $articulo ? $articulo->getId() : null;
                }

                if ($articuloId === null || !in_array($articuloId, $articulosUsados)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    $preguntasIdsUsadas[] = $pregunta->getId();
                    if ($articuloId !== null) {
                        $articulosUsados[] = $articuloId;
                    }
                }
            }
        }

        if (count($preguntasSeleccionadas) < $cantidadTotal) {
            $preguntasRestantes = array_filter($preguntas, function ($p) use ($preguntasIdsUsadas) {
                return !in_array($p->getId(), $preguntasIdsUsadas);
            });
            $preguntasRestantes = $this->mezclarDeterministico($preguntasRestantes, $semilla + 1000);

            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidadTotal) {
                    break;
                }

                if (!in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    $preguntasIdsUsadas[] = $pregunta->getId();
                }
            }
        }

        if (count($preguntasSeleccionadas) > $cantidadTotal) {
            $preguntasSeleccionadas = array_slice($preguntasSeleccionadas, 0, $cantidadTotal);
        }

        return $this->mezclarDeterministico($preguntasSeleccionadas, $semilla + 2000);
    }

    private function seleccionarPreguntasSinRepetirArticulosDeterministico(array $preguntas, int $cantidad, int $semilla): array
    {
        $preguntasSeleccionadas = [];
        $articulosUsados = [];
        $preguntasIdsUsadas = [];

        foreach ($preguntas as $pregunta) {
            if (count($preguntasSeleccionadas) >= $cantidad) {
                break;
            }

            $articuloId = null;
            if (method_exists($pregunta, 'getArticulo')) {
                $articulo = $pregunta->getArticulo();
                $articuloId = $articulo ? $articulo->getId() : null;
            }

            if ($articuloId !== null && in_array($articuloId, $articulosUsados)) {
                continue;
            }

            if (in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                continue;
            }

            $preguntasSeleccionadas[] = $pregunta;
            $preguntasIdsUsadas[] = $pregunta->getId();

            if ($articuloId !== null) {
                $articulosUsados[] = $articuloId;
            }
        }

        if (count($preguntasSeleccionadas) < $cantidad) {
            $preguntasRestantes = array_filter($preguntas, function ($p) use ($preguntasIdsUsadas) {
                return !in_array($p->getId(), $preguntasIdsUsadas);
            });
            $preguntasRestantes = $this->mezclarDeterministico($preguntasRestantes, $semilla + 1000);

            foreach ($preguntasRestantes as $pregunta) {
                if (count($preguntasSeleccionadas) >= $cantidad) {
                    break;
                }

                if (!in_array($pregunta->getId(), $preguntasIdsUsadas)) {
                    $preguntasSeleccionadas[] = $pregunta;
                    $preguntasIdsUsadas[] = $pregunta->getId();
                }
            }
        }

        return $preguntasSeleccionadas;
    }
}
