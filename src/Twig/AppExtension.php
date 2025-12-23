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
     * Convierte markdown básico a HTML
     * Soporta: **texto** (negritas), *texto* (cursiva), saltos de línea
     */
    public function markdownToHtml(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Convertir **texto** a <strong>texto</strong>
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        
        // Convertir *texto* a <em>texto</em> (solo si no está dentro de **)
        $text = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/', '<em>$1</em>', $text);
        
        // Convertir saltos de línea dobles a párrafos
        $text = preg_replace('/\n\n+/', '</p><p>', $text);
        $text = '<p>' . $text . '</p>';
        
        // Convertir saltos de línea simples a <br>
        $text = preg_replace('/\n/', '<br>', $text);
        
        // Limpiar párrafos vacíos
        $text = preg_replace('/<p>\s*<\/p>/', '', $text);
        $text = preg_replace('/<p><br><\/p>/', '', $text);
        
        return trim($text);
    }
}




