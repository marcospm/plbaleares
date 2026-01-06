<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/logs')]
#[IsGranted('ROLE_ADMIN')]
class LogController extends AbstractController
{
    public function __construct(
        private KernelInterface $kernel
    ) {
    }

    #[Route('/', name: 'app_log_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $environment = $this->kernel->getEnvironment();
        $logDir = $this->kernel->getLogDir();
        $logFile = $logDir . '/' . $environment . '.log';
        
        $level = $request->query->get('level', 'all');
        $channel = $request->query->get('channel', 'all');
        $limit = (int) $request->query->get('limit', 100);
        $search = $request->query->get('search', '');
        
        $logs = [];
        $availableChannels = [];
        $logFileExists = file_exists($logFile);
        $logDirWritable = is_dir($logDir) && is_writable($logDir);
        
        if ($logFileExists) {
            $result = $this->parseLogFile($logFile, $level, $channel, $limit, $search);
            $logs = $result['logs'];
            $availableChannels = $result['channels'];
        } elseif (!$logDirWritable && $environment === 'prod') {
            // En producción, si el directorio no es escribible, intentar crear el archivo
            // Esto puede ayudar en plataformas como Upsun donde el directorio puede no existir inicialmente
            try {
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                if (is_dir($logDir) && is_writable($logDir)) {
                    // Crear archivo vacío para que Monolog pueda escribir
                    @touch($logFile);
                    $logFileExists = file_exists($logFile);
                }
            } catch (\Exception $e) {
                // Ignorar errores al crear el directorio/archivo
            }
        }
        
        return $this->render('log/index.html.twig', [
            'logs' => $logs,
            'level' => $level,
            'channel' => $channel,
            'limit' => $limit,
            'search' => $search,
            'environment' => $environment,
            'logFile' => $logFile,
            'logFileExists' => $logFileExists,
            'logDirWritable' => $logDirWritable,
            'logDir' => $logDir,
            'availableChannels' => $availableChannels,
        ]);
    }

    #[Route('/clear', name: 'app_log_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('clear_logs', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad inválido.');
            return $this->redirectToRoute('app_log_index');
        }

        $environment = $this->kernel->getEnvironment();
        $logFile = $this->kernel->getLogDir() . '/' . $environment . '.log';
        
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            $this->addFlash('success', 'Logs limpiados correctamente.');
        } else {
            $this->addFlash('warning', 'El archivo de logs no existe.');
        }
        
        return $this->redirectToRoute('app_log_index');
    }

    private function parseLogFile(string $logFile, string $level, string $channel, int $limit, string $search): array
    {
        $logs = [];
        
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return [];
        }
        
        // Leer el archivo desde el final hacia atrás para obtener los logs más recientes
        // Esto evita cargar todo el archivo en memoria
        $handle = fopen($logFile, 'rb');
        if (!$handle) {
            return ['logs' => [], 'channels' => []];
        }
        
        // Obtener el tamaño del archivo
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        
        if ($fileSize === 0) {
            fclose($handle);
            return ['logs' => [], 'channels' => []];
        }
        
        // Calcular cuántos bytes leer desde el final
        // Leemos más de lo necesario para tener líneas completas y poder filtrar
        $bytesToRead = min($fileSize, max(200000, $limit * 2000)); // Mínimo 200KB, o más según el límite
        $startPosition = max(0, $fileSize - $bytesToRead);
        
        // Si no empezamos desde el principio, avanzar hasta la primera línea completa
        if ($startPosition > 0) {
            fseek($handle, $startPosition);
            // Leer hasta encontrar el primer salto de línea
            $firstChunk = fread($handle, 1024);
            if ($firstChunk !== false && ($newlinePos = strpos($firstChunk, "\n")) !== false) {
                $startPosition += $newlinePos + 1;
            }
        }
        
        fseek($handle, $startPosition);
        
        // Leer líneas desde la posición calculada
        $lineBuffer = [];
        $channels = [];
        $chunkSize = 8192; // 8KB chunks
        $buffer = '';
        
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            
            $buffer .= $chunk;
            
            // Procesar líneas completas del buffer
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);
                
                if (!empty(trim($line))) {
                    $lineBuffer[] = trim($line);
                }
                
                // Limitar el buffer de líneas para no usar demasiada memoria
                if (count($lineBuffer) > $limit * 20) {
                    // Mantener solo las últimas líneas
                    $lineBuffer = array_slice($lineBuffer, -($limit * 10));
                }
            }
        }
        
        // Procesar cualquier resto en el buffer
        if (!empty(trim($buffer))) {
            $lineBuffer[] = trim($buffer);
        }
        
        fclose($handle);
        
        // Las líneas ya están en orden cronológico (más recientes al final)
        // Invertir para tener los más recientes primero
        $lineBuffer = array_reverse($lineBuffer);
        
        // Procesar las líneas y aplicar filtros
        foreach ($lineBuffer as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $logEntry = $this->parseLogLine($line);
            
            if (!$logEntry) {
                continue;
            }
            
            // Recopilar canales disponibles
            if (!in_array($logEntry['channel'], $channels)) {
                $channels[] = $logEntry['channel'];
            }
            
            // Filtrar por canal
            if ($channel !== 'all' && $logEntry['channel'] !== $channel) {
                continue;
            }
            
            // Filtrar por nivel
            if ($level !== 'all' && $logEntry['level'] !== $level) {
                continue;
            }
            
            // Filtrar por búsqueda
            if (!empty($search)) {
                $searchLower = strtolower($search);
                $messageLower = strtolower($logEntry['message']);
                $contextLower = strtolower(json_encode($logEntry['context']));
                
                if (strpos($messageLower, $searchLower) === false && 
                    strpos($contextLower, $searchLower) === false) {
                    continue;
                }
            }
            
            $logs[] = $logEntry;
            
            if (count($logs) >= $limit) {
                break;
            }
        }
        
        sort($channels);
        
        return [
            'logs' => $logs,
            'channels' => $channels
        ];
    }

    private function parseLogLine(string $line): ?array
    {
        // Formato estándar Monolog: [2025-12-13T07:11:16.715082+00:00] channel.LEVEL: message {"context": "data"} []
        
        // Intentar parsear formato completo con contexto JSON
        if (preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s+(.+?)(?:\s+(\{.*?\})\s*)?(\[\])?$/', $line, $matches)) {
            $timestamp = $matches[1];
            $channel = $matches[2];
            $level = strtolower($matches[3]);
            $message = trim($matches[4]);
            $contextJson = $matches[5] ?? null;
            
            $context = [];
            if ($contextJson) {
                // Intentar parsear el JSON del contexto
                $decoded = json_decode($contextJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decoded;
                } else {
                    // Si falla, guardar como string
                    $context = ['raw' => $contextJson];
                }
            }
            
            return [
                'timestamp' => $timestamp,
                'channel' => $channel,
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'raw' => $line,
            ];
        }
        
        // Formato simple con timestamp: [timestamp] message
        if (preg_match('/^\[([^\]]+)\]\s+(.+)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'channel' => 'unknown',
                'level' => 'info',
                'message' => trim($matches[2]),
                'context' => [],
                'raw' => $line,
            ];
        }
        
        // Si no coincide con ningún formato, devolver como mensaje genérico
        if (!empty(trim($line))) {
            return [
                'timestamp' => date('Y-m-d H:i:s'),
                'channel' => 'unknown',
                'level' => 'info',
                'message' => trim($line),
                'context' => [],
                'raw' => $line,
            ];
        }
        
        return null;
    }
}

