<?php

namespace App\Service;

use App\Repository\PreguntaRepository;
use Psr\Cache\CacheItemPoolInterface;

class PartidaPreguntasService
{
    private const CACHE_PREFIX = 'partida_';
    private const TTL_MINIMO = 1800; // 30 minutos en segundos

    public function __construct(
        private CacheItemPoolInterface $cache,
        private PreguntaRepository $preguntaRepository
    ) {
    }

    /**
     * Crea una nueva partida
     * @param int $numPreguntas Número de preguntas (5-20)
     * @param int $tiempoLimite Tiempo límite en minutos (1-20)
     * @param string|null $dificultad Dificultad ('facil', 'media', 'dificil') o null
     * @param int|null $creadoPorId ID del usuario creador (opcional)
     * @return string Código de la partida
     */
    public function crearPartida(int $numPreguntas, int $tiempoLimite, ?string $dificultad, ?int $creadoPorId = null): string
    {
        // Validar parámetros
        if ($numPreguntas < 5 || $numPreguntas > 20) {
            throw new \InvalidArgumentException('El número de preguntas debe estar entre 5 y 20');
        }

        if ($tiempoLimite < 1 || $tiempoLimite > 20) {
            throw new \InvalidArgumentException('El tiempo límite debe estar entre 1 y 20 minutos');
        }

        // Generar código único
        $codigo = $this->generarCodigoUnico();

        // Seleccionar preguntas aleatorias del temario general
        $preguntas = $this->preguntaRepository->findAleatoriasActivasPorDificultad($numPreguntas, $dificultad);

        if (empty($preguntas)) {
            throw new \RuntimeException('No hay preguntas disponibles con los criterios especificados');
        }

        // Extraer solo los IDs
        $preguntasIds = array_map(fn($p) => $p->getId(), $preguntas);

        // Crear estructura de datos
        $partida = [
            'codigo' => $codigo,
            'preguntasIds' => $preguntasIds,
            'numPreguntas' => $numPreguntas,
            'tiempoLimite' => $tiempoLimite,
            'dificultad' => $dificultad,
            'fechaCreacion' => time(),
            'fechaInicioPrimerJugador' => null,
            'estado' => 'creada',
            'creadoPorId' => $creadoPorId,
            'participantes' => [],
        ];

        // Guardar en cache con TTL mínimo
        $this->guardarPartida($partida);

        return $codigo;
    }

    /**
     * Obtiene una partida por su código
     */
    public function obtenerPartida(string $codigo): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $codigo;
        $item = $this->cache->getItem($cacheKey);
        
        if (!$item->isHit()) {
            return null;
        }
        
