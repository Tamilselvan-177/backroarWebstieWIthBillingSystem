<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Coupon;
use App\Helpers\Validator;

class AdminCouponController extends BaseController
{
    private $couponModel;
    private function normalizeDateTimeLocal($value)
    {
        if (!$value) {
            return '';
        }
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        return $value;
    }

    public function __construct()
    {
        $this->couponModel = new Coupon();
        
        // Check if user is admin
        if (!\isLoggedIn() || !\isAdmin()) {
            \flash('error', 'Unauthorized access');
            header('Location: /');
            exit;
        }
    }

    /**
     * List all coupons
     */
public function index()
{
    $page = (int)($_GET['page'] ?? 1);
    $perPage = 15;
    
    $result = $this->couponModel->getAllPaginated($page, $perPage);

    $this->view('admin/coupons/index.twig', [
        'title' => 'Manage Coupons - Admin',
        'coupons' => $result['coupons'],
        'pagination' => [
            'total' => $result['total'],
            'current_page' => $result['current_page'],
            'total_pages' => $result['total_pages']
        ],
        'csrf_token' => csrf_token()  // ← ADD THIS LINE
    ]);
}
    /**
     * Show create form
     */
    public function create()
    {
        $this->view('admin/coupons/create.twig', [
            'title' => 'Create Coupon - Admin',
            'csrf_token' => csrf_token(),
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? [],
            
        ]
    );

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Store new coupon
     */
   /**
 * Store new coupon
 */
public function store()
{
    if (!\csrf_verify($_POST['_token'] ?? null)) {
        \flash('error', 'Session expired');
        return $this->redirect('/admin/coupons/create');
    }

    $dtype = strtoupper(trim($_POST['discount_type'] ?? 'PERCENT'));
    if ($dtype !== 'FIXED' && $dtype !== 'PERCENT') { $dtype = 'PERCENT'; }
    $data = [
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'discount_type' => $dtype,
        'discount_value' => (float)($_POST['discount_value'] ?? 0),
        'min_purchase' => (float)($_POST['min_purchase'] ?? 0),
        'max_discount' => (float)($_POST['max_discount'] ?? 0),
        'usage_limit' => (int)($_POST['usage_limit'] ?? 0),
        'per_user_limit' => (int)($_POST['per_user_limit'] ?? 0),
        'valid_from' => $this->normalizeDateTimeLocal($_POST['valid_from'] ?? ''),
        'valid_to' => $this->normalizeDateTimeLocal($_POST['valid_to'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'description' => trim($_POST['description'] ?? '')
    ];

    if ($data['discount_type'] === 'fixed') {
        $data['max_discount'] = 0;
    }

    // Validation
    $v = new Validator($data);
    $v->required('code')->min('code', 3)->max('code', 50)
      ->required('discount_type')
      ->required('discount_value')->min('discount_value', 1)
      ->required('valid_from')
      ->required('valid_to');

    // Manual validation for discount_type
if (!in_array($data['discount_type'], ['PERCENT', 'FIXED'])) {
    $v->addError('discount_type', 'Invalid discount type');
}

    // Additional validations
    if ($data['discount_type'] === 'PERCENT' && $data['discount_value'] > 100) {
        $v->addError('discount_value', 'Percentage cannot exceed 100%');
    }

    if (strtotime($data['valid_to']) < strtotime($data['valid_from'])) {
        $v->addError('valid_to', 'End date must be after start date');
    }

    // Check if code already exists
    if ($this->couponModel->codeExists($data['code'])) {
        $v->addError('code', 'Coupon code already exists');
    }

    if ($v->fails()) {
        $_SESSION['errors'] = $v->getErrors();
        $_SESSION['old'] = $data;
        return $this->redirect('/admin/coupons/create');
    }

    $data = $this->couponModel->filterColumns($data);
    if ($this->couponModel->create($data)) {
        \flash('success', 'Coupon created successfully');
        return $this->redirect('/admin/coupons');
    }

    \flash('error', 'Failed to create coupon');
    return $this->redirect('/admin/coupons/create');
}
    /**
     * Show edit form
     */
  /**
 * Show edit form
 */
public function edit($id)
{
    $coupon = $this->couponModel->find($id);

    if (!$coupon) {
        \flash('error', 'Coupon not found');
        return $this->redirect('/admin/coupons');
    }

    // DEBUG: Check what values we're getting
    error_log("Coupon data: " . print_r($coupon, true));

    // Format datetime for datetime-local input
    $from = $coupon['valid_from'] ?? null;
    $to = $coupon['valid_to'] ?? null;
    $coupon['valid_from_formatted'] = $from ? date('Y-m-d\\TH:i', strtotime($from)) : '';
    $coupon['valid_to_formatted'] = $to ? date('Y-m-d\\TH:i', strtotime($to)) : '';
    $coupon['discount_type'] = $coupon['type'] ?? null;
    $coupon['discount_value'] = $coupon['value'] ?? null;
    $coupon['min_purchase'] = $coupon['min_order_amount'] ?? 0;
    $coupon['max_discount'] = $coupon['max_discount_amount'] ?? 0;
    $coupon['usage_limit'] = $coupon['usage_limit_global'] ?? 0;
    $coupon['per_user_limit'] = $coupon['usage_limit_per_user'] ?? 0;
    $columns = $this->couponModel->getColumns();
    $coupon['dtype'] = $coupon['discount_type'];

    $this->view('admin/coupons/edit.twig', [
        'title' => 'Edit Coupon - Admin',
        'coupon' => $coupon,
        'csrf_token' => csrf_token(),
        'errors' => $_SESSION['errors'] ?? [],
        'old' => $_SESSION['old'] ?? [],
        'columns' => $columns
    ]);

    unset($_SESSION['errors'], $_SESSION['old']);
}    /**
     * Update coupon
     */
   /**
 * Update coupon
 */
public function update($id)
{
    if (!\csrf_verify($_POST['_token'] ?? null)) {
        \flash('error', 'Session expired');
        return $this->redirect('/admin/coupons/' . $id . '/edit');
    }

    $coupon = $this->couponModel->find($id);
    if (!$coupon) {
        \flash('error', 'Coupon not found');
        return $this->redirect('/admin/coupons');
    }

    $dtype = strtoupper(trim($_POST['discount_type'] ?? 'PERCENT'));
    if ($dtype !== 'FIXED' && $dtype !== 'PERCENT') { $dtype = 'PERCENT'; }
    $data = [
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'discount_type' => $dtype,
        'discount_value' => (float)($_POST['discount_value'] ?? 0),
        'min_purchase' => (float)($_POST['min_purchase'] ?? 0),
        'max_discount' => (float)($_POST['max_discount'] ?? 0),
        'usage_limit' => (int)($_POST['usage_limit'] ?? 0),
        'per_user_limit' => (int)($_POST['per_user_limit'] ?? 0),
        'valid_from' => $this->normalizeDateTimeLocal($_POST['valid_from'] ?? ''),
        'valid_to' => $this->normalizeDateTimeLocal($_POST['valid_to'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'description' => trim($_POST['description'] ?? '')
    ];

    $existingType = $coupon['type'] ?? $data['discount_type'];
    $data['discount_type'] = $existingType;
    if ($data['discount_type'] === 'FIXED') {
        $data['max_discount'] = 0;
    }

    // Validation
    $v = new Validator($data);
    $v->required('code')->min('code', 3)->max('code', 50)
      ->required('discount_type')
      ->required('discount_value')->min('discount_value', 1)
      ->required('valid_from')
      ->required('valid_to');

    // Manual validation for discount_type
if (!in_array($data['discount_type'], ['PERCENT', 'FIXED'])) {
    $v->addError('discount_type', 'Invalid discount type');
}
    if ($data['discount_type'] === 'PERCENT' && $data['discount_value'] > 100) {
        $v->addError('discount_value', 'Percentage cannot exceed 100%');
    }

    if (strtotime($data['valid_to']) < strtotime($data['valid_from'])) {
        $v->addError('valid_to', 'End date must be after start date');
    }

    // Check if code exists (excluding current)
    if ($data['code'] !== $coupon['code'] && $this->couponModel->codeExists($data['code'], $id)) {
        $v->addError('code', 'Coupon code already exists');
    }

    if ($v->fails()) {
        $_SESSION['errors'] = $v->getErrors();
        $_SESSION['old'] = $data;
        return $this->redirect('/admin/coupons/' . $id . '/edit');
    }

    $data = $this->couponModel->filterColumns($data);
    if ($this->couponModel->update($id, $data)) {
        \flash('success', 'Coupon updated successfully');
        return $this->redirect('/admin/coupons');
    }

    \flash('error', 'Failed to update coupon');
    return $this->redirect('/admin/coupons/' . $id . '/edit');
}
    /**
     * Delete coupon
     */
    public function delete($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/coupons');
        }

        $coupon = $this->couponModel->find($id);
        if (!$coupon) {
            \flash('error', 'Coupon not found');
            return $this->redirect('/admin/coupons');
        }

        // Check if coupon has been used
        $usageCount = $this->couponModel->totalUsageCount($id);
        if ($usageCount > 0) {
            \flash('error', 'Cannot delete coupon that has been used. Deactivate it instead.');
            return $this->redirect('/admin/coupons');
        }

        if ($this->couponModel->delete($id)) {
            \flash('success', 'Coupon deleted successfully');
        } else {
            \flash('error', 'Failed to delete coupon');
        }

        return $this->redirect('/admin/coupons');
    }

    /**
     * Toggle active status
     */
    public function toggleStatus($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/coupons');
        }

        $coupon = $this->couponModel->find($id);
        if (!$coupon) {
            \flash('error', 'Coupon not found');
            return $this->redirect('/admin/coupons');
        }

        $newStatus = $coupon['is_active'] ? 0 : 1;
        
        if ($this->couponModel->update($id, ['is_active' => $newStatus])) {
            \flash('success', 'Coupon status updated');
        } else {
            \flash('error', 'Failed to update status');
        }

        return $this->redirect('/admin/coupons');
    }
}
