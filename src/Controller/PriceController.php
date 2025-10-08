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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/price')]
#[IsGranted('ROLE_STAFF')]
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

    #[Route('/set/{id}', name: 'price_set')]
    public function set(
        int $id,
        ProductRepository $productRepo,
        Request $request,
        EntityManagerInterface $em
    ): \Symfony\Component\HttpFoundation\Response {
        $product = $productRepo->find($id);
        if (!$product) { throw $this->createNotFoundException(); }

        $form = $this->createForm(SetExportPriceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $exportPrice = (int) $form->get('exportPrice')->getData();
            if ($exportPrice < 2000) {
                $this->addFlash('admin.error', 'Giá bán tối thiểu là 2.000 VND.');
                return $this->redirectToRoute('price_set', ['id' => $product->getId()]);
            }

            $import = $product->getOriginalImportPrice();
            if ($import === null) {
                $this->addFlash('admin.error', 'Product chưa có import price.');
            } else {
                $price = new Price();
                $price->setProduct($product);
                $price->setImportPrice((float)$import);
                $price->setExportPrice((float)$exportPrice);
                $em->persist($price);
                $em->flush();

                $this->addFlash('admin.success', 'Đã cập nhật giá bán: '.number_format($exportPrice, 0, '.', ',').' ₫');
            }

            return $this->redirectToRoute('price_index');
        }

        return $this->render('price/set.html.twig', [
            'product' => $product,
            'form'    => $form->createView(),
        ]);
    }
}
