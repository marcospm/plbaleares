<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;

#[Route('/boe')]
#[IsGranted('ROLE_USER')]
class BoeController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient
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
     * - Filtra el departamento de código 8121 (Illes Balears)
     * - Busca términos relacionados con policía / proceso unificado en el título del item
     * - Devuelve los enlaces a PDF / HTML para cada item que tenga coincidencias
     */
    private function procesarXmlBoe(string $xmlContent): array
    {
        $resultados = [];

        try {
            $crawler = new Crawler($xmlContent);

            // Buscar el departamento de Illes Balears (código 8121)
            $departamentos = $crawler->filter('departamento[codigo="8121"]');

            if ($departamentos->count() === 0) {
                return [];
            }

            // Términos a buscar en los títulos (combinando los usados en BOIB)
            $terminosBusqueda = [
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

            foreach ($departamentos as $departamentoNode) {
                $departamentoCrawler = new Crawler($departamentoNode);

                // Todos los <item> dentro de los epígrafes de este departamento
                $items = $departamentoCrawler->filter('item');

                foreach ($items as $itemNode) {
                    $itemCrawler = new Crawler($itemNode);

                    // Extraer información del item
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

                    if ($titulo === '') {
                        continue;
                    }

                    // Buscar términos en el título
                    $tituloLower = mb_strtolower($titulo, 'UTF-8');
                    $terminosEncontrados = [];

                    foreach ($terminosBusqueda as $termino) {
                        $terminoLower = mb_strtolower($termino, 'UTF-8');

                        // Si el término contiene .* lo tratamos como expresión regular
                        if (str_contains($termino, '.*')) {
                            $pattern = '/' . $termino . '/i';
                            if (preg_match($pattern, $titulo)) {
                                $terminosEncontrados[] = $termino;
                            }
                        } else {
                            // Búsqueda simple por substring (insensible a mayúsculas/minúsculas)
                            if (mb_stripos($tituloLower, $terminoLower, 0, 'UTF-8') !== false) {
                                $terminosEncontrados[] = $termino;
                            }
                        }
                    }

                    if (!empty($terminosEncontrados) && $urlPdf !== '') {
                        $terminosEncontrados = array_values(array_unique($terminosEncontrados));

                        $resultados[] = [
                            'identificador' => $identificador,
                            'titulo' => $titulo,
                            'url_pdf' => $urlPdf,
                            'url_html' => $urlHtml,
                            'control' => $control,
                            'terminos' => $terminosEncontrados,
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Error al procesar el XML del BOE: ' . $e->getMessage());
        }

        return $resultados;
    }
}

