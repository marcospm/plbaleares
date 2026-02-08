<?php

namespace App\Controller;

use App\Entity\Mensaje;
use App\Entity\User;
use App\Form\MensajeType;
use App\Repository\MensajeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    #[Route('', name: 'app_chat_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('chat/index.html.twig');
    }

    #[Route('/api/conversaciones', name: 'app_chat_api_conversaciones', methods: ['GET'])]
    public function apiConversaciones(MensajeRepository $mensajeRepository): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }

        $conversaciones = $mensajeRepository->findConversacionesByUsuario($usuario);
        
        $data = [];
        $timezone = new \DateTimeZone('Europe/Madrid');
        
        foreach ($conversaciones as $conv) {
            $otroUsuario = $conv['usuario'];
            $ultimoMensaje = $conv['ultimoMensaje'];
            $noLeidos = $conv['noLeidos'];
            
            $fechaEnvio = clone $ultimoMensaje->getFechaEnvio();
            $fechaEnvio->setTimezone($timezone);
            
            $data[] = [
                'usuarioId' => $otroUsuario->getId(),
                'usuarioNombre' => $otroUsuario->getNombreDisplay(),
                'usuarioUsername' => $otroUsuario->getUsername(),
                'ultimoMensaje' => [
                    'contenido' => $ultimoMensaje->getContenido(),
                    'fechaEnvio' => $fechaEnvio->format('d/m/Y H:i'),
                    'esMio' => $ultimoMensaje->getRemitente()->getId() === $usuario->getId(),
                ],
                'noLeidos' => $noLeidos,
            ];
        }

        return new JsonResponse(['conversaciones' => $data]);
    }

    #[Route('/api/mensajes/{userId}', name: 'app_chat_api_mensajes', methods: ['GET'], requirements: ['userId' => '\d+'])]
    public function apiMensajes(int $userId, MensajeRepository $mensajeRepository, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }

        $otroUsuario = $userRepository->find($userId);
        if (!$otroUsuario) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }

        // Marcar mensajes como leídos
        $mensajeRepository->marcarComoLeidos($otroUsuario, $usuario);

        $mensajes = $mensajeRepository->findMensajesConversacion($usuario, $otroUsuario);
        
        $data = [];
        $timezone = new \DateTimeZone('Europe/Madrid');
        
        foreach ($mensajes as $mensaje) {
            $fechaEnvio = clone $mensaje->getFechaEnvio();
            $fechaEnvio->setTimezone($timezone);
            
            $data[] = [
                'id' => $mensaje->getId(),
                'contenido' => $mensaje->getContenido(),
                'fechaEnvio' => $fechaEnvio->format('d/m/Y H:i'),
                'esMio' => $mensaje->getRemitente()->getId() === $usuario->getId(),
                'leido' => $mensaje->isLeido(),
            ];
        }

        return new JsonResponse([
            'mensajes' => $data,
            'otroUsuario' => [
                'id' => $otroUsuario->getId(),
                'nombre' => $otroUsuario->getNombreDisplay(),
                'username' => $otroUsuario->getUsername(),
            ]
        ]);
    }

    #[Route('/api/enviar', name: 'app_chat_api_enviar', methods: ['POST'])]
    public function apiEnviar(Request $request, MensajeRepository $mensajeRepository, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['destinatarioId']) || !isset($data['contenido'])) {
            return new JsonResponse(['error' => 'Datos incompletos'], 400);
        }

        $destinatario = $userRepository->find($data['destinatarioId']);
        if (!$destinatario) {
            return new JsonResponse(['error' => 'Usuario destinatario no encontrado'], 404);
        }

        if ($destinatario->getId() === $usuario->getId()) {
            return new JsonResponse(['error' => 'No puedes enviarte mensajes a ti mismo'], 400);
        }

        $mensaje = new Mensaje();
        $mensaje->setRemitente($usuario);
        $mensaje->setDestinatario($destinatario);
        $mensaje->setContenido(trim($data['contenido']));

        if (empty($mensaje->getContenido())) {
            return new JsonResponse(['error' => 'El mensaje no puede estar vacío'], 400);
        }

        $entityManager->persist($mensaje);
        $entityManager->flush();

        $timezone = new \DateTimeZone('Europe/Madrid');
        $fechaEnvio = clone $mensaje->getFechaEnvio();
        $fechaEnvio->setTimezone($timezone);

        return new JsonResponse([
            'success' => true,
            'mensaje' => [
                'id' => $mensaje->getId(),
                'contenido' => $mensaje->getContenido(),
                'fechaEnvio' => $fechaEnvio->format('d/m/Y H:i'),
                'esMio' => true,
                'leido' => false,
            ]
        ]);
    }

    #[Route('/api/marcar-leidos/{userId}', name: 'app_chat_api_marcar_leidos', methods: ['POST'], requirements: ['userId' => '\d+'])]
    public function apiMarcarLeidos(int $userId, MensajeRepository $mensajeRepository, UserRepository $userRepository): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }

        $otroUsuario = $userRepository->find($userId);
        if (!$otroUsuario) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }

        $mensajeRepository->marcarComoLeidos($otroUsuario, $usuario);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/contador', name: 'app_chat_api_contador', methods: ['GET'])]
    public function apiContador(MensajeRepository $mensajeRepository): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['contador' => 0]);
        }

        $contador = $mensajeRepository->countNoLeidos($usuario);

        return new JsonResponse(['contador' => $contador]);
    }

    #[Route('/api/buscar-usuarios', name: 'app_chat_api_buscar_usuarios', methods: ['GET'])]
    public function apiBuscarUsuarios(Request $request, UserRepository $userRepository): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }

        $search = trim($request->query->get('q', ''));
        
        if (empty($search) || strlen($search) < 2) {
            return new JsonResponse(['usuarios' => []]);
        }

        // Buscar usuarios (excluyendo eliminados y el usuario actual)
        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.eliminado = :eliminado')
            ->andWhere('u.activo = :activo')
            ->andWhere('u.id != :usuarioId')
            ->andWhere('(u.username LIKE :search OR u.nombre LIKE :search)')
            ->setParameter('eliminado', false)
            ->setParameter('activo', true)
            ->setParameter('usuarioId', $usuario->getId())
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($usuarios as $user) {
            $data[] = [
                'id' => $user->getId(),
                'nombre' => $user->getNombreDisplay(),
                'username' => $user->getUsername(),
            ];
        }

        return new JsonResponse(['usuarios' => $data]);
    }

    #[Route('/api/usuarios', name: 'app_chat_api_usuarios', methods: ['GET'])]
    public function apiUsuarios(UserRepository $userRepository): JsonResponse
    {
        $usuario = $this->getUser();
        if (!$usuario) {
            return new JsonResponse(['error' => 'Usuario no autenticado'], 401);
        }

        // Obtener todos los usuarios activos (excluyendo eliminados y el usuario actual)
        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.eliminado = :eliminado')
            ->andWhere('u.activo = :activo')
            ->andWhere('u.id != :usuarioId')
            ->setParameter('eliminado', false)
            ->setParameter('activo', true)
            ->setParameter('usuarioId', $usuario->getId())
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($usuarios as $user) {
            $data[] = [
                'id' => $user->getId(),
                'nombre' => $user->getNombreDisplay(),
                'username' => $user->getUsername(),
            ];
        }

        return new JsonResponse(['usuarios' => $data]);
    }
}
