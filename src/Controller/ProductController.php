<?php

namespace App\Controller;

use App\Entity\Price;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/product')]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductRepository $repo,
        private EntityManagerInterface $em
    ) {}

    /** Admin product list */
    #[Route('/', name: 'app_product_index')]
    #[IsGranted('ROLE_STAFF')]
    public function index(Request $request): Response
    {
        $q       = trim((string) $request->query->get('q', ''));
        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = 12;

        $allProducts = $this->repo->findBy([], ['id' => 'DESC']);

        // Filter theo tên nếu có q
        if ($q !== '') {
            $allProducts = \array_values(\array_filter(
                $allProducts,
                fn (Product $p) => stripos($p->getName(), $q) !== false
            ));
        }

        $total     = \count($allProducts);
        $pageCount = (int) \ceil(max(1, $total) / $perPage);
        $page      = min($page, max(1, $pageCount));

        $offset   = ($page - 1) * $perPage;
        $products = \array_slice($allProducts, $offset, $perPage);

        return $this->render('product/index.html.twig', [
            'products'  => $products,
            'page'      => $page,
            'pageCount' => $pageCount,
            'total'     => $total,
            'q'         => $q,
        ]);
    }

    /** Create — STAFF/ADMIN */
    #[Route('/add', name: 'product_create')]
    #[IsGranted('ROLE_STAFF')]
    public function createAction(Request $req, SluggerInterface $slugger): Response
    {
        $p = new Product();
        $form = $this->createForm(ProductType::class, $p);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }

            if (!$p->getSlug()) {
                $base = $slugger->slug((string) $p->getName())->lower();
                $p->setSlug($this->uniqueSlug((string)$base));
            }

            /** @var UploadedFile|null $imgFile */
            $imgFile = $form->get('file')->getData();
            if ($imgFile instanceof UploadedFile) {
                $p->setImage($this->uploadImage($imgFile, $slugger));
            } else {
                $p->setImage($form->get('image')->getData());
            }

            /** @var UploadedFile[] $galleryFiles */
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            foreach ($galleryFiles as $gf) {
                if (!$gf instanceof UploadedFile) continue;
                $filename = $this->uploadImage($gf, $slugger);
                $gi = (new ProductImage())->setProduct($p)->setPath($filename);
                $this->em->persist($gi);
            }

            $descImgs = $form->has('descImages') ? ($form->get('descImages')->getData() ?? []) : [];
            if ($descImgs) {
                $html = $p->getDescription() ?? '';
                foreach ($descImgs as $df) {
                    if (!$df instanceof UploadedFile) continue;
                    $fn = $this->uploadImage($df, $slugger);
                    $html .= sprintf('<p><img src="/uploads/%s" alt=""></p>', htmlspecialchars($fn, ENT_QUOTES));
                }
                $p->setDescription($html);
            }

            $this->repo->add($p, true);

            if ($form->has('importPrice')) {
                $ip = $form->get('importPrice')->getData();
                if ($ip !== null && $ip !== '') {
                    $price = (new Price())
                        ->setProduct($p)
                        ->setImportPrice((float)$ip)
                        ->setExportPrice(null);
                    $this->em->persist($price);
                    $this->em->flush();
                }
            }

            $this->addFlash('admin.success', 'Product created.');
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }

    /** Edit — STAFF/ADMIN */
    #[Route('/edit/{id}', name: 'product_edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STAFF')]
    public function editAction(Request $req, Product $p, SluggerInterface $slugger): Response
    {
        $oldName = $p->getName();
        $form = $this->createForm(ProductType::class, $p, ['is_edit' => true]);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($p->getCreated() === null) {
                $p->setCreated(new \DateTime());
            }

            if (!$p->getSlug() || $p->getName() !== $oldName) {
                $base = $slugger->slug((string) $p->getName())->lower();
                $p->setSlug($this->uniqueSlug((string)$base, $p->getId()));
            }

            /** @var UploadedFile|null $imgFile */
            $imgFile = $form->get('file')->getData();
            if ($imgFile instanceof UploadedFile) {
                $p->setImage($this->uploadImage($imgFile, $slugger));
            } else {
                if ($form->get('image')->getData()) {
                    $p->setImage($form->get('image')->getData());
                }
            }

            /** @var UploadedFile[] $galleryFiles */
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            foreach ($galleryFiles as $gf) {
                if (!$gf instanceof UploadedFile) continue;
                $filename = $this->uploadImage($gf, $slugger);
                $gi = (new ProductImage())->setProduct($p)->setPath($filename);
                $this->em->persist($gi);
            }

            $descImgs = $form->has('descImages') ? ($form->get('descImages')->getData() ?? []) : [];
            if ($descImgs) {
                $html = $p->getDescription() ?? '';
                foreach ($descImgs as $df) {
                    if (!$df instanceof UploadedFile) continue;
                    $fn = $this->uploadImage($df, $slugger);
                    $html .= sprintf('<p><img src="/uploads/%s" alt=""></p>', htmlspecialchars($fn, ENT_QUOTES));
                }
                $p->setDescription($html);
            }

            if ($form->has('importPrice')) {
                $ip = $form->get('importPrice')->getData();
                if ($ip !== null && $ip !== '') {
                    $price = (new Price())
                        ->setProduct($p)
                        ->setImportPrice((float)$ip);
                    $this->em->persist($price);
                }
            }

            $this->repo->add($p, true);

            $this->addFlash('admin.success', 'Product updated.');
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }

    /** Delete — STAFF/ADMIN */
    #[Route('/delete/{id}', name: 'product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function deleteAction(Request $request, Product $p): Response
    {
        if ($this->isCsrfTokenValid('delete_product_'.$p->getId(), $request->request->get('_token'))) {
            $this->repo->remove($p, true);
            $this->addFlash('admin.success', 'Product deleted.');
        } else {
            $this->addFlash('admin.error', 'Invalid token. Cannot delete.');
        }
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'product_new')]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        return $this->createAction($request, $slugger);
    }

    /** Public detail page — slug */
    #[Route('/{slug}', name:'product_detail',
        requirements:['slug'=>'(?!add$|edit$|delete$|new$|editor-upload$).*'], methods:['GET'])]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Product $p,
        ProductReviewRepository $reviewRepo
    ): Response {
        $session = $request->getSession();
        $ids = $session->get('recent_products', []);
        $currentId = $p->getId();

        $ids = array_filter(array_map('intval', (array)$ids), fn($v) => $v > 0 && $v !== $currentId);
        array_unshift($ids, $currentId);
        $ids = array_slice(array_unique($ids), 0, 10);
        $session->set('recent_products', $ids);

        $relatedProducts = [];
        if ($p->getCategory()) {
            $all = $this->repo->findTopByCategory($p->getCategory(), 12);
            foreach ($all as $rp) {
                if ($rp->getId() !== $p->getId()) {
                    $relatedProducts[] = $rp;
                }
            }
        }

        $reviews = $reviewRepo->findBy(['product' => $p], ['createdAt' => 'DESC']);
        $averageRating = null;
        if ($reviews) {
            $sum = 0;
            foreach ($reviews as $r) {
                $sum += $r->getRating();
            }
            $averageRating = round($sum / count($reviews), 1);
        }

        return $this->render('detail.html.twig', [
            'p'              => $p,
            'relatedProducts'=> $relatedProducts,
            'reviews'        => $reviews,
            'averageRating'  => $averageRating,
        ]);
    }

    // ================= helpers =================

    private function uploadImage(UploadedFile $imgFile, SluggerInterface $slugger): ?string
    {
        $originalFilename = pathinfo($imgFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $slugger->slug($originalFilename)->lower();
        $newFilename      = $safeFilename.'-'.uniqid().'.'.$imgFile->guessExtension();

        try {
            $imgFile->move($this->getParameter('image_dir'), $newFilename);
        } catch (FileException $e) {
            throw $e;
        }
        return $newFilename;
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $i = 1;
        while (true) {
            $found = $this->repo->findOneBy(['slug' => $slug]);
            if (!$found || ($ignoreId !== null && $found->getId() === $ignoreId)) {
                return $slug;
            }
            $slug = $base.'-'.$i++;
        }
    }

    #[Route('/editor-upload', name: 'editor_image_upload', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function editorUpload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file'], 400);
        }
        $ok = ['image/png','image/jpeg','image/gif','image/webp'];
        if (!in_array($file->getMimeType(), $ok, true)) {
            return new JsonResponse(['error' => 'Invalid type'], 415);
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['error' => 'File too large'], 413);
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = $slugger->slug((string)$original)->lower().'-'.uniqid().'.'.$file->guessExtension();
        $file->move($this->getParameter('image_dir'), $name);

        return new JsonResponse(['location' => '/uploads/'.$name]);
    }
}
