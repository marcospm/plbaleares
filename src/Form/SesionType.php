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
    private static ?array $municipiosRequestIds = null;
    
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
            // Determinar el valor por defecto según los datos existentes
            $sesion = $options['data'] ?? null;
            $tipoDefault = 'municipal'; // Por defecto municipal
            if ($sesion && $sesion->getId()) {
                if ($sesion->getTemas()->count() > 0) {
                    $tipoDefault = 'general';
                } elseif ($sesion->getTemasMunicipales()->count() > 0) {
                    $tipoDefault = 'municipal';
                }
            }
            
            // Agregar campo tipoTema para permitir cambiar entre general y municipal
            $builder->add('tipoTema', ChoiceType::class, [
                'label' => 'Tipo de Tema',
                'choices' => [
                    'General' => 'general',
                    'Municipal' => 'municipal',
                ],
                'required' => true,
                'mapped' => false,
                'data' => $tipoDefault, // Establecer valor por defecto
                'attr' => ['class' => 'form-control', 'id' => 'tipo_tema'],
                'help' => 'Selecciona si quieres usar temas generales o temas municipales.',
            ]);
            
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

                if ($sesion && $form->has('tipoTema')) {
                    // Si ya existe, establecer el tipo de tema según los datos
                    if ($sesion->getId()) {
                        if ($sesion->getTemas()->count() > 0) {
                            // Es tema general
                            $form->get('tipoTema')->setData('general');
                        } elseif ($sesion->getTemasMunicipales()->count() > 0) {
                            // Es tema municipal
                            $form->get('tipoTema')->setData('municipal');
                        } else {
                            // Si no hay temas, establecer un valor por defecto (municipal)
                            $form->get('tipoTema')->setData('municipal');
                        }
                    } else {
                        // Si es nueva sesión, establecer un valor por defecto
                        $form->get('tipoTema')->setData('municipal');
                    }
                }
            });
        }
        
        // Listener PRE_SUBMIT para capturar los valores del request antes de la validación
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            
            // Capturar municipios del request (ahora es un array)
            if ($data && isset($data['municipios']) && is_array($data['municipios'])) {
                self::$municipiosRequestIds = array_filter(array_map('intval', $data['municipios']));
            } else {
                self::$municipiosRequestIds = null;
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
            
            // Si hay temas municipales seleccionados, asegurar que pertenezcan a alguno de los municipios seleccionados
            if ($sesion->getTemasMunicipales()->count() > 0 && $sesion->getMunicipios()->count() > 0) {
                $municipiosIds = $sesion->getMunicipios()->map(fn($m) => $m->getId())->toArray();
                $temasValidos = [];
                foreach ($sesion->getTemasMunicipales() as $temaMunicipal) {
                    // Verificar que el tema pertenece a alguno de los municipios seleccionados
                    if ($temaMunicipal->getMunicipio() && in_array($temaMunicipal->getMunicipio()->getId(), $municipiosIds)) {
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
            ->add('convocatorias', EntityType::class, [
                'class' => Convocatoria::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Convocatorias',
                'attr' => [
                    'class' => 'form-control',
                    'size' => 5
                ],
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.nombre', 'ASC');
                }
            ])
            ->add('municipios', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Municipios',
                'attr' => [
                    'class' => 'form-control',
                    'size' => 5
                ],
                'query_builder' => function ($er) use ($options) {
                    $qb = $er->createQueryBuilder('m')
                        ->where('m.activo = :activo')
                        ->setParameter('activo', true);
                    
                    $sesion = $options['data'] ?? null;
                    $municipiosSeleccionadosIds = [];
                    
                    // Incluir municipios ya seleccionados para que siempre estén disponibles
                    if ($sesion && $sesion->getMunicipios()->count() > 0) {
                        $municipiosSeleccionadosIds = $sesion->getMunicipios()->map(fn($m) => $m->getId())->toArray();
                    }
                    
                    // Si hay municipios ya seleccionados, incluirlos siempre
                    if (!empty($municipiosSeleccionadosIds)) {
                        $qb->orWhere('m.id IN (:municipiosSeleccionadosIds)')
                           ->setParameter('municipiosSeleccionadosIds', $municipiosSeleccionadosIds);
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
                'label' => 'Temas Municipales (opcional)',
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
                    $municipiosIds = [];
                    
                    // Obtener municipios: primero del request (si existe), luego de la sesión
                    if (self::$municipiosRequestIds !== null && !empty(self::$municipiosRequestIds)) {
                        $municipiosIds = self::$municipiosRequestIds;
                    }
                    
                    if ($sesion && $sesion->getMunicipios()->count() > 0) {
                        $municipiosIds = array_merge($municipiosIds, $sesion->getMunicipios()->map(fn($m) => $m->getId())->toArray());
                    }
                    $municipiosIds = array_unique($municipiosIds);
                    
                    // Obtener IDs de temas municipales ya seleccionados en la entidad
                    if ($sesion && $sesion->getTemasMunicipales()->count() > 0) {
                        $temasSeleccionadosIds = $sesion->getTemasMunicipales()->map(fn($tm) => $tm->getId())->toArray();
                    }
                    
                    // También incluir los IDs del request si existen (para validación durante el submit)
                    if (self::$temasMunicipalesRequestIds !== null && !empty(self::$temasMunicipalesRequestIds)) {
                        $temasSeleccionadosIds = array_unique(array_merge($temasSeleccionadosIds, self::$temasMunicipalesRequestIds));
                    }
                    
                    // Si hay municipios seleccionados, filtrar temas de esos municipios
                    if (!empty($municipiosIds)) {
                        if (!empty($temasSeleccionadosIds)) {
                            // Mostrar temas de los municipios seleccionados O temas ya seleccionados (para validación)
                            $qb->andWhere('(tm.municipio IN (:municipiosIds) OR tm.id IN (:temasIds))')
                               ->setParameter('municipiosIds', $municipiosIds)
                               ->setParameter('temasIds', $temasSeleccionadosIds);
                        } else {
                            // Mostrar solo temas de los municipios seleccionados
                            $qb->andWhere('tm.municipio IN (:municipiosIds)')
                               ->setParameter('municipiosIds', $municipiosIds);
                        }
                    } else {
                        // Si no hay municipios seleccionados, mostrar todos los temas activos o solo los ya seleccionados
                        if (!empty($temasSeleccionadosIds)) {
                            // Mostrar solo los temas ya seleccionados (para validación)
                            $qb->andWhere('tm.id IN (:temasIds)')
                               ->setParameter('temasIds', $temasSeleccionadosIds);
                        }
                        // Si no hay municipios ni temas seleccionados, mostrar todos los temas activos (opcional)
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
