<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class InformeUsoPlataformaService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(int $mes, int $anio): array
    {
        $inicio = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $anio, $mes));
        $fin = $inicio->modify('last day of this month')->setTime(23, 59, 59);
        $inicioSerie = $inicio->modify('-5 months');
        $anterior = $inicio->modify('-1 month');

        $serie = $this->serie($inicioSerie, $fin);
        $clave = $inicio->format('Y-m');
        $claveAnterior = $anterior->format('Y-m');
        $actual = $serie[$clave];
        $previo = $serie[$claveAnterior];

        $ahora = new \DateTimeImmutable();

        return [
            'mes' => $mes,
            'anio' => $anio,
            'nombreMes' => $this->nombreMes($mes),
            'nombreMesAnterior' => $this->nombreMes((int) $anterior->format('n')).' '.$anterior->format('Y'),
            'enCurso' => $inicio->format('Y-m') === $ahora->format('Y-m'),
            'generadoEn' => $ahora,
            'cuentas' => $this->cuentas($inicio, $fin),
            'actual' => $actual,
            'anterior' => $previo,
            'variacion' => $this->variaciones($actual, $previo),
            'evolucion' => array_values($serie),
            'dificultades' => $this->dificultades($inicio, $fin),
            'juegos' => $this->juegos($inicio, $fin),
            'semanales' => $this->semanalesPublicados($inicio, $fin),
            'grupos' => $this->grupos($inicio, $fin),
            'alumnosSinGrupo' => $this->alumnosSinGrupo(),
            'topAlumnos' => $this->topAlumnos($inicio, $fin),
        ];
    }

    /**
     * @return array<string, array<string, int|float|string|null>>
     */
    private function serie(\DateTimeImmutable $desde, \DateTimeImmutable $hasta): array
    {
        $filas = [];
        $cursor = $desde;
        while ($cursor <= $hasta) {
            $clave = $cursor->format('Y-m');
            $filas[$clave] = [
                'clave' => $clave,
                'etiqueta' => $this->nombreMes((int) $cursor->format('n')).' '.$cursor->format('Y'),
                'participantes' => 0,
                'diasConActividad' => 0,
                'examenes' => 0,
                'alumnosExamen' => 0,
                'notaMedia' => null,
                'generales' => 0,
                'municipales' => 0,
                'convocatoria' => 0,
                'semanales' => 0,
                'alumnosSemanal' => 0,
                'enPdf' => 0,
                'partidas' => 0,
                'alumnosPartida' => 0,
                'flashcards' => 0,
                'alumnosFlashcard' => 0,
                'mensajes' => 0,
                'alumnosMensaje' => 0,
                'tareasCompletadas' => 0,
                'alumnosTarea' => 0,
                'tareasAsignadas' => 0,
                'contactos' => 0,
                'semanalesAbiertos' => 0,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        $params = $this->params([
            'desde' => $desde->format('Y-m-d H:i:s'),
            'hasta' => $hasta->format('Y-m-d H:i:s'),
        ]);
        $alumno = $this->filtroAlumno('u');

        $examenes = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(e.fecha, '%Y-%m') AS mes,
                    COUNT(*) AS total,
                    COUNT(DISTINCT e.usuario_id) AS alumnos,
                    ROUND(AVG(e.nota), 2) AS nota,
                    SUM(e.examen_semanal_id IS NULL AND e.municipio_id IS NULL AND e.convocatoria_id IS NULL) AS generales,
                    SUM(e.examen_semanal_id IS NULL AND e.municipio_id IS NOT NULL) AS municipales,
                    SUM(e.examen_semanal_id IS NULL AND e.municipio_id IS NULL AND e.convocatoria_id IS NOT NULL) AS convocatoria,
                    SUM(e.examen_semanal_id IS NOT NULL) AS semanales,
                    COUNT(DISTINCT CASE WHEN e.examen_semanal_id IS NOT NULL THEN e.usuario_id END) AS alumnos_semanal,
                    SUM(e.realizado_en_pdf = 1) AS en_pdf
             FROM examen e
             INNER JOIN `user` u ON u.id = e.usuario_id
             WHERE e.fecha >= :desde AND e.fecha <= :hasta AND $alumno
             GROUP BY mes",
            $params
        );
        foreach ($examenes as $fila) {
            if (!isset($filas[$fila['mes']])) {
                continue;
            }
            $filas[$fila['mes']]['examenes'] = (int) $fila['total'];
            $filas[$fila['mes']]['alumnosExamen'] = (int) $fila['alumnos'];
            $filas[$fila['mes']]['notaMedia'] = $fila['nota'] !== null ? (float) $fila['nota'] : null;
            $filas[$fila['mes']]['generales'] = (int) $fila['generales'];
            $filas[$fila['mes']]['municipales'] = (int) $fila['municipales'];
            $filas[$fila['mes']]['convocatoria'] = (int) $fila['convocatoria'];
            $filas[$fila['mes']]['semanales'] = (int) $fila['semanales'];
            $filas[$fila['mes']]['alumnosSemanal'] = (int) $fila['alumnos_semanal'];
            $filas[$fila['mes']]['enPdf'] = (int) $fila['en_pdf'];
        }

        $this->volcarConteo($filas, $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(p.fecha_creacion, '%Y-%m') AS mes,
                    COUNT(*) AS total,
                    COUNT(DISTINCT p.usuario_id) AS alumnos
             FROM partida_juego p
             INNER JOIN `user` u ON u.id = p.usuario_id
             WHERE p.fecha_creacion >= :desde AND p.fecha_creacion <= :hasta AND $alumno
             GROUP BY mes",
            $params
        ), 'partidas', 'alumnosPartida');

        $this->volcarConteo($filas, $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(f.creado_en, '%Y-%m') AS mes,
                    COUNT(*) AS total,
                    COUNT(DISTINCT f.usuario_id) AS alumnos
             FROM flashcard f
             INNER JOIN `user` u ON u.id = f.usuario_id
             WHERE f.creado_en >= :desde AND f.creado_en <= :hasta AND $alumno
             GROUP BY mes",
            $params
        ), 'flashcards', 'alumnosFlashcard');

        $this->volcarConteo($filas, $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(m.fecha_envio, '%Y-%m') AS mes,
                    COUNT(*) AS total,
                    COUNT(DISTINCT m.remitente_id) AS alumnos
             FROM mensaje m
             INNER JOIN `user` u ON u.id = m.remitente_id
             WHERE m.fecha_envio >= :desde AND m.fecha_envio <= :hasta AND $alumno
             GROUP BY mes",
            $params
        ), 'mensajes', 'alumnosMensaje');

        $this->volcarConteo($filas, $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(ta.fecha_completada, '%Y-%m') AS mes,
                    COUNT(*) AS total,
                    COUNT(DISTINCT ta.usuario_id) AS alumnos
             FROM tarea_asignada ta
             INNER JOIN `user` u ON u.id = ta.usuario_id
             WHERE ta.fecha_completada IS NOT NULL
               AND ta.fecha_completada >= :desde AND ta.fecha_completada <= :hasta
               AND $alumno
             GROUP BY mes",
            $params
        ), 'tareasCompletadas', 'alumnosTarea');

        $asignadas = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(ta.fecha_asignacion, '%Y-%m') AS mes, COUNT(*) AS total
             FROM tarea_asignada ta
             INNER JOIN `user` u ON u.id = ta.usuario_id
             WHERE ta.fecha_asignacion >= :desde AND ta.fecha_asignacion <= :hasta AND $alumno
             GROUP BY mes",
            $params
        );
        foreach ($asignadas as $fila) {
            if (isset($filas[$fila['mes']])) {
                $filas[$fila['mes']]['tareasAsignadas'] = (int) $fila['total'];
            }
        }

        $contactos = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') AS mes, COUNT(*) AS total
             FROM mensaje_contacto
             WHERE fecha_creacion >= :desde AND fecha_creacion <= :hasta
             GROUP BY mes",
            [
                'desde' => $params['desde'],
                'hasta' => $params['hasta'],
            ]
        );
        foreach ($contactos as $fila) {
            if (isset($filas[$fila['mes']])) {
                $filas[$fila['mes']]['contactos'] = (int) $fila['total'];
            }
        }

        $abiertos = $this->connection->fetchAllAssociative(
            "SELECT DATE_FORMAT(fecha_apertura, '%Y-%m') AS mes, COUNT(*) AS total
             FROM examen_semanal
             WHERE fecha_apertura >= :desde AND fecha_apertura <= :hasta
             GROUP BY mes",
            [
                'desde' => $params['desde'],
                'hasta' => $params['hasta'],
            ]
        );
        foreach ($abiertos as $fila) {
            if (isset($filas[$fila['mes']])) {
                $filas[$fila['mes']]['semanalesAbiertos'] = (int) $fila['total'];
            }
        }

        $participantes = $this->connection->fetchAllAssociative(
            "SELECT mes, COUNT(DISTINCT usuario_id) AS total
             FROM (
                 SELECT DATE_FORMAT(e.fecha, '%Y-%m') AS mes, e.usuario_id
                 FROM examen e
                 INNER JOIN `user` u ON u.id = e.usuario_id
                 WHERE e.fecha >= :desde AND e.fecha <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(p.fecha_creacion, '%Y-%m'), p.usuario_id
                 FROM partida_juego p
                 INNER JOIN `user` u ON u.id = p.usuario_id
                 WHERE p.fecha_creacion >= :desde AND p.fecha_creacion <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(f.creado_en, '%Y-%m'), f.usuario_id
                 FROM flashcard f
                 INNER JOIN `user` u ON u.id = f.usuario_id
                 WHERE f.creado_en >= :desde AND f.creado_en <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(m.fecha_envio, '%Y-%m'), m.remitente_id
                 FROM mensaje m
                 INNER JOIN `user` u ON u.id = m.remitente_id
                 WHERE m.fecha_envio >= :desde AND m.fecha_envio <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(ta.fecha_completada, '%Y-%m'), ta.usuario_id
                 FROM tarea_asignada ta
                 INNER JOIN `user` u ON u.id = ta.usuario_id
                 WHERE ta.fecha_completada IS NOT NULL
                   AND ta.fecha_completada >= :desde AND ta.fecha_completada <= :hasta
                   AND $alumno
             ) actividad
             GROUP BY mes",
            $params
        );
        foreach ($participantes as $fila) {
            if (isset($filas[$fila['mes']])) {
                $filas[$fila['mes']]['participantes'] = (int) $fila['total'];
            }
        }

        $dias = $this->connection->fetchAllAssociative(
            "SELECT mes, COUNT(DISTINCT dia) AS total
             FROM (
                 SELECT DATE_FORMAT(e.fecha, '%Y-%m') AS mes, DATE(e.fecha) AS dia
                 FROM examen e
                 INNER JOIN `user` u ON u.id = e.usuario_id
                 WHERE e.fecha >= :desde AND e.fecha <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(p.fecha_creacion, '%Y-%m'), DATE(p.fecha_creacion)
                 FROM partida_juego p
                 INNER JOIN `user` u ON u.id = p.usuario_id
                 WHERE p.fecha_creacion >= :desde AND p.fecha_creacion <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(f.creado_en, '%Y-%m'), DATE(f.creado_en)
                 FROM flashcard f
                 INNER JOIN `user` u ON u.id = f.usuario_id
                 WHERE f.creado_en >= :desde AND f.creado_en <= :hasta AND $alumno
                 UNION
                 SELECT DATE_FORMAT(m.fecha_envio, '%Y-%m'), DATE(m.fecha_envio)
                 FROM mensaje m
                 INNER JOIN `user` u ON u.id = m.remitente_id
                 WHERE m.fecha_envio >= :desde AND m.fecha_envio <= :hasta AND $alumno
             ) actividad
             GROUP BY mes",
            $params
        );
        foreach ($dias as $fila) {
            if (isset($filas[$fila['mes']])) {
                $filas[$fila['mes']]['diasConActividad'] = (int) $fila['total'];
            }
        }

        return $filas;
    }

    /**
     * @param array<string, array<string, int|float|string|null>> $filas
     * @param array<int, array<string, mixed>> $conteos
     */
    private function volcarConteo(array &$filas, array $conteos, string $campoTotal, string $campoAlumnos): void
    {
        foreach ($conteos as $fila) {
            if (!isset($filas[$fila['mes']])) {
                continue;
            }
            $filas[$fila['mes']][$campoTotal] = (int) $fila['total'];
            $filas[$fila['mes']][$campoAlumnos] = (int) $fila['alumnos'];
        }
    }

    /**
     * @return array<string, int>
     */
    private function cuentas(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $ahora = new \DateTimeImmutable();
        $fila = $this->connection->fetchAssociative(
            "SELECT
                SUM(activo = 1) AS activos,
                SUM(activo = 0) AS inactivos,
                SUM(activo = 1 AND ultimo_login IS NULL) AS sin_login,
                SUM(activo = 1 AND ultimo_login >= :hace7) AS acceso7,
                SUM(activo = 1 AND ultimo_login >= :hace30) AS acceso30,
                SUM(ultimo_login >= :desde AND ultimo_login <= :hasta) AS ultimo_en_mes
             FROM `user` u
             WHERE ".$this->filtroAlumno('u'),
            $this->params([
                'hace7' => $ahora->modify('-7 days')->format('Y-m-d H:i:s'),
                'hace30' => $ahora->modify('-30 days')->format('Y-m-d H:i:s'),
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ])
        ) ?: [];

        $personal = $this->connection->fetchAssociative(
            "SELECT
                SUM(roles LIKE :rolAdmin) AS administradores,
                SUM(roles LIKE :rolProfesor AND roles NOT LIKE :rolAdmin) AS profesores
             FROM `user`
             WHERE eliminado = 0 AND suprimido = 0",
            [
                'rolProfesor' => '%"ROLE_PROFESOR"%',
                'rolAdmin' => '%"ROLE_ADMIN"%',
            ]
        ) ?: [];

        return [
            'alumnosActivos' => (int) ($fila['activos'] ?? 0),
            'alumnosInactivos' => (int) ($fila['inactivos'] ?? 0),
            'sinLogin' => (int) ($fila['sin_login'] ?? 0),
            'acceso7dias' => (int) ($fila['acceso7'] ?? 0),
            'acceso30dias' => (int) ($fila['acceso30'] ?? 0),
            'ultimoAccesoEnElMes' => (int) ($fila['ultimo_en_mes'] ?? 0),
            'profesores' => (int) ($personal['profesores'] ?? 0),
            'administradores' => (int) ($personal['administradores'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{dificultad: string, total: int, nota: float|null}>
     */
    private function dificultades(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $filas = $this->connection->fetchAllAssociative(
            "SELECT e.dificultad AS dificultad, COUNT(*) AS total, ROUND(AVG(e.nota), 2) AS nota
             FROM examen e
             INNER JOIN `user` u ON u.id = e.usuario_id
             WHERE e.fecha >= :desde AND e.fecha <= :hasta AND ".$this->filtroAlumno('u')."
             GROUP BY e.dificultad
             ORDER BY total DESC",
            $this->params([
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ])
        );

        return array_map(function (array $fila): array {
            return [
                'dificultad' => $this->etiquetaDificultad(is_string($fila['dificultad']) ? $fila['dificultad'] : null),
                'total' => (int) $fila['total'],
                'nota' => $fila['nota'] !== null ? (float) $fila['nota'] : null,
            ];
        }, $filas);
    }

    /**
     * @return array<int, array{nombre: string, partidas: int, alumnos: int}>
     */
    private function juegos(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $filas = $this->connection->fetchAllAssociative(
            "SELECT p.tipo_juego AS tipo, COUNT(*) AS total, COUNT(DISTINCT p.usuario_id) AS alumnos
             FROM partida_juego p
             INNER JOIN `user` u ON u.id = p.usuario_id
             WHERE p.fecha_creacion >= :desde AND p.fecha_creacion <= :hasta AND ".$this->filtroAlumno('u')."
             GROUP BY p.tipo_juego
             ORDER BY total DESC",
            $this->params([
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ])
        );

        return array_map(function (array $fila): array {
            return [
                'nombre' => $this->etiquetaJuego(is_string($fila['tipo']) ? $fila['tipo'] : ''),
                'partidas' => (int) $fila['total'],
                'alumnos' => (int) $fila['alumnos'],
            ];
        }, $filas);
    }

    /**
     * @return array<int, array{nombre: string, apertura: string, cierre: string, realizados: int, alumnos: int}>
     */
    private function semanalesPublicados(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $filas = $this->connection->fetchAllAssociative(
            "SELECT es.nombre,
                    es.fecha_apertura,
                    es.fecha_cierre,
                    COUNT(u.id) AS realizados,
                    COUNT(DISTINCT u.id) AS alumnos
             FROM examen_semanal es
             LEFT JOIN examen e ON e.examen_semanal_id = es.id
             LEFT JOIN `user` u ON u.id = e.usuario_id
                AND u.eliminado = 0 AND u.suprimido = 0
                AND u.roles NOT LIKE :rolProfesor AND u.roles NOT LIKE :rolAdmin
             WHERE es.fecha_apertura >= :desde AND es.fecha_apertura <= :hasta
             GROUP BY es.id, es.nombre, es.fecha_apertura, es.fecha_cierre
             ORDER BY es.fecha_apertura ASC",
            $this->params([
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ])
        );

        return array_map(function (array $fila): array {
            return [
                'nombre' => (string) $fila['nombre'],
                'apertura' => $this->fechaCorta($fila['fecha_apertura']),
                'cierre' => $this->fechaCorta($fila['fecha_cierre']),
                'realizados' => (int) $fila['realizados'],
                'alumnos' => (int) $fila['alumnos'],
            ];
        }, $filas);
    }

    /**
     * @return array<int, array{nombre: string, alumnos: int, conExamen: int, examenes: int, conPartida: int, partidas: int}>
     */
    private function grupos(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $filas = $this->connection->fetchAllAssociative(
            "SELECT g.nombre,
                    COUNT(DISTINCT u.id) AS alumnos,
                    COUNT(DISTINCT ex.usuario_id) AS con_examen,
                    COALESCE(SUM(ex.total), 0) AS examenes,
                    COUNT(DISTINCT pj.usuario_id) AS con_partida,
                    COALESCE(SUM(pj.total), 0) AS partidas
             FROM grupo g
             INNER JOIN grupo_user gu ON gu.grupo_id = g.id
             INNER JOIN `user` u ON u.id = gu.user_id AND ".$this->filtroAlumno('u')."
             LEFT JOIN (
                 SELECT usuario_id, COUNT(*) AS total
                 FROM examen
                 WHERE fecha >= :desde AND fecha <= :hasta
                 GROUP BY usuario_id
             ) ex ON ex.usuario_id = u.id
             LEFT JOIN (
                 SELECT usuario_id, COUNT(*) AS total
                 FROM partida_juego
                 WHERE fecha_creacion >= :desde AND fecha_creacion <= :hasta
                 GROUP BY usuario_id
             ) pj ON pj.usuario_id = u.id
             GROUP BY g.id, g.nombre
             ORDER BY g.nombre ASC",
            $this->params([
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ])
        );

        return array_map(static function (array $fila): array {
            return [
                'nombre' => (string) $fila['nombre'],
                'alumnos' => (int) $fila['alumnos'],
                'conExamen' => (int) $fila['con_examen'],
                'examenes' => (int) $fila['examenes'],
                'conPartida' => (int) $fila['con_partida'],
                'partidas' => (int) $fila['partidas'],
            ];
        }, $filas);
    }

    private function alumnosSinGrupo(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*)
             FROM `user` u
             WHERE u.activo = 1 AND ".$this->filtroAlumno('u')."
               AND NOT EXISTS (SELECT 1 FROM grupo_user gu WHERE gu.user_id = u.id)",
            $this->params()
        );
    }

    /**
     * @return array<int, array{nombre: string, usuario: string, examenes: int, semanales: int, partidas: int, flashcards: int}>
     */
    private function topAlumnos(\DateTimeImmutable $inicio, \DateTimeImmutable $fin): array
    {
        $filas = $this->connection->fetchAllAssociative(
            "SELECT COALESCE(NULLIF(TRIM(u.nombre), ''), u.username) AS nombre,
                    u.username AS usuario,
                    COALESCE(ex.total, 0) AS examenes,
                    COALESCE(ex.semanales, 0) AS semanales,
                    COALESCE(pj.total, 0) AS partidas,
                    COALESCE(fc.total, 0) AS flashcards
             FROM `user` u
             LEFT JOIN (
                 SELECT usuario_id,
                        COUNT(*) AS total,
                        SUM(examen_semanal_id IS NOT NULL) AS semanales
                 FROM examen
                 WHERE fecha >= :desde AND fecha <= :hasta
                 GROUP BY usuario_id
             ) ex ON ex.usuario_id = u.id
             LEFT JOIN (
                 SELECT usuario_id, COUNT(*) AS total
                 FROM partida_juego
                 WHERE fecha_creacion >= :desde AND fecha_creacion <= :hasta
                 GROUP BY usuario_id
             ) pj ON pj.usuario_id = u.id
             LEFT JOIN (
                 SELECT usuario_id, COUNT(*) AS total
                 FROM flashcard
                 WHERE creado_en >= :desde AND creado_en <= :hasta
                 GROUP BY usuario_id
             ) fc ON fc.usuario_id = u.id
             WHERE ".$this->filtroAlumno('u')."
               AND (COALESCE(ex.total, 0) + COALESCE(pj.total, 0) + COALESCE(fc.total, 0)) > 0
             ORDER BY (COALESCE(ex.total, 0) + COALESCE(pj.total, 0) + COALESCE(fc.total, 0)) DESC, nombre ASC
             LIMIT 20",
            $this->params([
                'desde' => $inicio->format('Y-m-d H:i:s'),
                'hasta' => $fin->format('Y-m-d H:i:s'),
            ])
        );

        return array_map(static function (array $fila): array {
            return [
                'nombre' => (string) $fila['nombre'],
                'usuario' => (string) $fila['usuario'],
                'examenes' => (int) $fila['examenes'],
                'semanales' => (int) $fila['semanales'],
                'partidas' => (int) $fila['partidas'],
                'flashcards' => (int) $fila['flashcards'],
            ];
        }, $filas);
    }

    /**
     * @param array<string, int|float|string|null> $actual
     * @param array<string, int|float|string|null> $anterior
     * @return array<string, array{diff: float, pct: float|null}>
     */
    private function variaciones(array $actual, array $anterior): array
    {
        $claves = [
            'participantes',
            'diasConActividad',
            'examenes',
            'alumnosExamen',
            'semanales',
            'alumnosSemanal',
            'partidas',
            'alumnosPartida',
            'flashcards',
            'mensajes',
            'tareasCompletadas',
            'notaMedia',
        ];
        $resultado = [];
        foreach ($claves as $clave) {
            $resultado[$clave] = $this->variacion((float) ($actual[$clave] ?? 0), (float) ($anterior[$clave] ?? 0));
        }

        return $resultado;
    }

    /**
     * @return array{diff: float, pct: float|null}
     */
    private function variacion(float $actual, float $anterior): array
    {
        $diff = round($actual - $anterior, 2);
        if ($anterior == 0.0) {
            return ['diff' => $diff, 'pct' => null];
        }

        return [
            'diff' => $diff,
            'pct' => round(($diff / $anterior) * 100, 1),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function params(array $extra = []): array
    {
        return $extra + [
            'rolProfesor' => '%"ROLE_PROFESOR"%',
            'rolAdmin' => '%"ROLE_ADMIN"%',
        ];
    }

    private function filtroAlumno(string $alias): string
    {
        return sprintf(
            '%1$s.eliminado = 0 AND %1$s.suprimido = 0 AND %1$s.roles NOT LIKE :rolProfesor AND %1$s.roles NOT LIKE :rolAdmin',
            $alias
        );
    }

    private function nombreMes(int $mes): string
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ][$mes] ?? 'Mes';
    }

    private function etiquetaDificultad(?string $dificultad): string
    {
        return match ($dificultad) {
            'facil' => 'Fácil',
            'moderada' => 'Moderada',
            'dificil' => 'Difícil',
            null, '' => 'Sin indicar',
            default => $dificultad,
        };
    }

    private function etiquetaJuego(string $tipo): string
    {
        return match ($tipo) {
            'adivina_numero_articulo' => '¿Qué número tiene el artículo?',
            'adivina_nombre_articulo' => '¿Cómo se llama el artículo?',
            'completa_fecha_ley' => '¿Cuándo se publicó la ley?',
            'completa_texto_legal' => 'Completa el artículo',
            'articulo_correcto' => 'El artículo correcto',
            'pena_articulo' => '¿Multa o cárcel?',
            'tablas_multiplicar' => 'Tablas de multiplicar',
            default => $tipo !== '' ? $tipo : 'Juego',
        };
    }

    private function fechaCorta(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }
        if (!is_string($valor) || $valor === '') {
            return '';
        }
        $fecha = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($valor, 0, 19));

        return $fecha instanceof \DateTimeImmutable ? $fecha->format('d/m/Y') : $valor;
    }
}
