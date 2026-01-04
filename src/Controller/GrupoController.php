<?php

namespace App\Controller;

use App\Entity\Grupo;
use App\Form\GrupoType;
use App\Repository\GrupoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/grupo')]
#[IsGranted('ROLE_ADMIN')]
class GrupoController extends AbstractController
{
    #[Route('/', name: 'app_grupo_index', methods: ['GET'])]
    public function index(GrupoRepository $grupoRepository): Response
    {
        return $this->render('grupo/index.html.twig', [
            'grupos' => $grupoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_grupo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $grupo = new Grupo();
        $form = $this->createForm(GrupoType::class, $grupo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($grupo);
            $entityManager->flush();

            $this->addFlash('success', 'Grupo creado correctamente.');
            return $this->redirectToRoute('app_grupo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('grupo/new.html.twig', [
            'grupo' => $grupo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_grupo_show', methods: ['GET'])]
    public function show(Grupo $grupo): Response
    {
        return $this->render('grupo/show.html.twig', [
            'grupo' => $grupo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_grupo_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Grupo $grupo, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GrupoType::class, $grupo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Grupo actualizado correctamente.');
            return $this->redirectToRoute('app_grupo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('grupo/edit.html.twig', [
            'grupo' => $grupo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_grupo_delete', methods: ['POST'])]
    public function delete(Request $request, Grupo $grupo, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$grupo->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($grupo);
            $entityManager->flush();
            $this->addFlash('success', 'Grupo eliminado correctamente.');
        }

        return $this->redirectToRoute('app_grupo_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/gestionar-alumnos', name: 'app_grupo_gestionar_alumnos', methods: ['GET', 'POST'])]
    public function gestionarAlumnos(
        Request $request,
        Grupo $grupo,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $alumnoIds = $request->request->all()['alumnos'] ?? [];
            
            // Limpiar alumnos actuales
            foreach ($grupo->getAlumnos() as $alumno) {
                $grupo->removeAlumno($alumno);
            }

            // Asignar nuevos alumnos
            if (!empty($alumnoIds)) {
                $alumnos = $userRepository->createQueryBuilder('u')
                    ->where('u.id IN (:ids)')
                    ->setParameter('ids', $alumnoIds)
                    ->getQuery()
                    ->getResult();

                foreach ($alumnos as $alumno) {
                    $grupo->addAlumno($alumno);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Alumnos actualizados correctamente.');
            return $this->redirectToRoute('app_grupo_show', ['id' => $grupo->getId()], Response::HTTP_SEE_OTHER);
        }

        // Obtener todos los alumnos activos
        $todosAlumnos = $userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->orderBy('u.nombre', 'ASC')
            ->addOrderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('grupo/gestionar_alumnos.html.twig', [
            'grupo' => $grupo,
            'todosAlumnos' => $todosAlumnos,
        ]);
    }
}

