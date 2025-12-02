<?php

namespace App\Controllers\Admin;

use App\Models\Product;
use App\Helpers\ImageUploader;

class ImageController extends \App\Controllers\BaseController
{
    private Product $productModel;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->productModel = new Product();
    }

    public function index($productId)
    {
        $product = $this->productModel->find((int)$productId);
        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/products');
        }
        $images = $this->productModel->getImages($product['id']);
        $this->view('admin/products/images.twig', [
            'title' => 'Manage Images',
            'product' => $product,
            'images' => $images,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function upload($productId)
    {
        $product = $this->productModel->find((int)$productId);
        if (!$product) {
            \flash('error', 'Product not found');
            return $this->redirect('/products');
        }

        $files = $_FILES['images'] ?? null;
        if (!$files) {
            \flash('error', 'No files selected');
            return $this->redirect('/admin/products/' . $product['id'] . '/images');
        }

        $uploaded = ImageUploader::uploadProductImages((int)$product['id'], $files);
        $existing = $this->productModel->getImages($product['id']);
        $isPrimary = empty($existing) ? 1 : 0;
        $orderBase = count($existing);
        $i = 0;
        foreach ($uploaded as $u) {
            $imgId = $this->productModel->addImage($product['id'], $u['image_path'], $isPrimary && $i === 0 ? 1 : 0, $orderBase + $i);
            $i++;
        }

        \flash('success', 'Images uploaded');
        return $this->redirect('/admin/products/' . $product['id'] . '/images');
    }

    public function delete($imageId)
    {
        $deleted = $this->productModel->deleteImage((int)$imageId);
        \flash($deleted ? 'success' : 'error', $deleted ? 'Image deleted' : 'Failed to delete image');
        $ref = $_SERVER['HTTP_REFERER'] ?? '/products';
        return $this->redirect($ref);
    }

    public function primary($productId, $imageId)
    {
        $ok = $this->productModel->setPrimaryImage((int)$productId, (int)$imageId);
        \flash($ok ? 'success' : 'error', $ok ? 'Primary image updated' : 'Failed to update');
        return $this->redirect('/admin/products/' . (int)$productId . '/images');
    }

    public function reorder($productId)
    {
        $ids = $_POST['image_ids'] ?? [];
        $this->productModel->updateImageOrders((int)$productId, $ids);
        \flash('success', 'Order updated');
        return $this->redirect('/admin/products/' . (int)$productId . '/images');
    }
}
