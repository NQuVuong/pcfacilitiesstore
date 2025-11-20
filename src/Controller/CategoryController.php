<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    #[Route('/', name: 'category_index')]
    public function index(CategoryRepository $repo, Request $request): Response
    {
        $q       = trim((string) $request->query->get('q', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $all = $repo->findBy([], ['name' => 'ASC']);

        if ($q !== '') {
            $all = \array_values(\array_filter(
                $all,
                fn (Category $c) => stripos($c->getName(), $q) !== false
            ));
        }

        $total     = \count($all);
        $pageCount = (int) \ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));
        $offset    = ($page - 1) * $perPage;
        $categories = \array_slice($all, $offset, $perPage);

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
            'q'          => $q,
            'page'       => $page,
            'pageCount'  => $pageCount,
            'total'      => $total,
        ]);
    }

    #[Route('/new', name: 'category_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $cat = new Category();
        $form = $this->createForm(CategoryType::class, $cat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cat);
            $em->flush();
            $this->addFlash('admin.success', 'Category created.');
            return $this->redirectToRoute('category_index');
        }

        return $this->render('category/form.html.twig', [
            'form'  => $form->createView(),
            'title' => 'Add Category',
        ]);
    }

    #[Route('/{id}/edit', name: 'category_edit', methods: ['GET','POST'])]
    public function edit(Category $cat, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategoryType::class, $cat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('admin.success', 'Category updated.');
            return $this->redirectToRoute('category_index');
        }

        return $this->render('category/form.html.twig', [
            'form'  => $form->createView(),
            'title' => 'Edit Category',
        ]);
    }

    #[Route('/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function delete(Category $cat, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_cat_'.$cat->getId(), $request->request->get('_token'))) {
            $em->remove($cat);
            $em->flush();
            $this->addFlash('admin.success', 'Deleted category successfully.');
        } else {
            $this->addFlash('admin.error', 'Delete category failed');
        }
        return $this->redirectToRoute('category_index');
    }
}
