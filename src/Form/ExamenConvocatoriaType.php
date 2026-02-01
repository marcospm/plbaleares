<?php

namespace App\Form;

use App\Entity\ExamenConvocatoria;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExamenConvocatoriaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Convocatoria',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'help' => 'Ejemplo: "Convocatoria 2023 - Palma de Mallorca"'
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('archivoPDF', FileType::class, [
                'label' => 'Archivo PDF de Preguntas',
                'required' => $options['require_file'],
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf'
                ],
                'help' => 'Tamaño máximo: 20MB. Solo archivos PDF (extensión .pdf).'
            ])
            ->add('archivoRespuestas', FileType::class, [
                'label' => 'Archivo PDF de Respuestas (opcional)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf'
                ],
                'help' => 'PDF con las respuestas del examen. Los alumnos podrán descargarlo desde sus recursos.'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExamenConvocatoria::class,
            'require_file' => true,
        ]);
    }
}
