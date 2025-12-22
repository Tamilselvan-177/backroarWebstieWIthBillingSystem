<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\AccountController;
use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\OrderController;
use App\Controllers\Admin\ImageController as AdminImageController;
use App\Controllers\Admin\ProductController as AdminProductController;
use App\Controllers\Admin\OrderController as AdminOrderController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Middleware\AdminMiddleware;
use App\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Controllers\Admin\BrandController as AdminBrandController;
use App\Controllers\Admin\ModelController as AdminModelController;
use App\Controllers\Admin\ReviewController as AdminReviewController;
use App\Controllers\WishlistController;
use App\Controllers\Admin\SubcategoryController;
use App\Controllers\Admin\AdminCouponController;
use App\Controllers\Admin\PosController as AdminPosController;
use App\Controllers\Admin\StockController as AdminStockController;
use App\Controllers\Admin\ReturnsController as AdminReturnsController;
use App\Controllers\Admin\ProductStockController as AdminProductStockController;
use App\Controllers\Admin\CounterController as AdminCounterController;
use App\Controllers\Admin\StaffController as AdminStaffController;
use App\Controllers\Admin\StockTransferController as AdminStockTransferController;
use App\Controllers\Admin\StoreController as AdminStoreController;

// ============================================================
// HOME & MAIN ROUTES
// ============================================================
$router->get('/', HomeController::class, 'index');
$router->get('/home', HomeController::class, 'index');

// ============================================================
// AUTHENTICATION ROUTES
// ============================================================
$router->get('/login', AuthController::class, 'showLogin');
$router->post('/login', AuthController::class, 'login');
$router->get('/register', AuthController::class, 'showRegister');
$router->post('/register', AuthController::class, 'register');
$router->get('/logout', AuthController::class, 'logout');
$router->get('/forgot-password', AuthController::class, 'showForgotPassword');
$router->post('/forgot-password', AuthController::class, 'forgotPassword');

// ============================================================
// ACCOUNT ROUTES (Requires Login)
// ============================================================
$router->get('/account', AccountController::class, 'dashboard');
$router->get('/account/profile', AccountController::class, 'profile');
$router->post('/account/profile', AccountController::class, 'updateProfile');
$router->get('/account/orders', AccountController::class, 'orders');

// ============================================================
// CATEGORY ROUTES
// ============================================================
$router->get('/categories', CategoryController::class, 'index');
$router->get('/category/{slug}', CategoryController::class, 'show');

// ============================================================
// PRODUCT ROUTES
// ============================================================
$router->get('/products', ProductController::class, 'index');
$router->get('/product/{slug}', ProductController::class, 'detail');
$router->get('/search', ProductController::class, 'search');

// ============================================================
// CART ROUTES
// ============================================================
$router->get('/cart', CartController::class, 'index');
$router->post('/cart/add', CartController::class, 'add');
$router->post('/cart/update', CartController::class, 'update');
$router->post('/cart/remove', CartController::class, 'remove');
$router->post('/cart/clear', CartController::class, 'clear');
$router->get('/cart/count', CartController::class, 'count');
$router->post('/cart/buy-now', CartController::class, 'buyNow');

// ============================================================
// CHECKOUT ROUTES
// ============================================================
$router->get('/checkout', CheckoutController::class, 'index');
$router->post('/checkout/address', CheckoutController::class, 'addAddress');
$router->post('/checkout/place-order', CheckoutController::class, 'placeOrder');
$router->get('/checkout/success/{orderNumber}', CheckoutController::class, 'success');

// ============================================================
// ORDER ROUTES
// ============================================================
$router->get('/orders', OrderController::class, 'history');
$router->get('/order/{orderNumber}', OrderController::class, 'detail');
$router->post('/order/cancel', OrderController::class, 'cancel');

// ============================================================
// WISHLIST ROUTES
// ============================================================
$router->post('/wishlist/add', WishlistController::class, 'add');
$router->post('/wishlist/remove', WishlistController::class, 'remove');
$router->get('/wishlist', WishlistController::class, 'index');
$router->post('/wishlist/remove-form', WishlistController::class, 'removeForm');
$router->post('/reviews', \App\Controllers\ReviewController::class, 'store');

// ============================================================
// ADMIN ROUTES - APPLY MIDDLEWARE FIRST!
// ============================================================
$router->guardPrefix('/admin', AdminMiddleware::class);

// ADMIN DASHBOARD
$router->get('/admin', AdminDashboardController::class, 'index');

