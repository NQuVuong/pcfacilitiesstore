<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Product Name
            ->add('name', TextType::class, [
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                    'placeholder' => 'Enter product name',
                ],
            ])
            // Category
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])
            // Brand
            ->add('brand', EntityType::class, [
                'class'        => Brand::class,
                'choice_label' => 'name',
                'placeholder'  => 'Select a brand',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])
            // Import Price (mapped=false -> lưu vào Price entity)
            ->add('importPrice', IntegerType::class, [
                'required' => false,
                'mapped'   => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                    'placeholder' => 'Enter import price',
                ],
            ])
            // Quantity
            ->add('quantity', IntegerType::class, [
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                    'placeholder' => 'Enter product quantity',
                ],
            ])
            // Created
            ->add('created', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])
            // Short image text fallback
            ->add('image', TextType::class, [
                'required' => false,
            ])
            // Main image file (replace)
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Main image',
            ])
            // NEW: Gallery images (multiple)
            ->add('galleryFiles', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'label' => 'Gallery images (you can select many)',
            ])
            // NEW: long description (HTML allowed if you dùng WYSIWYG)
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                    'placeholder' => 'Long description (HTML allowed)',
                ],
            ])
            // Save
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr' => [
                    'class' => 'text-white bg-blue-700 hover:bg-blue-800 focus:ring-4
                                focus:outline-none focus:ring-blue-300 font-medium rounded-lg
                                text-sm px-5 py-2.5 text-center',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'class' => 'textarea textarea-bordered w-full',
                    'placeholder' => 'Mô tả (hỗ trợ HTML). Có thể chèn <img ...> hoặc dùng ô tải ảnh bên dưới để tự chèn.'
                ],
            ])
            // ảnh chèn vào mô tả (tùy chọn)
            ->add('descImages', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => ['accept' => 'image/*'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'is_edit'    => false,
        ]);
    }
}
