<?php

namespace App\Controller;

use App\Entity\Price;
use App\Entity\Product;
use App\Form\SetExportPriceType;
use App\Repository\ProductRepository;
use App\Repository\PriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/price')]
class PriceController extends AbstractController
{
    #[Route('/', name: 'price_index')]
    public function index(ProductRepository $productRepo): \Symfony\Component\HttpFoundation\Response
    {
        $products = $productRepo->findAll();
        return $this->render('price/index.html.twig', [
            'products' => $products,
        ]);
    }

    // Set giá xuất cho 1 sản phẩm (tạo bản ghi Price mới, giữ nguyên importPrice của lần đầu)
    #[Route('/set/{id}', name: 'price_set')]
    public function set(int $id, ProductRepository $productRepo, Request $request, EntityManagerInterface $em): \Symfony\Component\HttpFoundation\Response
    {
        $product = $productRepo->find($id);
        if (!$product) { throw $this->createNotFoundException(); }

        $form = $this->createForm(SetExportPriceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $exportPrice = (float) $form->get('exportPrice')->getData();

            // Lấy importPrice gốc (không cho sửa)
            $import = $product->getOriginalImportPrice();
            if ($import === null) {
                $this->addFlash('error', 'Product chưa có import price.');
            } else {
                $price = new Price();
                $price->setProduct($product);
                $price->setImportPrice($import);
                $price->setExportPrice($exportPrice);
                $em->persist($price);
                $em->flush();
                $this->addFlash('success', 'Đã cập nhật giá xuất.');
            }

            return $this->redirectToRoute('price_index');
        }

        return $this->render('price/set.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }
}
