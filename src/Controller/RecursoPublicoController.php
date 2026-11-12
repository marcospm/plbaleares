<?php

namespace App\Controller;

use App\Repository\RecursoRepository;
use App\Repository\ExamenPDFRepository;
use App\Repository\ExamenConvocatoriaRepository;
use App\Repository\TemaRepository;
use App\Repository\TemaMunicipalRepository;
use App\Repository\MunicipioRepository;
use App\Entity\RecursoEspecifico;
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
        ExamenConvocatoriaRepository $examenConvocatoriaRepository,
        TemaRepository $temaRepository,
        TemaMunicipalRepository $temaMunicipalRepository,
        MunicipioRepository $municipioRepository,
        RecursoEspecificoRepository $recursoEspecificoRepository,
        ConvocatoriaRepository $convocatoriaRepository
    ): Response {
        $user = $this->getUser();
        
        // Obtener solo los 10 últimos exámenes PDF ordenados por fecha de subida descendente
        $examenes = $examenPDFRepository->findUltimos(10);
        
        // Obtener todos los exámenes de otras convocatorias ordenados por fecha de subida descendente
        $examenesConvocatorias = $examenConvocatoriaRepository->createQueryBuilder('e')
            ->orderBy('e.fechaSubida', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Obtener recursos específicos asignados al usuario
        $recursosEspecificos = [];
        if ($user) {
            $recursosEspecificos = $this->agruparRecursosEspecificos(
                $recursoEspecificoRepository->findByAlumno($user)
            );
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
                // Si el usuario está asignado a la convocatoria, tiene acceso a todos sus municipios
                foreach ($convocatoria->getMunicipios() as $municipio) {
                    if (!$municipio->isActivo()) {
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
            
            // Ya no se muestran municipios que no están en ninguna convocatoria
            // Todos los municipios deben pertenecer a una convocatoria para que los alumnos tengan acceso
        }
        
        return $this->render('recurso/publico_index.html.twig', [
            'examenes' => $examenes,
            'examenesConvocatorias' => $examenesConvocatorias,
            'recursosEspecificos' => $recursosEspecificos,
            'temas' => $temas,
            'temasPorMunicipio' => $temasPorMunicipio,
            'temasPorConvocatoria' => $temasPorConvocatoria,
        ]);
    }

    /**
     * @param list<RecursoEspecifico> $recursos
     * @return list<array{nombre: string, descripcion: ?string, profesor: mixed, fecha: ?\DateTimeInterface, recursos: list<RecursoEspecifico>}>
     */
    private function agruparRecursosEspecificos(array $recursos): array
    {
        $grupos = [];
        foreach ($recursos as $recurso) {
            $lote = $recurso->getLote();
            $clave = ($lote !== null && $lote !== '') ? 'lote-' . $lote : 'recurso-' . $recurso->getId();
            $grupos[$clave][] = $recurso;
        }

        $resultado = [];
        foreach ($grupos as $items) {
            usort($items, static fn (RecursoEspecifico $a, RecursoEspecifico $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));
            $primero = $items[0];
            $resultado[] = [
                'nombre' => $this->nombreVisibleDelGrupo($items),
                'descripcion' => $primero->getDescripcion(),
                'profesor' => $primero->getProfesor(),
                'fecha' => $primero->getFechaCreacion(),
                'recursos' => $items,
            ];
        }

        return $resultado;
    }

    /**
     * @param list<RecursoEspecifico> $recursos
     */
    private function nombreVisibleDelGrupo(array $recursos): string
    {
        return $recursos[0]->getNombreBase();
    }
}

