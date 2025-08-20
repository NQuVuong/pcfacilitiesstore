<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class SetExportPriceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('exportPrice', NumberType::class, [
                'required' => true,
                'label' => 'Export Price',
            ])
            ->add('save', SubmitType::class, ['label' => 'Save']);
    }
}
