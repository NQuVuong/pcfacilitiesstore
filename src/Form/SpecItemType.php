<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class SpecItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('key', TextType::class, [
                'label' => false,
                'attr'  => [
                    'placeholder' => 'Example: Resolution',
                    'class'       => 'input input-bordered w-full'
                ],
            ])
            ->add('value', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => [
                    'rows'        => 1,
                    'placeholder' => 'Example: 1920 x 1.080 pixels',
                    'class'       => 'textarea textarea-bordered w-full'
                ],
            ]);
    }
}
