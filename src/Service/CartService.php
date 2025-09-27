<?php

namespace App\Service;

use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    public const CART_KEY = 'cart';

    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,               // <— thay vì SessionInterface
        private ProductRepository $products
    ) {
        $this->session = $requestStack->getSession(); // lấy session từ RequestStack
    }

    /** Lấy mảng thuần id=>qty */
    public function raw(): array
    {
        return $this->session->get(self::CART_KEY, []);
    }

    public function add(int $productId, int $qty = 1): void
    {
        $cart = $this->raw();
        $cart[$productId] = ($cart[$productId] ?? 0) + max(1, $qty);
        $this->session->set(self::CART_KEY, $cart);
    }

    public function setQty(int $productId, int $qty): void
    {
        $cart = $this->raw();
        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $qty;
        }
        $this->session->set(self::CART_KEY, $cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->raw();
        unset($cart[$productId]);
        $this->session->set(self::CART_KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->remove(self::CART_KEY);
    }

    /** Items chi tiết để render: name, image, unitPrice, qty, lineTotal, product */
    public function items(): array
    {
        $items = [];
        foreach ($this->raw() as $id => $qty) {
            $p = $this->products->find($id);
            if (!$p) {
                // Nếu product đã bị xóa -> bỏ khỏi cart
                $this->remove((int)$id);
                continue;
            }
            $unitPrice = $p->getCurrentExportPrice() ?? 0.0; // bạn đã có method này
            $items[] = [
                'id'        => $p->getId(),
                'name'      => $p->getName(),
                'image'     => $p->getImage(),
                'unitPrice' => (float)$unitPrice,
                'qty'       => (int)$qty,
                'lineTotal' => (float)$unitPrice * (int)$qty,
                'product'   => $p,
            ];
        }
        return $items;
    }

    public function total(): float
    {
        return array_reduce($this->items(), fn($s, $it) => $s + $it['lineTotal'], 0.0);
    }

    /** tổng số lượng (không phải số dòng) */
    public function count(): int
    {
        return array_reduce($this->raw(), fn($s, $q) => $s + (int)$q, 0);
    }
}
