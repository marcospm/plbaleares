<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;
use Smalot\PdfParser\Parser;

#[Route('/boib')]
#[IsGranted('ROLE_USER')]
class BoibController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    #[Route('/', name: 'app_boib_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $xmlContent = null;
        $error = null;
        $buscando = false;
        $debug = [];
        $resultados = [];
        

        // Cargar automáticamente los boletines al entrar (últimos 16)
        $buscando = true;
        $debug[] = 'Cargando boletines automáticamente';
            
            try {
                // URL del feed RSS del BOIB (en español)
                $rssUrl = 'https://www.caib.es/eboibfront/indexrss.do?lang=es';
                $debug[] = 'URL: ' . $rssUrl;
                
                $response = $this->httpClient->request('GET', $rssUrl, [
                    'timeout' => 15,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'application/rss+xml, application/xml, text/xml',
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $debug[] = 'Código de estado HTTP: ' . $statusCode;
                
                if ($statusCode === 200) {
                    $xmlContent = $response->getContent();
                    $debug[] = 'Contenido descargado. Tamaño: ' . strlen($xmlContent) . ' bytes';
                    
                    // Verificar que el contenido no esté vacío
                    if (empty(trim($xmlContent))) {
                        $error = 'El feed RSS está vacío';
                        $debug[] = 'ERROR: El contenido está vacío';
                    } else {
                        $debug[] = 'XML válido, primeros 100 caracteres: ' . substr($xmlContent, 0, 100);
                        
                        // Parsear el XML y extraer los items
                        try {
                            $crawler = new Crawler($xmlContent);
                            $items = $crawler->filter('item');
                            $debug[] = 'Items encontrados: ' . $items->count();
                            
                            $itemsData = [];
                            $contador = 0;
                            $limite = 16; // Limitar a los últimos 16 boletines
                            
                            foreach ($items as $itemNode) {
                                // Limitar a los primeros 16 boletines
                                if ($contador >= $limite) {
                                    break;
                                }
                                $contador++;
                                $itemCrawler = new Crawler($itemNode);
                                
                                $title = $itemCrawler->filter('title')->count() > 0 ? trim($itemCrawler->filter('title')->text()) : '';
                                $link = $itemCrawler->filter('link')->count() > 0 ? trim($itemCrawler->filter('link')->text()) : '';
                                $pubDate = $itemCrawler->filter('pubDate')->count() > 0 ? trim($itemCrawler->filter('pubDate')->text()) : '';
                                $dcDate = $itemCrawler->filter('dc\\:date')->count() > 0 ? trim($itemCrawler->filter('dc\\:date')->text()) : '';
                                
                                // Extraer el último número del link
                                // Ejemplo: https://www.caib.es/eboibfront/es/2026/12210 -> 12210
                                $ultimoNumero = null;
                                if ($link && preg_match('/\/(\d+)$/', $link, $matches)) {
                                    $ultimoNumero = $matches[1];
                                }
                                
                                // Extraer número del boletín del título
                                // Ejemplo: "BOIB Núm 001/2026" -> 1
                                $numeroBoletin = null;
                                if ($title && preg_match('/Núm\s+(\d+)\//i', $title, $matches)) {
                                    $numeroBoletin = (int)$matches[1];
                                }
                                
                                // Extraer año del link
                                // Ejemplo: https://www.caib.es/eboibfront/es/2026/12210 -> 2026
                                $ano = null;
                                if ($link && preg_match('/\/(\d{4})\/\d+$/', $link, $matches)) {
                                    $ano = (int)$matches[1];
                                }
                                
                                // Construir URL del sumario
                                // Formato: http://caib.es/eboibfront/pdf/es/{año}/{número_boletín}/sumari/{último_número}
                                $urlSumario = null;
                                if ($ano && $numeroBoletin && $ultimoNumero) {
                                    $urlSumario = sprintf(
                                        'http://caib.es/eboibfront/pdf/es/%d/%d/sumari/%s',
                                        $ano,
                                        $numeroBoletin,
                                        $ultimoNumero
                                    );
                                }
                                
                                // Procesar fecha
                                $fecha = null;
                                $fechaFormateada = '';
                                if ($dcDate) {
                                    try {
                                        $fechaObj = new \DateTime($dcDate);
                                        $fecha = $fechaObj->format('Y-m-d');
                                        $fechaFormateada = $fechaObj->format('d/m/Y');
                                    } catch (\Exception $e) {
                                        // Intentar con pubDate
                                        if ($pubDate) {
                                            $timestamp = strtotime($pubDate);
                                            if ($timestamp) {
                                                $fecha = date('Y-m-d', $timestamp);
                                                $fechaFormateada = date('d/m/Y', $timestamp);
                                            }
                                        }
                                    }
                                } elseif ($pubDate) {
                                    $timestamp = strtotime($pubDate);
                                    if ($timestamp) {
                                        $fecha = date('Y-m-d', $timestamp);
                                        $fechaFormateada = date('d/m/Y', $timestamp);
                                    }
                                }
                                
                                $itemsData[] = [
                                    'titulo' => $title,
                                    'link' => $link,
                                    'fecha' => $fecha,
                                    'fechaFormateada' => $fechaFormateada,
                                    'pubDate' => $pubDate,
                                    'urlSumario' => $urlSumario,
                                    'numeroBoletin' => $numeroBoletin,
                                    'ano' => $ano,
                                ];
                            }
                            
                            $xmlContent = null; // Ya no necesitamos el XML crudo
                            $resultados = $itemsData;
                            
                        } catch (\Exception $e) {
                            $error = 'Error al parsear el XML: ' . $e->getMessage();
                            $debug[] = 'ERROR al parsear: ' . $e->getMessage();
                        }
                    }
                } else {
                    $error = 'Error al acceder al feed RSS. Código de estado: ' . $statusCode;
                    $debug[] = 'ERROR: Código de estado no es 200';
                }
            } catch (\Exception $e) {
                $error = 'Error al buscar en el BOIB: ' . $e->getMessage() . ' (Tipo: ' . get_class($e) . ')';
                $debug[] = 'EXCEPCIÓN: ' . $e->getMessage();
                $debug[] = 'Trace: ' . $e->getTraceAsString();
            }

        // Si hay XML, asegurarse de que se pasa como string
        if ($xmlContent !== null) {
            $xmlContent = (string) $xmlContent;
        }

        return $this->render('boib/index.html.twig', [
            'xmlContent' => $xmlContent,
            'resultados' => $resultados ?? [],
            'error' => $error,
            'buscando' => $buscando,
            'debug' => $debug,
        ]);
    }

    #[Route('/consultar', name: 'app_boib_consultar', methods: ['GET'])]
    public function consultarSumario(Request $request): Response
    {
        $url = $request->query->get('consultar');
        
        if (!$url) {
            return new JsonResponse([
                'success' => false,
                'error' => 'URL no proporcionada',
            ], 400);
        }
        
        try {
            // Descargar el PDF
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 20,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/pdf, */*',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Error al descargar el PDF. Código: ' . $response->getStatusCode(),
                ], 500);
            }

            $pdfContent = $response->getContent();
            
            // Extraer texto del PDF
            $texto = $this->extraerTextoDePdf($pdfContent);
            
            if (empty($texto)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No se pudo extraer texto del PDF. El PDF puede estar en un formato no soportado o estar vacío.',
                    'tamaño_pdf' => strlen($pdfContent),
                ], 500);
            }
            
            // Términos a buscar
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
                'plaza.*policía',
                'plaza.*policia',
                'policía.*plaza',
                'policia.*plaza',
                'convocatoria.*policía',
                'convocatoria.*policia',
                'bases.*policía',
                'bases.*policia',
            ];
            
            // Buscar términos en el texto
            $textoLower = mb_strtolower($texto, 'UTF-8');
            $extractos = [];
            $terminosEncontrados = [];
            
            foreach ($terminosBusqueda as $termino) {
                $terminoLower = mb_strtolower($termino, 'UTF-8');
                
                // Si el término contiene .* es una expresión regular
                if (str_contains($termino, '.*')) {
                    $pattern = '/' . $termino . '/i';
                    if (preg_match_all($pattern, $texto, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $pos = $match[1];
                            $terminoEncontrado = $match[0];
                            
                            // Extraer contexto alrededor (300 caracteres antes y después)
                            $inicio = max(0, $pos - 300);
                            $longitud = min(600, mb_strlen($texto, 'UTF-8') - $inicio);
                            $extracto = mb_substr($texto, $inicio, $longitud, 'UTF-8');
                            
                            // Asegurar que el extracto comience y termine en palabras completas
                            if ($inicio > 0) {
                                $primerEspacio = mb_strpos($extracto, ' ', 0, 'UTF-8');
                                if ($primerEspacio !== false && $primerEspacio < 50) {
                                    $extracto = '...' . mb_substr($extracto, $primerEspacio + 1, null, 'UTF-8');
                                } else {
                                    $extracto = '...' . $extracto;
                                }
                            }
                            
                            $ultimoEspacio = mb_strrpos($extracto, ' ', 0, 'UTF-8');
                            if ($ultimoEspacio !== false && (mb_strlen($extracto, 'UTF-8') - $ultimoEspacio) < 50) {
                                $extracto = mb_substr($extracto, 0, $ultimoEspacio, 'UTF-8') . '...';
                            } else {
                                $extracto = $extracto . '...';
                            }
                            
                            $extractos[] = [
                                'termino' => $terminoEncontrado,
                                'texto' => trim($extracto),
                                'posicion' => $pos,
                            ];
                            
                            if (!in_array($terminoEncontrado, $terminosEncontrados)) {
                                $terminosEncontrados[] = $terminoEncontrado;
                            }
                        }
                    }
                } else {
                    // Búsqueda simple
                    $pos = 0;
                    while (($pos = mb_stripos($textoLower, $terminoLower, $pos, 'UTF-8')) !== false) {
                        // Extraer contexto alrededor
                        $inicio = max(0, $pos - 300);
                        $longitud = min(600, mb_strlen($texto, 'UTF-8') - $inicio);
                        $extracto = mb_substr($texto, $inicio, $longitud, 'UTF-8');
                        
                        // Asegurar que el extracto comience y termine en palabras completas
                        if ($inicio > 0) {
                            $primerEspacio = mb_strpos($extracto, ' ', 0, 'UTF-8');
                            if ($primerEspacio !== false && $primerEspacio < 50) {
                                $extracto = '...' . mb_substr($extracto, $primerEspacio + 1, null, 'UTF-8');
                            } else {
                                $extracto = '...' . $extracto;
                            }
                        }
                        
                        $ultimoEspacio = mb_strrpos($extracto, ' ', 0, 'UTF-8');
                        if ($ultimoEspacio !== false && (mb_strlen($extracto, 'UTF-8') - $ultimoEspacio) < 50) {
                            $extracto = mb_substr($extracto, 0, $ultimoEspacio, 'UTF-8') . '...';
                        } else {
                            $extracto = $extracto . '...';
                        }
                        
                        $extractos[] = [
                            'termino' => $termino,
                            'texto' => trim($extracto),
                            'posicion' => $pos,
                        ];
                        
                        if (!in_array($termino, $terminosEncontrados)) {
                            $terminosEncontrados[] = $termino;
                        }
                        
                        $pos += mb_strlen($terminoLower, 'UTF-8');
                    }
                }
            }
            
            // Ordenar extractos por posición en el texto
            usort($extractos, function($a, $b) {
                return $a['posicion'] <=> $b['posicion'];
            });
            
            return new JsonResponse([
                'success' => true,
                'url' => $url,
                'terminosEncontrados' => array_unique($terminosEncontrados),
                'extractos' => $extractos,
                'totalExtractos' => count($extractos),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function buscarEnBoib(?int $limite = null): array
    {
        $resultados = [];
        
        // Palabras clave a buscar
        $palabrasClave = [
            'policía',
            'policia',
            'policía local',
            'policia local',
            'subinspector',
            'subinspector/a',
            'subinspectora',
            'agente de policía',
            'agente de policia',
            'proceso unificado',
            'OEP',
            'oferta de empleo público',
            'plaza.*policía',
            'plaza.*policia',
        ];

        // URL del feed RSS del BOIB
        $rssUrl = 'https://www.caib.es/eboibfront/indexrss.do?lang=ca';
        
        try {
            $response = $this->httpClient->request('GET', $rssUrl, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('No se pudo acceder al feed RSS del BOIB');
            }

            $xml = $response->getContent();
            $crawler = new Crawler($xml);
            
            $items = $crawler->filter('item');
            
            $contador = 0;
            foreach ($items as $itemNode) {
                // Si hay límite, solo procesar los primeros N boletines
                if ($limite !== null && $contador >= $limite) {
                    break;
                }
                $contador++;
                $itemCrawler = new Crawler($itemNode);
                
                // Extraer información del item
                $title = $itemCrawler->filter('title')->count() > 0 ? trim($itemCrawler->filter('title')->text()) : '';
                $link = $itemCrawler->filter('link')->count() > 0 ? trim($itemCrawler->filter('link')->text()) : '';
                $pubDate = $itemCrawler->filter('pubDate')->count() > 0 ? trim($itemCrawler->filter('pubDate')->text()) : '';
                $dcDate = $itemCrawler->filter('dc\\:date')->count() > 0 ? trim($itemCrawler->filter('dc\\:date')->text()) : '';
                
                if (empty($link)) {
                    continue;
                }
                
                // Extraer el último número del link
                // Ejemplo: https://www.caib.es/eboibfront/ca/2025/12210 -> 12210
                $ultimoNumero = $this->extraerUltimoNumeroDelLink($link);
                if (!$ultimoNumero) {
                    continue;
                }
                
                // Extraer número del boletín del título
                // Ejemplo: "BOIB Núm 164/2025" -> 164
                $numeroBoletin = $this->extraerNumeroBoletin($title);
                if (!$numeroBoletin) {
                    continue;
                }
                
                // Extraer año del link
                // Ejemplo: https://www.caib.es/eboibfront/ca/2025/12210 -> 2025
                $ano = $this->extraerAnoDelLink($link);
                if (!$ano) {
                    continue;
                }
                
                // Construir URL del sumario
                // Formato: http://caib.es/eboibfront/pdf/ca/2025/164/sumari/12210
                $urlSumario = sprintf(
                    'http://caib.es/eboibfront/pdf/ca/%d/%d/sumari/%s',
                    $ano,
                    $numeroBoletin,
                    $ultimoNumero
                );
                
                // Buscar en el PDF del sumario
                $coincidencias = $this->buscarEnPdf($urlSumario, $palabrasClave);
                
                if (!empty($coincidencias)) {
                    // Procesar fecha
                    $fecha = null;
                    if ($dcDate) {
                        try {
                            $fechaObj = new \DateTime($dcDate);
                            $fecha = $fechaObj->format('Y-m-d');
                        } catch (\Exception $e) {
                            // Intentar con pubDate
                            if ($pubDate) {
                                $timestamp = strtotime($pubDate);
                                if ($timestamp) {
                                    $fecha = date('Y-m-d', $timestamp);
                                }
                            }
                        }
                    } elseif ($pubDate) {
                        $timestamp = strtotime($pubDate);
                        if ($timestamp) {
                            $fecha = date('Y-m-d', $timestamp);
                        }
                    }
                    
                    $resultados[] = [
                        'titulo' => $title,
                        'numero' => $numeroBoletin,
                        'ano' => $ano,
                        'url' => $urlSumario,
                        'urlOriginal' => $link,
                        'fecha' => $fecha,
                        'coincidencias' => $coincidencias,
                    ];
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Error al procesar el feed RSS: ' . $e->getMessage());
        }

        // Ordenar por fecha (más recientes primero)
        usort($resultados, function($a, $b) {
            if ($a['fecha'] && $b['fecha']) {
                return strcmp($b['fecha'], $a['fecha']);
            }
            // Si no hay fecha, ordenar por número de boletín descendente
            return $b['numero'] <=> $a['numero'];
        });

        return $resultados;
    }

    private function extraerUltimoNumeroDelLink(string $link): ?string
    {
        // Extraer el último número del path
        // Ejemplo: https://www.caib.es/eboibfront/ca/2025/12210 -> 12210
        if (preg_match('/\/(\d+)$/', $link, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extraerNumeroBoletin(string $title): ?int
    {
        // Extraer número del boletín del título
        // Ejemplo: "BOIB Núm 164/2025" -> 164
        if (preg_match('/Núm\s+(\d+)\//i', $title, $matches)) {
            return (int)$matches[1];
        }
        // También intentar sin "Núm"
        if (preg_match('/(\d+)\/\d{4}/', $title, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    private function extraerAnoDelLink(string $link): ?int
    {
        // Extraer año del link
        // Ejemplo: https://www.caib.es/eboibfront/ca/2025/12210 -> 2025
        if (preg_match('/\/(\d{4})\/\d+$/', $link, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    private function buscarEnPdf(string $urlPdf, array $palabrasClave): array
    {
        $resultado = [
            'palabras' => [],
            'extractos' => [],
        ];
        
        try {
            // Intentar descargar el PDF
            $response = $this->httpClient->request('GET', $urlPdf, [
                'timeout' => 20,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/pdf, */*',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $pdfContent = $response->getContent();
            
            // Intentar extraer texto del PDF
            // Primero intentar con pdftotext si está disponible
            $texto = $this->extraerTextoDePdf($pdfContent);
            
            if (empty($texto)) {
                // Si no se puede extraer texto, devolver vacío
                // El PDF existe pero no podemos leerlo
                return [];
            }
            
            $textoLower = mb_strtolower($texto, 'UTF-8');
            
            // Buscar palabras clave
            foreach ($palabrasClave as $palabra) {
                $palabraLower = mb_strtolower($palabra, 'UTF-8');
                
                // Para OEP, verificar que también mencione policía
                if ($palabraLower === 'oep' || $palabraLower === 'oferta de empleo público') {
                    if (str_contains($textoLower, 'oep') || str_contains($textoLower, 'oferta de empleo público')) {
                        // Verificar que también mencione policía
                        if (str_contains($textoLower, 'policía') || str_contains($textoLower, 'policia')) {
                            $resultado['palabras'][] = $palabra;
                        }
                    }
                } else {
                    if (str_contains($textoLower, $palabraLower)) {
                        $resultado['palabras'][] = $palabra;
                    }
                }
            }
            
            // Si hay coincidencias, guardar un extracto del texto relevante
            if (!empty($resultado['palabras'])) {
                // Buscar contexto alrededor de las coincidencias
                $extractos = [];
                foreach ($resultado['palabras'] as $coincidencia) {
                    $pos = mb_stripos($textoLower, mb_strtolower($coincidencia, 'UTF-8'));
                    if ($pos !== false) {
                        $inicio = max(0, $pos - 100);
                        $longitud = min(300, mb_strlen($texto, 'UTF-8') - $inicio);
                        $extracto = mb_substr($texto, $inicio, $longitud, 'UTF-8');
                        $extractos[] = '...' . trim($extracto) . '...';
                    }
                }
                $resultado['extractos'] = array_slice($extractos, 0, 3); // Máximo 3 extractos
            }
            
        } catch (\Exception $e) {
            // Si hay error al descargar o procesar el PDF, no devolver coincidencias
            // pero no fallar completamente
            return [];
        }

        // Solo devolver si hay palabras encontradas
        return !empty($resultado['palabras']) ? $resultado : [];
    }

    private function extraerTextoDePdf(string $pdfContent): string
    {
        // Método 1: Usar la librería smalot/pdfparser (más confiable)
        try {
            $parser = new Parser();
            $pdf = $parser->parseContent($pdfContent);
            $texto = $pdf->getText();
            
            if (!empty(trim($texto))) {
                return $texto;
            }
        } catch (\Exception $e) {
            // Si falla, continuar con otros métodos
        }
        
        // Método 2: Usar pdftotext si está disponible en el sistema
        $tempFile = sys_get_temp_dir() . '/boib_' . uniqid() . '.pdf';
        try {
            file_put_contents($tempFile, $pdfContent);
            
            // Intentar usar pdftotext con opciones para mejor extracción
            $output = [];
            $returnVar = 0;
            $command = 'pdftotext -layout -nopgbrk "' . escapeshellarg($tempFile) . '" - 2>&1';
            @exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                $texto = implode("\n", $output);
                @unlink($tempFile);
                if (!empty(trim($texto))) {
                    return $texto;
                }
            }
            
            @unlink($tempFile);
        } catch (\Exception $e) {
            @unlink($tempFile);
        }
        
        // Método 3: Intentar con el método básico mejorado (último recurso)
        $texto = $this->extraerTextoBasicoPdf($pdfContent);
        if (!empty($texto)) {
            return $texto;
        }
        
        return '';
    }

    private function extraerTextoBasicoPdf(string $pdfContent): string
    {
        // Extracción mejorada de texto de PDF con manejo de compresión
        $texto = '';
        
        // Método 1: Buscar y descomprimir streams de PDF
        // Los PDFs modernos suelen tener el texto comprimido en streams
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $pdfContent, $streamMatches, PREG_SET_ORDER)) {
            foreach ($streamMatches as $streamMatch) {
                $streamData = $streamMatch[1];
                
                // Buscar el diccionario del stream para ver si está comprimido
                $streamStart = strpos($pdfContent, $streamMatch[0]) - 500;
                $streamDict = substr($pdfContent, max(0, $streamStart), 500);
                
                // Intentar descomprimir si está comprimido con FlateDecode
                $decompressed = false;
                if (strpos($streamDict, '/FlateDecode') !== false || strpos($streamDict, '/FlateDecode') !== false) {
                    // Intentar descomprimir con gzuncompress
                    $decompressed = @gzuncompress($streamData);
                    if ($decompressed === false) {
                        // Intentar con inflate
                        $decompressed = @gzinflate($streamData);
                    }
                    if ($decompressed === false) {
                        // Intentar sin los primeros bytes (a veces hay headers)
                        $decompressed = @gzuncompress(substr($streamData, 2));
                    }
                }
                
                $dataToProcess = $decompressed !== false ? $decompressed : $streamData;
                
                // Extraer texto del stream (descomprimido o no)
                // Buscar texto entre paréntesis
                if (preg_match_all('/\((.*?)\)/s', $dataToProcess, $textMatches)) {
                    foreach ($textMatches[1] as $textMatch) {
                        // Decodificar secuencias de escape comunes en PDFs
                        $textMatch = str_replace(['\\n', '\\r', '\\t'], [' ', ' ', ' '], $textMatch);
                        $textMatch = preg_replace('/\\\\([0-9]{3})/', '', $textMatch); // Eliminar códigos octales
                        
                        // Filtrar caracteres de control
                        $linea = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F-\x9F]/', '', $textMatch);
                        
                        // Mantener caracteres legibles
                        $linea = preg_replace('/[^\x20-\x7E\xC0-\xFF]/u', '', $linea);
                        
                        if (mb_strlen(trim($linea), 'UTF-8') > 2) {
                            $texto .= trim($linea) . ' ';
                        }
                    }
                }
                
                // También buscar texto entre corchetes (otro formato común)
                if (preg_match_all('/\[(.*?)\]/s', $dataToProcess, $bracketMatches)) {
                    foreach ($bracketMatches[1] as $bracketMatch) {
                        $linea = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $bracketMatch);
                        $linea = preg_replace('/[^\x20-\x7E\xC0-\xFF]/u', '', $linea);
                        if (mb_strlen(trim($linea), 'UTF-8') > 2) {
                            $texto .= trim($linea) . ' ';
                        }
                    }
                }
                
                // Buscar texto directamente en el stream (palabras completas)
                if (preg_match_all('/[A-Za-zÁÉÍÓÚáéíóúÑñÜü][A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]{5,}/u', $dataToProcess, $wordMatches)) {
                    foreach ($wordMatches[0] as $word) {
                        $word = trim($word);
                        // Filtrar palabras que parezcan legibles (tienen vocales)
                        if (mb_strlen($word, 'UTF-8') > 3 && preg_match('/[aeiouáéíóúAEIOUÁÉÍÓÚ]{1,}/ui', $word)) {
                            $texto .= $word . ' ';
                        }
                    }
                }
            }
        }
        
        // Método 2: Buscar texto directamente en el PDF sin streams (para PDFs simples)
        if (empty($texto) || mb_strlen($texto, 'UTF-8') < 50) {
            // Buscar texto entre paréntesis en todo el PDF
            if (preg_match_all('/\((.*?)\)/s', $pdfContent, $directMatches)) {
                foreach ($directMatches[1] as $match) {
                    $linea = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $match);
                    $linea = preg_replace('/[^\x20-\x7E\xC0-\xFF]/u', '', $linea);
                    if (mb_strlen(trim($linea), 'UTF-8') > 2) {
                        $texto .= trim($linea) . ' ';
                    }
                }
            }
        }
        
        // Limpiar y normalizar el texto
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = preg_replace('/\s*([,\.;:!?])\s*/', '$1 ', $texto); // Normalizar puntuación
        $texto = trim($texto);
        
        return $texto;
    }
}
