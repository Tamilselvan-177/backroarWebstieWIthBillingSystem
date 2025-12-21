<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Store;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\User;
use App\Models\BillSequence;
use App\Models\PosPayment;
use App\Models\Counter;
use App\Models\CounterStaff;
use App\Models\PosHold;
use App\Models\StockMovement;

class PosController extends BaseController
{
    private Store $stores;
    private Product $products;
    private StoreStock $storeStock;
    private PosOrder $posOrders;
    private PosOrderItem $posItems;
    private User $users;
    private BillSequence $billSeq;
    private PosPayment $posPayments;
    private Counter $counters;
    private CounterStaff $counterStaff;
    private PosHold $holds;
    private StockMovement $movements;

    public function __construct()
    {
        if (!(\isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'staff'))) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->stores = new Store();
        $this->products = new Product();
        $this->storeStock = new StoreStock();
        $this->posOrders = new PosOrder();
        $this->posItems = new PosOrderItem();
        $this->users = new User();
        $this->billSeq = new BillSequence();
        $this->posPayments = new PosPayment();
        $this->counters = new Counter();
        $this->counterStaff = new CounterStaff();
        $this->holds = new PosHold();
        $this->movements = new StockMovement();
    }

    public function loginForm()
    {
        $storeList = $this->stores->getActive();
        $years = $this->financialYears();
        $this->view('admin/pos/login.twig', [
            'title' => 'POS Login',
            'stores' => $storeList,
            'years' => $years,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }
    public function countersAjax()
    {
        $storeId = (int)($_GET['store_id'] ?? 0);
        if ($storeId <= 0) {
            return $this->json([]);
        }
        $rows = $this->counters->getByStore($storeId);
        return $this->json($rows);
    }

    public function start()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/pos/login');
        }
        $storeId = (int)($_POST['store_id'] ?? 0);
        $counterId = (int)($_POST['counter_id'] ?? 0);
        $counterPin = (string)($_POST['counter_pin'] ?? '');
        $finYear = trim($_POST['financial_year'] ?? '');
        if ($storeId <= 0 || $finYear === '') {
            \flash('error', 'Select store and financial year');
            return $this->redirect('/admin/pos/login');
        }
        if ($counterId <= 0 || $counterPin === '') {
            \flash('error', 'Select counter and enter PIN');
            return $this->redirect('/admin/pos/login');
        }
        $store = $this->stores->find($storeId);
        if (!$store || !(int)$store['is_active']) {
            \flash('error', 'Invalid store');
            return $this->redirect('/admin/pos/login');
        }
        $counter = $this->counters->find($counterId);
        if (!$counter || (int)$counter['store_id'] !== $storeId || !(int)$counter['is_active']) {
            \flash('error', 'Invalid counter for store');
            return $this->redirect('/admin/pos/login');
        }
        if (!$this->counters->verifyPin($counterId, $counterPin)) {
            \flash('error', 'Invalid counter PIN');
            return $this->redirect('/admin/pos/login');
        }
        if ((isset($_SESSION['role']) && $_SESSION['role'] === 'staff') && \userId() && !$this->counterStaff->isAssigned($counterId, (int)\userId())) {
            \flash('error', 'You are not assigned to this counter');
            return $this->redirect('/admin/pos/login');
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['pos_store_id'] = $storeId;
        $_SESSION['pos_store_code'] = $store['code'];
        $_SESSION['pos_counter_id'] = $counterId;
        $_SESSION['pos_counter_code'] = $counter['code'];
        $_SESSION['pos_fin_year'] = $finYear;
        $_SESSION['pos_active'] = true;
        $_SESSION['pos_cart'] = [];
        unset($_SESSION['pos_checkout_lock']);
        unset($_SESSION['pos_temp_data']);
        \flash('success', 'Switched store to ' . ($store['name'] ?? $store['code']) . '. Cart cleared.');
        return $this->redirect('/admin/pos/billing');
    }

    public function billing()
    {
        if (empty($_SESSION['pos_active'])) {
            return $this->redirect('/admin/pos/login');
        }
        $cart = $_SESSION['pos_cart'] ?? [];
        $svc = (float)($_SESSION['pos_service_charge'] ?? 0);
        $staffUsers = $this->users->getStaffUsers();
        $desktopMode = (isset($_GET['mode']) && $_GET['mode'] === 'desktop');
        $storeId = (int)($_SESSION['pos_store_id'] ?? 0);
        $counterId = (int)($_SESSION['pos_counter_id'] ?? 0);
        $recent = [];
        $holds = [];

        // Recover temp data if set
        $tempData = $_SESSION['pos_temp_data'] ?? [];
        // Only keep it for one view? No, keep until cleared.

        if ($storeId > 0) {
            $recent = $this->posOrders->query(
                "SELECT id, order_number, grand_total, created_at 
                 FROM pos_orders 
                 WHERE store_id = :sid 
                 ORDER BY created_at DESC 
                 LIMIT 20",
                ['sid' => $storeId]
            );
            $conds = "store_id = :sid";
            $params = ['sid' => $storeId];
            if ($counterId > 0) {
                $conds .= " AND counter_id = :cid";
                $params['cid'] = $counterId;
            }
            $holds = $this->holds->query(
                "SELECT id, customer_name, customer_phone, created_at FROM pos_holds WHERE {$conds} ORDER BY created_at DESC LIMIT 20",
                $params
            );
        }
        $this->view('admin/pos/billing.twig', [
            'title' => 'POS Billing',
            'store_code' => $_SESSION['pos_store_code'] ?? '',
            'counter_code' => $_SESSION['pos_counter_code'] ?? '',
            'financial_year' => $_SESSION['pos_fin_year'] ?? '',
            'cart' => $cart,
            'service_charge' => $svc,
            'staff' => $staffUsers,
            'desktop_mode' => $desktopMode,
            'recent_orders' => $recent,
            'holds' => $holds,
            'temp' => $tempData
        ]);
    }
    public function updateServiceCharge()
    {
        if (empty($_SESSION['pos_active'])) {
            return $this->redirect('/admin/pos/login');
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/pos/billing');
        }
        
        // Persist form data
        $_SESSION['pos_temp_data'] = [
            'staff_id' => $_POST['staff_id'] ?? '',
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'cash_amount' => $_POST['cash_amount'] ?? '',
            'card_amount' => $_POST['card_amount'] ?? '',
            'upi_amount' => $_POST['upi_amount'] ?? ''
        ];

        $val = (float)($_POST['service_charge'] ?? 0);
        if ($val < 0) { $val = 0; }
        $_SESSION['pos_service_charge'] = round($val, 2);
        \flash('success', 'Service charge updated');
        return $this->redirect('/admin/pos/billing');
    }
    public function hold()
    {
        if (empty($_SESSION['pos_active'])) {
            return $this->redirect('/admin/pos/login');
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/pos/billing');
        }
        $cart = $_SESSION['pos_cart'] ?? [];
        if (empty($cart)) {
            \flash('error', 'No items to hold');
            return $this->redirect('/admin/pos/billing');
        }
        $storeId = (int)($_SESSION['pos_store_id'] ?? 0);
        $counterId = (int)($_SESSION['pos_counter_id'] ?? 0);
        $staffId = \userId() ?: null;
        $this->holds->create([
            'store_id' => $storeId,
            'counter_id' => $counterId ?: null,
            'staff_id' => $staffId ?: null,
            'customer_name' => trim($_POST['customer_name'] ?? ''),
            'customer_phone' => trim($_POST['customer_phone'] ?? ''),
            'cart_json' => json_encode($cart)
        ]);
        $_SESSION['pos_cart'] = [];
        \flash('success', 'Bill held');
        return $this->redirect('/admin/pos/billing');
    }
    public function recall()
    {
        if (empty($_SESSION['pos_active'])) {
            return $this->redirect('/admin/pos/login');
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            return $this->redirect('/admin/pos/billing');
        }
        $row = $this->holds->find($id);
        if (!$row) {
            \flash('error', 'Hold not found');
            return $this->redirect('/admin/pos/billing');
        }
        $_SESSION['pos_cart'] = json_decode((string)$row['cart_json'], true) ?: [];
        \flash('success', 'Bill recalled');
        return $this->redirect('/admin/pos/billing');
    }
    public function orders()
    {
        $view = trim($_GET['view'] ?? '');
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 24;
        $offset = ($page - 1) * $perPage;
        $storeId = (int)($_GET['store_id'] ?? 0);
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        $stores = $this->stores->getActive();
        $staffUsers = $this->users->getStaffUsers();
        if ($view === 'returns') {
            $conds = ['pos_order_id IS NOT NULL'];
            $params = [];
            if ($storeId > 0) { $conds[] = 'store_id = :sid'; $params['sid'] = $storeId; }
            if ($from !== '') { $conds[] = 'DATE(created_at) >= :from'; $params['from'] = $from; }
            if ($to !== '') { $conds[] = 'DATE(created_at) <= :to'; $params['to'] = $to; }
            $where = empty($conds) ? '' : (' WHERE ' . implode(' AND ', $conds));
            $countRows = $this->posOrders->query("SELECT COUNT(*) AS total FROM returns{$where}", $params);
            $total = (int)($countRows[0]['total'] ?? 0);
            $sql = "SELECT r.*, po.order_number, po.customer_name, p.name AS product_name
                    FROM returns r
                    LEFT JOIN pos_orders po ON po.id = r.pos_order_id
                    LEFT JOIN products p ON p.id = r.product_id
                    {$where}
                    ORDER BY r.created_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";
            $returns = $this->posOrders->query($sql, $params);
            if ((isset($_GET['export']) && $_GET['export'] === 'csv')) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="pos_returns.csv"');
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Return ID','POS Order #','Customer','Product','Quantity','Refund Method','Refund Amount','GST Adjust','Date']);
                foreach ($returns as $r) {
                    fputcsv($out, [
                        (int)$r['id'],
                        (string)($r['order_number'] ?? ''),
                        (string)($r['customer_name'] ?? ''),
                        (string)($r['product_name'] ?? ''),
                        (int)$r['quantity'],
                        (string)$r['refund_method'],
                        number_format((float)$r['refund_amount'], 2, '.', ''),
                        number_format((float)$r['gst_adjustment'], 2, '.', ''),
                        (string)$r['created_at']
                    ]);
                }
                fclose($out);
                return;
            }
            $this->view('admin/pos/orders.twig', [
                'title' => 'POS Returns',
                'view_returns' => true,
                'returns' => $returns,
                'orders' => [],
                'previews' => [],
                'return_counts' => [],
                'stores' => $stores,
                'staff' => $staffUsers,
                'filters' => [
                    'store_id' => $storeId,
                    'staff_id' => $staffId,
                    'from' => $from,
                    'to' => $to
                ],
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'total_pages' => max(1, (int)ceil($total / $perPage))
                ]
            ]);
            return;
        }
        $conds = [];
        $params = [];
        if ($storeId > 0) { $conds[] = 'store_id = :sid'; $params['sid'] = $storeId; }
        if ($staffId > 0) { $conds[] = 'staff_id = :uid'; $params['uid'] = $staffId; }
        if ($from !== '') { $conds[] = 'DATE(created_at) >= :from'; $params['from'] = $from; }
        if ($to !== '') { $conds[] = 'DATE(created_at) <= :to'; $params['to'] = $to; }
        $where = empty($conds) ? '' : (' WHERE ' . implode(' AND ', $conds));
        $countRows = $this->posOrders->query("SELECT COUNT(*) AS total FROM pos_orders{$where}", $params);
        $total = (int)($countRows[0]['total'] ?? 0);

        // Calculate period totals (Gross Sales)
        $grossRows = $this->posOrders->query("SELECT SUM(grand_total) AS gross FROM pos_orders{$where}", $params);
        $periodGross = (float)($grossRows[0]['gross'] ?? 0);

        // Calculate period returns (Total Returns in this period)
        $rConds = [];
        $rParams = [];
        if ($storeId > 0) { $rConds[] = 'store_id = :sid'; $rParams['sid'] = $storeId; }
        if ($from !== '') { $rConds[] = 'DATE(created_at) >= :from'; $rParams['from'] = $from; }
        if ($to !== '') { $rConds[] = 'DATE(created_at) <= :to'; $rParams['to'] = $to; }
        
        $rWhere = empty($rConds) ? '' : (' WHERE ' . implode(' AND ', $rConds));
        $retSumRows = $this->posOrders->query("SELECT SUM(refund_amount) AS total_refund, SUM(gst_adjustment) AS total_gst FROM returns{$rWhere}", $rParams);
        $periodReturns = (float)($retSumRows[0]['total_refund'] ?? 0);
        $periodGstAdj = (float)($retSumRows[0]['total_gst'] ?? 0);

        $orders = $this->posOrders->query("SELECT * FROM pos_orders{$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}", $params);
        $summ = [];
        $ids = array_map(static function($o){ return (int)$o['id']; }, $orders);
        $returnCounts = [];
        
        // Page specific calculations (kept for reference if needed, but we use period totals for display)
        $pageGross = 0.0;
        foreach ($orders as $o) { $pageGross += (float)($o['grand_total'] ?? 0); }
        
        if (!empty($ids)) {
            $in = implode(',', $ids);
            $rows = $this->posItems->query("SELECT pos_order_id, product_name, quantity FROM pos_order_items WHERE pos_order_id IN ({$in})");
            foreach ($rows as $r) {
                $oid = (int)$r['pos_order_id'];
                if (!isset($summ[$oid])) { $summ[$oid] = []; }
                if (count($summ[$oid]) < 3) {
                    $summ[$oid][] = trim((string)$r['product_name']) . ' x' . (int)$r['quantity'];
                }
            }
            $retRows = $this->posOrders->query("SELECT pos_order_id, COUNT(*) AS c FROM returns WHERE pos_order_id IN ({$in}) GROUP BY pos_order_id");
            foreach ($retRows as $rr) {
                $returnCounts[(int)$rr['pos_order_id']] = (int)$rr['c'];
            }
        }
        $this->view('admin/pos/orders.twig', [
            'title' => 'POS Orders',
            'view_returns' => false,
            'orders' => $orders,
            'returns' => [],
            'previews' => $summ,
            'return_counts' => $returnCounts,
            'page_totals' => [
                'gross' => $periodGross,
                'returns' => $periodReturns,
                'net' => max(0.0, $periodGross - ($periodReturns - $periodGstAdj))
            ],
            'stores' => $stores,
            'staff' => $staffUsers,
            'filters' => [
                'store_id' => $storeId,
                'staff_id' => $staffId,
                'from' => $from,
                'to' => $to
            ],
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'total_pages' => max(1, (int)ceil($total / $perPage))
            ]
        ]);
    }
    public function orderShow($id)
    {
        $oid = (int)$id;
        $order = $this->posOrders->find($oid);
        if (!$order) {
            \flash('error', 'POS order not found');
            return $this->redirect('/admin/pos/orders');
        }
        
        // Fetch store details for display
        $store = $this->stores->find($order['store_id']);
        if ($store) {
            $order['store_name'] = $store['name'];
            $order['store_address'] = $store['address'];
            $order['store_city'] = $store['city'];
            $order['store_state'] = $store['state'];
            $order['store_pincode'] = $store['pincode'];
            $order['store_gstin'] = $store['gstin'];
        }
        $items = $this->posItems->where('pos_order_id', $oid);
        $retRows = $this->posOrders->query("SELECT pos_order_item_id, COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(refund_amount),0) AS refund, COALESCE(SUM(gst_adjustment),0) AS gst FROM returns WHERE pos_order_id = :oid GROUP BY pos_order_item_id", ['oid' => $oid]);
        $retMap = [];
        $refundTotal = 0.0;
        $gstReturn = 0.0;
        foreach ($retRows as $r) {
            $retMap[(int)$r['pos_order_item_id']] = [
                'qty' => (int)($r['qty'] ?? 0),
                'refund' => (float)($r['refund'] ?? 0),
                'gst' => (float)($r['gst'] ?? 0)
            ];
            $refundTotal += (float)($r['refund'] ?? 0);
            $gstReturn += (float)($r['gst'] ?? 0);
        }
        $net = [
            'subtotal' => (float)$order['subtotal'] - max(0.0, $refundTotal - $gstReturn),
            'gst_total' => (float)$order['gst_total'] - $gstReturn,
            'grand_total' => (float)$order['grand_total'] - $refundTotal,
            'refund_total' => $refundTotal,
            'gst_return' => $gstReturn
        ];
        $this->view('admin/pos/show.twig', [
            'title' => 'POS Order #' . $order['order_number'],
            'order' => $order,
            'items' => $items,
            'returns_by_item' => $retMap,
            'net' => $net
        ]);
    }
    public function gstReport()
    {
        $storeId = (int)($_GET['store_id'] ?? 0);
        $range = trim($_GET['range'] ?? 'daily');
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        $params = [];
        $conds = [];
        if ($storeId > 0) { $conds[] = 'store_id = :sid'; $params['sid'] = $storeId; }
        if ($from !== '') { $conds[] = 'DATE(created_at) >= :from'; $params['from'] = $from; }
        if ($to !== '') { $conds[] = 'DATE(created_at) <= :to'; $params['to'] = $to; }
        $where = empty($conds) ? '' : (' WHERE ' . implode(' AND ', $conds));
        if ($range === 'monthly') {
            $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period,
                           SUM(subtotal) AS subtotal,
                           SUM(discount_total) AS discounts,
                           SUM(gst_total) AS gst,
                           SUM(grand_total) AS grand_total
                    FROM pos_orders{$where}
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY period DESC
                    LIMIT 24";
        } else {
            $sql = "SELECT DATE(created_at) AS period,
                           SUM(subtotal) AS subtotal,
                           SUM(discount_total) AS discounts,
                           SUM(gst_total) AS gst,
                           SUM(grand_total) AS grand_total
                    FROM pos_orders{$where}
                    GROUP BY DATE(created_at)
                    ORDER BY period DESC
                    LIMIT 60";
        }
        $rows = $this->posOrders->query($sql, $params);
        $rParams = [];
        $rConds = [];
        if ($storeId > 0) { $rConds[] = 'store_id = :sid'; $rParams['sid'] = $storeId; }
        if ($from !== '') { $rConds[] = 'DATE(created_at) >= :from'; $rParams['from'] = $from; }
        if ($to !== '') { $rConds[] = 'DATE(created_at) <= :to'; $rParams['to'] = $to; }
        $rWhere = empty($rConds) ? '' : (' WHERE ' . implode(' AND ', $rConds));
        if ($range === 'monthly') {
            $retSql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period,
                              SUM(refund_amount) AS refund_total,
                              SUM(gst_adjustment) AS gst_return
                       FROM returns{$rWhere}
                       GROUP BY DATE_FORMAT(created_at, '%Y-%m')";
        } else {
            $retSql = "SELECT DATE(created_at) AS period,
                              SUM(refund_amount) AS refund_total,
                              SUM(gst_adjustment) AS gst_return
                       FROM returns{$rWhere}
                       GROUP BY DATE(created_at)";
        }
        $retRows = $this->posOrders->query($retSql, $rParams);
        $retMap = [];
        foreach ($retRows as $r) {
            $retMap[(string)$r['period']] = [
                'refund_total' => (float)($r['refund_total'] ?? 0),
                'gst_return' => (float)($r['gst_return'] ?? 0)
            ];
        }
        foreach ($rows as &$row) {
            $p = (string)$row['period'];
            $ref = $retMap[$p] ?? ['refund_total' => 0.0, 'gst_return' => 0.0];
            $refund = (float)$ref['refund_total'];
            $gstRet = (float)$ref['gst_return'];
            $row['gst'] = (float)$row['gst'] - $gstRet;
            $row['grand_total'] = (float)$row['grand_total'] - $refund;
            $row['subtotal'] = (float)$row['subtotal'] - max(0.0, $refund - $gstRet);
        }
        if ((isset($_GET['export']) && $_GET['export'] === 'csv')) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gst_' . ($range ?? 'daily') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, [$range === 'monthly' ? 'Month' : 'Day', 'Subtotal', 'Discounts', 'GST', 'Grand Total']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['period'],
                    number_format((float)$r['subtotal'], 2, '.', ''),
                    number_format((float)$r['discounts'], 2, '.', ''),
                    number_format((float)$r['gst'], 2, '.', ''),
                    number_format((float)$r['grand_total'], 2, '.', ''),
                ]);
            }
            fclose($out);
            return;
        }
        $stores = $this->stores->getActive();
        $this->view('admin/pos/gst_report.twig', [
            'title' => 'GST Report',
            'rows' => $rows,
            'stores' => $stores,
            'filters' => ['store_id' => $storeId, 'range' => $range, 'from' => $from, 'to' => $to]
        ]);
    }

    public function backfillStock()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            return $this->redirect('/admin/pos/orders');
        }
        $products = $this->products->all('id', 'ASC');
        $stores = $this->stores->getActive();
        $created = 0;
        foreach ($products as $product) {
            $pid = (int)$product['id'];
            $total = $this->storeStock->getTotalForProduct($pid);
            foreach ($stores as $store) {
                $sid = (int)$store['id'];
                $existing = $this->storeStock->getByStoreAndProduct($sid, $pid);
                if (!$existing) {
                    $qty = ($total === 0) ? 10 : 0;
                    if ($this->storeStock->setQuantity($sid, $pid, $qty)) {
                        $created++;
                    }
                }
            }
        }
        \flash('success', 'Backfill complete. Created ' . $created . ' stock rows');
        return $this->redirect('/admin/pos/orders');
    }

    public function populateProductFields()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            return $this->redirect('/admin/pos/orders');
        }
        $products = $this->products->all('id', 'ASC');
        $updated = 0;
        foreach ($products as $p) {
            $patch = [];
            $barcode = $p['barcode'] ?? '';
            $gst = $p['gst_percent'] ?? null;
            if ($barcode === '' || $barcode === null) {
                $patch['barcode'] = 'BR' . str_pad((string)$p['id'], 8, '0', STR_PAD_LEFT);
            }
            if ($gst === '' || $gst === null) {
                $patch['gst_percent'] = 18.0;
            }
            if (!empty($patch)) {
                try {
                    if ($this->products->update((int)$p['id'], $patch)) {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    if (isset($patch['barcode']) && count($patch) > 1) {
                        unset($patch['barcode']);
                        try {
                            if (!empty($patch) && $this->products->update((int)$p['id'], $patch)) {
                                $updated++;
                            }
                        } catch (\Throwable $e2) {
                        }
                    }
                }
            }
        }
        \flash('success', 'Populated fields for ' . $updated . ' products');
        return $this->redirect('/admin/pos/orders');
    }

    public function addItem()
    {
        if (empty($_SESSION['pos_active'])) {
            return $this->redirect('/admin/pos/login');
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/pos/billing');
        }
        $input = trim($_POST['barcode'] ?? '');
        $productIdParam = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $product = null;
        if ($productIdParam > 0) {
            $product = $this->products->find($productIdParam);
        } elseif ($input !== '') {
            $product = $this->products->findBySku($input);
            if (!$product) {
                try {
                    $product = $this->products->whereFirst('barcode', $input);
                } catch (\Throwable $e) {
                    $product = null;
                }
            }
        }
        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/admin/pos/billing');
        }
        $storeId = (int)($_SESSION['pos_store_id'] ?? 0);
        $productStoreId = (int)($product['store_id'] ?? 0);
        $noStoreStock = (int)($product['no_store_stock'] ?? 0) === 1;
        $stockRow = $this->storeStock->getByStoreAndProduct($storeId, (int)$product['id']);
        $available = (int)($stockRow['quantity'] ?? 0);
        if ($productStoreId > 0 && $productStoreId !== $storeId && !$noStoreStock && $available <= 0) {
            $ps = $this->stores->find($productStoreId);
            $pname = $ps['name'] ?? ($ps['code'] ?? 'another shop');
            \flash('error', 'This product belongs to ' . $pname . ' and has no stock here');
            return $this->redirect('/admin/pos/billing');
        }
        if (!$noStoreStock && $qty > $available) {
            \flash('error', 'Only ' . $available . ' available in this store');
            return $this->redirect('/admin/pos/billing');
        }
        if (!isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'] = [];
        }
        $pid = (int)$product['id'];
        $existing = $_SESSION['pos_cart'][$pid]['quantity'] ?? 0;
        $newQty = $existing + $qty;
        if (!$noStoreStock && $newQty > $available) {
            $newQty = $available;
        }
        $_SESSION['pos_cart'][$pid] = [
            'product_id' => $pid,
            'name' => $product['name'],
            'price' => (float)($product['sale_price'] ?? $product['price']),
            'gst_percent' => (float)($product['gst_percent'] ?? 0),
            'discount_percent' => 0.0,
            'quantity' => $newQty,
            'no_store_stock' => $noStoreStock ? 1 : 0,
            'store_id' => $productStoreId
        ];
        \flash('success', 'Item added');
        return $this->redirect('/admin/pos/billing');
    }
    public function clear()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->redirect('/admin/pos/billing');
        }
        $_SESSION['pos_cart'] = [];
        unset($_SESSION['pos_temp_data']);
        \flash('success', 'New bill started');
        return $this->redirect('/admin/pos/billing');
    }
    public function searchProducts()
    {
        if (empty($_SESSION['pos_active'])) {
            http_response_code(403);
            echo json_encode(['error' => 'POS inactive']);
            return;
        }
        $q = trim($_GET['q'] ?? '');
        $storeId = (int)($_SESSION['pos_store_id'] ?? 0);
        if ($q === '' || $storeId <= 0) {
            echo json_encode([]);
            return;
        }
        $results = $this->products->searchForStore($q, $storeId, 30);
        header('Content-Type: application/json');
        echo json_encode($results);
    }

    public function updateQty()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->redirect('/admin/pos/billing');
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        if (isset($_SESSION['pos_cart'][$pid])) {
            $storeId = (int)($_SESSION['pos_store_id'] ?? 0);
            $stockRow = $this->storeStock->getByStoreAndProduct($storeId, $pid);
            $available = (int)($stockRow['quantity'] ?? 0);
            $_SESSION['pos_cart'][$pid]['quantity'] = min($qty, $available);
        }
        return $this->redirect('/admin/pos/billing');
    }
    public function updateDiscount()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->redirect('/admin/pos/billing');
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        $dp = (float)($_POST['discount_percent'] ?? 0);
        if (isset($_SESSION['pos_cart'][$pid])) {
            if ($dp < 0) { $dp = 0; }
            if ($dp > 100) { $dp = 100; }
            $_SESSION['pos_cart'][$pid]['discount_percent'] = $dp;
        }
        return $this->redirect('/admin/pos/billing');
    }

    public function removeItem()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            return $this->redirect('/admin/pos/billing');
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['pos_cart'][$pid]);
        return $this->redirect('/admin/pos/billing');
    }

    public function checkout()
    {
        if (empty($_SESSION['pos_active'])) {
            return $this->redirect('/admin/pos/login');
        }
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/pos/billing');
        }
        $cart = $_SESSION['pos_cart'] ?? [];
        if (empty($cart)) {
            \flash('error', 'No items');
            return $this->redirect('/admin/pos/billing');
        }
        if (!empty($_SESSION['pos_checkout_lock'])) {
            \flash('error', 'Checkout already in progress');
            return $this->redirect('/admin/pos/billing');
        }
        $_SESSION['pos_checkout_lock'] = 1;
        $cash = (float)($_POST['cash_amount'] ?? 0);
        $card = (float)($_POST['card_amount'] ?? 0);
        $upi  = (float)($_POST['upi_amount'] ?? 0);
        $service = isset($_POST['service_charge']) ? (float)$_POST['service_charge'] : (float)($_SESSION['pos_service_charge'] ?? 0);
        $customer = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $fy = $_SESSION['pos_fin_year'] ?? '';
        $storeId = (int)($_SESSION['pos_store_id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $staffUse = null;
        if ($staffId > 0) {
            $u = $this->users->getUserById($staffId);
            if ($u && ($u['role'] ?? '') === 'staff' && (int)($u['is_active'] ?? 0) === 1) {
                $staffUse = $staffId;
            }
        }
        if ($staffUse === null && \userId()) {
            $staffUse = \userId();
        }

        $subtotal = 0; $gstTotal = 0; $discountTotal = 0;
        foreach ($cart as $item) {
            $price = (float)$item['price'];
            $qty = (int)$item['quantity'];
            $dp = (float)$item['discount_percent'];
            $lineSub = $price * $qty;
            $disc = $lineSub * ($dp / 100);
            $taxBase = $lineSub - $disc;
            $gst = $taxBase * ((float)$item['gst_percent'] / 100);
            $subtotal += $lineSub;
            $discountTotal += $disc;
            $gstTotal += $gst;
        }
        $grand = $subtotal - $discountTotal + $gstTotal + $service;
        $paid = $cash + $card + $upi;
        $creditSale = (isset($_POST['credit_sale']) && $_POST['credit_sale'] === '1');
        $dueDate = trim($_POST['due_date'] ?? '');
        
        if (!$creditSale && round($paid, 2) < round($grand, 2) - 0.009) {
            \flash('error', 'Payment less than total');
            unset($_SESSION['pos_checkout_lock']);
            return $this->redirect('/admin/pos/billing');
        }
        
        $tendered = $paid;
        $change = 0;

        // Handle Change (Overpayment)
        // If user tenders 500 for 433 bill, we record 433 as cash revenue.
        // We assume overpayment is Cash.
        if (!$creditSale && $paid > $grand) {
            $change = $paid - $grand;
            if ($cash >= $change) {
                $cash -= $change;
            }
            // If cash < change, it implies card/upi overpayment. 
            // We keep the recorded amounts as is (accounting mismatch?) or strict to grand total.
            // For now, only adjusting cash.
        }

        // Compute change breakdown for drawer
        $breakdown = [];
        $denoms = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
        $rem = (int)floor($change + 0.00001);
        foreach ($denoms as $d) {
            $cnt = (int)floor($rem / $d);
            if ($cnt > 0) {
                $breakdown[(string)$d] = $cnt;
                $rem -= $cnt * $d;
            } else {
                $breakdown[(string)$d] = 0;
            }
        }
        $paise = max(0.0, round($change - floor($change), 2));
        $changeBreakdownJson = json_encode(['denoms' => $breakdown, 'remainder' => $paise]);

        $saleType = $_POST['sale_type'] ?? 'Shop';
        if (!in_array($saleType, ['Shop', 'Online'])) {
            $saleType = 'Shop';
        }
        $onlineOrderId = isset($_SESSION['pos_online_order_id']) ? (int)$_SESSION['pos_online_order_id'] : null;

        $orderNumber = $this->billSeq->nextNumber($storeId, $fy, $_SESSION['pos_store_code'] ?? '') ?? ('POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)));
        $orderId = $this->posOrders->createOrder([
            'order_number' => $orderNumber,
            'store_id' => $storeId,
            'financial_year' => $fy,
            'customer_name' => $customer,
            'customer_phone' => $phone,
            'staff_id' => $staffUse,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'service_charge' => $service,
            'gst_total' => $gstTotal,
            'grand_total' => $grand,
            'tendered_amount' => $tendered,
            'change_amount' => $change,
            'cash_amount' => $cash,
            'card_amount' => $card,
            'upi_amount' => $upi,
            'payments_json' => json_encode(['cash' => $cash, 'card' => $card, 'upi' => $upi]),
            'change_breakdown_json' => $changeBreakdownJson,
            'sale_type' => $saleType,
            'online_order_id' => $onlineOrderId
        ]);
        if (!$orderId) {
            \flash('error', 'Failed to create order');
            unset($_SESSION['pos_checkout_lock']);
            return $this->redirect('/admin/pos/billing');
        }
        $this->posPayments->record((int)$orderId, 'Cash', $cash);
        $this->posPayments->record((int)$orderId, 'Card', $card);
        $this->posPayments->record((int)$orderId, 'UPI', $upi);
        foreach ($cart as $item) {
            $price = (float)$item['price'];
            $qty = (int)$item['quantity'];
            $dp = (float)$item['discount_percent'];
            $lineSub = $price * $qty;
            $disc = $lineSub * ($dp / 100);
            $taxBase = $lineSub - $disc;
            $gst = $taxBase * ((float)$item['gst_percent'] / 100);
            $lineTotal = $taxBase + $gst;
            $this->posItems->create([
                'pos_order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'price' => $price,
                'quantity' => $qty,
                'discount_percent' => $dp,
                'gst_percent' => (float)$item['gst_percent'],
                'gst_amount' => $gst,
                'subtotal' => $lineSub,
                'line_total' => $lineTotal
            ]);
            if (!(int)($item['no_store_stock'] ?? 0)) {
                $this->storeStock->adjustStock($storeId, (int)$item['product_id'], -$qty);
                $this->movements->log($storeId, (int)$item['product_id'], $qty, 'OUT', 'SALE', 'pos_orders', (int)$orderId, null);
            }
        }
        $_SESSION['pos_cart'] = [];
        unset($_SESSION['pos_temp_data']);
        unset($_SESSION['pos_online_order_id']);
        if ($creditSale) {
            try {
                $bal = round($grand - $paid, 2);
                $ledger = new \App\Models\CustomerLedger();
                $ledger->create([
                    'store_id' => $storeId,
                    'customer_phone' => $phone,
                    'customer_name' => $customer,
                    'order_id' => (int)$orderId,
                    'debit' => $bal,
                    'credit' => 0,
                    'balance' => $bal,
                    'due_date' => $dueDate ?: null,
                    'notes' => 'Credit sale'
                ]);
            } catch (\Throwable $e) {}
        }
        \flash('success', 'POS order ' . $orderNumber . ' completed');
        unset($_SESSION['pos_checkout_lock']);
        return $this->redirect('/admin/pos/orders/' . (int)$orderId . '/show?print=1');
    }
    private function financialYears(): array
    {
        $y = (int)date('Y');
        $pairs = [];
        for ($i = 0; $i < 5; $i++) {
            $start = $y - $i;
            $endShort = substr((string)($start + 1), -2);
            $pairs[] = $start . '-' . $endShort;
        }
        return $pairs;
    }
}
