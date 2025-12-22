<?php

namespace App\Controllers;

use App\Models\User;
use App\Helpers\Validator;

class AuthController extends BaseController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Show login page
     */
    public function showLogin()
    {
        // Redirect if already logged in
        if (\isLoggedIn()) {
            return $this->redirect('/account');
        }

        $this->view('auth/login.twig', [
            'title' => 'Login - BlackRoar',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        // Clear flash data
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Handle login
     */
    public function login()
    {
        $email = \clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validation
        $validator = new Validator($_POST);
        $validator->required('email', 'Email is required')
                  ->email('email', 'Invalid email format')
                  ->required('password', 'Password is required');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = ['email' => $email];
            return $this->redirect('/login');
        }

        // Verify credentials
        $user = $this->userModel->verifyCredentials($email, $password);

        if (!$user) {
            $_SESSION['errors'] = ['login' => 'Invalid email or password'];
            $_SESSION['old'] = ['email' => $email];
            return $this->redirect('/login');
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Update last login
        $this->userModel->updateLastLogin($user['id']);

        // Set remember me cookie
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
        }

        // Flash success message
        \flash('success', 'Welcome back, ' . $user['name'] . '!');

        // Redirect
        $target = ($_SESSION['role'] ?? 'customer') === 'admin' ? '/admin' : '/account';
        return $this->redirect($target);
    }

    /**
     * Show register page
     */
    public function showRegister()
    {
        // Redirect if already logged in
        if (\isLoggedIn()) {
            return $this->redirect('/account');
        }

        $this->view('auth/register.twig', [
            'title' => 'Register - BlackRoar',
            'errors' => $_SESSION['errors'] ?? [],
            'old' => $_SESSION['old'] ?? []
        ]);

        // Clear flash data
        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * Handle registration
     */
    public function register()
    {
        $name = \clean($_POST['name'] ?? '');
        $email = \clean($_POST['email'] ?? '');
        $phone = \clean($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required')
                  ->min('name', 3, 'Name must be at least 3 characters')
                  ->required('email', 'Email is required')
                  ->email('email', 'Invalid email format')
                  ->required('phone', 'Phone number is required')
                  ->phone('phone', 'Invalid phone number (must be 10 digits)')
                  ->required('password', 'Password is required')
                  ->min('password', 6, 'Password must be at least 6 characters')
                  ->required('confirm_password', 'Please confirm password')
                  ->matches('confirm_password', 'password', 'Passwords do not match');

        // Check if email already exists
        if ($this->userModel->emailExists($email)) {
            $validator->custom('email', function() { return false; }, 'Email already registered');
        }

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->getErrors();
            $_SESSION['old'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ];
            return $this->redirect('/register');
        }

        // Create user
        $userId = $this->userModel->register([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password
        ]);

        if ($userId) {
            // Auto login
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['role'] = 'customer';

            \flash('success', 'Registration successful! Welcome to BlackRoar.');
            return $this->redirect('/account');
        }

        $_SESSION['errors'] = ['register' => 'Registration failed. Please try again.'];
        return $this->redirect('/register');
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        // Clear session
        session_unset();
        session_destroy();

        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        \flash('success', 'You have been logged out successfully.');
        return $this->redirect('/');
    }

    /**
     * Show forgot password page
     */
    public function showForgotPassword()
    {
        $this->view('auth/forgot-password.twig', [
            'title' => 'Forgot Password - BlackRoar'
        ]);
    }

    /**
     * Handle forgot password (placeholder)
     */
    public function forgotPassword()
    {
        // TODO: Implement password reset logic with email
        \flash('info', 'Password reset functionality will be available soon. Please contact support.');
        return $this->redirect('/login');
    }
}
