<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RedisController extends AbstractController
{
    public function __construct(
        private CacheInterface $redisCache,
        private CacheItemPoolInterface $redisPool,
        private ?CacheItemPoolInterface $cachePartidas = null,
        private ?CacheItemPoolInterface $cacheBoe = null,
        private ?CacheItemPoolInterface $cacheQueries = null
    ) {
    }

    #[Route('/redis/hello', name: 'app_redis_hello', methods: ['GET'])]
    public function hello(): JsonResponse
    {
        try {
            // Configurar timeout para evitar bloqueos largos
            set_time_limit(5); // Máximo 5 segundos para esta operación
            
            // Detectar si estamos usando Redis real o filesystem (fallback)
            $isRedis = $this->isUsingRedis();
            
            // Ejemplo básico: guardar y recuperar un valor
            $key = 'hello_world';
            $value = 'Hello from Redis! ' . date('Y-m-d H:i:s');
            
            // Guardar en Redis con TTL de 60 segundos
            $this->redisCache->get($key, function (ItemInterface $item) use ($value) {
                $item->expiresAfter(60);
                return $value;
            });
            
            // Recuperar el valor
            $cachedValue = $this->redisCache->get($key, function (ItemInterface $item) {
                return null;
            });
            
            return $this->json([
                'status' => 'success',
                'message' => $isRedis 
                    ? 'Redis está funcionando correctamente!' 
                    : 'Cache funcionando (usando filesystem como fallback - Redis no disponible en local)',
                'using_redis' => $isRedis,
                'data' => [
                    'key' => $key,
                    'value' => $cachedValue,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Symfony\Component\Cache\Exception\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error de configuración de Redis: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'hint' => 'Verifica que Redis esté corriendo y accesible',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error al conectar con Redis: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'hint' => 'Asegúrate de que Redis esté corriendo (redis-server) o desactiva Redis temporalmente',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/redis/test', name: 'app_redis_test', methods: ['GET', 'POST'])]
    public function test(Request $request): JsonResponse
    {
        try {
            // Configurar timeout para evitar bloqueos largos
            set_time_limit(5); // Máximo 5 segundos para esta operación
            
            $action = $request->query->get('action', 'get');
            $key = $request->query->get('key', 'test_key');
            $value = $request->query->get('value', 'test_value');
            
            $result = [];
            
            switch ($action) {
                case 'set':
                    // Guardar un valor
                    $this->redisCache->get($key, function (ItemInterface $item) use ($value) {
                        $item->expiresAfter(300); // 5 minutos
                        return $value;
                    });
                    $result = [
                        'action' => 'set',
                        'key' => $key,
                        'value' => $value,
                        'message' => 'Valor guardado correctamente',
                    ];
                    break;
                    
                case 'get':
                    // Recuperar un valor
                    $cachedValue = $this->redisCache->get($key, function (ItemInterface $item) {
                        return null;
                    });
                    $result = [
                        'action' => 'get',
                        'key' => $key,
                        'value' => $cachedValue,
                        'found' => $cachedValue !== null,
                    ];
                    break;
                    
                case 'delete':
                    // Eliminar un valor usando el pool
                    $this->redisPool->deleteItem($key);
                    $result = [
                        'action' => 'delete',
                        'key' => $key,
                        'message' => 'Clave eliminada (si existía)',
                    ];
                    break;
                    
                case 'info':
                    // Información sobre Redis - Test completo de lectura/escritura
                    $testKey = 'redis_info_test_' . time();
                    $testValue = 'test_value_' . time();
                    
                    // Primero eliminar la clave si existe (por si acaso)
                    try {
                        $this->redisPool->deleteItem($testKey);
                    } catch (\Exception $e) {
                        // Ignorar errores al eliminar
                    }
                    
                    // Guardar el valor usando get() con callback
                    $saved = $this->redisCache->get($testKey, function (ItemInterface $item) use ($testValue) {
                        $item->expiresAfter(10);
                        return $testValue;
                    });
                    
                    // Pequeña pausa para asegurar que se guardó
                    usleep(10000); // 10ms
                    
                    // Recuperar el valor inmediatamente
                    // Si el valor existe, el callback no se ejecuta y devuelve el valor en caché
                    $retrieved = $this->redisCache->get($testKey, function (ItemInterface $item) {
                        // Este callback solo se ejecuta si el valor NO existe en caché
                        return 'NOT_FOUND';
                    });
                    
                    // Verificar que ambos valores sean iguales al esperado
                    $savedOk = ($saved === $testValue);
                    $retrievedOk = ($retrieved === $testValue);
                    $testPassed = $savedOk && $retrievedOk;
                    
                    // Limpiar la clave de prueba
                    try {
                        $this->redisPool->deleteItem($testKey);
                    } catch (\Exception $e) {
                        // Ignorar errores al eliminar
                    }
                    
                    $result = [
                        'action' => 'info',
                        'status' => 'connected',
                        'test' => $testPassed ? 'passed' : 'failed',
                        'saved_ok' => $savedOk,
                        'retrieved_ok' => $retrievedOk,
                        'saved_value' => $saved,
                        'retrieved_value' => $retrieved,
                        'expected_value' => $testValue,
                        'saved_type' => gettype($saved),
                        'retrieved_type' => gettype($retrieved),
                        'expected_type' => gettype($testValue),
                        'message' => $testPassed 
                            ? 'Redis está conectado y funcionando correctamente' 
                            : 'Redis está conectado pero el test de lectura/escritura falló. Verifica los valores arriba.',
                    ];
                    break;
                    
                default:
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Acción no válida. Usa: set, get, delete o info',
                    ], Response::HTTP_BAD_REQUEST);
            }
            
            return $this->json([
                'status' => 'success',
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            
        } catch (\Symfony\Component\Cache\Exception\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error de configuración de Redis: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'hint' => 'Verifica que Redis esté corriendo y accesible',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $isTimeout = strpos($errorMessage, 'timeout') !== false || 
                         strpos($errorMessage, 'Maximum execution time') !== false ||
                         strpos($errorMessage, 'Connection refused') !== false;
            
            return $this->json([
                'status' => 'error',
                'message' => 'Error: ' . $errorMessage,
                'error_type' => $isTimeout ? 'timeout' : 'other',
                'hint' => $isTimeout 
                    ? 'Redis no está disponible o no responde. Asegúrate de que Redis esté corriendo (redis-server) o desactiva Redis temporalmente en cache.yaml'
                    : 'Error desconocido. Verifica la configuración de Redis.',
                'trace' => $this->getParameter('kernel.debug') ? $e->getTraceAsString() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/redis', name: 'app_redis_admin', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request): Response
    {
        $action = $request->request->get('action', $request->query->get('action'));
        $result = null;
        
        // Procesar acciones
        if ($action === 'clear') {
            try {
                $this->redisPool->clear();
                $result = [
                    'success' => true,
                    'message' => 'Caché limpiado correctamente',
                ];
            } catch (\Exception $e) {
                $result = [
                    'success' => false,
                    'message' => 'Error al limpiar caché: ' . $e->getMessage(),
                ];
            }
        }
        
        // Obtener estadísticas
        $stats = $this->getCacheStats();
        
        // Si es petición AJAX, devolver JSON
        if ($request->isXmlHttpRequest() || $request->query->get('format') === 'json') {
            return $this->json([
                'status' => 'success',
                'stats' => $stats,
                'action_result' => $result,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
        
        // Renderizar vista HTML
        return $this->render('redis/admin.html.twig', [
            'stats' => $stats,
            'action_result' => $result,
            'using_redis' => $this->isUsingRedis(),
        ]);
    }
    
    /**
     * Obtiene estadísticas del caché
     */
    private function getCacheStats(): array
    {
        $stats = [
            'using_redis' => $this->isUsingRedis(),
            'adapter_class' => get_class($this->redisPool),
            'memory' => null,
            'keys_count' => null,
            'info' => null,
            'sample_keys' => [],
            'cache_pools' => [],
        ];
        
        // Obtener información de todos los pools de caché
        $cachePools = [
            'cache.redis' => 'Caché Principal (Redis)',
            'cache.partidas' => 'Caché de Partidas',
            'cache.boe' => 'Caché de BOE',
            'cache.queries' => 'Caché de Consultas',
            'cache.app' => 'Caché de Aplicación (App)',
        ];
        
        // Mapear nombres de pools a las propiedades inyectadas
        $poolMappings = [
            'cache.redis' => $this->redisPool,
            'cache.partidas' => $this->cachePartidas,
            'cache.boe' => $this->cacheBoe,
            'cache.queries' => $this->cacheQueries,
            'cache.app' => null, // Intentar obtenerlo del contenedor si es necesario
        ];
        
        foreach ($cachePools as $poolName => $poolLabel) {
            $poolStats = [
                'name' => $poolName,
                'label' => $poolLabel,
                'adapter_class' => 'N/A',
                'is_redis' => false,
                'available' => false,
                'error' => null,
            ];
            
            try {
                // Obtener el pool desde las propiedades inyectadas o del contenedor
                $pool = null;
                
                if (isset($poolMappings[$poolName])) {
                    $pool = $poolMappings[$poolName];
                } elseif ($poolName === 'cache.app') {
                    // Intentar obtener cache.app del contenedor
                    try {
                        if ($this->container->has('cache.app')) {
                            $pool = $this->container->get('cache.app');
                        }
                    } catch (\Exception $e) {
                        // No disponible
                    }
                }
                
                if ($pool === null) {
                    $poolStats['error'] = 'Pool no disponible. Verifica que esté configurado correctamente en cache.yaml';
                    $stats['cache_pools'][] = $poolStats;
                    continue;
                }
                
                $poolStats['available'] = true;
                $poolStats['adapter_class'] = get_class($pool);
                $poolStats['is_redis'] = $this->isPoolUsingRedis($pool);
                
                // Si es Redis, intentar obtener estadísticas detalladas
                if ($poolStats['is_redis']) {
                    try {
                        $redisClient = $this->getRedisClientFromPool($pool);
                        $poolStats['redis_client_obtained'] = $redisClient !== null;
                        $poolStats['redis_client_class'] = $redisClient ? get_class($redisClient) : null;
                        
                        if ($redisClient) {
                            // Obtener todas las claves - Symfony Cache usa prefijos
                            try {
                                // Intentar obtener el prefijo del pool si es posible
                                $prefix = null;
                                try {
                                    $reflection = new \ReflectionClass($pool);
                                    if ($reflection->hasProperty('namespace')) {
                                        $prop = $reflection->getProperty('namespace');
                                        $prop->setAccessible(true);
                                        $prefix = $prop->getValue($pool);
                                    }
                                } catch (\Exception $e) {
                                    // Ignorar
                                }
                                
                                // Buscar todas las claves (Symfony Cache puede usar prefijos)
                                $allKeys = [];
                                try {
                                    // Intentar con diferentes patrones
                                    $patterns = ['*', $prefix ? $prefix . '*' : null];
                                    foreach ($patterns as $pattern) {
                                        if ($pattern === null) continue;
                                        try {
                                            if (method_exists($redisClient, 'keys')) {
                                                $keys = $redisClient->keys($pattern);
                                            } elseif (method_exists($redisClient, 'executeCommand')) {
                                                $keys = $redisClient->executeCommand(
                                                    $redisClient->createCommand('KEYS', [$pattern])
                                                );
                                            } else {
                                                $keys = [];
                                            }
                                            
                                            if (is_array($keys)) {
                                                $allKeys = array_merge($allKeys, $keys);
                                            }
                                        } catch (\Exception $e) {
                                            // Continuar con el siguiente patrón
                                        }
                                    }
                                    
                                    // Eliminar duplicados
                                    $allKeys = array_unique($allKeys);
                                    $poolStats['keys_count'] = count($allKeys);
                                    $poolStats['prefix_used'] = $prefix;
                                    
                                    // Si no hay claves, intentar sin prefijo o con dbSize
                                    if (empty($allKeys)) {
                                        try {
                                            if (method_exists($redisClient, 'dbSize')) {
                                                $dbSize = $redisClient->dbSize();
                                                $poolStats['db_size'] = $dbSize;
                                                if ($dbSize > 0) {
                                                    // Si hay claves pero no las encontramos, puede ser un problema de prefijo
                                                    $poolStats['keys_count'] = $dbSize;
                                                    $poolStats['keys_note'] = 'Hay ' . $dbSize . ' claves en Redis, pero no se encontraron con el patrón usado. Puede ser un problema de prefijo.';
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            // Ignorar
                                        }
                                    }
                                } catch (\Exception $e) {
                                    $poolStats['keys_count'] = 'N/A';
                                    $poolStats['error'] = 'Error al obtener claves: ' . $e->getMessage();
                                }
                                
                                // Obtener muestra de claves con información detallada
                                if (!empty($allKeys)) {
                                    $sampleKeys = array_slice($allKeys, 0, 50); // Primeras 50 claves
                                    $poolStats['sample_keys'] = [];
                                
                                foreach ($sampleKeys as $key) {
                                    $keyInfo = [
                                        'key' => $key,
                                        'type' => null,
                                        'ttl' => null,
                                        'size' => null,
                                    ];
                                    
                                    try {
                                        // Obtener tipo de la clave
                                        if (method_exists($redisClient, 'type')) {
                                            $keyInfo['type'] = $redisClient->type($key);
                                        } elseif (method_exists($redisClient, 'executeCommand')) {
                                            // Para Predis
                                            $keyInfo['type'] = $redisClient->executeCommand(
                                                $redisClient->createCommand('TYPE', [$key])
                                            );
                                        }
                                        
                                        // Obtener TTL (tiempo de vida restante)
                                        $ttl = null;
                                        if (method_exists($redisClient, 'ttl')) {
                                            $ttl = $redisClient->ttl($key);
                                        } elseif (method_exists($redisClient, 'executeCommand')) {
                                            // Para Predis
                                            $ttl = $redisClient->executeCommand(
                                                $redisClient->createCommand('TTL', [$key])
                                            );
                                        }
                                        
                                        if ($ttl !== null) {
                                            if ($ttl > 0) {
                                                $keyInfo['ttl'] = $ttl; // Segundos restantes
                                                $keyInfo['ttl_human'] = $this->formatTtl($ttl);
                                            } elseif ($ttl === -1) {
                                                $keyInfo['ttl'] = -1; // Sin expiración
                                                $keyInfo['ttl_human'] = 'Sin expiración';
                                            } else {
                                                $keyInfo['ttl'] = 0; // Expirada
                                                $keyInfo['ttl_human'] = 'Expirada';
                                            }
                                        }
                                        
                                        // Obtener tamaño aproximado
                                        try {
                                            $memoryUsage = null;
                                            
                                            // Intentar con MEMORY USAGE (Redis 4.0+)
                                            if (method_exists($redisClient, 'executeCommand')) {
                                                // Para Predis
                                                try {
                                                    $memoryUsage = $redisClient->executeCommand(
                                                        $redisClient->createCommand('MEMORY', ['USAGE', $key])
                                                    );
                                                } catch (\Exception $e) {
                                                    // MEMORY USAGE puede no estar disponible
                                                }
                                            } elseif (method_exists($redisClient, 'memory')) {
                                                // Para PhpRedis
                                                try {
                                                    $memoryUsage = $redisClient->memory('usage', $key);
                                                } catch (\Exception $e) {
                                                    // MEMORY USAGE puede no estar disponible
                                                }
                                            }
                                            
                                            if ($memoryUsage !== null && $memoryUsage !== false) {
                                                $keyInfo['size'] = (int)$memoryUsage;
                                                $keyInfo['size_human'] = $this->formatBytes($keyInfo['size']);
                                            } else {
                                                // Fallback: estimar tamaño desde el valor
                                                $value = null;
                                                if (method_exists($redisClient, 'get')) {
                                                    $value = $redisClient->get($key);
                                                } elseif (method_exists($redisClient, 'executeCommand')) {
                                                    $value = $redisClient->executeCommand(
                                                        $redisClient->createCommand('GET', [$key])
                                                    );
                                                }
                                                
                                                if ($value !== false && $value !== null) {
                                                    $keyInfo['size'] = strlen(serialize($value));
                                                    $keyInfo['size_human'] = $this->formatBytes($keyInfo['size']);
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            // Ignorar errores al obtener el tamaño
                                        }
                                    } catch (\Exception $e) {
                                        // Ignorar errores al obtener información de la clave
                                    }
                                    
                                    $poolStats['sample_keys'][] = $keyInfo;
                                }
                                } else {
                                    $poolStats['sample_keys'] = [];
                                    $poolStats['no_keys_message'] = 'No se encontraron claves en este pool. Puede que el caché aún no se haya utilizado o que las claves usen un prefijo diferente.';
                                }
                                
                                // Estadísticas adicionales del pool
                                try {
                                    $info = null;
                                    if (method_exists($redisClient, 'info')) {
                                        $info = $redisClient->info('memory');
                                    } elseif (method_exists($redisClient, 'executeCommand')) {
                                        $info = $redisClient->executeCommand(
                                            $redisClient->createCommand('INFO', ['memory'])
                                        );
                                    }
                                    
                                    if ($info !== null) {
                                        if (is_array($info) && isset($info['used_memory'])) {
                                            $poolStats['memory_used'] = $info['used_memory'];
                                            $poolStats['memory_used_human'] = $this->formatBytes($info['used_memory']);
                                        } elseif (is_string($info)) {
                                            // Para Predis, info() devuelve un string
                                            if (preg_match('/used_memory:(\d+)/', $info, $matches)) {
                                                $poolStats['memory_used'] = (int)$matches[1];
                                                $poolStats['memory_used_human'] = $this->formatBytes($poolStats['memory_used']);
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    // Ignorar si no se puede obtener info de memoria
                                }
                                
                            } catch (\Exception $e) {
                                $poolStats['keys_count'] = 'N/A';
                                $poolStats['error'] = $e->getMessage();
                            }
                        }
                    } catch (\Exception $e) {
                        $poolStats['error'] = $e->getMessage();
                    }
                }
            } catch (\Exception $e) {
                $poolStats['error'] = 'Error al obtener pool: ' . $e->getMessage() . ' (Tipo: ' . get_class($e) . ')';
            }
            
            $stats['cache_pools'][] = $poolStats;
        }
        
        if ($this->isUsingRedis()) {
            try {
                // Intentar obtener el cliente Redis directamente
                $redisClient = $this->getRedisClient();
                
                if ($redisClient) {
                    // Obtener información de Redis
                    $info = $redisClient->info();
                    $stats['info'] = $info;
                    
                    // Memoria usada
                    if (isset($info['memory']['used_memory_human'])) {
                        $stats['memory'] = $info['memory']['used_memory_human'];
                    } elseif (isset($info['used_memory_human'])) {
                        $stats['memory'] = $info['used_memory_human'];
                    }
                    
                    // Contar claves (aproximado, puede ser lento en producción)
                    try {
                        $dbSize = $redisClient->dbSize();
                        $stats['keys_count'] = $dbSize;
                    } catch (\Exception $e) {
                        $stats['keys_count'] = 'N/A (error: ' . $e->getMessage() . ')';
                    }
                    
                    // Obtener algunas claves de ejemplo (limitado a 20 para no sobrecargar)
                    try {
                        $keys = $redisClient->keys('*');
                        $sampleKeys = array_slice($keys, 0, 20);
                        $stats['sample_keys'] = $sampleKeys;
                        $stats['total_keys_estimated'] = count($keys);
                    } catch (\Exception $e) {
                        $stats['sample_keys'] = [];
                        $stats['keys_error'] = $e->getMessage();
                    }
                }
            } catch (\Exception $e) {
                $stats['error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * Detecta si un pool específico está usando Redis
     */
    private function isPoolUsingRedis($pool): bool
    {
        try {
            $reflection = new \ReflectionClass($pool);
            $className = $reflection->getName();
            return strpos($className, 'Redis') !== false || 
                   strpos($className, 'Predis') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtiene el cliente Redis desde un pool específico
     */
    private function getRedisClientFromPool($pool)
    {
        try {
            $reflection = new \ReflectionClass($pool);
            
            // Método 1: Intentar getConnection() si existe
            if (method_exists($pool, 'getConnection')) {
                $client = $pool->getConnection();
                if ($client && is_object($client)) {
                    return $client;
                }
            }
            
            // Método 2: Buscar en propiedades comunes
            $properties = ['redis', 'client', 'connection', 'connectionPool'];
            foreach ($properties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $client = $property->getValue($pool);
                    
                    // Verificar si es un cliente Redis válido
                    if ($client && is_object($client)) {
                        // Para Predis, verificar si tiene métodos de Redis
                        if (method_exists($client, 'keys') || method_exists($client, 'executeCommand')) {
                            return $client;
                        }
                        // Si es un pool de conexiones, obtener una conexión
                        if (method_exists($client, 'getConnection')) {
                            $conn = $client->getConnection();
                            if ($conn) {
                                return $conn;
                            }
                        }
                        // Verificar si tiene métodos de Redis directamente
                        if (method_exists($client, 'info') || method_exists($client, 'dbSize')) {
                            return $client;
                        }
                    }
                }
            }
            
            // Método 3: Para RedisAdapter de Symfony, buscar en propiedades anidadas
            if (strpos(get_class($pool), 'RedisAdapter') !== false) {
                // RedisAdapter puede tener el cliente en diferentes lugares
                $nestedProperties = [
                    ['redis', 'client'],
                    ['redis', 'connection'],
                    ['connection', 'client'],
                ];
                
                foreach ($nestedProperties as $path) {
                    try {
                        $current = $pool;
                        foreach ($path as $prop) {
                            $ref = new \ReflectionClass($current);
                            if ($ref->hasProperty($prop)) {
                                $p = $ref->getProperty($prop);
                                $p->setAccessible(true);
                                $current = $p->getValue($current);
                                if ($current && is_object($current)) {
                                    if (method_exists($current, 'keys') || method_exists($current, 'executeCommand') || method_exists($current, 'dbSize')) {
                                        return $current;
                                    }
                                }
                            } else {
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // Continuar con el siguiente path
                    }
                }
            }
        } catch (\Exception $e) {
            // No se pudo obtener el cliente
        }
        
        return null;
    }
    
    /**
     * Obtiene el cliente Redis directamente desde el adaptador
     */
    private function getRedisClient()
    {
        try {
            // Intentar obtener el cliente Redis usando reflexión
            $reflection = new \ReflectionClass($this->redisPool);
            
            // Para RedisAdapter de Symfony, el cliente está en una propiedad privada
            if (method_exists($this->redisPool, 'getConnection')) {
                return $this->redisPool->getConnection();
            }
            
            // Intentar acceder a la propiedad 'redis' o 'client'
            $properties = ['redis', 'client', 'connection'];
            foreach ($properties as $prop) {
                if ($reflection->hasProperty($prop)) {
                    $property = $reflection->getProperty($prop);
                    $property->setAccessible(true);
                    $client = $property->getValue($this->redisPool);
                    if ($client && (is_object($client) && method_exists($client, 'info'))) {
                        return $client;
                    }
                }
            }
            
            // Si es Predis, intentar obtener el cliente
            if (strpos(get_class($this->redisPool), 'Predis') !== false) {
                // Para Predis, el cliente puede estar en diferentes lugares
                foreach ($properties as $prop) {
                    try {
                        $property = $reflection->getProperty($prop);
                        $property->setAccessible(true);
                        $client = $property->getValue($this->redisPool);
                        if ($client) {
                            return $client;
                        }
                    } catch (\ReflectionException $e) {
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            // No se pudo obtener el cliente
        }
        
        return null;
    }
    
    /**
     * Detecta si el pool está usando Redis real o filesystem como fallback
     */
    private function isUsingRedis(): bool
    {
        try {
            // Intentar obtener información del pool
            // Si es Redis, el pool será una instancia de RedisAdapter
            $reflection = new \ReflectionClass($this->redisPool);
            $className = $reflection->getName();
            
            // Verificar si es un adaptador de Redis
            return strpos($className, 'Redis') !== false || 
                   strpos($className, 'Predis') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Formatea TTL en formato legible
     */
    private function formatTtl(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seg';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' min';
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'min';
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return $days . 'd ' . $hours . 'h';
        }
    }
    
    /**
     * Formatea bytes en formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
