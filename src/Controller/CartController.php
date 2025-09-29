<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Form\CheckoutType;
use App\Repository\ProductRepository;
use App\Service\CartService;
use App\Service\MomoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cart')]
class CartController extends AbstractController
{
    public function __construct(private CartService $cart) {}

    // ====== LIST CART ======
    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(ProductRepository $products): Response
    {
        $raw   = $this->cart->raw(); // [productId => qty]
        $rows  = [];
        $total = 0.0;

        foreach ($raw as $pid => $qty) {
            $p = $products->find((int)$pid);
            if (!$p) continue;

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
    public function add(int $id, Request $req, ProductRepository $products): Response
    {
        $qty = max(1, (int)$req->request->get('qty', 1));
        $p = $products->find($id);
        if (!$p) {
            $this->addFlash('error', 'Sản phẩm không tồn tại.');
            return $this->redirectToRoute('app_homepage');
        }

        // allowed = quantity - 1 (không cho mua nếu còn 1)
        $allowed = max(0, $p->getQuantity() - 1);
        if ($allowed <= 0) {
            $this->addFlash('error', sprintf('"%s" đang đạt giới hạn. Chưa thể mua lúc này.', $p->getName()));
            return $this->redirectToRoute('app_homepage');
        }

        // Không cho tổng số lượng trong giỏ > allowed
        $current = (int)$this->cart->get($id); // nếu chưa có trả 0
        if ($current + $qty > $allowed) {
            $newQty = $allowed; // chặn ở mức tối đa
            $this->cart->set($id, $newQty);
            $this->addFlash(
                'warning',
                sprintf('"%s" chỉ còn %d suất có thể mua. Đã cập nhật số lượng trong giỏ.', $p->getName(), $allowed)
            );
        } else {
            $this->cart->add($id, $qty);
            $this->addFlash('success', 'Đã thêm vào giỏ.');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $req, ProductRepository $products): Response
    {
        $qty = max(1, (int)$req->request->get('qty', 1));
        $p = $products->find($id);
        if (!$p) {
            $this->addFlash('error', 'Sản phẩm không tồn tại.');
            return $this->redirectToRoute('app_cart_index');
        }

        $allowed = max(0, $p->getQuantity() - 1);
        if ($allowed <= 0) {
            $this->cart->remove($id);
            $this->addFlash('error', sprintf('"%s" đã đạt giới hạn, không thể mua.', $p->getName()));
            return $this->redirectToRoute('app_cart_index');
        }

        if ($qty > $allowed) {
            $qty = $allowed;
            $this->addFlash('warning', sprintf('Tối đa mua được %d sản phẩm "%s".', $allowed, $p->getName()));
        }

        $this->cart->set($id, $qty);
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
        EntityManagerInterface $em,
        MomoService $momo
    ): Response {
        $selected = $req->getSession()->get('checkout_selected', []);
        if (!$selected) {
            $this->addFlash('error', 'Phiên thanh toán không hợp lệ.');
            return $this->redirectToRoute('app_cart_index');
        }

        // chuẩn bị danh sách item được chọn + KIỂM TRA LẠI TỒN KHO
        $raw = $this->cart->raw(); // id=>qty
        $items = [];
        $total = 0.0;

        foreach ($selected as $pid) {
            if (!isset($raw[$pid])) continue;

            $p = $products->find((int)$pid);
            if (!$p) continue;

            $qty = (int)$raw[$pid];

            // Re-check rule: có thể mua = quantity - 1
            $allowed = max(0, $p->getQuantity() - 1);
            if ($qty <= 0 || $allowed <= 0 || $qty > $allowed) {
                $this->addFlash(
                    'error',
                    sprintf('Số lượng khả dụng của "%s" đã thay đổi. Vui lòng cập nhật giỏ hàng.', $p->getName())
                );
                return $this->redirectToRoute('app_cart_index');
            }

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
            $data = $form->getData();

            // tạo Order (PENDING)
            $order = new Order();
            $order->setUser($user);
            $order->setCustomerName($data['customerName'] ?? null);
            $order->setCustomerEmail($data['customerEmail'] ?? null);
            $order->setCustomerPhone($data['customerPhone'] ?? null);
            $order->setShippingAddress($data['shippingAddress'] ?? null);
            $order->setNote($data['note'] ?? null);
            $order->setPaymentMethod($data['paymentMethod'] ?? 'COD');
            $order->setStatus('PENDING');
            $order->setTotal(number_format($total, 2, '.', ''));

            foreach ($items as $it) {
                $order->addItem(
                    (new OrderItem())
                        ->setProduct($it['p'])
                        ->setProductName($it['p']->getName())
                        ->setUnitPrice(number_format($it['unit'], 2, '.', ''))
                        ->setQuantity($it['qty'])
                        ->setLineTotal(number_format($it['line'], 2, '.', ''))
                );
            }

            $em->persist($order);
            $em->flush();

            // Nếu chọn MoMo: tạo payment & redirect (KHÔNG trừ tồn kho ở đây)
            if (($data['paymentMethod'] ?? 'COD') === 'MOMO') {
                $returnUrl = $this->generateUrl('momo_return', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $ipnUrl    = $this->generateUrl('momo_ipn',    [], UrlGeneratorInterface::ABSOLUTE_URL);

                $pay = $momo->createPayment($order, $returnUrl, $ipnUrl);
                if (($pay['resultCode'] ?? -1) === 0 && !empty($pay['payUrl'])) {
                    $req->getSession()->remove('checkout_selected'); // tránh double-submit
                    return $this->redirect($pay['payUrl']);
                }

                $this->addFlash('error', 'MoMo error: ' . ($pay['message'] ?? 'unknown'));
                return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
            }

            // COD/BANK: hoàn tất tại chỗ -> TRỪ TỒN, DỌN GIỎ
            foreach ($items as $it) {
                $p = $it['p'];
                $p->setQuantity(max(0, $p->getQuantity() - $it['qty']));
                $this->cart->remove($p->getId());
            }

            $req->getSession()->remove('checkout_selected');

            // Đơn đã đặt thành công (chưa thanh toán online)
            $order->setStatus('NEW');
            $em->flush();

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
