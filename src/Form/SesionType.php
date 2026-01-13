<?php

namespace App\Form;

use App\Entity\Sesion;
use App\Entity\Tema;
use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use App\Entity\Convocatoria;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SesionType extends AbstractType
{
    private static ?array $temasMunicipalesRequestIds = null;
    private static ?int $municipioRequestId = null;
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tipo = $options['tipo'] ?? null;
        
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Sesión',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ]);
        
        // Solo agregar campos según el tipo
        if ($tipo === 'general') {
            $this->addGeneralFields($builder, $options);
        } elseif ($tipo === 'municipal') {
            $this->addMunicipalFields($builder, $options);
        } else {
            // Si no hay tipo, mostrar todos los campos (para edición)
            $this->addGeneralFields($builder, $options);
            $this->addMunicipalFields($builder, $options);
        }
        
        $builder->add('enlaceVideo', TextareaType::class, [
            'label' => 'Código de Video (Vimeo)',
            'required' => true,
            'attr' => [
                'class' => 'form-control',
                'rows' => 5,
                'placeholder' => 'Pega aquí el código iframe de Vimeo. Ejemplo: <iframe title="vimeo-player" src="https://player.vimeo.com/video/524933864?h=1ac4fd9fb4" width="640" height="360" frameborder="0" ...></iframe>'
            ],
            'help' => 'Pega el código completo del iframe de Vimeo que quieres incrustar.'
        ]);

        // Manejar la lógica de mostrar/ocultar campos según el tipo de tema (solo para edición)
        if (!$tipo) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $sesion = $event->getData();
                $form = $event->getForm();

                if ($sesion && $sesion->getId()) {
                    // Si ya existe, establecer el tipo de tema según los datos
                    if ($sesion->getTemas()->count() > 0) {
                        // Es tema general
                    } elseif ($sesion->getTemasMunicipales()->count() > 0) {
                        // Es tema municipal
                    }
                }
            });
        }
        
        // Listener PRE_SUBMIT para capturar los valores del request antes de la validación
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            
            // Capturar municipio del request
            if ($data && isset($data['municipio']) && $data['municipio']) {
                self::$municipioRequestId = (int)$data['municipio'];
            } else {
                self::$municipioRequestId = null;
            }
            
            // Capturar temas municipales del request
            if ($data && isset($data['temasMunicipales']) && is_array($data['temasMunicipales'])) {
                // Guardar los IDs de los temas municipales del request para usarlos en el query_builder
                self::$temasMunicipalesRequestIds = array_filter(array_map('intval', $data['temasMunicipales']));
            } else {
                self::$temasMunicipalesRequestIds = null;
            }
        });
        
        // Listener POST_SUBMIT para limpiar temas inválidos después de la validación
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $sesion = $event->getData();
            $form = $event->getForm();
            
            if (!$sesion) {
                return;
            }
            
            // Si hay temas municipales seleccionados, asegurar que pertenezcan al municipio correcto
            if ($sesion->getTemasMunicipales()->count() > 0 && $sesion->getMunicipio()) {
                $temasValidos = [];
                foreach ($sesion->getTemasMunicipales() as $temaMunicipal) {
                    // Verificar que el tema pertenece al municipio seleccionado
                    if ($temaMunicipal->getMunicipio() && $temaMunicipal->getMunicipio()->getId() === $sesion->getMunicipio()->getId()) {
                        $temasValidos[] = $temaMunicipal;
                    }
                }
                // Reemplazar la colección con solo los temas válidos
                $sesion->getTemasMunicipales()->clear();
                foreach ($temasValidos as $temaValido) {
                    $sesion->addTemaMunicipal($temaValido);
                }
            }
        });
    }
    
    private function addGeneralFields(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('temas', EntityType::class, [
            'class' => Tema::class,
            'choice_label' => 'nombre',
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'label' => 'Temas Generales',
            'attr' => [
                'class' => 'form-control',
                'size' => 10
            ],
            'query_builder' => function ($er) use ($options) {
                $qb = $er->createQueryBuilder('t')
                    ->where('t.activo = :activo')
                    ->setParameter('activo', true);
                
                // Incluir temas ya seleccionados para que pasen la validación
                $sesion = $options['data'] ?? null;
                if ($sesion && $sesion->getTemas()->count() > 0) {
                    $temasIds = $sesion->getTemas()->map(fn($t) => $t->getId())->toArray();
                    $qb->orWhere('t.id IN (:temasIds)')
                       ->setParameter('temasIds', $temasIds);
                }
                
                return $qb->orderBy('t.id', 'ASC');
            }
        ]);
    }
    
    private function addMunicipalFields(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('convocatoria', EntityType::class, [
                'class' => Convocatoria::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => 'Convocatoria',
                'attr' => [
                    'class' => 'form-control'
                ],
                'placeholder' => 'Selecciona una convocatoria'
            ])
            ->add('municipio', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => false,
                'label' => 'Municipio',
                'attr' => [
                    'class' => 'form-control'
                ],
                'placeholder' => 'Selecciona un municipio',
                'query_builder' => function ($er) use ($options) {
                    $qb = $er->createQueryBuilder('m')
                        ->where('m.activo = :activo')
                        ->setParameter('activo', true);
                    
                    // Si hay una convocatoria en la sesión, filtrar municipios de esa convocatoria
                    $sesion = $options['data'] ?? null;
                    if ($sesion && $sesion->getConvocatoria()) {
                        $municipiosIds = $sesion->getConvocatoria()->getMunicipios()->map(fn($m) => $m->getId())->toArray();
                        if (!empty($municipiosIds)) {
                            $qb->andWhere('m.id IN (:municipiosIds)')
                               ->setParameter('municipiosIds', $municipiosIds);
                        }
                        // Si hay un municipio ya seleccionado, incluirlo siempre para que pase la validación
                        if ($sesion->getMunicipio()) {
                            $qb->orWhere('m.id = :municipioId')
                               ->setParameter('municipioId', $sesion->getMunicipio()->getId());
                        }
                    }
                    
                    return $qb->orderBy('m.nombre', 'ASC');
                }
            ])
            ->add('temasMunicipales', EntityType::class, [
                'class' => TemaMunicipal::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Temas Municipales',
                'attr' => [
                    'class' => 'form-control',
                    'size' => 10
                ],
                'query_builder' => function ($er) use ($options) {
                    $qb = $er->createQueryBuilder('tm')
                        ->where('tm.activo = :activo')
                        ->setParameter('activo', true);
                    
                    $sesion = $options['data'] ?? null;
                    $temasSeleccionadosIds = [];
                    $municipioId = null;
                    
                    // Obtener municipio: primero del request (si existe), luego de la sesión
                    if (self::$municipioRequestId !== null) {
                        $municipioId = self::$municipioRequestId;
                    } elseif ($sesion && $sesion->getMunicipio()) {
                        $municipioId = $sesion->getMunicipio()->getId();
                    }
                    
                    // Obtener IDs de temas municipales ya seleccionados en la entidad
                    if ($sesion && $sesion->getTemasMunicipales()->count() > 0) {
                        $temasSeleccionadosIds = $sesion->getTemasMunicipales()->map(fn($tm) => $tm->getId())->toArray();
                    }
                    
                    // También incluir los IDs del request si existen (para validación durante el submit)
                    if (self::$temasMunicipalesRequestIds !== null && !empty(self::$temasMunicipalesRequestIds)) {
                        $temasSeleccionadosIds = array_unique(array_merge($temasSeleccionadosIds, self::$temasMunicipalesRequestIds));
                    }
                    
                    // Si hay un municipio (del request o de la sesión)
                    if ($municipioId) {
                        // Obtener el objeto Municipio para usar en la query
                        $municipioRepo = $er->getEntityManager()->getRepository(\App\Entity\Municipio::class);
                        $municipio = $municipioRepo->find($municipioId);
                        
                        if ($municipio) {
                            // Construir condición: temas del municipio O temas ya seleccionados (para validación)
                            if (!empty($temasSeleccionadosIds)) {
                                $qb->andWhere('(tm.municipio = :municipio OR tm.id IN (:temasIds))')
                                   ->setParameter('municipio', $municipio)
                                   ->setParameter('temasIds', $temasSeleccionadosIds);
                            } else {
                                $qb->andWhere('tm.municipio = :municipio')
                                   ->setParameter('municipio', $municipio);
                            }
                        } else {
                            // Si el municipio no existe pero hay temas seleccionados, incluirlos para validación
                            if (!empty($temasSeleccionadosIds)) {
                                $qb->andWhere('tm.id IN (:temasIds)')
                                   ->setParameter('temasIds', $temasSeleccionadosIds);
                            } else {
                                $qb->andWhere('1 = 0');
                            }
                        }
                    } else {
                        // Si no hay municipio seleccionado
                        if (!empty($temasSeleccionadosIds)) {
                            // Mostrar solo los temas ya seleccionados (para validación)
                            $qb->andWhere('tm.id IN (:temasIds)')
                               ->setParameter('temasIds', $temasSeleccionadosIds);
                        } else {
                            // Si no hay municipio ni temas seleccionados, no mostrar ningún tema
                            $qb->andWhere('1 = 0');
                        }
                    }
                    
                    return $qb->orderBy('tm.nombre', 'ASC');
                }
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sesion::class,
            'tipo' => null, // 'general', 'municipal', o null para ambos
        ]);
    }
}
