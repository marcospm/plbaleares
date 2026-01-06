<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ofuscar_nombre', [$this, 'ofuscarNombre']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', [$this, 'markdownToHtml'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Ofusca el nombre de usuario para mantener privacidad en rankings
     * Usa un hash determinístico basado en el ID del usuario
     */
    public function ofuscarNombre(int $userId, ?string $username = null): string
    {
        // Crear un hash determinístico basado en el ID
        // Usar un salt fijo para que siempre sea el mismo para el mismo usuario
        $hash = hash('crc32', 'ranking_salt_' . $userId);
        
        // Tomar los primeros 8 caracteres del hash y convertir a un número
        $numero = hexdec(substr($hash, 0, 8)) % 9999;
        
        return 'Usuario #' . str_pad((string)$numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Convierte markdown básico a HTML y permite HTML existente
     * Soporta: **texto** (negritas), *texto* (cursiva), HTML tags, saltos de línea
     */
    public function markdownToHtml(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Primero, proteger el HTML existente para no procesarlo como markdown
        $htmlTags = [];
        $placeholder = '___HTML_PLACEHOLDER_';
        $counter = 0;
        
        // Reemplazar todos los tags HTML con placeholders
        $text = preg_replace_callback('/<[^>]+>/', function($matches) use (&$htmlTags, &$counter, $placeholder) {
            $key = $placeholder . $counter . '___';
            $htmlTags[$key] = $matches[0];
            $counter++;
            return $key;
        }, $text);
        
        // Procesar markdown en el texto sin HTML
        // Convertir **texto** a <strong>texto</strong> (negritas con doble asterisco)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        
        // Convertir *texto* a <em>texto</em> (cursiva con asterisco simple)
        // Solo si no está precedido o seguido de otro asterisco
        $text = preg_replace('/(?<!\*)\*([^*\n]+?)\*(?!\*)/', '<em>$1</em>', $text);
        
        // Convertir saltos de línea dobles a párrafos
        $parrafos = preg_split('/\n\n+/', $text);
        $resultado = '';
        
        foreach ($parrafos as $parrafo) {
            $parrafo = trim($parrafo);
            if (!empty($parrafo)) {
                // Convertir saltos de línea simples dentro del párrafo a <br>
                $parrafo = preg_replace('/\n/', '<br>', $parrafo);
                $resultado .= '<p>' . $parrafo . '</p>';
            }
        }
        
        // Si no hay párrafos (texto sin dobles saltos de línea), convertir saltos simples a <br>
        if (empty($resultado)) {
            $resultado = preg_replace('/\n/', '<br>', $text);
        }
        
        // Restaurar HTML tags originales
        foreach ($htmlTags as $key => $html) {
            $resultado = str_replace($key, $html, $resultado);
        }
        
        // Limpiar párrafos vacíos
        $resultado = preg_replace('/<p>\s*<\/p>/', '', $resultado);
        $resultado = preg_replace('/<p><br><\/p>/', '', $resultado);
        
        return trim($resultado);
    }
}




