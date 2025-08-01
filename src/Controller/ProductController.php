<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/product')]
class ProductController extends AbstractController
{
    private ProductRepository $repo;

    public function __construct(ProductRepository $repo)
    {
        $this->repo = $repo;
    }

    #[Route('/', name: 'app_product_index')]
    public function index(): Response
    {
        $products = $this->repo->findAll();
        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/{id}', name: 'product_detail', requirements: ['id' => '\d+'])]
    public function showAction(Product $p): Response
    {
        return $this->render('detail.html.twig', [
            'p' => $p
        ]);
    }

    #[Route('/add', name: 'product_create')]
    public function createAction(Request $req, SluggerInterface $slugger): Response
    {
        $p = new Product();
        $form = $this->createForm(ProductType::class, $p);

        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }
            $imgFile = $form->get('file')->getData();
            if ($imgFile) {
                $newFilename = $this->uploadImage($imgFile, $slugger);
                $p->setImage($newFilename);
            }
            $this->repo->add($p, true); // Changed from save to add
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }

    #[Route('/edit/{id}', name: 'product_edit', requirements: ['id' => '\d+'])]
    public function editAction(Request $req, Product $p, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $p);   
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }
            $imgFile = $form->get('file')->getData();
            if ($imgFile) {
                $newFilename = $this->uploadImage($imgFile, $slugger);
                $p->setImage($newFilename);
            }
            $this->repo->add($p, true); // Changed from save to add
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }

    public function uploadImage($imgFile, SluggerInterface $slugger): ?string
    {
        $originalFilename = pathinfo($imgFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imgFile->guessExtension();
        try {
            $imgFile->move(
                $this->getParameter('image_dir'),
                $newFilename
            );
        } catch (FileException $e) {
            echo $e;
        }
        return $newFilename;
    }

    #[Route('/delete/{id}', name: 'product_delete', requirements: ['id' => '\d+'])]
    public function deleteAction(Request $request, Product $p): Response
    {
        $this->repo->remove($p, true);
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'product_new')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $p = new Product();
        $form = $this->createForm(ProductType::class, $p);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }
            $imgFile = $form->get('file')->getData();
            if ($imgFile) {
                $newFilename = $this->uploadImage($imgFile, $slugger);
                $p->setImage($newFilename);
            }
            $this->repo->add($p, true);
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }
}