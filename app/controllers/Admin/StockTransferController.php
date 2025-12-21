<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Store;
use App\Models\Product;
use App\Models\StoreStock;

class StockTransferController extends BaseController
{
    private Store $stores;
    private Product $products;
    private StoreStock $stock;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->stores = new Store();
        $this->products = new Product();
        $this->stock = new StoreStock();
    }

    public function index()
    {
        $stores = $this->stores->getActive();
        $sourceId = (int)($_GET['source_id'] ?? 0);
        $destId = (int)($_GET['dest_id'] ?? 0);
        $barcode = trim($_GET['barcode'] ?? '');
        $q = trim($_GET['q'] ?? '');
        $results = [];
        $selected = null;
        $errorDetail = null;
        $debugInfo = [];
        
        // Get current cart from session
        $cart = $_SESSION['transfer_cart'] ?? [];
        
        if ($sourceId > 0) {
            if ($barcode !== '') {
                $productSql = "
                    SELECT p.* 
                    FROM products p 
                    WHERE (p.barcode = :barcode OR p.sku = :sku)
                    LIMIT 1
                ";
                $productRows = $this->products->query($productSql, [
                    'barcode' => $barcode,
                    'sku' => $barcode
                ]);
                $product = $productRows[0] ?? null;
                
                if ($product) {
                    $debugInfo[] = "Product found: ID={$product['id']}, Name={$product['name']}";
                    
                    if ((int)($product['no_store_stock'] ?? 0) === 1) {
                        $errorDetail = 'Product "' . $product['name'] . '" is marked as No Store Stock';
                    } else {
                        $stockSql = "
                            SELECT * FROM store_stock 
                            WHERE store_id = :store_id AND product_id = :product_id
                        ";
                        $stockRows = $this->products->query($stockSql, [
                            'store_id' => $sourceId,
                            'product_id' => $product['id']
                        ]);
                        $stockRow = $stockRows[0] ?? null;
                        
                        if ($stockRow) {
                            $available = (int)($stockRow['quantity'] ?? 0);
                            
                            if ($available > 0) {
                                $imgSql = "
                                    SELECT image_path 
                                    FROM product_images 
                                    WHERE product_id = :pid AND is_primary = 1 
                                    LIMIT 1
                                ";
                                $imgRows = $this->products->query($imgSql, ['pid' => $product['id']]);
                                $product['image_path'] = $imgRows[0]['image_path'] ?? null;
                                $product['available'] = $available;
                                $selected = $product;
                            } else {
                                $otherStockSql = "
                                    SELECT s.name, ss.quantity 
                                    FROM store_stock ss
                                    JOIN stores s ON s.id = ss.store_id
                                    WHERE ss.product_id = :product_id AND ss.quantity > 0
                                ";
                                $otherStocks = $this->products->query($otherStockSql, ['product_id' => $product['id']]);
                                
                                if (!empty($otherStocks)) {
                                    $storeList = array_map(function($s) {
                                        return $s['name'] . ' (' . $s['quantity'] . ' units)';
                                    }, $otherStocks);
                                    $errorDetail = 'No stock in selected shop. Available in: ' . implode(', ', $storeList);
                                } else {
                                    $errorDetail = 'No stock available in any store';
                                }
                            }
                        } else {
                            $otherStockSql = "
                                SELECT s.name, ss.quantity 
                                FROM store_stock ss
                                JOIN stores s ON s.id = ss.store_id
                                WHERE ss.product_id = :product_id AND ss.quantity > 0
                            ";
                            $otherStocks = $this->products->query($otherStockSql, ['product_id' => $product['id']]);
                            
                            if (!empty($otherStocks)) {
                                $storeList = array_map(function($s) {
                                    return $s['name'] . ' (' . $s['quantity'] . ' units)';
                                }, $otherStocks);
                                $errorDetail = 'Product not stocked in this shop. Available in: ' . implode(', ', $storeList);
                            } else {
                                $errorDetail = 'Product exists but has no stock in any store';
                            }
                        }
                    }
                } else {
                    $errorDetail = 'No product found with barcode: ' . htmlspecialchars($barcode);
                }
                
                if (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true') {
                    if (!empty($debugInfo)) {
                        $errorDetail .= '<br><br><small><strong>Debug Info:</strong><br>' . implode('<br>', $debugInfo) . '</small>';
                    }
                }
                
            } elseif ($q !== '') {
                $results = $this->products->searchForStore($q, $sourceId, 30);
            }
        }
        
        // Enrich cart with product details
        $cartItems = [];
        foreach ($cart as $pid => $qty) {
            $p = $this->products->find($pid);
            if ($p) {
                $imgSql = "SELECT image_path FROM product_images WHERE product_id = :pid AND is_primary = 1 LIMIT 1";
                $imgRows = $this->products->query($imgSql, ['pid' => $pid]);
                $p['image_path'] = $imgRows[0]['image_path'] ?? null;
                $p['cart_quantity'] = $qty;
                $cartItems[] = $p;
            }
        }
        
        $this->view('admin/stock/transfer.twig', [
            'title' => 'Stock Transfer',
            'stores' => $stores,
            'source_id' => $sourceId,
            'dest_id' => $destId,
            'barcode' => $barcode,
            'q' => $q,
            'results' => $results,
            'selected' => $selected,
            'error_detail' => $errorDetail,
            'cart_items' => $cartItems,
            'cart_count' => count($cart)
        ]);
    }

    public function addToCart()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        
        $p = $this->products->find($productId);
        if (!$p) {
            \flash('error', 'Product not found');
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        if ((int)($p['no_store_stock'] ?? 0) === 1) {
            \flash('error', 'Cannot transfer this product');
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        $srcRow = $this->stock->getByStoreAndProduct($sourceId, $productId);
        $srcQty = (int)($srcRow['quantity'] ?? 0);
        
        if (!isset($_SESSION['transfer_cart'])) {
            $_SESSION['transfer_cart'] = [];
        }
        
        $currentCartQty = (int)($_SESSION['transfer_cart'][$productId] ?? 0);
        $newTotal = $currentCartQty + $qty;
        
        if ($newTotal > $srcQty) {
            \flash('error', 'Only ' . $srcQty . ' available in source shop');
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        $_SESSION['transfer_cart'][$productId] = $newTotal;
        $_SESSION['transfer_source_id'] = $sourceId;
        
        \flash('success', 'Added ' . $qty . ' × ' . htmlspecialchars($p['name']) . ' to transfer list');
        return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
    }

    public function removeFromCart()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $productId = (int)($_POST['product_id'] ?? 0);
        $sourceId = (int)($_SESSION['transfer_source_id'] ?? 0);
        
        if (isset($_SESSION['transfer_cart'][$productId])) {
            unset($_SESSION['transfer_cart'][$productId]);
            \flash('success', 'Removed from transfer list');
        }
        
        return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
    }

    public function clearCart()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $sourceId = (int)($_SESSION['transfer_source_id'] ?? 0);
        unset($_SESSION['transfer_cart']);
        unset($_SESSION['transfer_source_id']);
        
        \flash('success', 'Transfer list cleared');
        return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
    }

    public function bulkTransfer()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $sourceId = (int)($_SESSION['transfer_source_id'] ?? 0);
        $destId = (int)($_POST['dest_id'] ?? 0);
        $cart = $_SESSION['transfer_cart'] ?? [];
        
        if (empty($cart)) {
            \flash('error', 'Transfer list is empty');
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        if ($sourceId <= 0 || $destId <= 0 || $sourceId === $destId) {
            \flash('error', 'Select different source and destination shops');
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        // Generate transfer number
        $transferNumber = 'TRF-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create transfer record
        $transferSql = "
            INSERT INTO stock_transfers 
            (transfer_number, source_store_id, dest_store_id, initiated_by, total_items, status) 
            VALUES (:num, :src, :dest, :by, :total, 'COMPLETED')
        ";
        $this->products->query($transferSql, [
            'num' => $transferNumber,
            'src' => $sourceId,
            'dest' => $destId,
            'by' => $_SESSION['user_id'] ?? 0,
            'total' => count($cart)
        ]);
        
        // Get the last insert ID - get it directly from database
        $idResult = $this->products->query("SELECT LAST_INSERT_ID() as id");
        $transferId = (int)($idResult[0]['id'] ?? 0);
        
        $successCount = 0;
        $failedItems = [];
        
        foreach ($cart as $productId => $qty) {
            $p = $this->products->find($productId);
            if (!$p) continue;
            
            $ok1 = $this->stock->adjustStock($sourceId, $productId, -$qty);
            $ok2 = $this->stock->adjustStock($destId, $productId, $qty);
            
            if ($ok1 && $ok2) {
                // Record transfer item
                $itemSql = "
                    INSERT INTO stock_transfer_items 
                    (transfer_id, product_id, quantity) 
                    VALUES (:tid, :pid, :qty)
                ";
                $this->products->query($itemSql, [
                    'tid' => $transferId,
                    'pid' => $productId,
                    'qty' => $qty
                ]);
                
                // Log movements
                try {
                    $m = new \App\Models\StockMovement();
                    $m->log($sourceId, $productId, $qty, 'OUT', 'TRANSFER', 'stock_transfers', $transferId, "Transfer #$transferNumber");
                    $m->log($destId, $productId, $qty, 'IN', 'TRANSFER', 'stock_transfers', $transferId, "Transfer #$transferNumber");
                } catch (\Throwable $e) {}
                
                $successCount++;
            } else {
                $failedItems[] = $p['name'];
            }
        }
        
        // Update transfer completion time
        $updateSql = "UPDATE stock_transfers SET completed_at = NOW() WHERE id = :id";
        $this->products->query($updateSql, ['id' => $transferId]);
        
        // Clear cart
        unset($_SESSION['transfer_cart']);
        unset($_SESSION['transfer_source_id']);
        
        if ($successCount > 0) {
            $msg = "Successfully transferred $successCount product(s) - Transfer #$transferNumber";
            if (!empty($failedItems)) {
                $msg .= '. Failed: ' . implode(', ', $failedItems);
            }
            \flash('success', $msg);
        } else {
            \flash('error', 'Transfer failed. Please try again.');
        }
        
        return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
    }

    public function history()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Get filters
        $sourceId = (int)($_GET['source_id'] ?? 0);
        $destId = (int)($_GET['dest_id'] ?? 0);
        $status = $_GET['status'] ?? '';
        
        // Build query
        $where = [];
        $params = [];
        
        if ($sourceId > 0) {
            $where[] = 'st.source_store_id = :src';
            $params['src'] = $sourceId;
        }
        if ($destId > 0) {
            $where[] = 'st.dest_store_id = :dest';
            $params['dest'] = $destId;
        }
        if ($status !== '') {
            $where[] = 'st.status = :status';
            $params['status'] = $status;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get transfers
        $sql = "
            SELECT st.*, 
                   ss.name as source_name,
                   ds.name as dest_name,
                   u.name as user_name
            FROM stock_transfers st
            LEFT JOIN stores ss ON ss.id = st.source_store_id
            LEFT JOIN stores ds ON ds.id = st.dest_store_id
            LEFT JOIN users u ON u.id = st.initiated_by
            $whereClause
            ORDER BY st.created_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $transfers = $this->products->query($sql, $params);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM stock_transfers st $whereClause";
        $countResult = $this->products->query($countSql, $params);
        $total = (int)($countResult[0]['total'] ?? 0);
        $totalPages = ceil($total / $perPage);
        
        $stores = $this->stores->getActive();
        
        $this->view('admin/stock/transfer_history.twig', [
            'title' => 'Transfer History',
            'transfers' => $transfers,
            'stores' => $stores,
            'source_id' => $sourceId,
            'dest_id' => $destId,
            'status' => $status,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total
        ]);
    }

    public function viewTransfer()
    {
        // Get ID from URL parameter or route param
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        
        $sql = "
            SELECT st.*, 
                   ss.name as source_name,
                   ds.name as dest_name,
                   u.name as user_name
            FROM stock_transfers st
            LEFT JOIN stores ss ON ss.id = st.source_store_id
            LEFT JOIN stores ds ON ds.id = st.dest_store_id
            LEFT JOIN users u ON u.id = st.initiated_by
            WHERE st.id = :id
        ";
        $result = $this->products->query($sql, ['id' => $id]);
        $transfer = $result[0] ?? null;
        
        if (!$transfer) {
            \flash('error', 'Transfer not found');
            return $this->redirect('/admin/stock/transfer/history');
        }
        
        // Get transfer items
        $itemsSql = "
            SELECT sti.*, p.name, p.sku, p.barcode
            FROM stock_transfer_items sti
            LEFT JOIN products p ON p.id = sti.product_id
            WHERE sti.transfer_id = :tid
            ORDER BY sti.id
        ";
        $items = $this->products->query($itemsSql, ['tid' => $id]);
        
        // Add images
        foreach ($items as &$item) {
            $imgSql = "SELECT image_path FROM product_images WHERE product_id = :pid AND is_primary = 1 LIMIT 1";
            $imgRows = $this->products->query($imgSql, ['pid' => $item['product_id']]);
            $item['image_path'] = $imgRows[0]['image_path'] ?? null;
        }
        
        $this->view('admin/stock/transfer_view.twig', [
            'title' => 'Transfer Details',
            'transfer' => $transfer,
            'items' => $items
        ]);
    }

    // Single product transfer (kept for compatibility)
    public function move()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $destId = (int)($_POST['dest_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 0));
        
        if ($sourceId <= 0 || $destId <= 0 || $sourceId === $destId) {
            \flash('error', 'Select different source and destination shops');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $p = $this->products->find($productId);
        if (!$p) {
            \flash('error', 'Product not found');
            return $this->redirect('/admin/stock/transfer');
        }
        
        if ((int)($p['no_store_stock'] ?? 0) === 1) {
            \flash('error', 'Transfer not allowed for this product');
            return $this->redirect('/admin/stock/transfer');
        }
        
        $srcRow = $this->stock->getByStoreAndProduct($sourceId, $productId);
        $srcQty = (int)($srcRow['quantity'] ?? 0);
        
        if ($qty > $srcQty) {
            \flash('error', 'Only ' . $srcQty . ' available in source shop');
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        // Generate transfer number
        $transferNumber = 'TRF-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create transfer record
        $transferSql = "
            INSERT INTO stock_transfers 
            (transfer_number, source_store_id, dest_store_id, initiated_by, total_items, status) 
            VALUES (:num, :src, :dest, :by, 1, 'COMPLETED')
        ";
        $this->products->query($transferSql, [
            'num' => $transferNumber,
            'src' => $sourceId,
            'dest' => $destId,
            'by' => $_SESSION['user_id'] ?? 0
        ]);
        
        // Get the last insert ID - get it directly from database
        $idResult = $this->products->query("SELECT LAST_INSERT_ID() as id");
        $transferId = (int)($idResult[0]['id'] ?? 0);
        
        $ok1 = $this->stock->adjustStock($sourceId, $productId, -$qty);
        $ok2 = $this->stock->adjustStock($destId, $productId, $qty);
        
        if ($ok1 && $ok2) {
            // Record transfer item
            $itemSql = "
                INSERT INTO stock_transfer_items 
                (transfer_id, product_id, quantity) 
                VALUES (:tid, :pid, :qty)
            ";
            $this->products->query($itemSql, [
                'tid' => $transferId,
                'pid' => $productId,
                'qty' => $qty
            ]);
            
            // Update transfer completion time
            $updateSql = "UPDATE stock_transfers SET completed_at = NOW() WHERE id = :id";
            $this->products->query($updateSql, ['id' => $transferId]);
            
            try {
                $m = new \App\Models\StockMovement();
                $m->log($sourceId, $productId, $qty, 'OUT', 'TRANSFER', 'stock_transfers', $transferId, "Transfer #$transferNumber");
                $m->log($destId, $productId, $qty, 'IN', 'TRANSFER', 'stock_transfers', $transferId, "Transfer #$transferNumber");
            } catch (\Throwable $e) {}
            
            \flash('success', 'Successfully transferred ' . $qty . ' units of ' . htmlspecialchars($p['name']) . ' - Transfer #' . $transferNumber);
            return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
        }
        
        \flash('error', 'Transfer failed. Please try again.');
        return $this->redirect('/admin/stock/transfer?source_id=' . $sourceId);
    }
}
