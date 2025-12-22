<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Store;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Order;
use App\Models\ReturnModel;
use App\Helpers\Validator;

class ReturnsController extends BaseController
{
    private Store $stores;
    private Product $products;
    private StoreStock $storeStock;
    private PosOrder $posOrders;
    private Order $webOrders;
    private ReturnModel $returns;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->stores = new Store();
        $this->products = new Product();
        $this->storeStock = new StoreStock();
        $this->posOrders = new PosOrder();
        $this->webOrders = new Order();
        $this->returns = new ReturnModel();
    }

    public function create()
    {
        $old = $_SESSION['old'] ?? [];
        $prefill = [
            'pos_order_id' => (int)($_GET['pos_order_id'] ?? 0),
            'pos_order_item_id' => (int)($_GET['pos_order_item_id'] ?? 0),
            'order_id' => (int)($_GET['order_id'] ?? 0),
            'order_item_id' => (int)($_GET['order_item_id'] ?? 0),
            'store_id' => (int)($_GET['store_id'] ?? 0),
            'product_id' => (int)($_GET['product_id'] ?? 0),
            'quantity' => (int)($_GET['quantity'] ?? 0),
            'refund_method' => trim($_GET['refund_method'] ?? ''),
            'refund_amount' => (float)($_GET['refund_amount'] ?? 0),
            'gst_adjustment' => (float)($_GET['gst_adjustment'] ?? 0),
            'reason' => trim($_GET['reason'] ?? ''),
        ];
        
        if ($prefill['pos_order_item_id'] > 0) {
            $poi = new PosOrderItem();
            $item = $poi->find($prefill['pos_order_item_id']);
            if ($item && (int)$item['pos_order_id'] === $prefill['pos_order_id']) {
                $prefill['product_id'] = (int)$item['product_id'];
                $prefill['quantity'] = (int)$item['quantity'];
                $prefill['refund_amount'] = (float)$item['line_total'];
                $prefill['gst_adjustment'] = (float)($item['gst_amount'] ?? 0);
            }
        }
        
        $merged = array_merge($prefill, $old);
        
        $this->view('admin/returns/create.twig', [
            'title' => 'Process Return',
            'stores' => $this->stores->getActive(),
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $merged
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function searchBill()
    {
        header('Content-Type: application/json');
        
        $billNumber = trim($_GET['bill_number'] ?? '');
        
        if (empty($billNumber)) {
            echo json_encode(['success' => false, 'message' => 'Bill number required']);
            exit;
        }
        
        // Search in POS orders
        $posOrder = $this->posOrders->query(
            "SELECT * FROM pos_orders WHERE order_number = :bill LIMIT 1",
            ['bill' => $billNumber]
        );
        
        if (!empty($posOrder)) {
            $order = $posOrder[0];
            $items = $this->posOrders->query(
                "SELECT poi.*, p.name as product_name, p.sku, 
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
                FROM pos_order_items poi
                LEFT JOIN products p ON poi.product_id = p.id
                WHERE poi.pos_order_id = :id",
                ['id' => (int)$order['id']]
            );
            
            // Calculate returned quantities
            foreach ($items as &$item) {
                $returned = $this->returns->query(
                    "SELECT COALESCE(SUM(quantity), 0) as qty FROM returns WHERE pos_order_item_id = :id",
                    ['id' => (int)$item['id']]
                );
                $item['returned_qty'] = (int)($returned[0]['qty'] ?? 0);
                $item['remaining_qty'] = (int)$item['quantity'] - $item['returned_qty'];
            }
            
            echo json_encode([
                'success' => true,
                'type' => 'pos',
                'order' => $order,
                'items' => $items
            ]);
            exit;
        }
        
        // Search in web orders
        $webOrder = $this->webOrders->query(
            "SELECT * FROM orders WHERE order_number = :bill LIMIT 1",
            ['bill' => $billNumber]
        );
        
        if (!empty($webOrder)) {
            $order = $webOrder[0];
            $items = $this->webOrders->query(
                "SELECT oi.*, p.id as product_id, p.name as product_name, p.sku,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :id",
                ['id' => (int)$order['id']]
            );
            
            // Calculate returned quantities
            foreach ($items as &$item) {
                $returned = $this->returns->query(
                    "SELECT COALESCE(SUM(quantity), 0) as qty FROM returns WHERE order_item_id = :id",
                    ['id' => (int)$item['id']]
                );
                $item['returned_qty'] = (int)($returned[0]['qty'] ?? 0);
                $item['remaining_qty'] = (int)$item['quantity'] - $item['returned_qty'];
            }
            
            echo json_encode([
                'success' => true,
                'type' => 'web',
                'order' => $order,
                'items' => $items
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Bill not found']);
        exit;
    }

    public function store()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/returns');
        }
        
        $data = [
            'pos_order_id' => (int)($_POST['pos_order_id'] ?? 0),
            'pos_order_item_id' => (int)($_POST['pos_order_item_id'] ?? 0),
            'order_id' => (int)($_POST['order_id'] ?? 0),
            'order_item_id' => (int)($_POST['order_item_id'] ?? 0),
            'store_id' => (int)($_POST['store_id'] ?? 0),
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'quantity' => (int)($_POST['quantity'] ?? 0),
            'refund_method' => trim($_POST['refund_method'] ?? ''),
            'refund_amount' => (float)($_POST['refund_amount'] ?? 0),
            'gst_adjustment' => (float)($_POST['gst_adjustment'] ?? 0),
            'reason' => trim($_POST['reason'] ?? ''),
        ];
        
        $v = new Validator($data);
        $v->required('store_id')->required('product_id')->required('quantity')->min('quantity', 1)
          ->required('refund_method');
          
        if ($v->fails()) {
            $_SESSION['errors'] = $v->getErrors();
            $_SESSION['old'] = $data;
            return $this->redirect('/admin/returns');
        }
        
        foreach (['pos_order_id','pos_order_item_id','order_id','order_item_id'] as $k) {
            if (!isset($data[$k]) || (int)$data[$k] <= 0) {
                $data[$k] = null;
            }
        }
        
        if ($data['pos_order_item_id'] !== null) {
            $data['order_item_id'] = null;
        }
        
        if ($data['pos_order_item_id'] !== null) {
            $item = (new PosOrderItem())->find((int)$data['pos_order_item_id']);
            if (!$item || (int)$item['pos_order_id'] !== (int)$data['pos_order_id']) {
                \flash('error', 'Invalid POS order item');
                return $this->redirect('/admin/returns');
            }
            
            $row = $this->returns->query("SELECT COALESCE(SUM(quantity),0) AS qty FROM returns WHERE pos_order_item_id = :id", ['id' => (int)$data['pos_order_item_id']]);
            $returnedQty = (int)($row[0]['qty'] ?? 0);
            $soldQty = (int)$item['quantity'];
            $remaining = $soldQty - $returnedQty;
            
            if ($remaining <= 0) {
                \flash('error', 'Item already fully returned');
                return $this->redirect('/admin/returns');
            }
            if ((int)$data['quantity'] > $remaining) {
                \flash('error', 'Return exceeds remaining quantity');
                return $this->redirect('/admin/returns');
            }
            
            if ((int)$data['store_id'] <= 0 && (int)$data['pos_order_id'] > 0) {
                $po = $this->posOrders->find((int)$data['pos_order_id']);
                $data['store_id'] = (int)($po['store_id'] ?? 0);
            }
            if ((int)$data['product_id'] <= 0) {
                $data['product_id'] = (int)$item['product_id'];
            }
            if ((float)$data['refund_amount'] <= 0) {
                $perUnit = ((float)($item['line_total'] ?? 0)) / max(1, (int)$item['quantity']);
                $data['refund_amount'] = round($perUnit * (int)$data['quantity'], 2);
            }
            if ((float)$data['gst_adjustment'] < 0) {
                $data['gst_adjustment'] = 0.0;
            }
            if ((float)$data['gst_adjustment'] === 0.0) {
                $perUnitGst = ((float)($item['gst_amount'] ?? 0)) / max(1, (int)$item['quantity']);
                $data['gst_adjustment'] = round($perUnitGst * (int)$data['quantity'], 2);
            }
        } elseif ($data['order_item_id'] !== null) {
            $rows = $this->webOrders->query("SELECT * FROM order_items WHERE id = :id LIMIT 1", ['id' => (int)$data['order_item_id']]);
            $oi = $rows[0] ?? null;
            if (!$oi || (int)$oi['order_id'] !== (int)$data['order_id']) {
                \flash('error', 'Invalid web order item');
                return $this->redirect('/admin/returns');
            }
            
            $row = $this->returns->query("SELECT COALESCE(SUM(quantity),0) AS qty FROM returns WHERE order_item_id = :id", ['id' => (int)$data['order_item_id']]);
            $returnedQty = (int)($row[0]['qty'] ?? 0);
            $soldQty = (int)$oi['quantity'];
            $remaining = $soldQty - $returnedQty;
            
            if ($remaining <= 0) {
                \flash('error', 'Item already fully returned');
                return $this->redirect('/admin/returns');
            }
            if ((int)$data['quantity'] > $remaining) {
                \flash('error', 'Return exceeds remaining quantity');
                return $this->redirect('/admin/returns');
            }
            
            if ((int)$data['product_id'] <= 0) {
                $data['product_id'] = (int)$oi['product_id'];
            }
            if ((float)$data['refund_amount'] <= 0) {
                $perUnit = ((float)($oi['subtotal'] ?? 0)) / max(1, (int)$oi['quantity']);
                $data['refund_amount'] = round($perUnit * (int)$data['quantity'], 2);
            }
            if ((float)$data['gst_adjustment'] < 0) {
                $data['gst_adjustment'] = 0.0;
            }
        }
        
        $retId = $this->returns->create($data);
        
        $pinfo = $this->products->find((int)$data['product_id']);
        $noStore = (int)($pinfo['no_store_stock'] ?? 0) === 1;
        
        if (!$noStore) {
            $this->storeStock->adjustStock($data['store_id'], $data['product_id'], $data['quantity']);
            try {
                $move = new \App\Models\StockMovement();
                $move->log((int)$data['store_id'], (int)$data['product_id'], (int)$data['quantity'], 'IN', 'RETURN', 'returns', (int)$retId, null);
            } catch (\Throwable $e) {}
        }
        
        \flash('success', 'Return processed successfully');
        return $this->redirect('/admin/returns');
    }
}