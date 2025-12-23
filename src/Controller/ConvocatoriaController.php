<?php

namespace App\Controller;

use App\Entity\Convocatoria;
use App\Form\ConvocatoriaType;
use App\Repository\ConvocatoriaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/convocatoria')]
#[IsGranted('ROLE_PROFESOR')]
class ConvocatoriaController extends AbstractController
{
    #[Route('/', name: 'app_convocatoria_index', methods: ['GET'])]
    public function index(ConvocatoriaRepository $convocatoriaRepository): Response
    {
        return $this->render('convocatoria/index.html.twig', [
            'convocatorias' => $convocatoriaRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_convocatoria_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $convocatoria = new Convocatoria();
        $form = $this->createForm(ConvocatoriaType::class, $convocatoria);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $convocatoria->setFechaActualizacion(new \DateTime());
            $entityManager->persist($convocatoria);
            $entityManager->flush();

            $this->addFlash('success', 'Convocatoria creada correctamente.');
            return $this->redirectToRoute('app_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('convocatoria/new.html.twig', [
            'convocatoria' => $convocatoria,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_convocatoria_show', methods: ['GET'])]
    public function show(Convocatoria $convocatoria): Response
    {
        return $this->render('convocatoria/show.html.twig', [
            'convocatoria' => $convocatoria,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_convocatoria_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Convocatoria $convocatoria, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConvocatoriaType::class, $convocatoria);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $convocatoria->setFechaActualizacion(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Convocatoria actualizada correctamente.');
            return $this->redirectToRoute('app_convocatoria_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('convocatoria/edit.html.twig', [
            'convocatoria' => $convocatoria,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-activo', name: 'app_convocatoria_toggle_activo', methods: ['POST'])]
    public function toggleActivo(Convocatoria $convocatoria, EntityManagerInterface $entityManager): Response
    {
        $convocatoria->setActivo(!$convocatoria->isActivo());
        $convocatoria->setFechaActualizacion(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Estado de la convocatoria actualizado.');
        return $this->redirectToRoute('app_convocatoria_index');
    }

    #[Route('/{id}/asignar-alumnos', name: 'app_convocatoria_asignar_alumnos', methods: ['GET', 'POST'])]
    public function asignarAlumnos(
        Request $request,
        Convocatoria $convocatoria,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $usuarioIds = $request->request->all()['usuarios'] ?? [];
            
            // Limpiar usuarios actuales
            foreach ($convocatoria->getUsuarios() as $usuario) {
                $convocatoria->removeUsuario($usuario);
            }
            
            // Añadir usuarios seleccionados
            if (!empty($usuarioIds)) {
                $usuarios = $userRepository->findBy(['id' => $usuarioIds]);
                foreach ($usuarios as $usuario) {
                    $convocatoria->addUsuario($usuario);
                }
            }
            
            $convocatoria->setFechaActualizacion(new \DateTime());
            $entityManager->flush();
            
            $this->addFlash('success', 'Alumnos asignados correctamente.');
            return $this->redirectToRoute('app_convocatoria_index');
        }

        // Obtener todos los usuarios activos que no sean profesores
        $todosUsuarios = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
        
        $usuarios = array_filter($todosUsuarios, function($usuario) {
            $roles = $usuario->getRoles();
            return !in_array('ROLE_PROFESOR', $roles) && !in_array('ROLE_ADMIN', $roles);
        });

        return $this->render('convocatoria/asignar_alumnos.html.twig', [
            'convocatoria' => $convocatoria,
            'usuarios' => $usuarios,
        ]);
    }

    #[Route('/{id}', name: 'app_convocatoria_delete', methods: ['POST'])]
    public function delete(Request $request, Convocatoria $convocatoria, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$convocatoria->getId(), $request->request->getString('_token'))) {
            $entityManager->remove($convocatoria);
            $entityManager->flush();
            $this->addFlash('success', 'Convocatoria eliminada correctamente.');
        }

        return $this->redirectToRoute('app_convocatoria_index', [], Response::HTTP_SEE_OTHER);
    }
}






