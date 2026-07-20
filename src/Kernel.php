<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    public function boot(): void
    {
        // Zona horaria antes de bootear el contenedor (Doctrine/fechas)
        date_default_timezone_set('Europe/Madrid');
        parent::boot();
    }
}
