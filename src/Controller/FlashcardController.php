<?php

namespace App\Controller;

use App\Entity\Flashcard;
use App\Http\Consulta;
use App\Entity\Pregunta;
use App\Entity\PreguntaMunicipal;
use App\Entity\User;
use App\Repository\FlashcardRepository;
use App\Repository\PreguntaMunicipalRepository;
use App\Repository\PreguntaRepository;
use App\Service\FlashcardTexto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/flashcards')]
#[IsGranted('ROLE_USER')]
class FlashcardController extends AbstractController
{
    private const MAXIMO = 8000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FlashcardRepository $flashcardRepository,
        private PreguntaRepository $preguntaRepository,
        private PreguntaMunicipalRepository $preguntaMunicipalRepository,
        private FlashcardTexto $flashcardTexto,
    ) {
    }

    #[Route('/', name: 'app_flashcard_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $usuario = $this->usuarioActual();
        $flashcards = $this->flashcardRepository->findPropias($usuario);
        $temas = $this->opcionesTema($flashcards);
        $leyes = $this->opcionesLey($flashcards);
        ['tema' => $tema, 'ley' => $ley, 'filtradas' => $filtradas] = $this->filtrarDesdeRequest($request, $flashcards, $temas, $leyes);

        return $this->render('flashcard/index.html.twig', [
            'grupos' => $this->agrupar($filtradas),
            'total' => count($filtradas),
            'totalGeneral' => count($flashcards),
            'temas' => $temas,
            'leyes' => $leyes,
            'temaSeleccionado' => $tema,
            'leySeleccionada' => $ley,
        ]);
    }

    #[Route('/practicar', name: 'app_flashcard_practicar', methods: ['GET'])]
    public function practicar(Request $request): Response
    {
        $usuario = $this->usuarioActual();
        $flashcards = $this->flashcardRepository->findPropias($usuario);
        $temas = $this->opcionesTema($flashcards);
        $leyes = $this->opcionesLey($flashcards);
        ['tema' => $tema, 'ley' => $ley, 'filtradas' => $filtradas] = $this->filtrarDesdeRequest($request, $flashcards, $temas, $leyes);

        $tarjetas = [];
        foreach ($filtradas as $flashcard) {
            $nombreLey = $flashcard->getPregunta()?->getLey()?->getNombre();
            $tarjetas[] = [
                'tema' => $flashcard->getNombreTema(),
                'ley' => is_string($nombreLey) && trim($nombreLey) !== '' ? trim($nombreLey) : '',
                'pregunta' => $flashcard->getAnverso(),
                'respuesta' => $flashcard->getReverso(),
            ];
        }
        shuffle($tarjetas);

        return $this->render('flashcard/practicar.html.twig', [
            'tarjetasJson' => json_encode($tarjetas, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE),
            'total' => count($tarjetas),
            'temaSeleccionado' => $tema,
            'leySeleccionada' => $ley,
        ]);
    }

    #[Route('/guardar', name: 'app_flashcard_guardar', methods: ['POST'])]
    public function guardar(Request $request): Response
    {
        $usuario = $this->usuarioActual();
        if (!$this->isCsrfTokenValid('flashcard_guardar', $request->request->getString('_token'))) {
            return $this->respuestaError($request, 'La sesión ha caducado. Vuelve a cargar la página.', 403);
        }

        $tipo = $request->request->getString('tipo');
        $preguntaId = $request->request->getInt('preguntaId');
        if (!in_array($tipo, ['general', 'municipal'], true) || $preguntaId <= 0) {
            return $this->respuestaError($request, 'No se ha podido identificar la pregunta.');
        }

        $municipal = $tipo === 'municipal';
        $pregunta = $this->cargarPregunta($preguntaId, $municipal);

        if (!$pregunta instanceof Pregunta && !$pregunta instanceof PreguntaMunicipal) {
            return $this->respuestaError($request, 'Esa pregunta ya no está disponible.', 404);
        }

        if (!$this->puedeUsarPregunta($usuario, $pregunta)) {
            return $this->respuestaError($request, 'No puedes guardar una flashcard de esta pregunta.', 403);
        }

        $textos = $this->textosDelRequest($request);
        if ($textos === null) {
            return $this->respuestaError($request, 'Escribe la pregunta y la respuesta. Cada una puede tener hasta '.self::MAXIMO.' caracteres.');
        }

        $flashcard = $this->flashcardRepository->findUnaPorPregunta($usuario, $preguntaId, $municipal) ?? new Flashcard();
        $flashcard->setUsuario($usuario);
        $flashcard->setAnverso($textos['anverso']);
        $flashcard->setReverso($textos['reverso']);
        $flashcard->setActualizadoEn(new \DateTime());

        if ($pregunta instanceof PreguntaMunicipal) {
            $flashcard->setPregunta(null);
            $flashcard->setTema(null);
            $flashcard->setPreguntaMunicipal($pregunta);
            $flashcard->setTemaMunicipal($pregunta->getTemaMunicipal());
        } else {
            $flashcard->setPreguntaMunicipal(null);
            $flashcard->setTemaMunicipal(null);
            $flashcard->setPregunta($pregunta);
            $flashcard->setTema($pregunta->getTema());
        }

        $this->entityManager->persist($flashcard);
        $this->entityManager->flush();

        if ($this->quiereJson($request)) {
            return $this->json([
                'ok' => true,
                'id' => $flashcard->getId(),
                'tema' => $flashcard->getNombreTema(),
            ]);
        }

        $this->addFlash('success', 'Flashcard guardada en '.$flashcard->getNombreTema().'.');

        return $this->redirectToRoute('app_flashcard_index');
    }

    #[Route('/{id}/editar', name: 'app_flashcard_editar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function editar(Request $request, Flashcard $flashcard): Response
    {
        $this->exigirPropietaria($flashcard);
        if (!$this->isCsrfTokenValid('flashcard_editar_'.$flashcard->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'La sesión ha caducado. Vuelve a intentarlo.');

            return $this->redirectToRoute('app_flashcard_index');
        }

        $textos = $this->textosDelRequest($request);
        if ($textos === null) {
            $this->addFlash('error', 'Escribe la pregunta y la respuesta. Cada una puede tener hasta '.self::MAXIMO.' caracteres.');

            return $this->redirectToRoute('app_flashcard_index');
        }

        $flashcard->setAnverso($textos['anverso']);
        $flashcard->setReverso($textos['reverso']);
        $flashcard->setActualizadoEn(new \DateTime());
        $this->entityManager->flush();
        $this->addFlash('success', 'Flashcard actualizada.');

        return $this->redirectToRoute('app_flashcard_index', $this->filtrosDeVuelta($request, [
            '_fragment' => 'flashcard-'.$flashcard->getId(),
        ]));
    }

    #[Route('/{id}/eliminar', name: 'app_flashcard_eliminar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function eliminar(Request $request, Flashcard $flashcard): Response
    {
        $this->exigirPropietaria($flashcard);
        if (!$this->isCsrfTokenValid('flashcard_eliminar_'.$flashcard->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'La sesión ha caducado. Vuelve a intentarlo.');

            return $this->redirectToRoute('app_flashcard_index');
        }

        $this->entityManager->remove($flashcard);
        $this->entityManager->flush();
        $this->addFlash('success', 'Flashcard eliminada.');

        return $this->redirectToRoute('app_flashcard_index', $this->filtrosDeVuelta($request));
    }

    #[Route('/imprimir', name: 'app_flashcard_imprimir', methods: ['GET'])]
    public function imprimir(Request $request): Response
    {
        $usuario = $this->usuarioActual();
        $ids = $request->query->all('ids');
        if (count($ids) > 500) {
            $this->addFlash('error', 'Puedes imprimir hasta 500 flashcards a la vez.');

            return $this->redirectToRoute('app_flashcard_index');
        }

        $flashcards = $this->flashcardRepository->findPropiasPorIds($usuario, $ids);
        if ($flashcards === []) {
            $this->addFlash('error', 'Selecciona al menos una flashcard para imprimir.');

            return $this->redirectToRoute('app_flashcard_index');
        }

        return $this->render('flashcard/imprimir.html.twig', [
            'grupos' => $this->agrupar($flashcards),
            'total' => count($flashcards),
        ]);
    }

    /**
     * @param Flashcard[] $flashcards
     * @return list<array{clave: string, nombre: string, flashcards: Flashcard[]}>
     */
    private function agrupar(array $flashcards): array
    {
        $grupos = [];
        foreach ($flashcards as $flashcard) {
            $clave = $flashcard->getClaveTema();
            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'clave' => $clave,
                    'nombre' => $flashcard->getNombreTema(),
                    'flashcards' => [],
                ];
            }
            $grupos[$clave]['flashcards'][] = $flashcard;
        }

        uasort($grupos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));

        return array_values($grupos);
    }

    /**
     * @param Flashcard[] $flashcards
     * @return list<array{clave: string, nombre: string, cantidad: int}>
     */
    private function opcionesTema(array $flashcards): array
    {
        $mapa = [];
        foreach ($flashcards as $flashcard) {
            $clave = $flashcard->getClaveTema();
            if (!isset($mapa[$clave])) {
                $mapa[$clave] = [
                    'clave' => $clave,
                    'nombre' => $flashcard->getNombreTema(),
                    'cantidad' => 0,
                ];
            }
            ++$mapa[$clave]['cantidad'];
        }

        $opciones = array_values($mapa);
        usort($opciones, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));

        return $opciones;
    }

    /**
     * @param Flashcard[] $flashcards
     * @return list<array{id: int, nombre: string, cantidad: int}>
     */
    private function opcionesLey(array $flashcards): array
    {
        $mapa = [];
        foreach ($flashcards as $flashcard) {
            $ley = $flashcard->getPregunta()?->getLey();
            $id = $ley?->getId();
            if ($id === null) {
                continue;
            }
            if (!isset($mapa[$id])) {
                $mapa[$id] = [
                    'id' => $id,
                    'nombre' => trim((string) $ley->getNombre()) !== '' ? trim((string) $ley->getNombre()) : 'Ley '.$id,
                    'cantidad' => 0,
                ];
            }
            ++$mapa[$id]['cantidad'];
        }

        $opciones = array_values($mapa);
        usort($opciones, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));

        return $opciones;
    }

    /**
     * @param Flashcard[] $flashcards
     * @param list<array{clave: string, nombre: string, cantidad: int}> $temas
     * @param list<array{id: int, nombre: string, cantidad: int}> $leyes
     * @return array{tema: string, ley: int, filtradas: Flashcard[]}
     */
    private function filtrarDesdeRequest(Request $request, array $flashcards, array $temas, array $leyes): array
    {
        $tema = $request->query->getString('tema');
        if (!preg_match('/^(general|municipal)-\d+$|^sin-tema$/', $tema) || !in_array($tema, array_column($temas, 'clave'), true)) {
            $tema = '';
        }

        $ley = Consulta::entero($request->query, 'ley');
        if ($ley <= 0 || !in_array($ley, array_column($leyes, 'id'), true)) {
            $ley = 0;
        }

        return [
            'tema' => $tema,
            'ley' => $ley,
            'filtradas' => $this->filtrar($flashcards, $tema, $ley),
        ];
    }

    /**
     * @param Flashcard[] $flashcards
     * @return Flashcard[]
     */
    private function filtrar(array $flashcards, string $tema, int $ley): array
    {
        if ($tema === '' && $ley <= 0) {
            return $flashcards;
        }

        return array_values(array_filter(
            $flashcards,
            static function (Flashcard $flashcard) use ($tema, $ley): bool {
                if ($tema !== '' && $flashcard->getClaveTema() !== $tema) {
                    return false;
                }

                return $ley <= 0 || $flashcard->getPregunta()?->getLey()?->getId() === $ley;
            }
        ));
    }

    /**
     * @param array<string, int|string> $extra
     * @return array<string, int|string>
     */
    private function filtrosDeVuelta(Request $request, array $extra = []): array
    {
        $tema = $request->request->getString('filtro_tema');
        if (preg_match('/^(general|municipal)-\d+$|^sin-tema$/', $tema)) {
            $extra['tema'] = $tema;
        }

        $ley = Consulta::entero($request->request, 'filtro_ley');
        if ($ley > 0) {
            $extra['ley'] = $ley;
        }

        return $extra;
    }

    private function usuarioActual(): User
    {
        $usuario = $this->getUser();
        if (!$usuario instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $usuario;
    }

    private function exigirPropietaria(Flashcard $flashcard): void
    {
        if ($flashcard->getUsuario()?->getId() !== $this->usuarioActual()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function puedeUsarPregunta(User $usuario, Pregunta|PreguntaMunicipal $pregunta): bool
    {
        if ($this->isGranted('ROLE_PROFESOR')) {
            return true;
        }

        $municipal = $pregunta instanceof PreguntaMunicipal;
        if ($municipal) {
            $municipio = $pregunta->getMunicipio();
            if ($municipio !== null && $usuario->tieneAccesoAMunicipio($municipio)) {
                return true;
            }
        } elseif ($pregunta->getTema()?->getCategoria() === $usuario->getCategoria()) {
            return true;
        }

        return $pregunta->getId() !== null && $this->apareceEnExamenPropio($usuario, $pregunta->getId(), $municipal);
    }

    private function cargarPregunta(int $id, bool $municipal): Pregunta|PreguntaMunicipal|null
    {
        if ($municipal) {
            return $this->preguntaMunicipalRepository->createQueryBuilder('p')
                ->leftJoin('p.temaMunicipal', 'tm')->addSelect('tm')
                ->leftJoin('p.municipio', 'm')->addSelect('m')
                ->leftJoin('tm.municipio', 'tmm')->addSelect('tmm')
                ->andWhere('p.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $this->preguntaRepository->createQueryBuilder('p')
            ->leftJoin('p.tema', 't')->addSelect('t')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function apareceEnExamenPropio(User $usuario, int $preguntaId, bool $municipal): bool
    {
        $sql = <<<'SQL'
            SELECT e.id
            FROM examen e
            WHERE e.usuario_id = :usuario
              AND (
                JSON_CONTAINS(e.preguntas_ids, :comoNumero, '$')
                OR JSON_CONTAINS(e.preguntas_ids, :comoTexto, '$')
              )
            SQL;
        $sql .= $municipal
            ? ' AND (e.municipio_id IS NOT NULL OR e.convocatoria_id IS NOT NULL)'
            : ' AND e.municipio_id IS NULL AND e.convocatoria_id IS NULL';
        $sql .= ' LIMIT 1';

        $id = $this->entityManager->getConnection()->fetchOne($sql, [
            'usuario' => $usuario->getId(),
            'comoNumero' => (string) $preguntaId,
            'comoTexto' => json_encode((string) $preguntaId, JSON_THROW_ON_ERROR),
        ]);

        return $id !== false;
    }

    /**
     * @return array{anverso: string, reverso: string}|null
     */
    private function textosDelRequest(Request $request): ?array
    {
        $anverso = $this->normalizar($request->request->getString('anverso'));
        $reverso = $this->normalizar($request->request->getString('reverso'));
        if ($anverso === '' || $reverso === '') {
            return null;
        }
        if (mb_strlen($anverso) > self::MAXIMO || mb_strlen($reverso) > self::MAXIMO) {
            return null;
        }

        return ['anverso' => $anverso, 'reverso' => $reverso];
    }

    private function normalizar(string $texto): string
    {
        $texto = str_replace("\0", '', $texto);
        $texto = preg_replace("/\r\n?/", "\n", $texto) ?? $texto;
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto) ?? $texto;

        return trim($texto);
    }

    private function quiereJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    private function respuestaError(Request $request, string $mensaje, int $estado = 400): Response
    {
        if ($this->quiereJson($request)) {
            return $this->json(['ok' => false, 'error' => $mensaje], $estado);
        }

        $this->addFlash('error', $mensaje);

        return $this->redirectToRoute('app_flashcard_index');
    }
}
