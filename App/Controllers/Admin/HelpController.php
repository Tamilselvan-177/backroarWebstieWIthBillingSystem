<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;

class HelpController extends BaseController
{
    public function __construct()
    {
        if (!\isAdmin()) {
            \flash('error', 'Admin access required');
            \redirect('/login');
            exit;
        }
    }

    public function index()
    {
        $this->view('admin/help/index.twig', [
            'title' => 'Admin Help & Instructions'
        ]);
    }
}

