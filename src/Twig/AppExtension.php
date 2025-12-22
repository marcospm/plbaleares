<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ofuscar_nombre', [$this, 'ofuscarNombre']),
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
}



