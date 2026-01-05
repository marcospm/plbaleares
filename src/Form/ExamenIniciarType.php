<?php

namespace App\Form;

use App\Entity\Tema;
use App\Entity\Municipio;
use App\Entity\TemaMunicipal;
use App\Entity\Convocatoria;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\FormError;

class ExamenIniciarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'] ?? null;
        $tieneMunicipiosActivos = $options['tiene_municipios_activos'] ?? false;
        
        // Construir las opciones de tipo de examen
        $choicesTipoExamen = [
            'Temario General' => 'general',
        ];
        
        // Solo agregar la opción municipal si el usuario tiene municipios activos
        if ($tieneMunicipiosActivos) {
            $choicesTipoExamen['Municipio'] = 'municipal';
        }
        
        // Agregar opción de convocatoria si el usuario tiene convocatorias activas
        $tieneConvocatoriasActivas = $options['tiene_convocatorias_activas'] ?? false;
        if ($tieneConvocatoriasActivas) {
            $choicesTipoExamen['Convocatoria'] = 'convocatoria';
        }
        
        $builder
            ->add('tipoExamen', ChoiceType::class, [
                'label' => 'Tipo de Examen',
                'choices' => $choicesTipoExamen,
                'required' => true,
                'expanded' => false,
                'multiple' => false,
                'data' => null, // Sin valor por defecto para mostrar el placeholder
                'placeholder' => 'Elige el tipo de examen',
                'attr' => ['class' => 'form-select form-select-lg', 'id' => 'tipo_examen_select', 'style' => 'font-size: 1rem; padding: 0.5rem 0.75rem;'],
            ])
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => false,
                'placeholder' => '-- Selecciona un municipio --',
                'attr' => ['class' => 'form-select', 'id' => 'municipio_select'],
                'query_builder' => function ($er) use ($user) {
                    $qb = $er->createQueryBuilder('m')
                        ->where('m.activo = :activo')
                        ->setParameter('activo', true);
                    if ($user) {
                        // Obtener municipios accesibles a través de las convocatorias del usuario
                        $municipiosAccesibles = $user->getMunicipiosAccesibles();
                        if ($municipiosAccesibles->count() > 0) {
                            $municipiosIds = array_map(fn($m) => $m->getId(), $municipiosAccesibles->toArray());
                            $qb->andWhere('m.id IN (:municipiosIds)')
                               ->setParameter('municipiosIds', $municipiosIds);
                        } else {
                            // Si no hay municipios accesibles, no mostrar ninguno
                            $qb->andWhere('1 = 0');
                        }
                    }
                    return $qb->orderBy('m.nombre', 'ASC');
                },
            ])
            ->add('convocatoria', EntityType::class, [
                'class' => Convocatoria::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => false,
                'placeholder' => '-- Selecciona una convocatoria --',
                'attr' => ['class' => 'form-select', 'id' => 'convocatoria_select'],
                'query_builder' => function ($er) use ($user) {
                    $qb = $er->createQueryBuilder('c')
                        ->where('c.activo = :activo')
                        ->setParameter('activo', true);
                    if ($user) {
                        $qb->innerJoin('c.usuarios', 'u')
                           ->andWhere('u.id = :userId')
                           ->setParameter('userId', $user->getId());
                    }
                    return $qb->orderBy('c.nombre', 'ASC');
                },
            ])
            ->add('temas', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Selecciona los Temas',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true)
                        ->orderBy('t.id', 'ASC');
                },
                'attr' => ['class' => 'form-check', 'id' => 'temas_general'],
                'placeholder' => false,
            ])
            ->add('temasMunicipales', EntityType::class, [
                'class' => TemaMunicipal::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Selecciona los Temas Municipales',
                'query_builder' => function ($er) use ($user, $options) {
                    $municipioId = $options['municipio_id'] ?? null;
                    $convocatoriaId = $options['convocatoria_id'] ?? null;
                    $qb = $er->createQueryBuilder('t')
                        ->where('t.activo = :activo')
                        ->setParameter('activo', true);
                    
                    if ($convocatoriaId) {
                        // Si hay convocatoria seleccionada, cargar temas de todos sus municipios
                        // Primero obtener los IDs de los municipios de la convocatoria
                        $em = $er->getEntityManager();
                        $convocatoria = $em->getRepository('App\Entity\Convocatoria')->find($convocatoriaId);
                        if ($convocatoria) {
                            $municipiosIds = array_map(function($m) {
                                return $m->getId();
                            }, $convocatoria->getMunicipios()->toArray());
                            
                            if (!empty($municipiosIds)) {
                                $qb->innerJoin('t.municipio', 'm')
                                   ->andWhere('m.id IN (:municipiosIds)')
                                   ->setParameter('municipiosIds', $municipiosIds);
                            } else {
                                // Si la convocatoria no tiene municipios, no mostrar temas
                                $qb->andWhere('1 = 0');
                            }
                        } else {
                            // Si no se encuentra la convocatoria, no mostrar temas
                            $qb->andWhere('1 = 0');
                        }
                    } elseif ($municipioId) {
                        // Filtrar por el ID del municipio usando join - solo temas de ese municipio
                        $qb->innerJoin('t.municipio', 'm')
                           ->andWhere('m.id = :municipioId')
                           ->setParameter('municipioId', $municipioId);
                    } else {
                        // Si no hay municipio ni convocatoria seleccionado, no mostrar ningún tema
                        $qb->andWhere('1 = 0');
                    }
                    return $qb->orderBy('t.nombre', 'ASC');
                },
                'attr' => ['class' => 'form-check', 'id' => 'temas_municipales', 'style' => 'display: none;'],
                'placeholder' => false,
            ])
            ->add('dificultad', ChoiceType::class, [
                'label' => 'Dificultad',
                'choices' => [
                    'Fácil' => 'facil',
                    'Moderada' => 'moderada',
                    'Difícil' => 'dificil',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('numeroPreguntas', ChoiceType::class, [
                'label' => 'Número de Preguntas',
                'choices' => [
                    '20' => 20,
                    '30' => 30,
                    '40' => 40,
                    '50' => 50,
                    '60' => 60,
                    '70' => 70,
                    '80' => 80,
                    '90' => 90,
                    '100' => 100,
                ],
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('tiempoLimite', ChoiceType::class, [
                'label' => 'Tiempo del Temporizador (minutos)',
                'choices' => [
                    '30 minutos' => 30,
                    '45 minutos' => 45,
                    '60 minutos' => 60,
                    '75 minutos' => 75,
                    '90 minutos' => 90,
                    '120 minutos' => 120,
                ],
                'required' => true,
                'data' => 60, // Valor por defecto: 60 minutos
                'attr' => ['class' => 'form-control'],
                'help' => 'Puedes pausar el temporizador en cualquier momento durante el examen.'
            ])
        ;

        // Agregar validación condicional para municipio y temas municipales
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();
            
            $tipoExamen = $data['tipoExamen'] ?? 'general';
            
            if ($tipoExamen === 'municipal') {
                $municipio = $data['municipio'] ?? null;
                $temasMunicipales = $data['temasMunicipales'] ?? null;
                
                // Validar que haya un municipio seleccionado
                if (!$municipio) {
                    $form->get('municipio')->addError(new FormError('Debes seleccionar un municipio para realizar un examen municipal.'));
                }
                
                // Validar que haya temas municipales seleccionados
                if (!$temasMunicipales || (is_countable($temasMunicipales) && count($temasMunicipales) === 0)) {
                    $form->get('temasMunicipales')->addError(new FormError('Debes seleccionar al menos un tema municipal.'));
                } elseif ($municipio) {
                    // Validar que los temas seleccionados pertenezcan al municipio
                    $temasArray = $temasMunicipales instanceof \Doctrine\Common\Collections\Collection 
                        ? $temasMunicipales->toArray() 
                        : (is_array($temasMunicipales) ? $temasMunicipales : [$temasMunicipales]);
                    
                    foreach ($temasArray as $tema) {
                        if ($tema->getMunicipio()->getId() !== $municipio->getId()) {
                            $form->get('temasMunicipales')->addError(new FormError('Los temas seleccionados deben pertenecer al municipio seleccionado.'));
                            break;
                        }
                    }
                }
            } else if ($tipoExamen === 'convocatoria') {
                $convocatoria = $data['convocatoria'] ?? null;
                $temasMunicipales = $data['temasMunicipales'] ?? null;
                
                // Validar que haya una convocatoria seleccionada
                if (!$convocatoria) {
                    $form->get('convocatoria')->addError(new FormError('Debes seleccionar una convocatoria para realizar un examen de convocatoria.'));
                }
                
                // Validar que haya temas municipales seleccionados
                if (!$temasMunicipales || (is_countable($temasMunicipales) && count($temasMunicipales) === 0)) {
                    $form->get('temasMunicipales')->addError(new FormError('Debes seleccionar al menos un tema municipal.'));
                } elseif ($convocatoria) {
                    // Validar que los temas seleccionados pertenezcan a algún municipio de la convocatoria
                    $municipiosConvocatoria = $convocatoria->getMunicipios();
                    $municipiosIds = array_map(fn($m) => $m->getId(), $municipiosConvocatoria->toArray());
                    
                    $temasArray = $temasMunicipales instanceof \Doctrine\Common\Collections\Collection 
                        ? $temasMunicipales->toArray() 
                        : (is_array($temasMunicipales) ? $temasMunicipales : [$temasMunicipales]);
                    
                    foreach ($temasArray as $tema) {
                        if (!in_array($tema->getMunicipio()->getId(), $municipiosIds)) {
                            $form->get('temasMunicipales')->addError(new FormError('Los temas seleccionados deben pertenecer a algún municipio de la convocatoria seleccionada.'));
                            break;
                        }
                    }
                }
            } else if ($tipoExamen === 'general') {
                // Validar que haya temas generales seleccionados
                $temas = $data['temas'] ?? null;
                if (!$temas || (is_countable($temas) && count($temas) === 0)) {
                    $form->get('temas')->addError(new FormError('Debes seleccionar al menos un tema del temario general.'));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'user' => null,
            'municipio_id' => null,
            'convocatoria_id' => null,
            'tiene_municipios_activos' => false,
            'tiene_convocatorias_activas' => false,
        ]);
    }
}

