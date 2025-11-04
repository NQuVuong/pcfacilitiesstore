<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\Supplier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\SpecItemType;

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

            // Supplier
            ->add('supplier', EntityType::class, [
                'class' => Supplier::class,
                'choice_label' => 'name',
                'placeholder'  => 'Select a supplier',
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

            // Main image file (upload)
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Main image',
                'attr' => ['accept' => 'image/*'],
            ])

            // Gallery (multi)
            ->add('galleryFiles', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'label' => 'Gallery Images (multiple)',
                'attr' => ['accept' => 'image/*'],
            ])

            // Description (TinyMCE)
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'tinymce w-full',
                    'rows'  => 20,
                    'style' => 'min-height:420px',
                ],
            ])

            // Specs (Collection)
            ->add('specs', CollectionType::class, [
                'label'         => 'Specifications',
                'entry_type'    => SpecItemType::class,
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
                'prototype'     => true,
                'required'      => false,
                'attr'          => ['class' => 'specs-collection'],
            ])

            // Save
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr' => ['class' => 'btn btn-primary'],
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
