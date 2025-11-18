<?php

namespace App\Controller;

use App\Entity\Price;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\PageView;
use App\Form\ProductType;
use App\Repository\ProductRepository;
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

    /** Danh sách sản phẩm (quản trị) */
    #[Route('/', name: 'app_product_index')]
    #[IsGranted('ROLE_STAFF')]
    public function index(): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $this->repo->findAll(),
        ]);
    }

    /** Tạo mới — STAFF/ADMIN */
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

            $this->repo->add($p, true);

            $this->addFlash('admin.success', 'Product created.');
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render("product/form.html.twig", [
            'form' => $form->createView()
        ]);
    }

    /** Chỉnh sửa — STAFF/ADMIN */
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

    /** Xoá — STAFF/ADMIN */
    #[Route('/delete/{id}', name: 'product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function deleteAction(Request $request, Product $p): Response
    {
        if ($this->isCsrfTokenValid('delete_product_'.$p->getId(), $request->request->get('_token'))) {
            $this->repo->remove($p, true);
            $this->addFlash('admin.success', 'Đã xóa product thành công.');
        } else {
            $this->addFlash('admin.error', 'Token không hợp lệ. Không thể xóa.');
        }
        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    /** Alias */
    #[Route('/new', name: 'product_new')]
    #[IsGranted('ROLE_STAFF')]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        return $this->createAction($request, $slugger);
    }

    /** Trang chi tiết — PUBLIC — dùng slug. */
    #[Route(
        '/{slug}',
        name: 'product_detail',
        requirements: ['slug' => '(?!add$|edit$|delete$|new$|editor-upload$).*'],
        methods: ['GET']
    )]
    public function show(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Product $p): Response
    {
        // Track page view
        $visit = new PageView();
        $visit->setPath($request->getPathInfo());
        $visit->setProduct($p);
        $visit->setBrowser($this->detectBrowser($request->headers->get('User-Agent', '')));

        $this->em->persist($visit);
        $this->em->flush();

        return $this->render('detail.html.twig', ['p' => $p]);
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

    /** Tạo slug duy nhất, tránh trùng với sản phẩm khác */
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

    private function detectBrowser(?string $ua): string
    {
        $ua = strtolower($ua ?? '');

        if (str_contains($ua, 'edg')) {
            return 'Edge';
        }
        if (str_contains($ua, 'chrome')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'firefox')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'safari')) {
            return 'Safari';
        }
        if (str_contains($ua, 'opera') || str_contains($ua, 'opr/')) {
            return 'Opera';
        }
        if (str_contains($ua, 'msie') || str_contains($ua, 'trident')) {
            return 'IE';
        }

        return 'Other';
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
