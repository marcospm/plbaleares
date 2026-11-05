<?php

namespace App\Form;

use App\Entity\Convocatoria;
use App\Entity\RecursoEspecifico;
use App\Entity\User;
use App\Entity\Grupo;
use App\Repository\ConvocatoriaRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class RecursoEspecificoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Recurso',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('grupo', EntityType::class, [
                'class' => Grupo::class,
                'choice_label' => function(Grupo $grupo) {
                    return $grupo->getNombre() . ' (' . $grupo->getAlumnos()->count() . ' alumno' . ($grupo->getAlumnos()->count() != 1 ? 's' : '') . ')';
                },
                'label' => 'Asignar a Grupo (opcional)',
                'required' => false,
                'placeholder' => 'Ningún grupo (asignar manualmente)',
                'query_builder' => $options['grupos_query_builder'],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'grupo-select'
                ],
                'help' => 'Si seleccionas un grupo, el recurso se asignará automáticamente a todos los alumnos de ese grupo y solo será visible para ellos.'
            ])
            ->add('convocatorias', EntityType::class, [
                'class' => Convocatoria::class,
                'choice_label' => function (Convocatoria $convocatoria) {
                    $nombre = $convocatoria->getNombre() ?? '';

                    return $convocatoria->isActivo() ? $nombre : $nombre . ' (inactiva)';
                },
                'label' => 'Asignar a convocatorias',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'query_builder' => function (ConvocatoriaRepository $repositorio) {
                    return $repositorio->createQueryBuilder('c')
                        ->orderBy('c.activo', 'DESC')
                        ->addOrderBy('c.nombre', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control',
                    'size' => 6,
                ],
                'help' => 'Otra forma de elegir alumnos: el recurso se asigna a los alumnos de las convocatorias seleccionadas. Mantén pulsada la tecla Ctrl para elegir varias.',
            ])
            ->add('alumnos', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getNombreDisplay() . ' (' . $user->getUsername() . ')';
                },
                'label' => 'Alumnos Asignados (opcional si seleccionas un grupo)',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => $options['alumnos_query_builder'],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'alumnos-select'
                ],
                'help' => 'Selecciona alumnos adicionales si lo deseas. Si has seleccionado un grupo, todos los alumnos del grupo se asignarán automáticamente.'
            ])
            ->add('tipoRecurso', ChoiceType::class, [
                'label' => 'Tipo de recurso',
                'mapped' => false,
                'choices' => [
                    'Archivo adjunto' => 'archivo',
                    'Enlace externo' => 'enlace',
                ],
                'expanded' => true,
                'data' => 'archivo',
                'attr' => ['class' => 'tipo-recurso-options'],
            ])
            ->add('enlace', TextType::class, [
                'label' => 'Enlace',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://ejemplo.com/documento.pdf',
                    'id' => 'recurso-enlace-input',
                ],
                'help' => 'URL del recurso (web, Drive, Dropbox, etc.).',
            ])
            ->add('archivo', FileType::class, [
                'label' => 'Archivo',
                'required' => false,
                'mapped' => false,
                'multiple' => $options['multiple_archivos'],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'recurso-archivo-input',
                ],
                'constraints' => [
                    $options['multiple_archivos']
                        ? new All(
                            constraints: [
                                new File(
                                    maxSize: '100M',
                                    maxSizeMessage: 'Hay un archivo demasiado grande. Tamaño máximo: 100 MB por archivo.'
                                ),
                            ]
                        )
                        : new File(
                            maxSize: '100M',
                            maxSizeMessage: 'El archivo es demasiado grande. Tamaño máximo: 100 MB.'
                        ),
                ],
                'help' => $options['multiple_archivos']
                    ? 'Puedes elegir varios archivos. Cada uno se guarda como un recurso, con el mismo nombre, descripción y alumnos. Máximo 100 MB por archivo.'
                    : 'Tamaño máximo: 100 MB. PDF u otro tipo de archivo.',
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $recurso = $event->getData();
            if (!$recurso instanceof RecursoEspecifico) {
                return;
            }

            $tipo = $recurso->tieneEnlace() && !$recurso->tieneArchivo() ? 'enlace' : 'archivo';
            $event->getForm()->get('tipoRecurso')->setData($tipo);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecursoEspecifico::class,
            'alumnos_query_builder' => null,
            'grupos_query_builder' => null,
            'multiple_archivos' => false,
        ]);
    }
}











