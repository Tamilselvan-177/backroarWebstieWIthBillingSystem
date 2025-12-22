<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Store;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\StockTransfer;
use App\Helpers\Validator;

class StockController extends BaseController
{
    private Store $stores;
    private Product $products;
    private StoreStock $storeStock;
    private StockTransfer $transfers;

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
        $this->transfers = new StockTransfer();
    }

    public function transferForm()
    {
        $storeList = $this->stores->getActive();
        $this->view('admin/stock/transfer.twig', [
            'title' => 'Stock Transfer',
            'stores' => $storeList,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function transfer()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/stock/transfer');
        }
        $data = [
            'from_store_id' => (int)($_POST['from_store_id'] ?? 0),
            'to_store_id' => (int)($_POST['to_store_id'] ?? 0),
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'quantity' => (int)($_POST['quantity'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
        ];
        $v = new Validator($data);
        $v->required('from_store_id')
          ->required('to_store_id')
          ->required('product_id')
          ->required('quantity')
          ->min('quantity', 1);
        if ($v->fails()) {
            $_SESSION['errors'] = $v->getErrors();
            $_SESSION['old'] = $data;
            return $this->redirect('/admin/stock/transfer');
        }
        if ($data['from_store_id'] === $data['to_store_id']) {
            \flash('error', 'Choose different source and destination');
            return $this->redirect('/admin/stock/transfer');
        }
        $product = $this->products->find($data['product_id']);
        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/admin/stock/transfer');
        }
        $src = $this->storeStock->getByStoreAndProduct($data['from_store_id'], $data['product_id']);
        $available = (int)($src['quantity'] ?? 0);
        if ($available < $data['quantity']) {
            \flash('error', 'Insufficient stock in source store');
            return $this->redirect('/admin/stock/transfer');
        }
        $ok1 = $this->storeStock->adjustStock($data['from_store_id'], $data['product_id'], -$data['quantity']);
        $ok2 = $this->storeStock->adjustStock($data['to_store_id'], $data['product_id'], $data['quantity']);
        if (!$ok1 || !$ok2) {
            \flash('error', 'Transfer failed');
            return $this->redirect('/admin/stock/transfer');
        }
        $this->transfers->create([
            'from_store_id' => $data['from_store_id'],
            'to_store_id' => $data['to_store_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'transferred_by' => \userId() ?: null,
            'notes' => $data['notes']
        ]);
        \flash('success', 'Stock transferred');
        return $this->redirect('/admin/stock/transfer');
    }
}