// ADMIN POS
$router->get('/admin/pos/login', AdminPosController::class, 'loginForm');
$router->get('/admin/pos/counters', AdminPosController::class, 'countersAjax');
$router->post('/admin/pos/start', AdminPosController::class, 'start');
$router->get('/admin/pos/billing', AdminPosController::class, 'billing');
$router->post('/admin/pos/hold', AdminPosController::class, 'hold');
$router->get('/admin/pos/recall', AdminPosController::class, 'recall');
$router->post('/admin/pos/add-item', AdminPosController::class, 'addItem');
$router->post('/admin/pos/update-qty', AdminPosController::class, 'updateQty');
$router->post('/admin/pos/update-discount', AdminPosController::class, 'updateDiscount');
$router->post('/admin/pos/update-service-charge', AdminPosController::class, 'updateServiceCharge');
$router->post('/admin/pos/remove-item', AdminPosController::class, 'removeItem');
$router->post('/admin/pos/checkout', AdminPosController::class, 'checkout');
$router->post('/admin/pos/clear', AdminPosController::class, 'clear');
$router->get('/admin/pos/search-products', AdminPosController::class, 'searchProducts');
$router->get('/admin/pos/orders', AdminPosController::class, 'orders');
$router->get('/admin/pos/orders/{id}/show', AdminPosController::class, 'orderShow');
$router->get('/admin/pos/gst-report', AdminPosController::class, 'gstReport');
$router->get('/admin/pos/backfill-stock', AdminPosController::class, 'backfillStock');
$router->get('/admin/pos/populate-products', AdminPosController::class, 'populateProductFields');

// ADMIN COUNTERS
$router->get('/admin/counters', AdminCounterController::class, 'index');
$router->get('/admin/counters/create', AdminCounterController::class, 'create');
$router->post('/admin/counters/store', AdminCounterController::class, 'store');
$router->get('/admin/counters/{id}/edit', AdminCounterController::class, 'edit');
$router->post('/admin/counters/{id}/update', AdminCounterController::class, 'update');
$router->post('/admin/counters/{id}/toggle', AdminCounterController::class, 'toggleActive');

// ADMIN STAFF
$router->get('/admin/staff', AdminStaffController::class, 'index');
$router->get('/admin/staff/create', AdminStaffController::class, 'create');
$router->post('/admin/staff/store', AdminStaffController::class, 'store');
$router->get('/admin/staff/{id}/edit', AdminStaffController::class, 'edit');
$router->post('/admin/staff/{id}/update', AdminStaffController::class, 'update');

// ADMIN STOCK TRANSFER
$router->get('/admin/stock/transfer', AdminStockTransferController::class, 'index');
$router->post('/admin/stock/transfer/add-to-cart', AdminStockTransferController::class, 'addToCart');
$router->post('/admin/stock/transfer/remove-from-cart', AdminStockTransferController::class, 'removeFromCart');
$router->post('/admin/stock/transfer/clear-cart', AdminStockTransferController::class, 'clearCart');
$router->post('/admin/stock/transfer/bulk-transfer', AdminStockTransferController::class, 'bulkTransfer');
$router->post('/admin/stock/transfer/move', AdminStockTransferController::class, 'move');
$router->get('/admin/stock/transfer/history', AdminStockTransferController::class, 'history');
$router->get('/admin/stock/transfer/view', AdminStockTransferController::class, 'viewTransfer');

// ADMIN RETURNS
$router->get('/admin/returns', AdminReturnsController::class, 'create');
$router->post('/admin/returns', AdminReturnsController::class, 'store');
$router->get('/admin/returns/search', AdminReturnsController::class,'searchBill');

// ADMIN PRODUCTS - COMPLETE
$router->get('/admin/products', AdminProductController::class, 'index');
$router->get('/admin/products/create', AdminProductController::class, 'create');
$router->post('/admin/products', AdminProductController::class, 'store');
$router->get('/admin/products/{id}/edit', AdminProductController::class, 'edit');
$router->post('/admin/products/{id}', AdminProductController::class, 'update');
$router->post('/admin/products/{id}/delete', AdminProductController::class, 'delete');
$router->post('/admin/products/{id}/toggleActive', AdminProductController::class, 'toggleActive');
$router->post('/admin/products/{id}/toggleFeatured', AdminProductController::class, 'toggleFeatured');

// ADMIN PRODUCT STORE STOCK
$router->get('/admin/products/{id}/stock', AdminProductStockController::class, 'edit');
$router->post('/admin/products/{id}/stock', AdminProductStockController::class, 'update');

// **FIXED AJAX ROUTES - MATCH YOUR JAVASCRIPT**
$router->get('/admin/products/ajaxSubcategories/{categoryId}', AdminProductController::class, 'ajaxSubcategories');
$router->get('/admin/products/ajaxModels/{brandId}', AdminProductController::class, 'ajaxModels');

