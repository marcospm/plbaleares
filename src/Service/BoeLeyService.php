<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use App\Repository\LeyRepository;

class BoeLeyService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger,
        private LeyRepository $leyRepository
    ) {
    }

    /**
     * Obtiene el link del BOE para una ley específica desde la base de datos
     */
    public function getBoeLink(int $leyId): ?string
    {
        $ley = $this->leyRepository->find($leyId);
        
        if ($ley && $ley->getBoeLink()) {
            return $ley->getBoeLink();
        }
        
        return null;
    }

    /**
     * Obtiene la última actualización de una ley desde el BOE
     * Busca en el desplegable "Seleccionar redacción" la fecha más reciente
     */
    public function getUltimaActualizacion(int $leyId): ?\DateTime
    {
        $boeLink = $this->getBoeLink($leyId);
        
        if (!$boeLink) {
            return null;
        }

        try {
            // Hacer la petición al BOE
            $response = $this->httpClient->request('GET', $boeLink, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
                'timeout' => 10,
            ]);

            $html = $response->getContent();
            $crawler = new Crawler($html);
            
            $fechas = [];
            
            // Buscar el texto "Última actualización publicada el DD/MM/YYYY" o 
            // "Texto inicial publicado el DD/MM/YYYY" dentro de un span 
            // que está dentro de un label con clase "version-actual"
            try {
                // Buscar el label con clase version-actual
                $labelVersionActual = $crawler->filter('label.version-actual, .version-actual label, [class*="version-actual"]')->first();
                
                if ($labelVersionActual->count() > 0) {
                    // Buscar el span dentro del label que contiene el texto de última actualización o texto inicial
                    $spanTexto = $labelVersionActual->filter('span')->reduce(function (Crawler $span) {
                        $texto = trim($span->text());
                        return stripos($texto, 'Última actualización publicada el') !== false 
                            || stripos($texto, 'Texto inicial publicado el') !== false
                            || stripos($texto, 'Última actualización') !== false
                            || stripos($texto, 'Texto inicial') !== false;
                    })->first();
                    
                    if ($spanTexto->count() > 0) {
                        $textoCompleto = trim($spanTexto->text());
                        // Buscar el patrón de fecha: "Última actualización publicada el DD/MM/YYYY"
                        if (preg_match('/Última actualización publicada el\s+(\d{1,2}\/\d{1,2}\/\d{4})/i', $textoCompleto, $matches)) {
                            $fecha = $this->parsearFecha($matches[1]);
                            if ($fecha) {
                                $fechas[] = $fecha;
                            }
                        }
                        // Buscar el patrón: "Texto inicial publicado el DD/MM/YYYY"
                        elseif (preg_match('/Texto inicial publicado el\s+(\d{1,2}\/\d{1,2}\/\d{4})/i', $textoCompleto, $matches)) {
                            $fecha = $this->parsearFecha($matches[1]);
                            if ($fecha) {
                                $fechas[] = $fecha;
                            }
                        }
                        // También buscar variaciones sin "publicada el" o "publicado el"
                        elseif (preg_match('/(?:Última actualización|Texto inicial)[^0-9]*(\d{1,2}\/\d{1,2}\/\d{4})/i', $textoCompleto, $matches)) {
                            $fecha = $this->parsearFecha($matches[1]);
                            if ($fecha) {
                                $fechas[] = $fecha;
                            }
                        }
                    }
                }
                
                // Si no se encontró con el método anterior, buscar directamente en el HTML
                if (empty($fechas)) {
                    // Buscar el patrón "Última actualización publicada el" directamente en el HTML
                    if (preg_match('/<label[^>]*class="[^"]*version-actual[^"]*"[^>]*>.*?<span[^>]*>.*?Última actualización publicada el\s+(\d{1,2}\/\d{1,2}\/\d{4})/is', $html, $matches)) {
                        $fecha = $this->parsearFecha($matches[1]);
                        if ($fecha) {
                            $fechas[] = $fecha;
                        }
                    }
                    // Buscar el patrón "Texto inicial publicado el" directamente en el HTML
                    elseif (preg_match('/<label[^>]*class="[^"]*version-actual[^"]*"[^>]*>.*?<span[^>]*>.*?Texto inicial publicado el\s+(\d{1,2}\/\d{1,2}\/\d{4})/is', $html, $matches)) {
                        $fecha = $this->parsearFecha($matches[1]);
                        if ($fecha) {
                            $fechas[] = $fecha;
                        }
                    }
                    // Buscar con variaciones sin "publicada el" o "publicado el"
                    elseif (preg_match('/<label[^>]*class="[^"]*version-actual[^"]*"[^>]*>.*?<span[^>]*>.*?(?:Última actualización|Texto inicial)[^0-9]*(\d{1,2}\/\d{1,2}\/\d{4})/is', $html, $matches)) {
                        $fecha = $this->parsearFecha($matches[1]);
                        if ($fecha) {
                            $fechas[] = $fecha;
                        }
                    }
                }
                
                // También buscar el select dentro de .version-actual para obtener todas las fechas disponibles
                // y seleccionar la más reciente
                $selectElement = $crawler->filter('.version-actual select, label.version-actual select, [class*="version-actual"] select')->first();
                
                if ($selectElement->count() > 0) {
                    // Extraer todas las opciones del select
                    $options = $selectElement->filter('option')->each(function (Crawler $option) {
                        $texto = trim($option->text());
                        $dataFecha = $option->attr('data-fecha');
                        $dataFechaversion = $option->attr('data-fecha-version');
                        $value = $option->attr('value');
                        
                        return [
                            'texto' => $texto,
                            'data-fecha' => $dataFecha,
                            'data-fecha-version' => $dataFechaversion,
                            'value' => $value,
                        ];
                    });
                    
                    foreach ($options as $option) {
                        // Intentar parsear fecha del texto (formato común: DD/MM/YYYY)
                        if (!empty($option['texto'])) {
                            // Buscar fecha en formato DD/MM/YYYY o DD-MM-YYYY
                            if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $option['texto'], $matches)) {
                                $fecha = $this->parsearFecha($matches[1]);
                                if ($fecha) {
                                    $fechas[] = $fecha;
                                }
                            }
                        }
                        
                        // Intentar parsear fecha de data-fecha
                        if (!empty($option['data-fecha'])) {
                            $fecha = $this->parsearFecha($option['data-fecha']);
                            if ($fecha) {
                                $fechas[] = $fecha;
                            }
                        }
                        
                        // Intentar parsear fecha de data-fecha-version
                        if (!empty($option['data-fecha-version'])) {
                            $fecha = $this->parsearFecha($option['data-fecha-version']);
                            if ($fecha) {
                                $fechas[] = $fecha;
                            }
                        }
                        
                        // Intentar parsear fecha del value si parece una fecha
                        if (!empty($option['value'])) {
                            // Buscar fechas en formato YYYYMMDD o similares
                            if (preg_match('/(\d{4}[\-\.\/]\d{1,2}[\-\.\/]\d{1,2}|\d{1,2}[\-\.\/]\d{1,2}[\-\.\/]\d{4}|\d{8})/', $option['value'], $matches)) {
                                $fecha = $this->parsearFecha($matches[1]);
                                if ($fecha) {
                                    $fechas[] = $fecha;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error al buscar .version-actual: ' . $e->getMessage());
            }
            
            // Si no se encontró nada con .version-actual, buscar también por texto "Seleccionar redacción" como fallback
            if (empty($fechas) && stripos($html, 'Seleccionar redacción') !== false) {
                try {
                    // Buscar cualquier select cerca del texto "Seleccionar redacción"
                    $allSelects = $crawler->filter('select');
                    foreach ($allSelects as $selectNode) {
                        $selectCrawler = new Crawler($selectNode);
                        $selectHtml = $selectCrawler->html();
                        $selectText = $selectCrawler->text();
                        
                        // Si el select o sus opciones contienen "redacción" o fechas
                        if (stripos($selectHtml, 'redacción') !== false || stripos($selectText, 'redacción') !== false) {
                            $options = $selectCrawler->filter('option');
                            foreach ($options as $optionNode) {
                                $optionCrawler = new Crawler($optionNode);
                                $texto = trim($optionCrawler->text());
                                
                                if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $texto, $matches)) {
                                    $fecha = $this->parsearFecha($matches[1]);
                                    if ($fecha) {
                                        $fechas[] = $fecha;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Error en fallback de búsqueda: ' . $e->getMessage());
                }
            }
            
            // Ordenar fechas y obtener la más reciente
            if (!empty($fechas)) {
                // Eliminar duplicados comparando timestamps
                $fechasUnicas = [];
                foreach ($fechas as $fecha) {
                    $timestamp = $fecha->getTimestamp();
                    if (!isset($fechasUnicas[$timestamp])) {
                        $fechasUnicas[$timestamp] = $fecha;
                    }
                }
                $fechas = array_values($fechasUnicas);
                
                // Ordenar de más reciente a más antigua
                usort($fechas, function($a, $b) {
                    return $b <=> $a;
                });
                
                return $fechas[0];
            }
            
            // Si no se encuentra el desplegable, intentar buscar la fecha de publicación original
            // que suele estar en el encabezado del documento
            if (preg_match('/Publicado en.*?BOE.*?núm\.\s*\d+.*?de\s*([\d]{1,2})\s+de\s+([A-Za-z]+)\s+de\s+([\d]{4})/i', $html, $match)) {
                $dia = (int)$match[1];
                $mesStr = $match[2];
                $ano = (int)$match[3];
                $mes = $this->convertirMes($mesStr);
                
                if ($mes) {
                    try {
                        return new \DateTime(sprintf('%d-%02d-%02d', $ano, $mes, $dia));
                    } catch (\Exception $e) {
                        $this->logger->warning('Error al parsear fecha del BOE: ' . $e->getMessage());
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error al obtener última actualización del BOE para ley ' . $leyId . ': ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Parsea una fecha en formato español (DD/MM/YYYY) a DateTime
     */
    private function parsearFecha(string $fechaStr): ?\DateTime
    {
        $fechaStr = trim($fechaStr);
        
        // Normalizar separadores
        $fechaStr = str_replace(['.', '-'], '/', $fechaStr);
        
        // Intentar varios formatos
        $formatos = [
            'd/m/Y',    // 21/11/2003
            'd/m/y',    // 21/11/03
            'Y/m/d',    // 2003/11/21
            'y/m/d',    // 03/11/21
            'd-m-Y',    // 21-11-2003
            'Y-m-d',    // 2003-11-21
        ];
        
        foreach ($formatos as $formato) {
            try {
                $fecha = \DateTime::createFromFormat($formato, $fechaStr);
                // Verificar que la fecha es válida (createFromFormat puede devolver false o fechas incorrectas)
                if ($fecha && $fecha->format($formato) === $fechaStr) {
                    // Si el año tiene solo 2 dígitos, convertirlo a 4
                    if (strlen(explode('/', $fechaStr)[2] ?? '') === 2) {
                        $ano = (int)$fecha->format('y');
                        if ($ano < 50) {
                            $fecha->setDate(2000 + $ano, (int)$fecha->format('m'), (int)$fecha->format('d'));
                        } else {
                            $fecha->setDate(1900 + $ano, (int)$fecha->format('m'), (int)$fecha->format('d'));
                        }
                    }
                    return $fecha;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Si no se pudo parsear con formatos conocidos, intentar con strtotime
        try {
            $timestamp = strtotime($fechaStr);
            if ($timestamp !== false) {
                return new \DateTime('@' . $timestamp);
            }
        } catch (\Exception $e) {
            // Ignorar error
        }
        
        return null;
    }

    /**
     * Convierte el nombre del mes en español a número
     */
    private function convertirMes(string $mesStr): ?int
    {
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];
        
        $mesLower = strtolower(trim($mesStr));
        
        return $meses[$mesLower] ?? null;
    }

    /**
     * Obtiene información completa de una ley desde el BOE
     */
    public function getInfoLey(int $leyId): array
    {
        $boeLink = $this->getBoeLink($leyId);
        $ultimaActualizacion = $this->getUltimaActualizacion($leyId);
        
        return [
            'boe_link' => $boeLink,
            'ultima_actualizacion' => $ultimaActualizacion,
            'tiene_link' => $boeLink !== null,
        ];
    }
}

