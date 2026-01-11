<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use App\Repository\LeyRepository;
use App\Repository\ArticuloRepository;
use \SimpleXMLElement;

class BoeLeyService
{
    private const CACHE_PREFIX = 'boe_ley_';
    private const CACHE_TTL = 86400; // 1 día en segundos

    public function __construct(
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface $logger,
        private LeyRepository $leyRepository,
        private ArticuloRepository $articuloRepository,
        private CacheItemPoolInterface $cache
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
     * Convierte la URL de la API del BOE a la URL de visualización
     * De: https://boe.es/datosabiertos/api/legislacion-consolidada/id/BOE-A-2025-12199
     * A: https://www.boe.es/buscar/act.php?id=BOE-A-2025-12199
     */
    public function convertirUrlApiAVisualizacion(?string $apiUrl): ?string
    {
        if (!$apiUrl) {
            return null;
        }

        // Patrón para extraer el ID de la norma de la URL de la API
        // Ejemplo: https://boe.es/datosabiertos/api/legislacion-consolidada/id/BOE-A-2025-12199
        if (preg_match('#/datosabiertos/api/legislacion-consolidada/id/([^/]+)#', $apiUrl, $matches)) {
            $idNorma = $matches[1];
            return 'https://www.boe.es/buscar/act.php?id=' . urlencode($idNorma);
        }

        // Si no coincide con el patrón de la API, devolver la URL original (por si acaso ya está en formato de visualización)
        return $apiUrl;
    }

    /**
     * Obtiene y cachea el XML parseado de una ley desde la API del BOE
     * Cachea el resultado por 1 día para evitar múltiples peticiones
     */
    private function getXmlCached(int $leyId): ?\SimpleXMLElement
    {
        $boeLink = $this->getBoeLink($leyId);
        
        if (!$boeLink) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . $leyId;
        $cacheItem = $this->cache->getItem($cacheKey);

        // Intentar obtener del caché
        if ($cacheItem->isHit()) {
            $xmlContent = $cacheItem->get();
            if (!empty($xmlContent)) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($xmlContent);
                if ($xml !== false) {
                    return $xml;
                }
            }
        }

        // Si no está en caché o es inválido, hacer la petición
        try {
            $response = $this->httpClient->request('GET', $boeLink, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/xml, text/xml, */*',
                ],
                'timeout' => 10,
            ]);

            $xmlContent = $response->getContent();
            
            // Parsear el XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            
            if ($xml === false) {
                $this->logger->warning('Error al parsear XML del BOE para ley ' . $leyId);
                return null;
            }

            // Guardar en caché por 1 día
            $cacheItem->set($xmlContent);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            return $xml;
            
        } catch (\Exception $e) {
            $this->logger->error('Error al obtener XML del BOE para ley ' . $leyId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la última actualización de una ley desde la API del BOE
     * Busca en los tags <version> la fecha de publicación más reciente
     * 
     * @param int $leyId ID de la ley
     * @param \SimpleXMLElement|null $xml XML parseado (opcional, si no se proporciona se obtiene del caché)
     */
    public function getUltimaActualizacion(int $leyId, ?\SimpleXMLElement $xml = null): ?\DateTime
    {
        if ($xml === null) {
            $xml = $this->getXmlCached($leyId);
        }
        
        if ($xml === null) {
            return null;
        }

        try {
            $fechas = [];
            
            // Buscar todos los tags <version> con atributo fecha_publicacion
            $versions = $xml->xpath('//version[@fecha_publicacion]');
            
            if ($versions === false || empty($versions)) {
                // Intentar con namespace si existe
                $xml->registerXPathNamespace('boe', 'http://www.boe.es/ns/XML/legislacion');
                $versions = $xml->xpath('//boe:version[@fecha_publicacion] | //version[@fecha_publicacion]');
            }
            
            if ($versions && !empty($versions)) {
                foreach ($versions as $version) {
                    $fechaPublicacion = (string)$version['fecha_publicacion'];
                    
                    if (!empty($fechaPublicacion)) {
                        // Formato: YYYYMMDD (ejemplo: 20250617)
                        $fecha = $this->parsearFechaApi($fechaPublicacion);
                        if ($fecha) {
                            $fechas[] = $fecha;
                        }
                    }
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
            
        } catch (\Exception $e) {
            $this->logger->error('Error al obtener última actualización del BOE para ley ' . $leyId . ': ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Parsea una fecha en formato YYYYMMDD (API del BOE) a DateTime
     */
    private function parsearFechaApi(string $fechaStr): ?\DateTime
    {
        $fechaStr = trim($fechaStr);
        
        // Formato esperado: YYYYMMDD (ejemplo: 20250617)
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $fechaStr, $matches)) {
            try {
                return new \DateTime(sprintf('%d-%02d-%02d', (int)$matches[1], (int)$matches[2], (int)$matches[3]));
            } catch (\Exception $e) {
                $this->logger->warning('Error al parsear fecha de la API: ' . $fechaStr . ' - ' . $e->getMessage());
            }
        }
        
        return null;
    }

    /**
     * Obtiene los artículos y otros elementos afectados por la última actualización
     * Identifica los bloques que tienen una versión con la fecha de última actualización
     * Retorna un array con dos claves: 'articulos' (objetos Articulo) y 'otros' (arrays con info del XML)
     * 
     * @param int $leyId ID de la ley
     * @param \SimpleXMLElement|null $xml XML parseado (opcional, si no se proporciona se obtiene del caché)
     * @param \DateTime|null $ultimaActualizacion Fecha de última actualización (opcional, si no se proporciona se calcula)
     * @return array{'articulos': array, 'otros': array}
     */
    public function getArticulosAfectados(int $leyId, ?\SimpleXMLElement $xml = null, ?\DateTime $ultimaActualizacion = null): array
    {
        if ($xml === null) {
            $xml = $this->getXmlCached($leyId);
        }
        
        if ($xml === null) {
            return ['articulos' => [], 'otros' => []];
        }

        try {
            // Obtener la última fecha de actualización si no se proporciona
            if ($ultimaActualizacion === null) {
                $ultimaActualizacion = $this->getUltimaActualizacion($leyId, $xml);
            }
            
            if ($ultimaActualizacion === null) {
                return ['articulos' => [], 'otros' => []];
            }
            
            $fechaUltimaStr = $ultimaActualizacion->format('Ymd');
            
            // Buscar todos los bloques que tienen una versión con la fecha de última actualización
            $bloques = $xml->xpath("//bloque[version[@fecha_publicacion='{$fechaUltimaStr}']]");
            
            if ($bloques === false || empty($bloques)) {
                // Intentar con namespace si existe
                $xml->registerXPathNamespace('boe', 'http://www.boe.es/ns/XML/legislacion');
                $bloques = $xml->xpath("//boe:bloque[boe:version[@fecha_publicacion='{$fechaUltimaStr}']] | //bloque[version[@fecha_publicacion='{$fechaUltimaStr}']]");
            }
            
            $numerosArticulosAfectados = [];
            $otrosElementosAfectados = [];
            
            if ($bloques && !empty($bloques)) {
                foreach ($bloques as $bloque) {
                    $id = (string)$bloque['id'];
                    $tipo = (string)$bloque['tipo'] ?? 'precepto';
                    $titulo = (string)$bloque['titulo'] ?? $id;
                    
                    // El id tiene formato "a5" donde 5 es el número del artículo
                    // También puede ser "a5bis", "a5ter", etc.
                    if (preg_match('/^a(\d+)([a-z]*)$/i', $id, $matches)) {
                        $numero = (int)$matches[1];
                        $sufijo = !empty($matches[2]) ? $matches[2] : null;
                        
                        $numerosArticulosAfectados[] = [
                            'numero' => $numero,
                            'sufijo' => $sufijo,
                        ];
                    } else {
                        // Es otro tipo de elemento (anexo, definición, etc.)
                        $otrosElementosAfectados[] = [
                            'id' => $id,
                            'tipo' => $tipo,
                            'titulo' => $titulo,
                        ];
                    }
                }
            }
            
            $articulosAfectados = [];
            $ley = $this->leyRepository->find($leyId);
            
            // Buscar artículos en la base de datos
            if (!empty($numerosArticulosAfectados) && $ley) {
                foreach ($numerosArticulosAfectados as $numArticulo) {
                    $articulo = $this->articuloRepository->findOneBy([
                        'ley' => $ley,
                        'numero' => $numArticulo['numero'],
                        'sufijo' => $numArticulo['sufijo'],
                    ]);
                    
                    if ($articulo) {
                        $articulosAfectados[] = $articulo;
                    }
                }
            }
            
            return [
                'articulos' => $articulosAfectados,
                'otros' => $otrosElementosAfectados,
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Error al obtener artículos afectados del BOE para ley ' . $leyId . ': ' . $e->getMessage());
        }
        
        return ['articulos' => [], 'otros' => []];
    }

    /**
     * Obtiene información completa de una ley desde el BOE
     * Hace una sola petición HTTP y reutiliza el XML parseado para obtener toda la información
     * Los resultados se cachean por 1 día
     */
    public function getInfoLey(int $leyId): array
    {
        $boeLinkApi = $this->getBoeLink($leyId);
        
        if (!$boeLinkApi) {
            return [
                'boe_link' => null,
                'ultima_actualizacion' => null,
                'tiene_link' => false,
                'articulos_afectados' => [],
                'otros_afectados' => [],
            ];
        }

        // Convertir URL de API a URL de visualización para mostrar a los usuarios
        $boeLinkVisualizacion = $this->convertirUrlApiAVisualizacion($boeLinkApi);

        // Obtener XML cacheado (hace una sola petición HTTP si no está en caché)
        $xml = $this->getXmlCached($leyId);
        
        if ($xml === null) {
            return [
                'boe_link' => $boeLinkVisualizacion,
                'ultima_actualizacion' => null,
                'tiene_link' => true,
                'articulos_afectados' => [],
                'otros_afectados' => [],
            ];
        }

        // Reutilizar el mismo XML para ambos métodos
        $ultimaActualizacionReal = $this->getUltimaActualizacion($leyId, $xml);
        
        // Obtener los elementos afectados solo si la actualización es de 2025 o 2026
        $elementosAfectados = ['articulos' => [], 'otros' => []];
        $ultimaActualizacion = null;
        
        if ($ultimaActualizacionReal) {
            $ano = (int)$ultimaActualizacionReal->format('Y');
            
            // Solo mostrar elementos afectados si la actualización es de 2025 o 2026
            if ($ano >= 2025 && $ano <= 2026) {
                $elementosAfectados = $this->getArticulosAfectados($leyId, $xml, $ultimaActualizacionReal);
                $ultimaActualizacion = $ultimaActualizacionReal;
            }
        }
        
        return [
            'boe_link' => $boeLinkVisualizacion,
            'ultima_actualizacion' => $ultimaActualizacion,
            'tiene_link' => true,
            'articulos_afectados' => $elementosAfectados['articulos'],
            'otros_afectados' => $elementosAfectados['otros'],
        ];
    }

    /**
     * Limpia el caché de una ley específica
     * Útil para forzar una actualización inmediata
     */
    public function clearCache(int $leyId): void
    {
        $cacheKey = self::CACHE_PREFIX . $leyId;
        $this->cache->deleteItem($cacheKey);
    }
}

