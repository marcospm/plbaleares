<?php

namespace App\Controller;

use App\Repository\RecursoRepository;
use App\Repository\ExamenPDFRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Repository\RecursoEspecificoRepository;
use App\Repository\ConvocatoriaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recursos')]
class RecursoPublicoController extends AbstractController
{
    #[Route('/', name: 'app_recurso_publico_index', methods: ['GET'])]
    public function index(
        ExamenPDFRepository $examenPDFRepository, 
        TemaRepository $temaRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        RecursoEspecificoRepository $recursoEspecificoRepository,
        ConvocatoriaRepository $convocatoriaRepository
    ): Response {
        $user = $this->getUser();
        
        $examenes = $examenPDFRepository->findAll();
        // Ordenar por fecha de subida descendente
        usort($examenes, function($a, $b) {
            return $b->getFechaSubida() <=> $a->getFechaSubida();
        });
        
        // Obtener recursos específicos asignados al usuario
        $recursosEspecificos = [];
        if ($user) {
            $recursosEspecificos = $recursoEspecificoRepository->findByAlumno($user);
        }
        
        // Obtener todos los temas activos, ordenados por ID
        $temas = $temaRepository->findBy(['activo' => true], ['id' => 'ASC']);
        
        // Obtener temas municipales agrupados por convocatoria y municipio
        $temasPorConvocatoria = [];
        $temasPorMunicipio = []; // Municipios que no están en ninguna convocatoria
        $municipiosEnConvocatorias = [];
        
        if ($user) {
            // Obtener convocatorias activas del usuario
            $convocatorias = $convocatoriaRepository->findByUsuario($user);
            
            foreach ($convocatorias as $convocatoria) {
                if (!$convocatoria->isActivo()) {
                    continue;
                }
                
                $temasPorMunicipioConvocatoria = [];
                
                // Obtener municipios de la convocatoria
                foreach ($convocatoria->getMunicipios() as $municipio) {
                    if (!$municipio->isActivo()) {
                        continue;
                    }
                    
                    // Verificar que el usuario tenga acceso a este municipio
                    if (!$user->getMunicipios()->contains($municipio)) {
                        continue;
                    }
                    
                    $municipiosEnConvocatorias[] = $municipio->getId();
                    
                    $temasMunicipales = $temaMunicipalRepository->findByMunicipio($municipio);
                    if (count($temasMunicipales) > 0) {
                        $temasPorMunicipioConvocatoria[$municipio->getId()] = [
                            'municipio' => $municipio,
                            'temas' => $temasMunicipales,
                        ];
                    }
                }
                
                // Solo agregar la convocatoria si tiene temas
                if (!empty($temasPorMunicipioConvocatoria)) {
                    $temasPorConvocatoria[$convocatoria->getId()] = [
                        'convocatoria' => $convocatoria,
                        'municipios' => $temasPorMunicipioConvocatoria,
                    ];
                }
            }
            
            // Obtener municipios que NO están en ninguna convocatoria
            $municipiosActivos = $user->getMunicipios();
            foreach ($municipiosActivos as $municipio) {
                if ($municipio->isActivo() && !in_array($municipio->getId(), $municipiosEnConvocatorias)) {
                    $temasMunicipales = $temaMunicipalRepository->findByMunicipio($municipio);
                    if (count($temasMunicipales) > 0) {
                        $temasPorMunicipio[$municipio->getId()] = [
                            'municipio' => $municipio,
                            'temas' => $temasMunicipales,
                        ];
                    }
                }
            }
        }
        
        return $this->render('recurso/publico_index.html.twig', [
            'examenes' => $examenes,
            'recursosEspecificos' => $recursosEspecificos,
            'temas' => $temas,
            'temasPorMunicipio' => $temasPorMunicipio,
            'temasPorConvocatoria' => $temasPorConvocatoria,
        ]);
    }
}