        return $item->get();
    }

    /**
     * Obtiene las preguntas completas de una partida
     * @return array Array de preguntas con texto, opciones, etc.
     */
    public function obtenerPreguntas(string $codigo): array
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida || empty($partida['preguntasIds'])) {
            return [];
        }

        // Consultar base de datos para obtener preguntas completas
        $preguntas = $this->preguntaRepository->findByIds($partida['preguntasIds']);

        // Crear un mapa de ID => Pregunta para mantener el orden
        $preguntasMap = [];
        foreach ($preguntas as $pregunta) {
            $preguntasMap[$pregunta->getId()] = $pregunta;
        }

        // Convertir a array para JSON manteniendo el orden original
        $resultado = [];
        foreach ($partida['preguntasIds'] as $preguntaId) {
            if (isset($preguntasMap[$preguntaId])) {
                $pregunta = $preguntasMap[$preguntaId];
                $resultado[] = [
                    'id' => $pregunta->getId(),
                    'texto' => $pregunta->getTexto(),
                    'opcionA' => $pregunta->getOpcionA(),
                    'opcionB' => $pregunta->getOpcionB(),
                    'opcionC' => $pregunta->getOpcionC(),
                    'opcionD' => $pregunta->getOpcionD(),
                    'tema' => $pregunta->getTema() ? $pregunta->getTema()->getNombre() : null,
                    'ley' => $pregunta->getLey() ? $pregunta->getLey()->getNombre() : null,
                    // No incluir respuestaCorrecta en la respuesta al frontend
                ];
            }
        }

        return $resultado;
    }

    /**
     * Agrega un participante a la partida
     * @return string ID del participante
     */
    public function agregarParticipante(string $codigo, string $nombre): string
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida) {
            throw new \RuntimeException('Partida no encontrada');
        }

        if ($partida['estado'] === 'finalizada') {
            throw new \RuntimeException('La partida ya ha finalizado');
        }

        // Generar ID único para el participante
        $participanteId = uniqid('participante_', true);

        // Agregar participante
        $partida['participantes'][$participanteId] = [
            'id' => $participanteId,
            'nombre' => substr($nombre, 0, 50), // Limitar longitud
            'fechaInicio' => null,
            'fechaFin' => null,
            'tiempoTotal' => null,
            'aciertos' => 0,
            'errores' => 0,
            'enBlanco' => 0,
            'respuestas' => [],
            'estado' => 'esperando',
            'nota' => null,
        ];

        // Guardar en cache
        $this->guardarPartida($partida);

        return $participanteId;
    }

    /**
     * Inicia el juego para un participante
     */
    public function iniciarJuego(string $codigo, string $participanteId): void
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida) {
            throw new \RuntimeException('Partida no encontrada');
        }

        if (!isset($partida['participantes'][$participanteId])) {
            throw new \RuntimeException('Participante no encontrado');
        }

        $participante = &$partida['participantes'][$participanteId];
        $participante['estado'] = 'jugando';
        $participante['fechaInicio'] = time();

        // Si es el primer jugador, actualizar fechaInicioPrimerJugador y resetear TTL
        if ($partida['fechaInicioPrimerJugador'] === null) {
            $partida['fechaInicioPrimerJugador'] = time();
            $partida['estado'] = 'en_curso';
        }

        $this->guardarPartida($partida);
    }

    /**
     * Guarda una respuesta del participante
     */
    public function guardarRespuesta(string $codigo, string $participanteId, int $preguntaId, ?string $respuesta): void
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida) {
            throw new \RuntimeException('Partida no encontrada');
        }

        if (!isset($partida['participantes'][$participanteId])) {
            throw new \RuntimeException('Participante no encontrado');
        }

        $participante = &$partida['participantes'][$participanteId];

        // Buscar si ya existe respuesta para esta pregunta y actualizarla
        $encontrado = false;
        foreach ($participante['respuestas'] as &$resp) {
            if ($resp['preguntaId'] === $preguntaId) {
                $resp['respuesta'] = $respuesta;
                $encontrado = true;
                break;
            }
        }

        // Si no existe, agregarla
        if (!$encontrado) {
            $participante['respuestas'][] = [
                'preguntaId' => $preguntaId,
                'respuesta' => $respuesta,
            ];
        }

        $this->guardarPartida($partida);
    }

    /**
     * Finaliza la partida para un participante
     * @return array ['resultados' => ..., 'partidaFinalizada' => bool]
     */
    public function finalizarPartida(string $codigo, string $participanteId): array
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida) {
            throw new \RuntimeException('Partida no encontrada');
        }

        if (!isset($partida['participantes'][$participanteId])) {
            throw new \RuntimeException('Participante no encontrado');
        }

        $participante = &$partida['participantes'][$participanteId];

        if ($participante['estado'] === 'finalizado') {
            // Ya estaba finalizado, retornar resultados existentes
            return [
                'resultados' => [
                    'aciertos' => $participante['aciertos'],
                    'errores' => $participante['errores'],
                    'enBlanco' => $participante['enBlanco'],
                    'nota' => $participante['nota'],
                    'tiempoTotal' => $participante['tiempoTotal'],
                ],
                'partidaFinalizada' => $partida['estado'] === 'finalizada',
            ];
        }

        // Calcular tiempo total
        $fechaFin = time();
        $tiempoTotal = $participante['fechaInicio'] ? ($fechaFin - $participante['fechaInicio']) : 0;

        // Obtener preguntas para validar respuestas
        $preguntas = $this->preguntaRepository->findByIds($partida['preguntasIds']);
        $preguntasMap = [];
        foreach ($preguntas as $pregunta) {
            $preguntasMap[$pregunta->getId()] = $pregunta;
        }

        // Calcular aciertos, errores y en blanco
        $aciertos = 0;
        $errores = 0;
        $enBlanco = 0;

        foreach ($partida['preguntasIds'] as $preguntaId) {
            $respuesta = null;
            foreach ($participante['respuestas'] as $resp) {
                if ($resp['preguntaId'] === $preguntaId) {
                    $respuesta = $resp['respuesta'];
                    break;
                }
            }

            if ($respuesta === null || $respuesta === '') {
                $enBlanco++;
            } elseif (isset($preguntasMap[$preguntaId])) {
                $pregunta = $preguntasMap[$preguntaId];
                if ($respuesta === $pregunta->getRespuestaCorrecta()) {
                    $aciertos++;
                } else {
                    $errores++;
                }
            }
        }

        // Calcular nota
        $nota = ($partida['numPreguntas'] > 0) ? ($aciertos / $partida['numPreguntas']) * 10 : 0;

        // Actualizar participante
        $participante['fechaFin'] = $fechaFin;
        $participante['tiempoTotal'] = $tiempoTotal;
        $participante['aciertos'] = $aciertos;
        $participante['errores'] = $errores;
        $participante['enBlanco'] = $enBlanco;
        $participante['nota'] = round($nota, 2);
        $participante['estado'] = 'finalizado';

        // NO finalizar la partida automáticamente cuando todos terminan
        // La partida solo se finaliza cuando se acaba el tiempo límite
        // Esto se hace en verificarTiempoLimite()

        $this->guardarPartida($partida);

        return [
            'resultados' => [
                'aciertos' => $aciertos,
                'errores' => $errores,
                'enBlanco' => $enBlanco,
                'nota' => round($nota, 2),
                'tiempoTotal' => $tiempoTotal,
            ],
            'partidaFinalizada' => $partida['estado'] === 'finalizada',
        ];
    }

    /**
     * Verifica si el tiempo límite se ha agotado y finaliza la partida si es necesario
     * @return bool True si la partida fue finalizada por tiempo
     */
    public function verificarTiempoLimite(string $codigo): bool
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida || $partida['estado'] === 'finalizada') {
            return false;
        }

        // Si no ha empezado ningún jugador, no hay tiempo límite que verificar
        if ($partida['fechaInicioPrimerJugador'] === null) {
            return false;
        }

        $tiempoTranscurrido = time() - $partida['fechaInicioPrimerJugador'];
        $tiempoLimiteSegundos = $partida['tiempoLimite'] * 60;

        // Si se agotó el tiempo, finalizar todos los participantes
        if ($tiempoTranscurrido >= $tiempoLimiteSegundos) {
            // Obtener preguntas para validar respuestas
            $preguntas = $this->preguntaRepository->findByIds($partida['preguntasIds']);
            $preguntasMap = [];
            foreach ($preguntas as $pregunta) {
                $preguntasMap[$pregunta->getId()] = $pregunta;
            }

            // Finalizar todos los participantes que no hayan terminado
            foreach ($partida['participantes'] as $participanteId => &$participante) {
                if ($participante['estado'] !== 'finalizado') {
                    $fechaFin = time();
                    $tiempoTotal = $participante['fechaInicio'] ? ($fechaFin - $participante['fechaInicio']) : 0;

                    // Calcular resultados
                    $aciertos = 0;
                    $errores = 0;
                    $enBlanco = 0;

                    foreach ($partida['preguntasIds'] as $preguntaId) {
                        $respuesta = null;
                        foreach ($participante['respuestas'] as $resp) {
                            if ($resp['preguntaId'] === $preguntaId) {
                                $respuesta = $resp['respuesta'];
                                break;
                            }
                        }

                        if ($respuesta === null || $respuesta === '') {
                            $enBlanco++;
                        } elseif (isset($preguntasMap[$preguntaId])) {
                            $pregunta = $preguntasMap[$preguntaId];
                            if ($respuesta === $pregunta->getRespuestaCorrecta()) {
                                $aciertos++;
                            } else {
                                $errores++;
                            }
                        }
                    }

                    $nota = ($partida['numPreguntas'] > 0) ? ($aciertos / $partida['numPreguntas']) * 10 : 0;

                    $participante['fechaFin'] = $fechaFin;
                    $participante['tiempoTotal'] = $tiempoTotal;
                    $participante['aciertos'] = $aciertos;
                    $participante['errores'] = $errores;
                    $participante['enBlanco'] = $enBlanco;
                    $participante['nota'] = round($nota, 2);
                    $participante['estado'] = 'finalizado';
                }
            }

            $partida['estado'] = 'finalizada';
            $this->guardarPartida($partida);

            return true;
        }

        return false;
    }

    /**
     * Obtiene las preguntas con respuestas correctas para mostrar al finalizar
     * @return array Array de preguntas con respuestas correctas y respuestas del participante
     */
    public function obtenerPreguntasConRespuestas(string $codigo, string $participanteId): array
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida || empty($partida['preguntasIds'])) {
            return [];
        }

        if (!isset($partida['participantes'][$participanteId])) {
            return [];
        }

        $participante = $partida['participantes'][$participanteId];
        $preguntas = $this->preguntaRepository->findByIds($partida['preguntasIds']);
        $preguntasMap = [];
        foreach ($preguntas as $pregunta) {
            $preguntasMap[$pregunta->getId()] = $pregunta;
        }

        // Crear mapa de respuestas del participante
        $respuestasMap = [];
        foreach ($participante['respuestas'] as $resp) {
            $respuestasMap[$resp['preguntaId']] = $resp['respuesta'];
        }

        $resultado = [];
        foreach ($partida['preguntasIds'] as $preguntaId) {
            if (isset($preguntasMap[$preguntaId])) {
                $pregunta = $preguntasMap[$preguntaId];
                $respuestaParticipante = $respuestasMap[$preguntaId] ?? null;
                $esCorrecta = $respuestaParticipante === $pregunta->getRespuestaCorrecta();

                $resultado[] = [
                    'id' => $pregunta->getId(),
                    'texto' => $pregunta->getTexto(),
                    'opcionA' => $pregunta->getOpcionA(),
                    'opcionB' => $pregunta->getOpcionB(),
                    'opcionC' => $pregunta->getOpcionC(),
                    'opcionD' => $pregunta->getOpcionD(),
                    'respuestaCorrecta' => $pregunta->getRespuestaCorrecta(),
                    'respuestaParticipante' => $respuestaParticipante,
                    'esCorrecta' => $esCorrecta,
                    'tema' => $pregunta->getTema() ? $pregunta->getTema()->getNombre() : null,
                    'ley' => $pregunta->getLey() ? $pregunta->getLey()->getNombre() : null,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Obtiene el ranking de la partida
     * @return array Array ordenado por aciertos DESC, luego tiempoTotal ASC
     */
    public function obtenerRanking(string $codigo): array
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida) {
            return [];
        }

        // Filtrar solo participantes finalizados
        $participantesFinalizados = array_filter($partida['participantes'], function($p) {
            return $p['estado'] === 'finalizado';
        });

        // Ordenar por aciertos DESC, luego por tiempoTotal ASC
        usort($participantesFinalizados, function($a, $b) {
            if ($a['aciertos'] !== $b['aciertos']) {
                return $b['aciertos'] - $a['aciertos']; // DESC
            }
            // Si hay empate en aciertos, ordenar por tiempo (más rápido primero)
            $tiempoA = $a['tiempoTotal'] ?? PHP_INT_MAX;
            $tiempoB = $b['tiempoTotal'] ?? PHP_INT_MAX;
            return $tiempoA - $tiempoB; // ASC
        });

        return array_values($participantesFinalizados);
    }

    /**
     * Obtiene el estado de la partida
     */
    public function obtenerEstado(string $codigo): array
    {
        $partida = $this->obtenerPartida($codigo);
        if (!$partida) {
            return [];
        }

        // Verificar tiempo límite antes de retornar estado
        $this->verificarTiempoLimite($codigo);
        $partida = $this->obtenerPartida($codigo); // Re-obtener por si cambió

        $participantes = [];
        foreach ($partida['participantes'] as $participante) {
            $participantes[] = [
                'id' => $participante['id'],
                'nombre' => $participante['nombre'],
                'estado' => $participante['estado'],
            ];
        }

        return [
            'codigo' => $partida['codigo'],
            'estado' => $partida['estado'],
            'numPreguntas' => $partida['numPreguntas'],
            'tiempoLimite' => $partida['tiempoLimite'],
            'fechaInicioPrimerJugador' => $partida['fechaInicioPrimerJugador'],
            'participantes' => $participantes,
        ];
    }

    /**
     * Genera un código único de 8 caracteres hexadecimales
     */
    private function generarCodigoUnico(): string
    {
        do {
            $codigo = bin2hex(random_bytes(4));
            $partida = $this->obtenerPartida($codigo);
        } while ($partida !== null); // Asegurar que sea único

        return $codigo;
    }

    /**
     * Guarda la partida en cache con TTL apropiado
     */
    private function guardarPartida(array $partida): void
    {
        $cacheKey = self::CACHE_PREFIX . $partida['codigo'];
        $item = $this->cache->getItem($cacheKey);
        
        // Calcular TTL: tiempoLimite + 10 minutos (mínimo 30 minutos)
        $ttl = ($partida['tiempoLimite'] * 60) + 600; // tiempoLimite en segundos + 10 minutos
        $ttl = max($ttl, self::TTL_MINIMO);

        // Si ya empezó el primer jugador, calcular desde esa fecha
        if ($partida['fechaInicioPrimerJugador'] !== null) {
            $tiempoTranscurrido = time() - $partida['fechaInicioPrimerJugador'];
            $ttlRestante = $ttl - $tiempoTranscurrido;
            $ttl = max($ttlRestante, 600); // Mínimo 10 minutos restantes
        }

        $item->set($partida);
        $item->expiresAfter($ttl);
        $this->cache->save($item);
    }
}

