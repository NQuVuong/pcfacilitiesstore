<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Form\CheckoutType;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cart')]
class CartController extends AbstractController
{
    public function __construct(private CartService $cart) {}

    // ====== LIST CART ======
    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(ProductRepository $products): Response
    {
        // $this->cart->raw() giả định trả về [productId => qty]
        $raw   = $this->cart->raw();
        $rows  = [];
        $total = 0.0;

        foreach ($raw as $pid => $qty) {
            $p = $products->find((int)$pid);
            if (!$p) { continue; }

            $qty  = (int)$qty;
            $unit = (float) ($p->getCurrentExportPrice() ?? 0);
            $line = $unit * $qty;
            $total += $line;

            $rows[] = ['p' => $p, 'qty' => $qty, 'unit' => $unit, 'line' => $line];
        }

        return $this->render('cart/index.html.twig', [
            'rows'  => $rows,
            'total' => $total,
        ]);
    }

    // ====== ADD / UPDATE / REMOVE / CLEAR ======
    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(int $id, Request $req): Response
    {
        $qty = (int)($req->request->get('qty', 1));
        if ($qty < 1) { $qty = 1; }
        $this->cart->add($id, $qty);
        $this->addFlash('success', 'Đã thêm vào giỏ.');
        return $this->redirect($req->headers->get('referer') ?: $this->generateUrl('app_cart_index'));
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $req): Response
    {
        $qty = (int)$req->request->get('qty', 1);
        $this->cart->set($id, max(1, $qty));
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(int $id): Response
    {
        $this->cart->remove($id);
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(): Response
    {
        $this->cart->clear();
        return $this->redirectToRoute('app_cart_index');
    }

    // ====== CHECKOUT (Bước 1: nhận sp được chọn từ giỏ) ======
    #[Route('/checkout', name: 'app_cart_checkout_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkoutStart(Request $req): Response
    {
        $selected = $req->request->all('selected'); // ["12","15",...]
        if (empty($selected)) {
            $this->addFlash('error', 'Bạn chưa chọn sản phẩm để mua.');
            return $this->redirectToRoute('app_cart_index');
        }
        $req->getSession()->set('checkout_selected', array_map('intval', $selected));
        return $this->redirectToRoute('app_cart_checkout_confirm');
    }

    // ====== CHECKOUT (Bước 2: form thanh toán + place order) ======
    #[Route('/checkout/confirm', name: 'app_cart_checkout_confirm', methods: ['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkoutConfirm(
        Request $req,
        ProductRepository $products,
        EntityManagerInterface $em
    ): Response {
        $selected = $req->getSession()->get('checkout_selected', []);
        if (!$selected) {
            $this->addFlash('error', 'Phiên thanh toán không hợp lệ.');
            return $this->redirectToRoute('app_cart_index');
        }

        $raw   = $this->cart->raw(); // id=>qty
        $items = [];
        $total = 0.0;

        foreach ($selected as $pid) {
            if (!isset($raw[$pid])) continue;
            $p = $products->find((int)$pid);
            if (!$p) continue;

            $qty  = (int)$raw[$pid];
            $unit = (float)($p->getCurrentExportPrice() ?? 0);
            $line = $unit * $qty;
            $total += $line;

            $items[] = ['p' => $p, 'qty' => $qty, 'unit' => $unit, 'line' => $line];
        }

        if (!$items) {
            $this->addFlash('error', 'Không có sản phẩm hợp lệ để đặt hàng.');
            return $this->redirectToRoute('app_cart_index');
        }

        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        $defaults = [
            'customerName'  => $user?->getEmail(),
            'customerEmail' => $user?->getEmail(),
        ];

        $form = $this->createForm(CheckoutType::class, $defaults);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            $data  = $form->getData();

            $order = (new Order())
                ->setUser($user)
                ->setCustomerName($data['customerName'] ?? null)
                ->setCustomerEmail($data['customerEmail'] ?? null)
                ->setCustomerPhone($data['customerPhone'] ?? null)
                ->setShippingAddress($data['shippingAddress'] ?? null)
                ->setNote($data['note'] ?? null)
                ->setPaymentMethod($data['paymentMethod'] ?? 'COD')
                ->setStatus('NEW')
                ->setTotal(number_format($total, 2, '.', ''));

            foreach ($items as $it) {
                $oi = (new OrderItem())
                    ->setProduct($it['p'])
                    ->setProductName($it['p']->getName())
                    ->setUnitPrice(number_format($it['unit'], 2, '.', ''))
                    ->setQuantity($it['qty'])
                    ->setLineTotal(number_format($it['line'], 2, '.', ''));
                $order->addItem($oi);

                // xóa khỏi giỏ sau khi đã thêm vào đơn
                $this->cart->remove($it['p']->getId());
            }

            $em->persist($order);
            $em->flush();

            $req->getSession()->remove('checkout_selected');
            $this->addFlash('success', 'Đặt hàng thành công.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $items,
            'total' => $total,
            'form'  => $form->createView(),
        ]);
    }
}
