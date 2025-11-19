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

    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(ProductRepository $products): Response
    {
        $raw   = $this->cart->raw();
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

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(int $id, Request $req, ProductRepository $products): Response
    {
        $qty = max(1, (int)$req->request->get('qty', 1));
        $p = $products->find($id);

        if (!$p) {
            $this->addFlash('shop.error', 'Product does not exist.');
            return $this->redirectToRoute('app_homepage');
        }

        $allowed = max(0, $p->getQuantity() - 1);
        if ($allowed <= 0) {
            $this->addFlash('shop.error', sprintf('"%s" is currently not available for purchase.', $p->getName()));
            return $this->redirectToRoute('app_homepage');
        }

        $current = (int)$this->cart->get($id);

        if ($current + $qty > $allowed) {
            $this->cart->set($id, $allowed);
            $this->addFlash(
                'shop.warning',
                sprintf('Only %d unit(s) of "%s" can be purchased. Cart quantity updated.', $allowed, $p->getName())
            );
        } else {
            $this->cart->add($id, $qty);
            $this->addFlash('shop.success', 'Added to cart.');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $req, ProductRepository $products): Response
    {
        $qty = max(1, (int)$req->request->get('qty', 1));
        $p = $products->find($id);

        if (!$p) {
            $this->addFlash('shop.error', 'Product does not exist.');
            return $this->redirectToRoute('app_cart_index');
        }

        $allowed = max(0, $p->getQuantity() - 1);
        if ($allowed <= 0) {
            $this->cart->remove($id);
            $this->addFlash('shop.error', sprintf('"%s" is no longer available.', $p->getName()));
            return $this->redirectToRoute('app_cart_index');
        }

        if ($qty > $allowed) {
            $qty = $allowed;
            $this->addFlash('shop.warning', sprintf('Maximum %d unit(s) of "%s".', $allowed, $p->getName()));
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

    // ------------------------------------------------------
    // CHECKOUT
    // ------------------------------------------------------

    #[Route('/checkout', name: 'app_cart_checkout_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkoutStart(Request $req): Response
    {
        $selected = $req->request->all('selected');

        if (empty($selected)) {
            $this->addFlash('shop.error', 'Please select at least one product to checkout.');
            return $this->redirectToRoute('app_cart_index');
        }

        $req->getSession()->set('checkout_selected', array_map('intval', $selected));
        return $this->redirectToRoute('app_cart_checkout_confirm');
    }

    #[Route('/checkout/confirm', name: 'app_cart_checkout_confirm', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkoutConfirm(
        Request $req,
        ProductRepository $products,
        EntityManagerInterface $em,
        MomoService $momo
    ): Response {
        $selected = $req->getSession()->get('checkout_selected', []);

        if (!$selected) {
            $this->addFlash('shop.error', 'Checkout session is not valid.');
            return $this->redirectToRoute('app_cart_index');
        }

        $raw   = $this->cart->raw();
        $items = [];
        $total = 0.0;

        foreach ($selected as $pid) {
            if (!isset($raw[$pid])) continue;

            $p = $products->find((int)$pid);
            if (!$p) continue;

            $qty     = (int)$raw[$pid];
            $allowed = max(0, $p->getQuantity() - 1);

            if ($qty <= 0 || $allowed <= 0 || $qty > $allowed) {
                $this->addFlash(
                    'shop.error',
                    sprintf('Available quantity of "%s" has changed. Please update your cart.', $p->getName())
                );
                return $this->redirectToRoute('app_cart_index');
            }

            $unit  = (float)($p->getCurrentExportPrice() ?? 0);
            $line  = $unit * $qty;
            $total += $line;

            $items[] = ['p' => $p, 'qty' => $qty, 'unit' => $unit, 'line' => $line];
        }

        if (!$items) {
            $this->addFlash('shop.error', 'No valid items to place order.');
            return $this->redirectToRoute('app_cart_index');
        }

        $user = $this->getUser();
        $defaults = [
            'customerName'  => $user?->getFullName() ?: $user?->getEmail(),
            'customerEmail' => $user?->getEmail(),
        ];

        $form = $this->createForm(CheckoutType::class, $defaults);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            $data          = $form->getData();
            $paymentMethod = $data['paymentMethod'] ?? 'COD';

            $order = new Order();
            $order->setUser($user);
            $order->setCustomerName($data['customerName']);
            $order->setCustomerEmail($data['customerEmail']);
            $order->setCustomerPhone($data['customerPhone']);
            $order->setShippingAddress($data['shippingAddress']);
            $order->setNote($data['note']);
            $order->setPaymentMethod($paymentMethod);

            // Status mapping:
            // COD → NEW               (shop preparing)
            // MOMO → PENDING          (waiting MoMo payment)
            $order->setStatus($paymentMethod === 'MOMO' ? 'PENDING' : 'NEW');
            $order->setTotal(number_format($total, 2, '.', ''));

            // Save order items
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

            // ------------------------
            // MoMo Payment
            // ------------------------
            if ($paymentMethod === 'MOMO') {
                $returnUrl = $this->generateUrl('momo_return', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $ipnUrl    = $this->generateUrl('momo_ipn', [], UrlGeneratorInterface::ABSOLUTE_URL);

                $pay = $momo->createPayment($order, $returnUrl, $ipnUrl);

                if (($pay['resultCode'] ?? -1) === 0 && !empty($pay['payUrl'])) {
                    $req->getSession()->remove('checkout_selected');
                    return $this->redirect($pay['payUrl']);
                }

                $this->addFlash('shop.error', 'MoMo error: ' . ($pay['message'] ?? 'unknown'));
                return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
            }

            // ------------------------
            // COD (Cash On Delivery)
            // ------------------------
            foreach ($items as $it) {
                $p = $it['p'];
                $p->setQuantity(max(0, $p->getQuantity() - $it['qty']));
                $this->cart->remove($p->getId());
            }

            $req->getSession()->remove('checkout_selected');

            $this->addFlash('shop.success', 'Order placed. Your package is being prepared.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $items,
            'total' => $total,
            'form'  => $form->createView(),
        ]);
    }
}
