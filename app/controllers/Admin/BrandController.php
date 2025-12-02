<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Brand;
use App\Helpers\Validator;

class BrandController extends BaseController
{
    private Brand $brands;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->brands = new Brand();
    }

    public function index()
    {
        $items = $this->brands->getActive();
        $this->view('admin/brands/index.twig', [
            'title' => 'Brands',
            'brands' => $items
        ]);
    }

    public function create()
    {
        $this->view('admin/brands/create.twig', [
            'title' => 'Add Brand',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store()
    {
        $name = \clean($_POST['name'] ?? '');
        $slug = \slugify($_POST['slug'] ?? $name);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required');
        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/brands/create');
        }

        $this->brands->create([
            'name' => $name,
            'slug' => $slug,
            'display_order' => $displayOrder,
            'is_active' => $isActive
        ]);
        \flash('success', 'Brand created');
        return $this->redirect('/admin/brands');
    }

    public function edit($id)
    {
        $b = $this->brands->find((int)$id);
        if (!$b) return $this->redirect('/admin/brands');
        $this->view('admin/brands/edit.twig', [
            'title' => 'Edit Brand',
            'brand' => $b,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function update($id)
    {
        $name = \clean($_POST['name'] ?? '');
        $slug = \slugify($_POST['slug'] ?? $name);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required');
        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/brands/' . (int)$id . '/edit');
        }

        $this->brands->update((int)$id, [
            'name' => $name,
            'slug' => $slug,
            'display_order' => $displayOrder,
            'is_active' => $isActive
        ]);
        \flash('success', 'Brand updated');
        return $this->redirect('/admin/brands');
    }

    public function delete($id)
    {
        $this->brands->delete((int)$id);
        \flash('success', 'Brand deleted');
        return $this->redirect('/admin/brands');
    }
}

