<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Counter;
use App\Models\Store;
use App\Helpers\Validator;

class CounterController extends BaseController
{
    private Counter $counters;
    private Store $stores;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->counters = new Counter();
        $this->stores = new Store();
    }

    public function index()
    {
        $storeId = (int)($_GET['store_id'] ?? 0);
        $stores = $this->stores->getActive();
        $rows = [];
        if ($storeId > 0) {
            $rows = $this->counters->query("SELECT c.*, s.name AS store_name, s.code AS store_code FROM counters c INNER JOIN stores s ON c.store_id = s.id WHERE c.store_id = :sid ORDER BY c.name ASC", ['sid' => $storeId]);
        } else {
            $rows = $this->counters->query("SELECT c.*, s.name AS store_name, s.code AS store_code FROM counters c INNER JOIN stores s ON c.store_id = s.id ORDER BY s.name ASC, c.name ASC");
        }
        $this->view('admin/counters/index.twig', [
            'title' => 'Counters',
            'stores' => $stores,
            'store_id' => $storeId,
            'items' => $rows
        ]);
    }

    public function create()
    {
        $stores = $this->stores->getActive();
        $this->view('admin/counters/create.twig', [
            'title' => 'Create Counter',
            'stores' => $stores,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/counters/create');
        }
        $data = [
            'store_id' => (int)($_POST['store_id'] ?? 0),
            'code' => strtoupper(trim($_POST['code'] ?? '')),
            'name' => trim($_POST['name'] ?? ''),
            'pin' => trim($_POST['pin'] ?? '')
        ];
        $v = new Validator($data);
        $v->required('store_id', 'Store is required')
          ->required('code', 'Code is required')
          ->required('name', 'Name is required')
          ->required('pin', 'PIN is required')
          ->min('pin', 4);
        if ($v->fails()) {
            $_SESSION['errors'] = $v->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/counters/create');
        }
        if ($this->counters->whereFirst('code', $data['code'])) {
            \flash('error', 'Counter code already exists');
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/counters/create');
        }
        $pinHash = password_hash($data['pin'], PASSWORD_DEFAULT);
        $this->counters->create([
            'store_id' => $data['store_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'pin_hash' => $pinHash,
            'is_active' => 1
        ]);
        \flash('success', 'Counter created');
        return $this->redirect('/admin/counters');
    }

    public function edit($id)
    {
        $row = $this->counters->find((int)$id);
        if (!$row) {
            \flash('error', 'Counter not found');
            return $this->redirect('/admin/counters');
        }
        $stores = $this->stores->getActive();
        $this->view('admin/counters/edit.twig', [
            'title' => 'Edit Counter',
            'item' => $row,
            'stores' => $stores,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function update($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/counters/' . (int)$id . '/edit');
        }
        $row = $this->counters->find((int)$id);
        if (!$row) {
            \flash('error', 'Counter not found');
            return $this->redirect('/admin/counters');
        }
        $patch = [
            'store_id' => (int)($_POST['store_id'] ?? (int)$row['store_id']),
            'code' => strtoupper(trim($_POST['code'] ?? (string)$row['code'])),
            'name' => trim($_POST['name'] ?? (string)$row['name']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        $v = new Validator($patch);
        $v->required('store_id', 'Store is required')
          ->required('code', 'Code is required')
          ->required('name', 'Name is required');
        if ($v->fails()) {
            $_SESSION['errors'] = $v->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/counters/' . (int)$id . '/edit');
        }
        $existing = $this->counters->whereFirst('code', $patch['code']);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            \flash('error', 'Counter code already exists');
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/counters/' . (int)$id . '/edit');
        }
        $pinNew = trim($_POST['pin_new'] ?? '');
        $pinConfirm = trim($_POST['pin_confirm'] ?? '');
        if ($pinNew !== '') {
            if (strlen($pinNew) < 4) {
                \flash('error', 'PIN must be at least 4 characters');
                $_SESSION['old'] = $_POST;
                return $this->redirect('/admin/counters/' . (int)$id . '/edit');
            }
            if ($pinNew !== $pinConfirm) {
                \flash('error', 'PIN confirmation does not match');
                $_SESSION['old'] = $_POST;
                return $this->redirect('/admin/counters/' . (int)$id . '/edit');
            }
            $patch['pin_hash'] = password_hash($pinNew, PASSWORD_DEFAULT);
        }
        $this->counters->update((int)$id, $patch);
        \flash('success', 'Counter updated');
        return $this->redirect('/admin/counters');
    }

    public function toggleActive($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/counters');
        }
        $row = $this->counters->find((int)$id);
        if (!$row) {
            return $this->redirect('/admin/counters');
        }
        $this->counters->update((int)$id, ['is_active' => (int)$row['is_active'] ? 0 : 1]);
        return $this->redirect('/admin/counters');
    }
}

