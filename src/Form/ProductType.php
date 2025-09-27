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

            // Category Dropdown
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])

            // Brand Dropdown
            ->add('brand', EntityType::class, [
                'class'        => Brand::class,
                'choice_label' => 'name',
                'placeholder'  => 'Select a brand',
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])

            // Import Price (mapped = false để lưu sang Price entity)
            ->add('importPrice', IntegerType::class, [
                'required' => false,
                'mapped' => false,
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

            // Created Date
            ->add('created', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5',
                ],
            ])

            // Upload File
            ->add('file', FileType::class, [
                'mapped' => false,
                'required' => false,
            ])

            // Image (nếu không upload file thì dùng text)
            ->add('image', TextType::class, [
                'required' => false,
            ])

            // Save Button
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr' => [
                    'class' => 'text-white bg-blue-700 hover:bg-blue-800 focus:ring-4
                                focus:outline-none focus:ring-blue-300 font-medium rounded-lg
                                text-sm px-5 py-2.5 text-center',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'is_edit' => false, // để check khi edit thì ẩn Import Price
        ]);
    }
}
