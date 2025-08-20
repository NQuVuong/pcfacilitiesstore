<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('name')
            ->add('created', DateType::class, [
                'widget'   => 'single_text',
                'required' => false
            ])
            ->add('quantity', IntegerType::class, [
                'required'   => false,
                'empty_data' => '0',        // ← để trống -> 0, tránh null gây invalid
                'attr'       => ['min' => 0],
            ])
            ->add('file', FileType::class, [
                'label'    => 'Product Image',
                'required' => false,
                'mapped'   => false
            ])
            ->add('image', HiddenType::class, ['required' => false]);

        // Chỉ thêm giá nhập khi tạo sản phẩm
        if (!$isEdit) {
            $builder->add('importPrice', NumberType::class, [
                'mapped'     => false,
                'required'   => false,
                'empty_data' => null,       // ← để trống -> null
                'label'      => 'Giá nhập'
            ]);
        }

        $builder->add('save', SubmitType::class, ['label' => 'Confirm']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'is_edit'    => false,
            // (mặc định CSRF đã bật; để yên là đủ)
        ]);
    }
}
