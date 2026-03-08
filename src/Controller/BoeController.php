<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;
use App\Repository\MunicipioRepository;

#[Route('/boe')]
#[IsGranted('ROLE_USER')]
class BoeController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MunicipioRepository $municipioRepository
    ) {
    }

    #[Route('/', name: 'app_boe_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $error = null;
        $resultados = [];
        $fechaSeleccionada = null;

        // Obtener fecha del formulario (solo buscamos si el usuario la indica)
        $fechaInput = $request->query->get('fecha');
        if ($fechaInput) {
            try {
                $fechaSeleccionada = new \DateTime($fechaInput);
            } catch (\Exception $e) {
                $error = 'Fecha inválida';
            }
        }

        // Si hay fecha seleccionada y no hay error, buscar en el BOE
        if ($fechaSeleccionada && !$error) {
            try {
                // Formatear fecha como YYYYMMDD para la API del BOE
                $fechaFormato = $fechaSeleccionada->format('Ymd');

                // Construir URL del sumario BOE
                $urlSumario = sprintf('https://boe.es/datosabiertos/api/boe/sumario/%s', $fechaFormato);

                // Descargar el XML
                $response = $this->httpClient->request('GET', $urlSumario, [
                    'timeout' => 15,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'application/xml, text/xml, */*',
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    $xmlContent = $response->getContent();

                    if (!empty(trim($xmlContent))) {
                        // Parsear el XML y buscar items del departamento de Illes Balears
                        $resultados = $this->procesarXmlBoe($xmlContent);
                    } else {
                        $error = 'El XML del sumario está vacío';
                    }
                } else {
                    $error = 'Error al acceder al sumario del BOE. Código de estado: ' . $statusCode;
                }
            } catch (\Exception $e) {
                $error = 'Error al buscar en el BOE: ' . $e->getMessage();
            }
        }

        return $this->render('boe/index.html.twig', [
            'resultados' => $resultados,
            'error' => $error,
            'fechaSeleccionada' => $fechaSeleccionada ? $fechaSeleccionada->format('Y-m-d') : null,
        ]);
    }

    /**
     * Procesa el XML del sumario del BOE para una fecha concreta.
     * - Busca directamente en todos los <item> por el título
     * - Criterio 1: términos de policía / proceso / OEP... en el título
     * - Criterio 2: título contiene nombre de municipio + convocatoria / plaza(s)
     * - Devuelve enlaces a PDF / HTML para cada item que cumpla alguno de los criterios
     */
    private function procesarXmlBoe(string $xmlContent): array
    {
        // Usamos un array indexado por identificador/url para evitar duplicados
        $resultadosIndexados = [];

        try {
            $crawler = new Crawler($xmlContent);

            // Términos a buscar en los títulos (combinando los usados en BOIB)
            $terminosBusquedaTitulo = [
                'policía',
                'policia',
                'policía local',
                'policia local',
                'subinspector',
                'subinspector/a',
                'subinspectora',
                'agente de policía',
                'agente de policia',
                'inspector de policía',
                'inspector de policia',
                'oficial de policía',
                'oficial de policia',
                'proceso unificado',
                'OEP',
                'oferta de empleo público',
                'oferta de empleo publico',
                'plaza.*policía',
                'plaza.*policia',
                'policía.*plaza',
                'policia.*plaza',
                'convocatoria.*policía',
                'convocatoria.*policia',
                'bases.*policía',
                'bases.*policia',
            ];

            // Búsqueda de convocatorias/plazas por municipios de Mallorca (lista fija)
            $nombresMunicipios = [
                'Alaró',
                'Alcúdia',
                'Algaida',
                'Andratx',
                'Ariany',
                'Artà',
                'Banyalbufar',
                'Binissalem',
                'Búger',
                'Bunyola',
                'Calvià',
                'Campanet',
                'Campos',
                'Capdepera',
                'Consell',
                'Costitx',
                'Deià',
                'Escorca',
                'Esporles',
                'Estellencs',
                'Felanitx',
                'Fornalutx',
                'Inca',
                'Lloret de Vistalegre',
                'Lloseta',
                'Llubí',
                'Llucmajor',
                'Manacor',
                'Mancor de la Vall',
                'Maria de la Salut',
                'Marratxí',
                'Montuïri',
                'Muro',
                'Palma',
                'Petra',
                'Pollença',
                'Porreres',
                'Puigpunyent',
                'Sa Pobla',
                'Sant Joan',
                'Sant Llorenç des Cardassar',
                'Santa Eugènia',
                'Santa Margalida',
                'Santa Maria del Camí',
                'Santanyí',
                'Selva',
                'Sencelles',
                'Ses Salines',
                'Sineu',
                'Sóller',
                'Son Servera',
                'Valldemossa',
                'Vilafranca de Bonany',
            ];

            // Preparar versión normalizada de los nombres de municipios para búsqueda robusta
            $municipiosNormalizados = [];
            foreach ($nombresMunicipios as $nombreMunicipio) {
                $municipiosNormalizados[] = [
                    'original' => $nombreMunicipio,
                    'normalizado' => $this->normalizarTextoBusqueda($nombreMunicipio),
                ];
            }

            // Recorremosdirectamente TODOS los <item> del sumario
            $items = $crawler->filter('item');

            foreach ($items as $itemNode) {
                $itemCrawler = new Crawler($itemNode);

                $identificador = $itemCrawler->filter('identificador')->count() > 0
                    ? trim($itemCrawler->filter('identificador')->text())
                    : '';

                $titulo = $itemCrawler->filter('titulo')->count() > 0
                    ? trim($itemCrawler->filter('titulo')->text())
                    : '';

                $urlPdf = $itemCrawler->filter('url_pdf')->count() > 0
                    ? trim($itemCrawler->filter('url_pdf')->text())
                    : '';

                $urlHtml = $itemCrawler->filter('url_html')->count() > 0
                    ? trim($itemCrawler->filter('url_html')->text())
                    : '';

                $control = $itemCrawler->filter('control')->count() > 0
                    ? trim($itemCrawler->filter('control')->text())
                    : '';

                if ($titulo === '' || $urlPdf === '') {
                    continue;
                }

                $tituloLower = mb_strtolower($titulo, 'UTF-8');
                $tituloNormalizado = $this->normalizarTextoBusqueda($titulo);

                // PASO 1: PRIMERO buscar municipio (obligatorio)
                // Si no hay municipio, saltamos directamente sin buscar palabras clave
                $municipiosEncontrados = [];
                foreach ($municipiosNormalizados as $municipio) {
                    if ($municipio['normalizado'] === '') {
                        continue;
                    }
                    
                    // Buscar el municipio como palabra completa (no como subcadena)
                    // Usamos límites de palabra (\b) para evitar falsos positivos
                    $pattern = '/\b' . preg_quote($municipio['normalizado'], '/') . '\b/u';
                    if (preg_match($pattern, $tituloNormalizado)) {
                        $municipiosEncontrados[] = $municipio['original'];
                    }
                }

                // Si NO hay municipio, saltamos este item (no buscamos palabras clave)
                if (empty($municipiosEncontrados)) {
                    continue;
                }

                // PASO 2: Si hay municipio, AHORA buscar palabras clave
                // 2a) Búsqueda por términos de policía / proceso / OEP, etc.
                $terminosTituloEncontrados = [];
                foreach ($terminosBusquedaTitulo as $termino) {
                    $terminoLower = mb_strtolower($termino, 'UTF-8');

                    if (str_contains($termino, '.*')) {
                        $pattern = '/' . $termino . '/i';
                        if (preg_match($pattern, $titulo)) {
                            $terminosTituloEncontrados[] = $termino;
                        }
                    } else {
                        if (mb_stripos($tituloLower, $terminoLower, 0, 'UTF-8') !== false) {
                            $terminosTituloEncontrados[] = $termino;
                        }
                    }
                }

                // 2b) Comprobar términos adicionales (convocatoria, plaza)
                $terminosConvocatoria = [];
                if (str_contains($tituloNormalizado, 'convocatoria')) {
                    $terminosConvocatoria[] = 'convocatoria';
                }
                if (str_contains($tituloNormalizado, 'plaza')) {
                    // cubre plaza y plazas
                    $terminosConvocatoria[] = 'plaza/plazas';
                }

                // Si hay municipio pero NO hay palabras clave, no incluimos el resultado
                $hayPalabrasClave = !empty($terminosTituloEncontrados) || !empty($terminosConvocatoria);
                if (!$hayPalabrasClave) {
                    continue;
                }

                $key = $identificador !== '' ? $identificador : $urlPdf;

                if (!isset($resultadosIndexados[$key])) {
                    $resultadosIndexados[$key] = [
                        'identificador' => $identificador,
                        'titulo' => $titulo,
                        'url_pdf' => $urlPdf,
                        'url_html' => $urlHtml,
                        'control' => $control,
                        'terminos' => [],
                    ];
                }

                // Añadir términos generales del título (policía, proceso unificado, etc.)
                if (!empty($terminosTituloEncontrados)) {
                    $resultadosIndexados[$key]['terminos'] = array_values(array_unique(array_merge(
                        $resultadosIndexados[$key]['terminos'],
                        $terminosTituloEncontrados
                    )));
                }

                // Añadimos los nombres de municipios y los términos de convocatoria/plazas como "términos" adicionales
                if (!empty($municipiosEncontrados)) {
                    $nuevosTerminos = array_merge(
                        array_map(static fn (string $m) => 'municipio: ' . $m, $municipiosEncontrados),
                        $terminosConvocatoria
                    );

                    $resultadosIndexados[$key]['terminos'] = array_values(array_unique(array_merge(
                        $resultadosIndexados[$key]['terminos'],
                        $nuevosTerminos
                    )));
                }
            }

            // Convertir el array indexado a array simple
            $resultados = array_values($resultadosIndexados);
        } catch (\Exception $e) {
            throw new \Exception('Error al procesar el XML del BOE: ' . $e->getMessage());
        }

        return $resultados;
    }

    /**
     * Normaliza texto para búsquedas:
     * - pasa a minúsculas
     * - elimina acentos y caracteres especiales
     * - deja solo letras, números y espacios, compactando espacios
     */
    private function normalizarTextoBusqueda(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        // Quitar acentos y caracteres especiales típicos en castellano/catalán
        $reemplazos = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $texto = strtr($texto, $reemplazos);

        // Eliminar todo lo que no sean letras, números o espacios
        $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto);

        // Compactar espacios múltiples
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto ?? '');
    }
}

