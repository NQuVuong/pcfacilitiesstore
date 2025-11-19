<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

class SetExportPriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('exportPrice', NumberType::class, [
                'required' => true,
                'label'    => 'Export Price (VND)',
                'scale'    => 0,      // VND nguyên
                'html5'    => true,
                'attr'     => [
                    'min'  => 2000,
                    'step' => 1,      // <-- CHO PHÉP 1900, 2900, 3450,...
                    'placeholder' => 'VD: 2900, 2500000',
                    'class' => 'input input-bordered w-full',
                ],
                'constraints' => [
                    new Range([
                        'min' => 2000,
                        'notInRangeMessage' => 'Minimum selling price is 2,000 VND',
                    ]),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr'  => ['class' => 'btn btn-primary'],
            ]);
    }
}
