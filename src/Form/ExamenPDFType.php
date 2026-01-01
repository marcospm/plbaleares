<?php

namespace App\Form;

use App\Entity\ExamenPDF;
use App\Entity\Tema;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExamenPDFType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre del Examen',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción (opcional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('temas', EntityType::class, [
                'class' => Tema::class,
                'choice_label' => 'nombre',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Temas (opcional)',
                'required' => false,
                'placeholder' => 'Selecciona uno o más temas',
                'attr' => [
                    'class' => 'form-control',
                    'size' => 10
                ],
                'help' => 'Puedes seleccionar múltiples temas. Mantén presionada la tecla Ctrl (o Cmd en Mac) para seleccionar varios.'
            ])
            ->add('archivoPDF', FileType::class, [
                'label' => 'Archivo PDF del Examen',
                'required' => $options['require_file'],
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf'
                ],
                'help' => 'Tamaño máximo: 20MB. Solo archivos PDF (extensión .pdf).'
            ])
            ->add('archivoRespuestas', FileType::class, [
                'label' => 'Hoja de Respuestas (opcional)',
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
            'data_class' => ExamenPDF::class,
            'require_file' => true,
        ]);
    }
}

