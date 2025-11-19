<?php

namespace App\Controller;

use App\Entity\ProductReview;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/product')]
class ProductReviewController extends AbstractController
{
    #[Route('/{slug}/review', name: 'product_review_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addReview(
        string $slug,
        Request $request,
        ProductRepository $products,
        ProductReviewRepository $reviewRepo,
        EntityManagerInterface $em
    ): Response {
        $product = $products->findOneBy(['slug' => $slug]);
        if (!$product) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $rating  = (int) $request->request->get('rating', 0);
        $comment = trim((string) $request->request->get('comment', ''));

        if ($rating < 1 || $rating > 5) {
            $this->addFlash('shop.error', 'Please choose a rating between 1 and 5 stars.');
            return $this->redirectToRoute('product_detail', ['slug' => $slug]);
        }

        // (Optional) bạn có thể check user đã mua sản phẩm hay chưa bằng OrderItem ở đây

        $review = new ProductReview();
        $review
            ->setProduct($product)
            ->setUser($user)
            ->setRating($rating)
            ->setComment($comment !== '' ? $comment : null);

        $em->persist($review);
        $em->flush();

        $this->addFlash('shop.success', 'Thank you for your review!');
        return $this->redirectToRoute('product_detail', ['slug' => $slug]);
    }
}
