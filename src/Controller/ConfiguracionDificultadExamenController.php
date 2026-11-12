<?php

namespace App\Controller;

use App\Repository\ConfiguracionDificultadExamenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/dificultades-examen')]
#[IsGranted('ROLE_ADMIN')]
class ConfiguracionDificultadExamenController extends AbstractController
{
    #[Route('', name: 'app_configuracion_dificultad_examen', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ConfiguracionDificultadExamenRepository $configuracionRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $config = $configuracionRepository->obtener();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('dificultades_examen', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'La sesión del formulario ha caducado. Vuelve a intentarlo.');

                return $this->redirectToRoute('app_configuracion_dificultad_examen');
            }

            $facil = $request->request->getBoolean('facil');
            $moderada = $request->request->getBoolean('moderada');
            $dificil = $request->request->getBoolean('dificil');

            if (!$facil && !$moderada && !$dificil) {
                $this->addFlash('error', 'Tiene que quedar al menos un nivel activo.');
            } else {
                $config->setFacil($facil);
                $config->setModerada($moderada);
                $config->setDificil($dificil);
                $config->marcarActualizado();
                $entityManager->flush();
                $this->addFlash('success', 'Niveles de dificultad actualizados. Los exámenes nuevos solo podrán usar los que has dejado activos.');
            }

            return $this->redirectToRoute('app_configuracion_dificultad_examen');
        }

        return $this->render('configuracion_dificultad_examen/index.html.twig', [
            'config' => $config,
        ]);
    }
}
