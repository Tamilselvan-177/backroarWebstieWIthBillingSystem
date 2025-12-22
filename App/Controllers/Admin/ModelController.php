<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PhoneModel;
use App\Models\Brand;
use App\Helpers\Validator;

class ModelController extends BaseController
{
    private PhoneModel $models;
    private Brand $brands;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->models = new PhoneModel();
        $this->brands = new Brand();
    }

    public function index()
    {
        $brands = $this->brands->getActive();
        $brandId = (int)($_GET['brand_id'] ?? 0);
        $items = $brandId ? $this->models->getByBrand($brandId) : [];
        $this->view('admin/models/index.twig', [
            'title' => 'Models',
            'brands' => $brands,
            'brand_id' => $brandId,
            'models' => $items
        ]);
    }

    public function create()
    {
        $brands = $this->brands->getActive();
        $this->view('admin/models/create.twig', [
            'title' => 'Add Model',
            'brands' => $brands,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

   public function store()
{
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $name = \clean($_POST['name'] ?? '');
    $slug = \slugify($_POST['slug'] ?? $name);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $validator = new Validator($_POST);
    $validator->required('brand_id', 'Brand is required')
              ->required('name', 'Name is required');

    if ($validator->fails()) {
        $_SESSION['errors'] = $validator->getErrors();
        $_SESSION['old'] = $_POST;
        return $this->redirect('/admin/models/create');
    }

    $this->models->create([
        'brand_id' => $brandId,
        'name' => $name,
        'slug' => $slug,
        'is_active' => $isActive
    ]);

    \flash('success', 'Model created');
    return $this->redirect('/admin/models');
}

    public function edit($id)
{
    $brands = $this->brands->getActive();
    
    $model = $this->models->find((int)$id);
    if (!$model) {
        return $this->redirect('/admin/models');
    }

    $this->view('admin/models/edit.twig', [
        'title' => 'Edit Model',
        'brands' => $brands,
        'model' => $model
    ]);
}


   public function update($id)
{
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $name = \clean($_POST['name'] ?? '');
    $slug = \slugify($_POST['slug'] ?? $name);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $this->models->update((int)$id, [
        'brand_id' => $brandId,
        'name' => $name,
        'slug' => $slug,
        'is_active' => $isActive
    ]);

    \flash('success', 'Model updated');
    return $this->redirect('/admin/models');
}


public function delete($id)
{
    $this->models->delete((int)$id);

    flash('success', 'Model deleted');
    return $this->redirect('/admin/models');
}


}

