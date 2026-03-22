<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\InformeMensualPdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/informe-mensual')]
#[IsGranted('ROLE_PROFESOR')]
class InformeMensualController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private InformeMensualPdfService $informeMensualPdfService
    ) {
    }

    #[Route('/', name: 'app_informe_mensual_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');

        $alumnosIds = [];
        if (!$esAdmin) {
            $alumnosIds = array_map(function ($alumno) {
                return $alumno->getId();
            }, $usuarioActual->getAlumnos()->toArray());

            if (empty($alumnosIds)) {
                $alumnosIds = [-1];
            }
        }

        $itemsPerPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('search', '');

        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%');

        if (!$esAdmin && !empty($alumnosIds)) {
            $qb->andWhere('u.id IN (:alumnosIds)')
               ->setParameter('alumnosIds', $alumnosIds);
        }

        if (!empty($search)) {
            $qb->andWhere('(u.username LIKE :search OR u.nombre LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalQb = clone $qb;
        $totalItems = (int) $totalQb->select('COUNT(u.id)')
                                   ->getQuery()
                                   ->getSingleScalarResult();

        $offset = ($page - 1) * $itemsPerPage;
        $alumnos = $qb->orderBy('u.username', 'ASC')
                     ->setFirstResult($offset)
                     ->setMaxResults($itemsPerPage)
                     ->getQuery()
                     ->getResult();

        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $page = min($page, $totalPages);

        $anioActual = (int) date('Y');
        $mesActual = (int) date('m');

        return $this->render('informe_mensual/index.html.twig', [
            'alumnos' => $alumnos,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
            'anioActual' => $anioActual,
            'mesActual' => $mesActual,
        ]);
    }

    #[Route('/{id}/generar', name: 'app_informe_mensual_generar', methods: ['GET'])]
    public function generarInforme(User $alumno, Request $request): Response
    {
        $usuarioActual = $this->getUser();
        $esAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$esAdmin) {
            $alumnosIds = array_map(function ($a) {
                return $a->getId();
            }, $usuarioActual->getAlumnos()->toArray());

            if (!in_array($alumno->getId(), $alumnosIds)) {
                $this->addFlash('error', 'No tienes acceso a este alumno.');

                return $this->redirectToRoute('app_informe_mensual_index');
            }
        }

        $mes = $request->query->getInt('mes', (int) date('m'));
        $anio = $request->query->getInt('anio', (int) date('Y'));

        if ($mes < 1 || $mes > 12) {
            $this->addFlash('error', 'Mes inválido.');

            return $this->redirectToRoute('app_informe_mensual_index');
        }

        if ($anio < 2000 || $anio > 2100) {
            $this->addFlash('error', 'Año inválido.');

            return $this->redirectToRoute('app_informe_mensual_index');
        }

        return $this->informeMensualPdfService->generatePdf($alumno, $mes, $anio);
    }
}
