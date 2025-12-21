<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\User;
use App\Helpers\Validator;

class StaffController extends BaseController
{
    private User $users;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->users = new User();
    }

    public function index()
    {
        $rows = $this->users->query("SELECT id, name, email, phone, is_active, created_at FROM users WHERE role = 'staff' ORDER BY name ASC");
        $this->view('admin/staff/index.twig', [
            'title' => 'Staff',
            'items' => $rows
        ]);
    }

    public function create()
    {
        $this->view('admin/staff/create.twig', [
            'title' => 'Add Staff',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/staff/create');
        }
        $data = [
            'name' => \clean($_POST['name'] ?? ''),
            'email' => strtolower(trim($_POST['email'] ?? '')),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => (string)($_POST['password'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 1,
            'role' => 'staff'
        ];
        $v = new Validator($data);
        $v->required('name', 'Name is required')
          ->required('email', 'Email is required')
          ->required('password', 'Password is required')
          ->min('password', 6);
        if ($v->fails()) {
            $_SESSION['errors'] = $v->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/staff/create');
        }
        if ($this->users->emailExists($data['email'])) {
            \flash('error', 'Email already exists');
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/staff/create');
        }
        $this->users->register($data);
        \flash('success', 'Staff user created');
        return $this->redirect('/admin/staff');
    }

    public function edit($id)
    {
        $u = $this->users->getUserById((int)$id);
        if (!$u || ($u['role'] ?? '') !== 'staff') {
            \flash('error', 'Staff not found');
            return $this->redirect('/admin/staff');
        }
        $this->view('admin/staff/edit.twig', [
            'title' => 'Edit Staff',
            'item' => $u,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function update($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/staff/' . (int)$id . '/edit');
        }
        $u = $this->users->getUserById((int)$id);
        if (!$u || ($u['role'] ?? '') !== 'staff') {
            \flash('error', 'Staff not found');
            return $this->redirect('/admin/staff');
        }
        $patch = [
            'name' => \clean($_POST['name'] ?? (string)$u['name']),
            'phone' => trim($_POST['phone'] ?? (string)($u['phone'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        $this->users->update((int)$id, $patch);
        $pwd = (string)($_POST['password'] ?? '');
        if ($pwd !== '') {
            if (strlen($pwd) < 6) {
                \flash('error', 'Password must be at least 6 characters');
                $_SESSION['old'] = $_POST;
                return $this->redirect('/admin/staff/' . (int)$id . '/edit');
            }
            $this->users->changePassword((int)$id, $pwd);
        }
        \flash('success', 'Staff updated');
        return $this->redirect('/admin/staff');
    }
}

