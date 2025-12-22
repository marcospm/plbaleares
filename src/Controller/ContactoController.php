<?php

namespace App\Controller;

use App\Entity\MensajeContacto;
use App\Form\MensajeContactoType;
use App\Repository\MensajeContactoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ContactoController extends AbstractController
{
    #[Route('/contacto', name: 'app_contacto', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $mensaje = new MensajeContacto();
        
        // Si el usuario está autenticado, pre-llenar nombre y email
        if ($this->getUser()) {
            $mensaje->setNombre($this->getUser()->getUsername());
            $mensaje->setEmail($this->getUser()->getUserIdentifier());
            $mensaje->setUsuario($this->getUser());
        }
        
        $form = $this->createForm(MensajeContactoType::class, $mensaje);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($mensaje);
            $entityManager->flush();

            $this->addFlash('success', '¡Mensaje enviado correctamente! Te responderemos lo antes posible.');
            return $this->redirectToRoute('app_contacto');
        }

        return $this->render('contacto/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/mensajes', name: 'app_contacto_mensajes', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function mensajes(MensajeContactoRepository $mensajeRepository): Response
    {
        $mensajes = $mensajeRepository->findBy([], ['fechaCreacion' => 'DESC']);
        $noLeidos = $mensajeRepository->count(['leido' => false]);

        return $this->render('contacto/mensajes.html.twig', [
            'mensajes' => $mensajes,
            'noLeidos' => $noLeidos,
        ]);
    }

    #[Route('/admin/mensajes/{id}', name: 'app_contacto_ver', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function ver(
        MensajeContacto $mensaje,
        EntityManagerInterface $entityManager
    ): Response {
        // Marcar como leído
        if (!$mensaje->isLeido()) {
            $mensaje->setLeido(true);
            $entityManager->flush();
        }

        return $this->render('contacto/ver.html.twig', [
            'mensaje' => $mensaje,
        ]);
    }

    #[Route('/admin/mensajes/{id}/eliminar', name: 'app_contacto_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        MensajeContacto $mensaje,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$mensaje->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($mensaje);
            $entityManager->flush();
            $this->addFlash('success', 'Mensaje eliminado correctamente.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('app_contacto_mensajes');
    }
}
