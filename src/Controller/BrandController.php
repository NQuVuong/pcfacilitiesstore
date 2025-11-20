<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Form\BrandType;
use App\Repository\BrandRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/brands')]
#[IsGranted('ROLE_STAFF')]
class BrandController extends AbstractController
{
    #[Route('/', name: 'brand_index')]
    public function index(BrandRepository $repo, Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $all = $repo->findBy([], ['name' => 'ASC']);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $all = \array_values(\array_filter(
                $all,
                function (Brand $b) use ($needle) {
                    return mb_stripos($b->getName() ?? '', $needle) !== false;
                }
            ));
        }

        $total     = \count($all);
        $pageCount = (int) \ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset = ($page - 1) * $perPage;
        $brands = \array_slice($all, $offset, $perPage);

        return $this->render('brand/index.html.twig', [
            'brands'    => $brands,
            'q'         => $q,
            'page'      => $page,
            'pageCount' => $pageCount,
            'total'     => $total,
        ]);
    }

    #[Route('/new', name: 'brand_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $brand = new Brand();
        $form = $this->createForm(BrandType::class, $brand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($brand);
            $em->flush();
            $this->addFlash('admin.success', 'Brand created.');
            return $this->redirectToRoute('brand_index');
        }

        return $this->render('brand/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Add Brand',
        ]);
    }

    #[Route('/{id}/edit', name: 'brand_edit')]
    public function edit(Brand $brand, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BrandType::class, $brand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('admin.success', 'Brand updated.');
            return $this->redirectToRoute('brand_index');
        }

        return $this->render('brand/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Brand',
        ]);
    }

    #[Route('/{id}/delete', name: 'brand_delete', methods: ['POST'])]
    public function delete(Brand $brand, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_brand_'.$brand->getId(), $request->request->get('_token'))) {
            $em->remove($brand);
            $em->flush();
            $this->addFlash('admin.success', 'Brand deleted.');
        } else {
            $this->addFlash('admin.error', 'Invalid token.');
        }
        return $this->redirectToRoute('brand_index');
    }
}
