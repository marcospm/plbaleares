<?php

namespace App\Service;

use App\Entity\CategoriaOposicion;
use App\Entity\User;

/**
 * Las categorías distintas de Policía solo entran, por ahora, a inicio,
 * temario, contacto y las páginas de cuenta. Policía no pasa por aquí.
 */
class AccesoPorCategoria
{
    /**
     * @var array<string, true>
     */
    private const RUTAS_PERMITIDAS = [
        'app_dashboard' => true,
        'app_recurso_publico_index' => true,
        'app_tema_pdf_ver' => true,
        'app_tema_pdf_descargar' => true,
        'app_contacto' => true,
        'app_perfil_index' => true,
        'app_perfil_exportar' => true,
        'app_perfil_suprimir' => true,
        'app_aviso_legal' => true,
        'app_privacidad' => true,
        'app_cookie_politica' => true,
        'app_cookie_aceptar' => true,
        'app_logout' => true,
        'app_home' => true,
        'app_login' => true,
        'app_register' => true,
        'app_forgot_password' => true,
        'app_reset_password' => true,
    ];

    public function restringe(?object $user, bool $esProfesor): bool
    {
        if (!$user instanceof User || $esProfesor) {
            return false;
        }

        return $user->getCategoria() !== CategoriaOposicion::POLICIA;
    }

    public function rutaPermitida(string $ruta): bool
    {
        return isset(self::RUTAS_PERMITIDAS[$ruta]);
    }
}
