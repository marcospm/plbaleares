<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PerfilType;
use App\Repository\UserRepository;
use App\Service\ExportacionDatosService;
use App\Service\SupresionDatosService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/perfil')]
#[IsGranted('ROLE_USER')]
class PerfilController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository
    ) {
    }

    #[Route('/', name: 'app_perfil_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        // Obtener el usuario completo de la base de datos
        $user = $this->userRepository->find($user->getId());
        
        if (!$user) {
            $this->addFlash('error', 'Usuario no encontrado.');
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(PerfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verificar si el email ya existe en otro usuario
            if ($user->getEmail()) {
                $existingUser = $this->userRepository->findOneBy(['email' => $user->getEmail()]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'Este email ya está registrado por otro usuario.');
                    return $this->render('perfil/index.html.twig', [
                        'form' => $form,
                        'user' => $user,
                        'retencionAnios' => SupresionDatosService::RETENCION_RESULTADOS_ANIOS,
                    ]);
                }
            }

            // Si se proporcionó una nueva contraseña, hashearla
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Tu perfil se ha actualizado correctamente.');
            return $this->redirectToRoute('app_perfil_index');
        }

        return $this->render('perfil/index.html.twig', [
            'form' => $form,
            'user' => $user,
            'retencionAnios' => SupresionDatosService::RETENCION_RESULTADOS_ANIOS,
        ]);
    }

    #[Route('/exportar', name: 'app_perfil_exportar', methods: ['GET'])]
    public function exportar(ExportacionDatosService $exportacionDatosService): Response
    {
        $user = $this->usuarioActual();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        $json = $exportacionDatosService->exportarJson($user);
        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="mis-datos-bispol.json"');

        return $response;
    }

    #[Route('/suprimir', name: 'app_perfil_suprimir', methods: ['POST'])]
    public function suprimir(Request $request, SupresionDatosService $supresionDatosService): Response
    {
        $user = $this->usuarioActual();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        $token = $request->request->getString('_token');
        if ($token === '') {
            $token = $request->getPayload()->getString('_token');
        }
        if (!$this->isCsrfTokenValid('suprimir-cuenta', $token)) {
            $this->addFlash('error', 'La solicitud no es válida. Vuelve a intentarlo.');

            return $this->redirectToRoute('app_perfil_index');
        }

        $confirmar = $request->request->getString('confirmar');
        if ($confirmar === '') {
            $confirmar = $request->getPayload()->getString('confirmar');
        }
        if ($confirmar !== '1') {
            $this->addFlash('error', 'Marca la casilla de confirmación para suprimir tus datos.');

            return $this->redirectToRoute('app_perfil_index');
        }

        try {
            $supresionDatosService->suprimir($user);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_perfil_index');
        }

        return $this->redirectToRoute('app_logout');
    }

    private function usuarioActual(): ?User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->userRepository->find($user->getId());
    }
}
