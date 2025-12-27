<?php

namespace App\Controller;

use App\Entity\ConfiguracionExamen;
use App\Entity\Tema;
use App\Form\ConfiguracionExamenType;
use App\Repository\ConfiguracionExamenRepository;
use App\Repository\TemaRepository;
use App\Service\ConfiguracionExamenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/configuracion-examen')]
#[IsGranted('ROLE_ADMIN')]
class ConfiguracionExamenController extends AbstractController
{
    public function __construct(
        private TemaRepository $temaRepository,
        private ConfiguracionExamenRepository $configuracionExamenRepository,
        private ConfiguracionExamenService $configuracionExamenService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'app_configuracion_examen_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        // Obtener todos los temas activos ordenados por ID
        $temas = $this->temaRepository->findBy(['activo' => true], ['id' => 'ASC']);

        // Obtener o crear configuraciones para cada tema
        $configuraciones = [];
        foreach ($temas as $tema) {
            $config = $this->configuracionExamenRepository->findByTema($tema);
            if (!$config) {
                // Crear configuración si no existe
                $config = new ConfiguracionExamen();
                $config->setTema($tema);
                $config->setPorcentaje(null);
                $this->entityManager->persist($config);
            }
            $configuraciones[] = $config;
        }
        $this->entityManager->flush();

        // Preparar datos para el formulario
        $formData = ['configuraciones' => $configuraciones];

        $form = $this->createForm(ConfiguracionExamenType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $configuracionesForm = $data['configuraciones'] ?? [];

            // Obtener configuraciones existentes por ID y actualizar
            foreach ($configuracionesForm as $configForm) {
                $configId = $configForm->getId();
                if ($configId) {
                    $config = $this->configuracionExamenRepository->find($configId);
                    if ($config) {
                        $config->setPorcentaje($configForm->getPorcentaje());
                    }
                } else {
                    // Si no tiene ID, es nueva (no debería pasar, pero por si acaso)
                    $this->entityManager->persist($configForm);
                }
            }

            // Normalizar porcentajes automáticamente
            $configuracionesParaNormalizar = [];
            foreach ($configuracionesForm as $configForm) {
                $configId = $configForm->getId();
                if ($configId) {
                    $config = $this->configuracionExamenRepository->find($configId);
                    if ($config) {
                        $configuracionesParaNormalizar[] = $config;
                    }
                }
            }
            
            $configuracionesParaNormalizar = $this->configuracionExamenService->normalizarPorcentajes($configuracionesParaNormalizar);

            $this->entityManager->flush();

            $this->addFlash('success', 'Configuración de porcentajes guardada correctamente.');
            return $this->redirectToRoute('app_configuracion_examen_index', [], Response::HTTP_SEE_OTHER);
        }

        // Calcular suma total para mostrar en la vista
        $sumaTotal = 0;
        foreach ($configuraciones as $config) {
            if ($config->getPorcentaje() !== null) {
                $sumaTotal += (float) $config->getPorcentaje();
            }
        }

        return $this->render('configuracion_examen/index.html.twig', [
            'form' => $form,
            'configuraciones' => $configuraciones,
            'sumaTotal' => $sumaTotal,
        ]);
    }

    #[Route('/resetear', name: 'app_configuracion_examen_resetear', methods: ['POST'])]
    public function resetear(Request $request): Response
    {
        if ($this->isCsrfTokenValid('resetear_configuracion', $request->getPayload()->getString('_token'))) {
            // Obtener todas las configuraciones activas
            $configuraciones = $this->configuracionExamenRepository->findAllActivos();
            
            // Establecer todos los porcentajes a null
            foreach ($configuraciones as $config) {
                $config->setPorcentaje(null);
                $this->entityManager->persist($config);
            }
            $this->entityManager->flush();

            $this->addFlash('success', 'Porcentajes reseteados. Se usará distribución equitativa.');
        }

        return $this->redirectToRoute('app_configuracion_examen_index', [], Response::HTTP_SEE_OTHER);
    }
}

