<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Review;
use App\Models\Product;
use App\Models\User;

class ReviewController extends BaseController
{
    private Review $reviews;
    private Product $products;
    private User $users;

    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
        $this->reviews = new Review();
        $this->products = new Product();
        $this->users = new User();
    }

    public function index()
    {
        $status = $_GET['status'] ?? 'pending';
        
        // Validate status
        $allowedStatuses = ['all', 'pending', 'approved', 'rejected'];
        if (!in_array(strtolower($status), $allowedStatuses)) {
            $status = 'pending';
        }

        // Build query
        $where = '';
        $params = [];
        
        if ($status !== 'all') {
            $where = 'WHERE r.status = :status';
            $params['status'] = ucfirst($status);
        }

        $sql = "SELECT r.*, u.name as user_name, u.email as user_email, 
                       p.name as product_name, p.slug as product_slug
                FROM reviews r
                INNER JOIN users u ON r.user_id = u.id
                INNER JOIN products p ON r.product_id = p.id
                {$where}
                ORDER BY r.created_at DESC";
        
        $items = $this->reviews->query($sql, $params);

        // Get counts
        $counts = [
            'all' => count($this->reviews->query("SELECT id FROM reviews")),
            'pending' => count($this->reviews->query("SELECT id FROM reviews WHERE status = 'Pending'")),
            'approved' => count($this->reviews->query("SELECT id FROM reviews WHERE status = 'Approved'")),
            'rejected' => count($this->reviews->query("SELECT id FROM reviews WHERE status = 'Rejected'"))
        ];

        $this->view('admin/reviews/index.twig', [
            'title' => 'Reviews',
            'items' => $items,
            'status' => $status,
            'counts' => $counts
        ]);
    }

public function countPending() {
    $sql = "SELECT COUNT(*) as total FROM reviews WHERE isapproved = 0";
    $result = $this->query($sql);
    return $result[0]['total'] ?? 0;
}

    public function updateStatus($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/reviews');
        }

        $reviewId = (int)$id;
        $status = trim($_POST['status'] ?? '');

        if ($reviewId <= 0) {
            \flash('error', 'Invalid review ID');
            return $this->redirect('/admin/reviews');
        }

        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array(strtolower($status), $allowedStatuses)) {
            \flash('error', 'Invalid status');
            return $this->redirect('/admin/reviews');
        }

        $review = $this->reviews->find($reviewId);
        if (!$review) {
            \flash('error', 'Review not found');
            return $this->redirect('/admin/reviews');
        }

        $statusForDb = ucfirst(strtolower($status));
        
        $sql = "UPDATE reviews SET status = :status WHERE id = :id";
        $this->reviews->query($sql, [
            'status' => $statusForDb,
            'id' => $reviewId
        ]);
        
        \flash('success', 'Review status updated');
        return $this->redirect('/admin/reviews?status=' . strtolower($status));
    }

    public function delete($id)
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/reviews');
        }

        $reviewId = (int)$id;

        $review = $this->reviews->find($reviewId);
        if (!$review) {
            \flash('error', 'Review not found');
            return $this->redirect('/admin/reviews');
        }

        $sql = "DELETE FROM reviews WHERE id = :id";
        $this->reviews->query($sql, ['id' => $reviewId]);
        
        \flash('success', 'Review deleted');
        return $this->redirect('/admin/reviews');
    }

    public function bulkAction()
    {
        if (!\csrf_verify($_POST['_token'] ?? null)) {
            \flash('error', 'Session expired');
            return $this->redirect('/admin/reviews');
        }

        $action = $_POST['action'] ?? '';
        $reviewIds = $_POST['review_ids'] ?? [];

        $allowedActions = ['approve', 'reject', 'delete'];
        if (!in_array($action, $allowedActions)) {
            \flash('error', 'Invalid action');
            return $this->redirect('/admin/reviews');
        }

        if (empty($reviewIds) || !is_array($reviewIds)) {
            \flash('error', 'No reviews selected');
            return $this->redirect('/admin/reviews');
        }

        $reviewIds = array_map('intval', $reviewIds);
        $reviewIds = array_filter($reviewIds, fn($id) => $id > 0);

        if (empty($reviewIds)) {
            \flash('error', 'Invalid selection');
            return $this->redirect('/admin/reviews');
        }

        $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
        
        if ($action === 'delete') {
            $sql = "DELETE FROM reviews WHERE id IN ($placeholders)";
            $this->reviews->query($sql, $reviewIds);
        } else {
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            $sql = "UPDATE reviews SET status = ? WHERE id IN ($placeholders)";
            $params = array_merge([$status], $reviewIds);
            $this->reviews->query($sql, $params);
        }

        $actionText = $action === 'delete' ? 'deleted' : ($action . 'd');
        \flash('success', count($reviewIds) . " review(s) $actionText");
        return $this->redirect('/admin/reviews');
    }
}