<?php

namespace App\Controller;

use App\Entity\Price;
use App\Entity\Product;
use App\Form\SetExportPriceType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/price')]
#[IsGranted('ROLE_STAFF')]
class PriceController extends AbstractController
{
    #[Route('/', name: 'price_index')]
    public function index(ProductRepository $productRepo, Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 12;

        $all = $productRepo->findBy([], ['id' => 'DESC']);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $all = \array_values(\array_filter(
                $all,
                function (Product $p) use ($needle) {
                    return mb_stripos($p->getName() ?? '', $needle) !== false;
                }
            ));
        }

        $total     = \count($all);
        $pageCount = (int) \ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset   = ($page - 1) * $perPage;
        $products = \array_slice($all, $offset, $perPage);

        return $this->render('price/index.html.twig', [
            'products'  => $products,
            'q'         => $q,
            'page'      => $page,
            'pageCount' => $pageCount,
            'total'     => $total,
        ]);
    }

    #[Route('/set/{id}', name: 'price_set')]
    public function set(
        int $id,
        ProductRepository $productRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $product = $productRepo->find($id);
        if (!$product) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(SetExportPriceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $exportPrice = (int) $form->get('exportPrice')->getData();
            if ($exportPrice < 2000) {
                $this->addFlash('admin.error', 'Minimum selling price is 2.000 VND.');
                return $this->redirectToRoute('price_set', ['id' => $product->getId()]);
            }

            $import = $product->getOriginalImportPrice();
            if ($import === null) {
                $this->addFlash('admin.error', 'Product does not have an import price yet.');
            } else {
                $price = new Price();
                $price->setProduct($product);
                $price->setImportPrice((float)$import);
                $price->setExportPrice((float)$exportPrice);
                $em->persist($price);
                $em->flush();

                $this->addFlash(
                    'admin.success',
                    'Export price updated: ' . \number_format($exportPrice, 0, '.', ',') . ' ₫'
                );
            }

            return $this->redirectToRoute('price_index');
        }

        return $this->render('price/set.html.twig', [
            'product' => $product,
            'form'    => $form->createView(),
        ]);
    }
}
