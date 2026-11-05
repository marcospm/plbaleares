<?php

namespace App\Form;

use App\Entity\Convocatoria;
use App\Entity\Municipio;
use App\Repository\MunicipioRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ConvocatoriaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre de la Convocatoria',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: Convocatoria Palma 2025'],
                'required' => true,
            ])
            ->add('municipios', EntityType::class, [
                'class' => Municipio::class,
                'choice_label' => 'nombre',
                'required' => true,
                'multiple' => true,
                'expanded' => false,
                'label' => 'Municipios <span style="color: #dc3545;">*</span>',
                'label_html' => true,
                'query_builder' => function (MunicipioRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->where('m.activo = :activo')
                        ->setParameter('activo', true)
                        ->orderBy('m.nombre', 'ASC');
                },
                'attr' => ['class' => 'form-control', 'size' => 5],
                'constraints' => [
                    new Count(
                        min: 1,
                        minMessage: 'Debes seleccionar al menos un municipio.'
                    ),
                ],
            ])
            ->add('fechaInscripcion', DateType::class, [
                'label' => 'Inscripción',
                'help' => 'Último día para inscribirse',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fechaTeorico', DateType::class, [
                'label' => 'Teórico',
                'help' => 'Día de la prueba',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fechaFisicas', DateType::class, [
                'label' => 'Físicas',
                'help' => 'Día de la prueba',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fechaPsicotecnico', DateType::class, [
                'label' => 'Psicotécnico',
                'help' => 'Día de la prueba',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('basesPdf', FileType::class, [
                'label' => 'Documentos (PDF)',
                'required' => false,
                'mapped' => false,
                'multiple' => true,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'application/pdf,.pdf',
                ],
                'help' => 'Opcional. Puedes subir uno o varios PDF: bases, admitidos, excluidos u otros. Cada archivo, máximo 30 MB. Si no subes nada, se mantienen los que ya hubiera.',
            ])
            ->add('activo', CheckboxType::class, [
                'label' => 'Activa',
                'required' => false,
                'data' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Convocatoria::class,
        ]);
    }
}












