<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Wishlist;

class AccountController extends BaseController
{
    private $userModel;
    private $orderModel;
    private $wishlistModel;

    public function __construct()
    {
        // Require login for all account actions
        if (!\isLoggedIn()) {
            \flash('error', 'Please login to access your account');
            \redirect('/login');
            exit;
        }

        $this->userModel     = new User();
        $this->orderModel    = new Order();
        $this->wishlistModel = new Wishlist();
    }

    /**
     * Show account dashboard
     */
    public function dashboard()
    {
        $userId = \userId();
        $user   = $this->userModel->getUserById($userId);

        // Total orders using existing pagination method
        $orderResult  = $this->orderModel->getUserOrders($userId, 1, 1);
        $totalOrders  = (int)($orderResult['total'] ?? 0);

        // Wishlist count from wishlist model
        $wishlistItems = $this->wishlistModel->getUserWishlist($userId);
        $wishlistCount = is_array($wishlistItems) ? count($wishlistItems) : 0;

        $this->view('account/dashboard.twig', [
            'title'          => 'My Account - BlackRoar',
            'user'           => $user,
            'total_orders'   => $totalOrders,
            'wishlist_count' => $wishlistCount,
        ]);
    }

    /**
     * Show profile page
     */
    public function profile()
    {
        $user = $this->userModel->getUserById(\userId());

        $this->view('account/profile.twig', [
            'title'  => 'My Profile - BlackRoar',
            'user'   => $user,
            'errors' => $_SESSION['errors'] ?? [],
            'old'    => $_SESSION['old'] ?? [],
        ]);

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Update profile
     */
    public function updateProfile()
    {
        $name  = \clean($_POST['name'] ?? '');
        $phone = \clean($_POST['phone'] ?? '');

        $validator = new \App\Helpers\Validator($_POST);
        $validator->required('name', 'Name is required')
                  ->min('name', 3, 'Name must be at least 3 characters')
                  ->required('phone', 'Phone number is required')
                  ->phone('phone', 'Invalid phone number');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old']    = ['name' => $name, 'phone' => $phone];
            return $this->redirect('/account/profile');
        }

        $updated = $this->userModel->updateProfile(\userId(), [
            'name'  => $name,
            'phone' => $phone,
        ]);

        if ($updated) {
            $_SESSION['user_name'] = $name;
            \flash('success', 'Profile updated successfully!');
        } else {
            \flash('error', 'Failed to update profile');
        }

        return $this->redirect('/account/profile');
    }

    /**
     * Show orders page
     */
    public function orders()
    {
        $orderModel = $this->orderModel;
        $page       = (int)($_GET['page'] ?? 1);
        $result     = $orderModel->getUserOrders(\userId(), $page, 10);

        $this->view('account/orders.twig', [
            'title'  => 'My Orders - BlackRoar',
            'orders' => $result['orders'],
            'pagination' => [
                'total'        => $result['total'],
                'current_page' => $result['current_page'],
                'total_pages'  => $result['total_pages'],
            ],
        ]);
    }
}
