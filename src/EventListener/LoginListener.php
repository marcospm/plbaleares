<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LoginListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin',
        ];
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            $userId = $user->getId();
            $now = new \DateTime();
            
            // Usar una query directa para asegurar que se actualice, forzando la actualización
            $connection = $this->entityManager->getConnection();
            
            // Primero establecer a NULL y luego actualizar para forzar el cambio
            $connection->executeStatement(
                'UPDATE user SET ultimo_login = NULL WHERE id = ?',
                [$userId],
                [\Doctrine\DBAL\ParameterType::INTEGER]
            );
            
            // Ahora establecer el nuevo valor
            $connection->executeStatement(
                'UPDATE user SET ultimo_login = ? WHERE id = ?',
                [$now->format('Y-m-d H:i:s'), $userId],
                [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER]
            );
            
            // Limpiar completamente el EntityManager para forzar recarga
            $this->entityManager->clear();
        }
    }
}
