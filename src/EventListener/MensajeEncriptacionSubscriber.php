<?php

namespace App\EventListener;

use App\Entity\Mensaje;
use App\Service\EncriptacionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postLoad)]
class MensajeEncriptacionSubscriber
{
    public function __construct(
        private EncriptacionService $encriptacionService
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Mensaje) {
            return;
        }

        $this->encriptarMensaje($entity);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Mensaje) {
            return;
        }

        // Solo encriptar si el contenido cambió
        if ($args->hasChangedField('contenido')) {
            $this->encriptarMensaje($entity);
        }
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Mensaje) {
            return;
        }

        $this->desencriptarMensaje($entity);
    }

    private function encriptarMensaje(Mensaje $mensaje): void
    {
        // Obtener el contenido desencriptado directamente usando reflexión
        $reflection = new \ReflectionClass($mensaje);
        $propertyContenido = $reflection->getProperty('contenido');
        $propertyContenido->setAccessible(true);
        $contenidoActual = $propertyContenido->getValue($mensaje);
        
        $propertyDesencriptado = $reflection->getProperty('contenidoDesencriptado');
        $propertyDesencriptado->setAccessible(true);
        $contenidoDesencriptado = $propertyDesencriptado->getValue($mensaje);
        
        // Usar el contenido desencriptado si está disponible, sino el contenido actual
        $contenidoAEncriptar = $contenidoDesencriptado ?? $contenidoActual;
        
        if ($contenidoAEncriptar === null || empty($contenidoAEncriptar)) {
            return;
        }

        // Verificar si ya está encriptado (para evitar doble encriptación)
        // Si el contenido actual parece estar encriptado, no hacer nada
        if ($contenidoActual !== null && $contenidoActual !== $contenidoDesencriptado) {
            $decoded = @base64_decode($contenidoActual, true);
            if ($decoded !== false && strlen($decoded) >= 16) {
                // Parece que ya está encriptado, verificar
                try {
                    $testDecrypt = $this->encriptacionService->desencriptar($contenidoActual);
                    // Si la desencriptación devuelve algo diferente, está encriptado
                    if ($testDecrypt !== $contenidoActual) {
                        return; // Ya está encriptado
                    }
                } catch (\Exception $e) {
                    // Continuar con la encriptación
                }
            }
        }

        // Encriptar el contenido
        $contenidoEncriptado = $this->encriptacionService->encriptar($contenidoAEncriptar);
        
        // Establecer el contenido encriptado
        $propertyContenido->setValue($mensaje, $contenidoEncriptado);
    }

    private function desencriptarMensaje(Mensaje $mensaje): void
    {
        $contenido = $mensaje->getContenido();
        if ($contenido === null || empty($contenido)) {
            return;
        }

        try {
            $contenidoDesencriptado = $this->encriptacionService->desencriptar($contenido);
            
            // Guardar el contenido desencriptado en la propiedad privada
            $reflection = new \ReflectionClass($mensaje);
            if ($reflection->hasProperty('contenidoDesencriptado')) {
                $property = $reflection->getProperty('contenidoDesencriptado');
                $property->setAccessible(true);
                $property->setValue($mensaje, $contenidoDesencriptado);
            }
        } catch (\Exception $e) {
            // Si falla la desencriptación, dejar el contenido original
            // (puede ser un mensaje antiguo sin encriptar)
        }
    }
}
