<?php

namespace App\Repository;

use App\Entity\Mensaje;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mensaje>
 */
class MensajeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mensaje::class);
    }

    /**
     * Obtiene todas las conversaciones del usuario (último mensaje de cada conversación)
     * @return array Array con información de cada conversación
     */
    public function findConversacionesByUsuario(User $usuario): array
    {
        // Obtener todos los mensajes donde el usuario participa
        $mensajes = $this->createQueryBuilder('m')
            ->where('m.remitente = :usuario OR m.destinatario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('m.fechaEnvio', 'DESC')
            ->getQuery()
            ->getResult();

        // Agrupar por el otro usuario y obtener el último mensaje de cada conversación
        $conversacionesMap = [];
        
        foreach ($mensajes as $mensaje) {
            $otroUsuario = $mensaje->getRemitente()->getId() === $usuario->getId() 
                ? $mensaje->getDestinatario() 
                : $mensaje->getRemitente();
            
            $otroUsuarioId = $otroUsuario->getId();
            
            // Si ya tenemos una conversación con este usuario y el mensaje actual es más antiguo, saltarlo
            if (isset($conversacionesMap[$otroUsuarioId])) {
                continue;
            }
            
            // Contar no leídos para este usuario
            $noLeidos = (int) $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.remitente = :otroUsuario')
                ->andWhere('m.destinatario = :usuario')
                ->andWhere('m.leido = :leido')
                ->setParameter('otroUsuario', $otroUsuario)
                ->setParameter('usuario', $usuario)
                ->setParameter('leido', false)
                ->getQuery()
                ->getSingleScalarResult();
            
            $conversacionesMap[$otroUsuarioId] = [
                'usuario' => $otroUsuario,
                'ultimoMensaje' => $mensaje,
                'noLeidos' => $noLeidos,
            ];
        }

        // Convertir a array y ordenar por fecha del último mensaje
        $resultado = array_values($conversacionesMap);
        usort($resultado, function($a, $b) {
            return $b['ultimoMensaje']->getFechaEnvio() <=> $a['ultimoMensaje']->getFechaEnvio();
        });

        return $resultado;
    }

    /**
     * Obtiene mensajes entre dos usuarios
     * @return Mensaje[]
     */
    public function findMensajesConversacion(User $usuario, User $otroUsuario): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.remitente = :usuario AND m.destinatario = :otroUsuario) OR (m.remitente = :otroUsuario AND m.destinatario = :usuario)')
            ->setParameter('usuario', $usuario)
            ->setParameter('otroUsuario', $otroUsuario)
            ->orderBy('m.fechaEnvio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta mensajes no leídos recibidos por el usuario
     */
    public function countNoLeidos(User $usuario): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.destinatario = :usuario')
            ->andWhere('m.leido = :leido')
            ->setParameter('usuario', $usuario)
            ->setParameter('leido', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marca mensajes como leídos entre dos usuarios
     */
    public function marcarComoLeidos(User $remitente, User $destinatario): void
    {
        $mensajes = $this->createQueryBuilder('m')
            ->where('m.remitente = :remitente')
            ->andWhere('m.destinatario = :destinatario')
            ->andWhere('m.leido = :noLeido')
            ->setParameter('remitente', $remitente)
            ->setParameter('destinatario', $destinatario)
            ->setParameter('noLeido', false)
            ->getQuery()
            ->getResult();

        $fechaLectura = new \DateTime();
        foreach ($mensajes as $mensaje) {
            $mensaje->setLeido(true);
            $mensaje->setFechaLectura($fechaLectura);
        }

        $this->getEntityManager()->flush();
    }
}
