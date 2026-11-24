<?php

namespace App\EventSubscriber;

use App\Service\AccesoPorCategoria;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AccesoPorCategoriaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AccesoPorCategoria $accesoPorCategoria,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->accesoPorCategoria->restringe($this->security->getUser(), $this->security->isGranted('ROLE_PROFESOR'))) {
            return;
        }

        $ruta = $event->getRequest()->attributes->get('_route');
        if (!is_string($ruta) || str_starts_with($ruta, '_') || $this->accesoPorCategoria->rutaPermitida($ruta)) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $session->getFlashBag()->add('info', 'Esta parte aún no está disponible para tu categoría.');
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_dashboard')));
    }
}
