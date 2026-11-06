<?php

namespace App\Controller;

use App\Entity\PartidaJuego;
use App\Repository\ArticuloRepository;
use App\Repository\LeyRepository;
use App\Repository\PreguntaRepository;
use App\Repository\TemaRepository;
use App\Service\PenaArticuloParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class JuegoController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemaRepository $temaRepository,
    ) {
    }

    #[Route('/juegos', name: 'app_juego_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_juego_adivina_numero_articulo');
    }

    #[Route('/juegos/adivina-numero-articulo', name: 'app_juego_adivina_numero_articulo')]
    #[IsGranted('ROLE_USER')]
    public function adivinaNumeroArticulo(): Response
    {
        return $this->render('juego/adivina_numero_articulo.html.twig', $this->temasViewData());
    }

    #[Route('/juegos/adivina-nombre-articulo', name: 'app_juego_adivina_nombre_articulo')]
    #[IsGranted('ROLE_USER')]
    public function adivinaNombreArticulo(): Response
    {
        return $this->render('juego/adivina_nombre_articulo.html.twig', $this->temasViewData());
    }

    #[Route('/juegos/completa-fecha-ley', name: 'app_juego_completa_fecha_ley')]
    #[IsGranted('ROLE_USER')]
    public function completaFechaLey(): Response
    {
        return $this->render('juego/completa_fecha_ley.html.twig', $this->temasViewData());
    }

    #[Route('/juegos/completa-texto-legal', name: 'app_juego_completa_texto_legal')]
    #[IsGranted('ROLE_USER')]
    public function completaTextoLegal(): Response
    {
        return $this->render('juego/completa_texto_legal.html.twig', $this->temasViewData());
    }

    #[Route('/juegos/articulo-correcto', name: 'app_juego_articulo_correcto')]
    #[IsGranted('ROLE_USER')]
    public function articuloCorrecto(): Response
    {
        return $this->render('juego/articulo_correcto.html.twig', $this->temasViewData());
    }

    #[Route('/juegos/multa-o-carcel', name: 'app_juego_pena_articulo')]
    #[IsGranted('ROLE_USER')]
    public function penaArticulo(): Response
    {
        return $this->render('juego/pena_articulo.html.twig');
    }

    /**
     * Los temas 25, 26 y 27 comparten el Código Penal. Sus artículos son los de
     * patrimonio (234-269), seguridad vial (379-385) y derechos fundamentales (510-521).
     */
    private function articuloDeTemasPena(?int $numero): bool
    {
        if ($numero === null) {
            return false;
        }

        return ($numero >= 234 && $numero <= 269)
            || ($numero >= 379 && $numero <= 385)
            || ($numero >= 510 && $numero <= 521);
    }

    /**
     * @return list<int>
     */
    private function idsTemasPena(): array
    {
        $ids = [];
        foreach ($this->temaRepository->findActivosOrderedByNombre() as $tema) {
            if (preg_match('/^Tema 2[567]\b/u', (string) $tema->getNombre())) {
                $ids[] = (int) $tema->getId();
            }
        }

        return $ids;
    }

    private function temasViewData(): array
    {
        return [
            'temas' => $this->temaRepository->findActivosOrderedByNombre(),
        ];
    }

    /**
     * @return int[]
     */
    private function parseTemaIds(Request $request): array
    {
        $temas = $request->query->all('temas');
        if ($temas === []) {
            $raw = $request->query->get('temas');
            if (is_string($raw) && $raw !== '') {
                $temas = explode(',', $raw);
            }
        }

        if (!is_array($temas)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $temas),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function requireTemaIds(Request $request): array|JsonResponse
    {
        $temaIds = $this->parseTemaIds($request);
        if ($temaIds === []) {
            return new JsonResponse(['error' => 'Debes seleccionar al menos un tema'], 400);
        }

        return $temaIds;
    }

    #[Route('/api/juegos/guardar-partida', name: 'app_juego_api_guardar_partida', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function guardarPartidaApi(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tipoJuego = $data['tipoJuego'] ?? null;

        $tiposValidos = [
            'adivina_numero_articulo',
            'adivina_nombre_articulo',
            'completa_fecha_ley',
            'completa_texto_legal',
            'articulo_correcto',
            'pena_articulo',
        ];

        if (!$tipoJuego || !in_array($tipoJuego, $tiposValidos)) {
            return new JsonResponse(['error' => 'Tipo de juego inválido'], 400);
        }

        $this->guardarPartida($tipoJuego);

        return new JsonResponse(['success' => true]);
    }

    private function guardarPartida(string $tipoJuego): void
    {
        $user = $this->getUser();
        if (!$user) {
            return;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_PROFESOR', $roles) || in_array('ROLE_ADMIN', $roles)) {
            return;
        }

        try {
            $partida = new PartidaJuego();
            $partida->setUsuario($user);
            $partida->setTipoJuego($tipoJuego);

            $this->entityManager->persist($partida);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            if ($this->entityManager->isOpen()) {
                $this->entityManager->clear();
            }
        }
    }

    #[Route('/api/juegos/pregunta-aleatoria', name: 'app_juego_api_pregunta_aleatoria')]
    public function getPreguntaAleatoria(PreguntaRepository $preguntaRepository): JsonResponse
    {
        $pregunta = $preguntaRepository->findAleatoriaActiva();

        if (!$pregunta) {
            return new JsonResponse(['error' => 'No hay preguntas disponibles'], 404);
        }

        if (!$pregunta->getTexto() || trim($pregunta->getTexto()) === '') {
            $pregunta = $preguntaRepository->findAleatoriaActiva();
            if (!$pregunta || !$pregunta->getTexto() || trim($pregunta->getTexto()) === '') {
                return new JsonResponse(['error' => 'No hay preguntas con texto disponible'], 404);
            }
        }

        $respuestaCorrecta = '';
        switch ($pregunta->getRespuestaCorrecta()) {
            case 'A':
                $respuestaCorrecta = $pregunta->getOpcionA() ?? '';
                break;
            case 'B':
                $respuestaCorrecta = $pregunta->getOpcionB() ?? '';
                break;
            case 'C':
                $respuestaCorrecta = $pregunta->getOpcionC() ?? '';
                break;
            case 'D':
                $respuestaCorrecta = $pregunta->getOpcionD() ?? '';
                break;
        }

        if (empty($respuestaCorrecta) || trim($respuestaCorrecta) === '') {
            return new JsonResponse(['error' => 'La pregunta no tiene respuesta correcta válida'], 404);
        }

        return new JsonResponse([
            'id' => $pregunta->getId(),
            'texto' => $pregunta->getTexto() ?? '',
            'opcionA' => $pregunta->getOpcionA() ?? '',
            'opcionB' => $pregunta->getOpcionB() ?? '',
            'opcionC' => $pregunta->getOpcionC() ?? '',
            'opcionD' => $pregunta->getOpcionD() ?? '',
            'respuestaCorrecta' => $respuestaCorrecta,
            'letraCorrecta' => $pregunta->getRespuestaCorrecta(),
            'ley' => [
                'id' => $pregunta->getLey()->getId(),
                'nombre' => $pregunta->getLey()->getNombre(),
            ],
        ]);
    }

    #[Route('/api/juegos/preguntas-lote', name: 'app_juego_api_preguntas_lote')]
    public function getPreguntasLote(Request $request, PreguntaRepository $preguntaRepository): JsonResponse
    {
        $temaIds = $this->requireTemaIds($request);
        if ($temaIds instanceof JsonResponse) {
            return $temaIds;
        }

        $preguntas = $preguntaRepository->findAleatoriasActivasPorDificultad(20, null, $temaIds);

        if (empty($preguntas)) {
            return new JsonResponse(['error' => 'No hay preguntas disponibles para los temas seleccionados'], 404);
        }

        $resultado = [];
        foreach ($preguntas as $pregunta) {
            $respuestaCorrecta = '';
            switch ($pregunta->getRespuestaCorrecta()) {
                case 'A':
                    $respuestaCorrecta = $pregunta->getOpcionA() ?? '';
                    break;
                case 'B':
                    $respuestaCorrecta = $pregunta->getOpcionB() ?? '';
                    break;
                case 'C':
                    $respuestaCorrecta = $pregunta->getOpcionC() ?? '';
                    break;
                case 'D':
                    $respuestaCorrecta = $pregunta->getOpcionD() ?? '';
                    break;
            }

            if (empty($respuestaCorrecta) || trim($respuestaCorrecta) === '') {
                continue;
            }

            $resultado[] = [
                'id' => $pregunta->getId(),
                'texto' => $pregunta->getTexto() ?? '',
                'opcionA' => $pregunta->getOpcionA() ?? '',
                'opcionB' => $pregunta->getOpcionB() ?? '',
                'opcionC' => $pregunta->getOpcionC() ?? '',
                'opcionD' => $pregunta->getOpcionD() ?? '',
                'respuestaCorrecta' => $respuestaCorrecta,
                'letraCorrecta' => $pregunta->getRespuestaCorrecta(),
                'ley' => [
                    'id' => $pregunta->getLey()->getId(),
                    'nombre' => $pregunta->getLey()->getNombre(),
                ],
            ];
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No hay preguntas con respuestas válidas'], 404);
        }

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/articulos-lote', name: 'app_juego_api_articulos_lote')]
    public function getArticulosLote(Request $request, ArticuloRepository $articuloRepository): JsonResponse
    {
        $temaIds = $this->requireTemaIds($request);
        if ($temaIds instanceof JsonResponse) {
            return $temaIds;
        }

        $articulos = $articuloRepository->findAleatoriosConNombre(20, $temaIds);

        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos disponibles para los temas seleccionados'], 404);
        }

        $resultado = [];
        foreach ($articulos as $articulo) {
            $resultado[] = [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'sufijo' => $articulo->getSufijo(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
                'ley' => [
                    'id' => $articulo->getLey()->getId(),
                    'nombre' => $articulo->getLey()->getNombre(),
                ],
            ];
        }

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/penas-lote', name: 'app_juego_api_penas_lote')]
    #[IsGranted('ROLE_USER')]
    public function getPenasLote(Request $request, ArticuloRepository $articuloRepository, PenaArticuloParser $parser): JsonResponse
    {
        $temaIds = $this->idsTemasPena();
        if ($temaIds === []) {
            return new JsonResponse(['error' => 'No están disponibles los temas 25, 26 y 27.'], 404);
        }

        $articulos = $articuloRepository->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->addSelect('l')
            ->innerJoin('l.temas', 't')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->andWhere('t.id IN (:temaIds)')
            ->andWhere('a.explicacion IS NOT NULL')
            ->andWhere('a.explicacion != :vacio')
            ->setParameter('activo', true)
            ->setParameter('temaIds', $temaIds)
            ->setParameter('vacio', '')
            ->getQuery()
            ->getResult();

        $resultado = [];
        $vistos = [];
        foreach ($articulos as $articulo) {
            if (isset($vistos[$articulo->getId()]) || !$this->articuloDeTemasPena($articulo->getNumero())) {
                continue;
            }
            $vistos[$articulo->getId()] = true;
            $pena = $parser->extraer($articulo->getExplicacion());
            if ($pena === null) {
                continue;
            }

            $resultado[] = [
                'id' => $articulo->getId(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'titulo' => $parser->titulo($articulo->getNombre(), $articulo->getExplicacion()),
                'ley' => [
                    'nombre' => $articulo->getLey()?->getNombre() ?? '',
                ],
                'tipos' => $pena['tipos'],
                'tramos' => $pena['tramos'],
                'opciones' => $pena['opciones'],
            ];
        }

        shuffle($resultado);
        $resultado = array_slice($resultado, 0, 20);

        if ($resultado === []) {
            return new JsonResponse(['error' => 'No hay artículos con pena en los temas 25, 26 y 27.'], 404);
        }

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/leyes-con-fecha', name: 'app_juego_api_leyes_con_fecha')]
    public function getLeyesConFecha(Request $request, LeyRepository $leyRepository): JsonResponse
    {
        $temaIds = $this->requireTemaIds($request);
        if ($temaIds instanceof JsonResponse) {
            return $temaIds;
        }

        $leyes = $leyRepository->findLeyesConFormatoFecha($temaIds);

        if (empty($leyes)) {
            return new JsonResponse(['error' => 'No hay leyes con formato de fecha para los temas seleccionados'], 404);
        }

        $resultado = [];
        foreach ($leyes as $ley) {
            $nombre = $ley->getNombre() ?? '';

            if (preg_match('/(\d+)\/(\d+),\s*de\s+(\d+)\s+de\s+(\w+)/i', $nombre, $matches)) {
                $resultado[] = [
                    'id' => $ley->getId(),
                    'nombre' => $nombre,
                    'numero1' => $matches[1],
                    'numero2' => $matches[2],
                    'dia' => $matches[3],
                    'mes' => $matches[4],
                ];
            }
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No se pudieron procesar las leyes'], 404);
        }

        shuffle($resultado);

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/articulos-texto-legal-lote', name: 'app_juego_api_articulos_texto_legal_lote')]
    public function getArticulosTextoLegalLote(Request $request, ArticuloRepository $articuloRepository): JsonResponse
    {
        $temaIds = $this->requireTemaIds($request);
        if ($temaIds instanceof JsonResponse) {
            return $temaIds;
        }

        $articulos = $articuloRepository->findAleatoriosConTextoLegal(20, $temaIds);

        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos con texto legal para los temas seleccionados'], 404);
        }

        $resultado = [];
        foreach ($articulos as $articulo) {
            $resultado[] = [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'sufijo' => $articulo->getSufijo(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
                'textoLegal' => $articulo->getTextoLegal(),
                'ley' => [
                    'id' => $articulo->getLey()->getId(),
                    'nombre' => $articulo->getLey()->getNombre(),
                ],
            ];
        }

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/articulos-correcto-lote', name: 'app_juego_api_articulos_correcto_lote')]
    public function getArticulosCorrectoLote(Request $request, ArticuloRepository $articuloRepository): JsonResponse
    {
        $temaIds = $this->requireTemaIds($request);
        if ($temaIds instanceof JsonResponse) {
            return $temaIds;
        }

        return $this->buildArticulosCorrectoLote($articuloRepository, $temaIds, 1);
    }

    /**
     * @param int[] $temaIds
     */
    private function buildArticulosCorrectoLote(ArticuloRepository $articuloRepository, array $temaIds, int $intento): JsonResponse
    {
        $articulos = $articuloRepository->findAleatoriosConTextoLegalParaJuego(20, $temaIds);

        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos suficientes para los temas seleccionados'], 404);
        }

        $idsArticulosLote = array_map(fn ($a) => $a->getId(), $articulos);

        $qb = $articuloRepository->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->addSelect('l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->andWhere('a.numero != :numeroExcluido')
            ->andWhere('a.textoLegal IS NOT NULL')
            ->andWhere('a.textoLegal != :vacio')
            ->andWhere('a.id NOT IN (:idsLote)')
            ->setParameter('activo', true)
            ->setParameter('numeroExcluido', 0)
            ->setParameter('vacio', '')
            ->setParameter('idsLote', $idsArticulosLote);

        $subquery = $articuloRepository->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);
        $qb->andWhere('l.id != (' . $subquery->getDQL() . ')')
           ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico');

        $qb->innerJoin('l.temas', 'temaFiltro')
            ->andWhere('temaFiltro.id IN (:temaIds)')
            ->setParameter('temaIds', $temaIds);

        $articulosParaIncorrectas = $qb->getQuery()->getResult();
        shuffle($articulosParaIncorrectas);

        $resultado = [];
        $textosUsadosEnJuego = [];

        foreach ($articulos as $articulo) {
            $textoCorrecto = trim($articulo->getTextoLegal());

            if (empty($textoCorrecto)) {
                continue;
            }

            $versionesIncorrectas = [];
            $articulosUsados = [$articulo->getId()];

            foreach ($articulosParaIncorrectas as $articuloAdicional) {
                if (count($versionesIncorrectas) >= 2) {
                    break;
                }

                $textoIncorrecto = trim($articuloAdicional->getTextoLegal());

                if (!empty($textoIncorrecto)
                    && $textoIncorrecto !== $textoCorrecto
                    && !in_array($textoIncorrecto, $textosUsadosEnJuego, true)
                    && !in_array($articuloAdicional->getId(), $articulosUsados, true)
                ) {
                    $versionesIncorrectas[] = $textoIncorrecto;
                    $articulosUsados[] = $articuloAdicional->getId();
                    $textosUsadosEnJuego[] = $textoIncorrecto;
                }
            }

            if (count($versionesIncorrectas) < 2) {
                continue;
            }

            $textosUsadosEnJuego[] = $textoCorrecto;

            $versiones = [$textoCorrecto, $versionesIncorrectas[0], $versionesIncorrectas[1]];
            shuffle($versiones);

            $indiceCorrecto = array_search($textoCorrecto, $versiones, true);
            if ($indiceCorrecto === false) {
                continue;
            }

            $resultado[] = [
                'id' => $articulo->getId(),
                'numero' => $articulo->getNumero(),
                'sufijo' => $articulo->getSufijo(),
                'numeroCompleto' => $articulo->getNumeroCompleto(),
                'nombre' => $articulo->getNombre(),
                'ley' => [
                    'id' => $articulo->getLey()->getId(),
                    'nombre' => $articulo->getLey()->getNombre(),
                ],
                'versiones' => $versiones,
                'indiceCorrecto' => $indiceCorrecto,
            ];
        }

        if (count($resultado) < 5 && $intento < 2) {
            return $this->buildArticulosCorrectoLote($articuloRepository, $temaIds, $intento + 1);
        }

        if (count($resultado) > 20) {
            $resultado = array_slice($resultado, 0, 20);
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No se pudieron generar artículos con versiones válidas para los temas seleccionados'], 404);
        }

        return new JsonResponse($resultado);
    }
}