// ADMIN PRODUCT IMAGES
$router->get('/admin/products/{id}/images', AdminImageController::class, 'index');
$router->post('/admin/products/{id}/images/upload', AdminImageController::class, 'upload');
$router->post('/admin/images/{imageId}/delete', AdminImageController::class, 'delete');
$router->post('/admin/products/{productId}/images/{imageId}/primary', AdminImageController::class, 'primary');
$router->post('/admin/products/{id}/images/reorder', AdminImageController::class, 'reorder');

// ADMIN ORDERS
$router->get('/admin/orders', AdminOrderController::class, 'index');
$router->post('/admin/orders/{id}/status', AdminOrderController::class, 'updateStatus');
$router->get('/admin/orders/{id}/show', AdminOrderController::class, 'show');
$router->get('/admin/orders/{id}/billing', AdminOrderController::class, 'billing');

// ADMIN CATEGORIES
$router->get('/admin/categories', AdminCategoryController::class, 'index');
$router->get('/admin/categories/create', AdminCategoryController::class, 'create');
$router->post('/admin/categories', AdminCategoryController::class, 'store');
$router->get('/admin/categories/{id}/edit', AdminCategoryController::class, 'edit');
$router->post('/admin/categories/{id}', AdminCategoryController::class, 'update');
$router->post('/admin/categories/{id}/delete', AdminCategoryController::class, 'delete');

// ADMIN BRANDS
$router->get('/admin/brands', AdminBrandController::class, 'index');
$router->get('/admin/brands/create', AdminBrandController::class, 'create');
$router->post('/admin/brands', AdminBrandController::class, 'store');
$router->get('/admin/brands/{id}/edit', AdminBrandController::class, 'edit');
$router->post('/admin/brands/{id}', AdminBrandController::class, 'update');
$router->post('/admin/brands/{id}/delete', AdminBrandController::class, 'delete');

// ADMIN MODELS
$router->get('/admin/models', AdminModelController::class, 'index');
$router->get('/admin/models/create', AdminModelController::class, 'create');
$router->post('/admin/models', AdminModelController::class, 'store');
$router->get('/admin/models/{id}/edit', AdminModelController::class, 'edit');
$router->post('/admin/models/{id}', AdminModelController::class, 'update');
$router->post('/admin/models/{id}/delete', AdminModelController::class, 'delete');

// ADMIN REVIEWS
$router->get('/admin/reviews', AdminReviewController::class, 'index');
$router->post('/admin/reviews/{id}/status', AdminReviewController::class, 'updateStatus');
$router->post('/admin/reviews/{id}/delete', AdminReviewController::class, 'delete');
$router->post('/admin/reviews/bulk-action', AdminReviewController::class, 'bulkAction');

// Test Route
$router->get('/test', HomeController::class, 'test');

// ADMIN SUBCATEGORIES
$router->get('/admin/subcategories', SubcategoryController::class, 'index');
$router->get('/admin/subcategories/create', SubcategoryController::class, 'create');
$router->post('/admin/subcategories', SubcategoryController::class, 'store');
$router->get('/admin/subcategories/{id}/edit', SubcategoryController::class, 'edit');
$router->post('/admin/subcategories/{id}', SubcategoryController::class, 'update');
$router->post('/admin/subcategories/{id}/delete', SubcategoryController::class, 'delete');
$router->post('/admin/subcategories/{id}/toggleActive', SubcategoryController::class, 'toggleActive');

$router->post('/checkout/apply-coupon', CheckoutController::class, 'applyCoupon');

// coupon management routes
$router->get('/admin/coupons', AdminCouponController::class, 'index');
$router->get('/admin/coupons/create', AdminCouponController::class, 'create');
$router->post('/admin/coupons/store', AdminCouponController::class, 'store');
$router->get('/admin/coupons/{id}/edit', AdminCouponController::class, 'edit');
$router->post('/admin/coupons/{id}/update', AdminCouponController::class, 'update');
$router->post('/admin/coupons/{id}/delete', AdminCouponController::class, 'delete');
$router->post('/admin/coupons/{id}/toggle', AdminCouponController::class, 'toggleStatus');

// ADMIN STORES
$router->get('/admin/stores', AdminStoreController::class, 'index');
$router->get('/admin/stores/create', AdminStoreController::class, 'create');
$router->post('/admin/stores/store', AdminStoreController::class, 'store');
$router->get('/admin/stores/{id}/edit', AdminStoreController::class, 'edit');
$router->post('/admin/stores/{id}/update', AdminStoreController::class, 'update');
$router->post('/admin/stores/{id}/delete', AdminStoreController::class, 'delete');
$router->post('/admin/stores/{id}/toggle', AdminStoreController::class, 'toggleActive');

// analysis
$router->get('/admin/analytics', AdminDashboardController::class, 'analytics');
