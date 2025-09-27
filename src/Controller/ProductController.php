<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Product;
use App\Entity\Price;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/product')]
class ProductController extends AbstractController
{
    private ProductRepository $repo;
    private EntityManagerInterface $em;

    public function __construct(ProductRepository $repo, EntityManagerInterface $em)
    {
        $this->repo = $repo;
        $this->em   = $em;
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
        $form = $this->createForm(\App\Form\ProductType::class, $p);

        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }

            /** @var UploadedFile|null $imgFile */
            $imgFile = $form->get('file')->getData();
            if ($imgFile instanceof UploadedFile) {
                $newFilename = $this->uploadImage($imgFile, $slugger);
                $p->setImage($newFilename);
            } else {
                $p->setImage($form->get('image')->getData());
            }

            // Lưu Product trước để có ID
            $this->repo->add($p, true);

            // === TẠO BẢN GHI PRICE ĐẦU TIÊN (NHẬP TỪ importPrice) ===
            if ($form->has('importPrice')) {
                $importPrice = $form->get('importPrice')->getData();
                if ($importPrice !== null && $importPrice !== '') {
                    $price = new Price();
                    $price->setProduct($p);
                    $price->setImportPrice((float)$importPrice);
                    $price->setExportPrice(null); // admin sẽ set sau
                    $this->em->persist($price);
                    $this->em->flush();
                }
            }

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }

    #[Route('/edit/{id}', name: 'product_edit', requirements: ['id' => '\d+'])]
    public function editAction(Request $req, Product $p, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $p, [
        'is_edit' => true, // truyền option để ẩn importPrice
    ]);

        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }

            /** @var UploadedFile|null $imgFile */
            $imgFile = $form->get('file')->getData();
            if ($imgFile instanceof UploadedFile) {
                $newFilename = $this->uploadImage($imgFile, $slugger);
                $p->setImage($newFilename);
            } else {
                $p->setImage($form->get('image')->getData());
            }

            // Chỉ cập nhật Product, KHÔNG tạo Price mới ở màn edit
            $this->repo->add($p, true);

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
        
    }

    public function uploadImage(UploadedFile $imgFile, SluggerInterface $slugger): ?string
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
            // Có thể đổi sang addFlash + redirect nếu muốn UX tốt hơn
            throw $e;
        }
        return $newFilename;
    }

    #[Route('/delete/{id}', name: 'product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAction(Request $request, Product $p): Response
    {
        if ($this->isCsrfTokenValid('delete_product_'.$p->getId(), $request->request->get('_token'))) {
            $this->repo->remove($p, true);
            $this->addFlash('success', 'Đã xóa product thành công.');
        } else {
            $this->addFlash('error', 'Token không hợp lệ. Không thể xóa.');
        }
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'product_new')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        // giữ behavior giống /add cho tiện
        return $this->createAction($request, $slugger);
    }
}
