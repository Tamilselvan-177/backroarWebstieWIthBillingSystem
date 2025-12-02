<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Category;
use App\Models\Subcategory;
use App\Helpers\Validator;


class SubcategoryController extends BaseController
{
    private $subcategories;
    private $categories;

    public function __construct()
    {
        if (!isAdmin()) {
            error('Admin access required');
            exit;
        }

        $this->subcategories = new Subcategory();
        $this->categories = new Category();
    }

    public function index()
    {
        // Pagination
        $page = (int)($_GET['page'] ?? 1);

        $config = require __DIR__ . '/../../config/app.php';
        $perPage = (int)($config['per_page'] ?? 24);

        $offset = ($page - 1) * $perPage;

        // Filters
        $q = trim($_GET['q'] ?? '');
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $active = $_GET['active'] ?? '';

        $conditions = [];
        $params = [];

        if ($q) {
            $conditions[] = "(s.name LIKE ? OR s.slug LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        if ($categoryId > 0) {
            $conditions[] = "s.category_id = ?";
            $params[] = $categoryId;
        }

        if ($active !== '') {
            $conditions[] = "s.is_active = ?";
            $params[] = ($active == '1') ? 1 : 0;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count rows
        $countSql = "SELECT COUNT(*) AS total FROM subcategories s {$where}";
        $stmt = $this->subcategories->query($countSql, $params);
        $total = $stmt[0]['total'] ?? 0;

        // Fetch list
        $sql = "SELECT s.*, c.name AS categoryname
                FROM subcategories s
                LEFT JOIN categories c ON s.category_id = c.id
                {$where}
                ORDER BY s.display_order ASC, s.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $subcategories = $this->subcategories->query($sql, $params);
        $categories = $this->categories->getActive();

        $this->view('admin/subcategories/index.twig', [
    'title' => 'Subcategories',
    'subcategories' => $subcategories,
    'categories' => $categories,
    'filters' => [
        'q' => $q,
        'category_id' => $categoryId,    // ✅ Fixed
        'active' => $active
    ],
    'pagination' => [
        'total' => $total,
        'current_page' => $page,         // ✅ Fixed: currentpage → current_page
        'total_pages' => max(1, (int)ceil($total / $perPage))  // ✅ Fixed: totalpages → total_pages
    ]
]);

    }

    public function create()
    {
        $categories = $this->categories->getActive();

        $this->view('admin/subcategories/create.twig', [
            'title' => 'Add Subcategory',
            'categories' => $categories,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function store()
    {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? $name);

        $validator = new Validator($_POST);
        $validator->required('category_id', 'Category is required');
        $validator->required('name', 'Name is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect('/admin/subcategories/create');
        }

        $data = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'display_order' => (int)($_POST['display_order'] ?? 0)
        ];

        $this->subcategories->create($data);

        flash('success', 'Subcategory created successfully');
        return $this->redirect('/admin/subcategories');
    }

    public function edit($id)
    {
        $subcategory = $this->subcategories->find((int)$id);

        if (!$subcategory) {
            error('Subcategory not found');
            return $this->redirect('/admin/subcategories');
        }

        $categories = $this->categories->getActive();

        $this->view('admin/subcategories/edit.twig', [
            'title' => 'Edit Subcategory',
            'subcategory' => $subcategory,
            'categories' => $categories,
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    public function update($id)
    {
        $subcategory = $this->subcategories->find((int)$id);

        if (!$subcategory) {
            error('Subcategory not found');
            return $this->redirect('/admin/subcategories');
        }

        $categoryId = (int)($_POST['category_id'] ?? $subcategory->category_id);
        $name = trim($_POST['name'] ?? $subcategory->name);
        $slug = trim($_POST['slug'] ?? $subcategory->slug);

        $validator = new Validator($_POST);
        $validator->required('category_id', 'Category is required');
        $validator->required('name', 'Name is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = $_POST;
            return $this->redirect("/admin/subcategories/{$id}/edit");
        }

        $data = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'display_order' => (int)($_POST['display_order'] ?? $subcategory->display_order)
        ];

        $this->subcategories->update((int)$id, $data);

        flash('success', 'Subcategory updated successfully');
        return $this->redirect("/admin/subcategories/{$id}/edit");
    }

    public function toggleActive($id)
    {
        $sub = $this->subcategories->find((int)$id);

        if (!$sub) {
            return $this->redirect('/admin/subcategories');
        }

        $this->subcategories->update((int)$id, [
            'is_active' => $sub->is_active ? 0 : 1
        ]);

        return $this->redirect('/admin/subcategories');
    }

    public function delete($id)
    {
        $subcategory = $this->subcategories->find((int)$id);

        if ($subcategory) {
            $this->subcategories->delete((int)$id);
            flash('success', 'Subcategory deleted successfully');
        } else {
            flash('error', 'Subcategory not found');
        }

        return $this->redirect('/admin/subcategories');
    }
}
?>
