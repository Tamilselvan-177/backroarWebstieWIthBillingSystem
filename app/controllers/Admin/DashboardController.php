<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brand;
use App\Models\Review;
class DashboardController extends BaseController
{
    public function __construct()
    {
        if (!isAdmin()) {
            flash('error', 'Admin access required');
            redirect('/login');
            exit;
        }
    }

    public function index()
    {
        $products = new Product();
        $orders = new Order();
        $categories = new Category();
        $subcategories = new Subcategory();
        $brands = new Brand();
        $reviews = new Review();

        // ✅ SAFE COUNTS - No special methods needed
        $this->view('admin/index.twig', [
            'title' => 'Admin Dashboard',
            'stats' => [
                'total_products' => count($products->all()),
                'open_orders' => count($orders->all()),  // All orders for now
                'total_review' => count($reviews->all()),   // Static number (add Review model later)
                'total_categories' => count($categories->all()),
                'total_subcategories' => count($subcategories->all()),
                'total_brands' => count($brands->all())
            ]
        ]);
    }
}
