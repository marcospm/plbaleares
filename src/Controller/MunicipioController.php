<?php

namespace App\Controller;

use App\Entity\Municipio;
use App\Form\MunicipioType;
use App\Repository\MunicipioRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/municipio')]
#[IsGranted('ROLE_PROFESOR')]
class MunicipioController extends AbstractController
{
    #[Route('/', name: 'app_municipio_index', methods: ['GET'])]
    public function index(MunicipioRepository $municipioRepository): Response
    {
        return $this->render('municipio/index.html.twig', [
            'municipios' => $municipioRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_municipio_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $municipio = new Municipio();
        $form = $this->createForm(MunicipioType::class, $municipio);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($municipio);
            $entityManager->flush();

            $this->addFlash('success', 'Municipio creado correctamente.');
            return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('municipio/new.html.twig', [
            'municipio' => $municipio,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_municipio_show', methods: ['GET'])]
    public function show(Municipio $municipio): Response
    {
        return $this->render('municipio/show.html.twig', [
            'municipio' => $municipio,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_municipio_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Municipio $municipio, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MunicipioType::class, $municipio);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Municipio actualizado correctamente.');
            return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('municipio/edit.html.twig', [
            'municipio' => $municipio,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_municipio_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Municipio $municipio, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$municipio->getId(), $request->getPayload()->getString('_token'))) {
            $municipio->setActivo(!$municipio->isActivo());
            $entityManager->flush();

            $estado = $municipio->isActivo() ? 'activado' : 'desactivado';
            $this->addFlash('success', "El municipio '{$municipio->getNombre()}' ha sido {$estado} correctamente.");
        }

        return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/asignar-alumnos', name: 'app_municipio_asignar_alumnos', methods: ['GET', 'POST'])]
    public function asignarAlumnos(Request $request, Municipio $municipio, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $usuariosIds = $request->request->all('usuarios') ?? [];
            
            // Obtener todos los usuarios activos que no son profesores
            $todosUsuarios = $userRepository->createQueryBuilder('u')
                ->where('u.activo = :activo')
                ->andWhere('u.roles NOT LIKE :role')
                ->setParameter('activo', true)
                ->setParameter('role', '%"ROLE_PROFESOR"%')
                ->getQuery()
                ->getResult();

            // Limpiar relaciones existentes
            foreach ($municipio->getUsuarios() as $usuario) {
                $municipio->removeUsuario($usuario);
            }

            // Añadir usuarios seleccionados
            foreach ($todosUsuarios as $usuario) {
                if (in_array($usuario->getId(), $usuariosIds)) {
                    $municipio->addUsuario($usuario);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Alumnos asignados correctamente al municipio.');
            return $this->redirectToRoute('app_municipio_index', [], Response::HTTP_SEE_OTHER);
        }

        // Obtener todos los usuarios activos que no son profesores
        $usuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :role')
            ->setParameter('activo', true)
            ->setParameter('role', '%"ROLE_PROFESOR"%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('municipio/asignar_alumnos.html.twig', [
            'municipio' => $municipio,
            'usuarios' => $usuarios,
        ]);
    }
}












