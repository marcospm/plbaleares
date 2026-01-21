<?php

namespace App\Controller;

use App\Entity\PartidaJuego;
use App\Repository\ArticuloRepository;
use App\Repository\LeyRepository;
use App\Repository\PreguntaRepository;
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
        private EntityManagerInterface $entityManager
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
        return $this->render('juego/adivina_numero_articulo.html.twig');
    }

    #[Route('/juegos/adivina-nombre-articulo', name: 'app_juego_adivina_nombre_articulo')]
    #[IsGranted('ROLE_USER')]
    public function adivinaNombreArticulo(): Response
    {
        return $this->render('juego/adivina_nombre_articulo.html.twig');
    }

    #[Route('/juegos/completa-fecha-ley', name: 'app_juego_completa_fecha_ley')]
    #[IsGranted('ROLE_USER')]
    public function completaFechaLey(): Response
    {
        return $this->render('juego/completa_fecha_ley.html.twig');
    }

    #[Route('/juegos/completa-texto-legal', name: 'app_juego_completa_texto_legal')]
    #[IsGranted('ROLE_USER')]
    public function completaTextoLegal(): Response
    {
        return $this->render('juego/completa_texto_legal.html.twig');
    }

    #[Route('/juegos/articulo-correcto', name: 'app_juego_articulo_correcto')]
    #[IsGranted('ROLE_USER')]
    public function articuloCorrecto(): Response
    {
        return $this->render('juego/articulo_correcto.html.twig');
    }

    #[Route('/api/juegos/guardar-partida', name: 'app_juego_api_guardar_partida', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function guardarPartidaApi(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tipoJuego = $data['tipoJuego'] ?? null;

        // Validar tipo de juego
        $tiposValidos = [
            'adivina_numero_articulo',
            'adivina_nombre_articulo',
            'completa_fecha_ley',
            'completa_texto_legal',
            'articulo_correcto',
        ];

        if (!$tipoJuego || !in_array($tipoJuego, $tiposValidos)) {
            return new JsonResponse(['error' => 'Tipo de juego inválido'], 400);
        }

        $this->guardarPartida($tipoJuego);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Guarda una partida de juego en la base de datos
     */
    private function guardarPartida(string $tipoJuego): void
    {
        $user = $this->getUser();
        if (!$user) {
            return;
        }

        // Verificar que el usuario no sea profesor ni admin (solo alumnos)
        $roles = $user->getRoles();
        if (in_array('ROLE_PROFESOR', $roles) || in_array('ROLE_ADMIN', $roles)) {
            return;
        }

        try {
            $partida = new PartidaJuego();
            $partida->setUsuario($user);
            $partida->setTipoJuego($tipoJuego);
            // fechaCreacion se establece automáticamente en el constructor

            $this->entityManager->persist($partida);
            // Usar flush sin esperar para no bloquear la respuesta
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Silenciar errores para no interrumpir el juego
            // En producción, podrías loguear el error
            // Resetear el entity manager en caso de error
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

        // Verificar que la pregunta tenga texto
        if (!$pregunta->getTexto() || trim($pregunta->getTexto()) === '') {
            // Si esta pregunta no tiene texto, intentar obtener otra
            $pregunta = $preguntaRepository->findAleatoriaActiva();
            if (!$pregunta || !$pregunta->getTexto() || trim($pregunta->getTexto()) === '') {
                return new JsonResponse(['error' => 'No hay preguntas con texto disponible'], 404);
            }
        }

        // Obtener la respuesta correcta completa
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

        // Verificar que la respuesta correcta no esté vacía
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
    public function getPreguntasLote(PreguntaRepository $preguntaRepository): JsonResponse
    {
        $preguntas = $preguntaRepository->findAleatoriasActivas(20);
        
        if (empty($preguntas)) {
            return new JsonResponse(['error' => 'No hay preguntas disponibles'], 404);
        }

        $resultado = [];
        foreach ($preguntas as $pregunta) {
            // Obtener la respuesta correcta completa
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
                continue; // Saltar preguntas sin respuesta válida
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
    public function getArticulosLote(ArticuloRepository $articuloRepository): JsonResponse
    {
        $articulos = $articuloRepository->findAleatoriosConNombre(20);
        
        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos disponibles'], 404);
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

    #[Route('/api/juegos/leyes-con-fecha', name: 'app_juego_api_leyes_con_fecha')]
    public function getLeyesConFecha(LeyRepository $leyRepository): JsonResponse
    {
        $leyes = $leyRepository->findLeyesConFormatoFecha();
        
        if (empty($leyes)) {
            return new JsonResponse(['error' => 'No hay leyes con formato de fecha disponible'], 404);
        }

        $resultado = [];
        foreach ($leyes as $ley) {
            $nombre = $ley->getNombre() ?? '';
            
            // Extraer los componentes: número/número, de día de mes
            // Patrón más flexible: puede empezar con "Ley" o no, y permite espacios variables
            // Ejemplos: "20/2006, de 15 de diciembre", "Ley 20/2006, de 15 de diciembre"
            if (preg_match('/(\d+)\/(\d+),\s*de\s+(\d+)\s+de\s+(\w+)/i', $nombre, $matches)) {
                $resultado[] = [
                    'id' => $ley->getId(),
                    'nombre' => $nombre,
                    'numero1' => $matches[1],      // Primer número
                    'numero2' => $matches[2],       // Año
                    'dia' => $matches[3],          // Día
                    'mes' => $matches[4],          // Mes
                ];
            }
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No se pudieron procesar las leyes'], 404);
        }

        // Mezclar aleatoriamente
        shuffle($resultado);

        return new JsonResponse($resultado);
    }

    #[Route('/api/juegos/articulos-texto-legal-lote', name: 'app_juego_api_articulos_texto_legal_lote')]
    public function getArticulosTextoLegalLote(ArticuloRepository $articuloRepository): JsonResponse
    {
        $articulos = $articuloRepository->findAleatoriosConTextoLegal(20);
        
        if (empty($articulos)) {
            return new JsonResponse(['error' => 'No hay artículos con texto legal disponibles'], 404);
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
    public function getArticulosCorrectoLote(ArticuloRepository $articuloRepository): JsonResponse
    {
        // Obtener artículos para el juego (excluye Tema 17 y numero 0)
        $articulos = $articuloRepository->findAleatoriosConTextoLegalParaJuego(20);
        
        if (empty($articulos) || count($articulos) < 20) {
            return new JsonResponse(['error' => 'No hay suficientes artículos con texto legal disponibles'], 404);
        }

        // Obtener IDs de los artículos del lote principal para excluirlos
        $idsArticulosLote = array_map(fn($a) => $a->getId(), $articulos);
        
        // Obtener más artículos para generar versiones incorrectas (excluyendo los del lote)
        // Obtener todos los artículos disponibles excepto los del lote
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
        
        // Excluir ley "Accidentes de Tráfico"
        $subquery = $articuloRepository->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);
        $qb->andWhere('l.id != (' . $subquery->getDQL() . ')')
           ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico');
        
        $articulosParaIncorrectas = $qb->getQuery()->getResult();
        
        // Mezclar para aleatoriedad
        shuffle($articulosParaIncorrectas);
        
        $resultado = [];
        $textosUsadosEnJuego = []; // Rastrear todos los textos usados en el juego para evitar duplicados
        
        foreach ($articulos as $articulo) {
            $textoCorrecto = trim($articulo->getTextoLegal());
            
            if (empty($textoCorrecto)) {
                continue; // Saltar artículos sin texto legal válido
            }

            // Generar 2 versiones incorrectas usando textos de artículos FUERA del lote de 20
            $versionesIncorrectas = [];
            $articulosUsados = [$articulo->getId()]; // Evitar usar el mismo artículo
            
            foreach ($articulosParaIncorrectas as $articuloAdicional) {
                if (count($versionesIncorrectas) >= 2) {
                    break;
                }
                
                $textoIncorrecto = trim($articuloAdicional->getTextoLegal());
                
                // Verificar que el texto no esté vacío, no sea el mismo que el correcto,
                // no esté ya usado en este juego, y no sea del artículo actual
                if (!empty($textoIncorrecto) && 
                    $textoIncorrecto !== $textoCorrecto &&
                    !in_array($textoIncorrecto, $textosUsadosEnJuego) &&
                    !in_array($articuloAdicional->getId(), $articulosUsados)) {
                    $versionesIncorrectas[] = $textoIncorrecto;
                    $articulosUsados[] = $articuloAdicional->getId();
                    $textosUsadosEnJuego[] = $textoIncorrecto; // Marcar como usado
                }
            }

            // Validar que tenemos 2 versiones incorrectas
            if (count($versionesIncorrectas) < 2) {
                // Si no se pueden generar, intentar recargar más artículos
                // o simplemente continuar - en este caso, mejor reintentar con otro artículo
                // Por ahora, saltamos este artículo y esperamos tener suficientes
                continue;
            }

            // Marcar el texto correcto como usado también para evitar que aparezca como incorrecta en otros
            $textosUsadosEnJuego[] = $textoCorrecto;

            // Crear array con 3 versiones: correcta + 2 incorrectas
            $versiones = [$textoCorrecto, $versionesIncorrectas[0], $versionesIncorrectas[1]];
            
            // Mezclar aleatoriamente
            shuffle($versiones);
            
            // Buscar el índice correcto después de mezclar
            $indiceCorrecto = array_search($textoCorrecto, $versiones);
            
            // Validar que se encontró el índice correcto
            if ($indiceCorrecto === false) {
                continue; // Saltar este artículo si hay problema con la mezcla
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

        // Si no tenemos exactamente 20 artículos, intentar recargar desde el principio
        if (count($resultado) < 20) {
            // Reintentar una vez más con un nuevo lote
            return $this->getArticulosCorrectoLote($articuloRepository);
        }

        // Limitar a exactamente 20 artículos
        if (count($resultado) > 20) {
            $resultado = array_slice($resultado, 0, 20);
        }

        if (empty($resultado)) {
            return new JsonResponse(['error' => 'No se pudieron generar artículos con versiones válidas'], 404);
        }

        return new JsonResponse($resultado);
    }
}

