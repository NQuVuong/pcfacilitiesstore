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
        RequestStack $requestStack,
        private ProductRepository $products
    ) {
        $session = $requestStack->getSession();
        if (!$session) {
            throw new \RuntimeException('Session is not available. Make sure the session is enabled.');
        }
        $this->session = $session;
    }

    /** Lấy toàn bộ giỏ thô: [productId => qty] */
    public function raw(): array
    {
        return $this->session->get(self::CART_KEY, []);
    }

    /** Tổng số lượng (không phải số dòng) */
    public function count(): int
    {
        return array_reduce($this->raw(), fn($s, $q) => $s + (int)$q, 0);
    }

    /** Thêm số lượng cho 1 sản phẩm (mặc định +1) */
    public function add(int $productId, int $qty = 1): void
    {
        $cart = $this->raw();
        $cart[$productId] = (int)($cart[$productId] ?? 0) + max(1, $qty);
        $this->session->set(self::CART_KEY, $cart);
    }

    /** Lấy số lượng hiện tại của 1 sản phẩm trong giỏ */
    public function get(int $productId): int
    {
        return (int)($this->raw()[$productId] ?? 0);
    }

    /**
     * Gán số lượng tuyệt đối cho 1 sản phẩm (<=0 thì xoá).
     * Alias: setQty()
     */
    public function set(int $productId, int $qty): void
    {
        $this->setQty($productId, $qty);
    }

    /** Gán số lượng tuyệt đối cho 1 sản phẩm (<=0 thì xoá) */
    public function setQty(int $productId, int $qty): void
    {
        $cart = $this->raw();

        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = (int)$qty;
        }

        $this->session->set(self::CART_KEY, $cart);
    }

    /** Xoá 1 sản phẩm khỏi giỏ */
    public function remove(int $productId): void
    {
        $cart = $this->raw();
        unset($cart[$productId]);
        $this->session->set(self::CART_KEY, $cart);
    }

    /** Xoá sạch giỏ */
    public function clear(): void
    {
        $this->session->remove(self::CART_KEY);
    }

    /**
     * Trả về danh sách item chi tiết để render
     * [
     *   ['id'=>..., 'name'=>..., 'image'=>..., 'unitPrice'=>..., 'qty'=>..., 'lineTotal'=>..., 'product'=>$entity],
     *   ...
     * ]
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->raw() as $id => $qty) {
            $p = $this->products->find((int)$id);
            if (!$p) {
                // Nếu sản phẩm không còn -> tự động loại khỏi giỏ
                $this->remove((int)$id);
                continue;
            }

            $unit = (float)($p->getCurrentExportPrice() ?? 0.0);
            $qty  = (int)$qty;

            $items[] = [
                'id'        => $p->getId(),
                'name'      => $p->getName(),
                'image'     => $p->getImage(),
                'unitPrice' => $unit,
                'qty'       => $qty,
                'lineTotal' => $unit * $qty,
                'product'   => $p,
            ];
        }

        return $items;
    }

    /** Tổng tiền giỏ hiện tại */
    public function total(): float
    {
        return array_reduce($this->items(), fn($s, $it) => $s + $it['lineTotal'], 0.0);
    }
    /** Xóa nhiều product id khỏi giỏ */
    public function removeMany(array $productIds): void
    {
        $cart = $this->raw();
        foreach ($productIds as $id) {
            unset($cart[(int)$id]);
        }
        $this->session->set(self::CART_KEY, $cart);
    }
}
