<?php
// src/Controller/CategoryController.php
namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categories')]
class CategoryController extends AbstractController
{
    #[Route('/', name: 'category_index')]
    public function index(CategoryRepository $repo): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $repo->findBy([], ['name' => 'ASC']),
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
            $this->addFlash('success', 'Category created.');
            return $this->redirectToRoute('category_index');
        }

        return $this->render('category/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Add Category',
        ]);
    }


    #[Route('/{id}/edit', name: 'category_edit')]
    public function edit(Category $cat, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategoryType::class, $cat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Category updated.');
            return $this->redirectToRoute('category_index');
        }

        return $this->render('category/form.html.twig', ['form' => $form->createView(), 'title' => 'Edit Category']);
    }

    #[Route('/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function delete(Category $cat, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_cat_'.$cat->getId(), $request->request->get('_token'))) {
            $em->remove($cat);
            $em->flush();
            $this->addFlash('success', 'Đã xóa category thành công.');
        } else {
            $this->addFlash('error', 'Token không hợp lệ. Không thể xóa.');
        }
        return $this->redirectToRoute('category_index');
    }
}
