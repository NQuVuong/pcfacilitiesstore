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
                    'placeholder' => 'Ví dụ: CPU',
                    'class'       => 'input input-bordered w-full'
                ],
            ])
            ->add('value', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => [
                    'rows'        => 1,
                    'placeholder' => 'Ví dụ: Intel Core i5-12400F',
                    'class'       => 'textarea textarea-bordered w-full'
                ],
            ]);
    }
}
