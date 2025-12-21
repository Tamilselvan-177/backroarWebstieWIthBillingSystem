<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\Product;
use App\Models\Order;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Brand;
use App\Models\Review;
use App\Models\PosOrder;
use App\Models\ReturnModel;
use App\Models\Store;

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
        $posOrders = new PosOrder();
        $returns = new ReturnModel();
        $stores = new Store();

        $this->view('admin/index.twig', [
            'title' => 'Admin Dashboard',
            'stats' => [
                'total_products' => count($products->all()),
                'open_orders' => count($orders->all()),
                'total_review' => count($reviews->all()),
                'total_categories' => count($categories->all()),
                'total_subcategories' => count($subcategories->all()),
                'total_brands' => count($brands->all()),
                'pos_orders_today' => (int)$posOrders->countWhere("DATE(created_at) = CURDATE()"),
                'pos_revenue_today' => (float)($posOrders->query("SELECT COALESCE(SUM(grand_total),0) AS t FROM pos_orders WHERE DATE(created_at) = CURDATE()")[0]['t'] ?? 0),
                'gst_today' => (float)($posOrders->query("SELECT COALESCE(SUM(gst_total),0) AS t FROM pos_orders WHERE DATE(created_at) = CURDATE()")[0]['t'] ?? 0),
                'returns_today' => (int)$returns->countWhere("DATE(created_at) = CURDATE()"),
                'stores_active' => count($stores->getActive())
            ]
        ]);
    }

    public function analytics()
    {
        $posOrders = new PosOrder();
        $orders = new Order();
        $products = new Product();
        $returns = new ReturnModel();

        $analyticsData = $this->getAnalyticsData($posOrders, $orders, $products, $returns);

        $this->view('admin/analytics/analytics.twig', [
            'title' => 'Analytics Dashboard',
            'analytics' => $analyticsData
        ]);
    }

    private function getAnalyticsData($posOrders, $orders, $products, $returns)
    {
        // Last 7 days revenue trend
        $revenueTrend = $posOrders->query("
            SELECT 
                DATE(created_at) as date,
                DAYNAME(created_at) as day_name,
                COUNT(*) as orders,
                SUM(grand_total) as revenue,
                SUM(gst_total) as gst
            FROM pos_orders 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), DAYNAME(created_at)
            ORDER BY date ASC
        ");

        // Payment method distribution (Today)
        $paymentMethods = $posOrders->query("
            SELECT 
                CASE 
                    WHEN cash_amount > 0 AND card_amount = 0 AND upi_amount = 0 THEN 'Cash'
                    WHEN card_amount > 0 AND cash_amount = 0 AND upi_amount = 0 THEN 'Card'
                    WHEN upi_amount > 0 AND cash_amount = 0 AND card_amount = 0 THEN 'UPI'
                    ELSE 'Mixed'
                END as method,
                COUNT(*) as count,
                SUM(grand_total) as total
            FROM pos_orders 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY method
        ");

        // Top selling products (Last 7 days)
        $topProducts = $posOrders->query("
            SELECT 
                p.name,
                p.id,
                p.price,
                SUM(poi.quantity) as total_sold,
                SUM(poi.line_total) as revenue
            FROM pos_order_items poi
            JOIN products p ON poi.product_id = p.id
            JOIN pos_orders po ON poi.pos_order_id = po.id
            WHERE po.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY p.id, p.name, p.price
            ORDER BY total_sold DESC
            LIMIT 10
        ");

        // Store performance (Today)
        $storePerformance = $posOrders->query("
            SELECT 
                s.name as store_name,
                s.code as store_code,
                COUNT(po.id) as orders,
                SUM(po.grand_total) as revenue,
                AVG(po.grand_total) as avg_order
            FROM pos_orders po
            JOIN stores s ON po.store_id = s.id
            WHERE DATE(po.created_at) = CURDATE()
            GROUP BY s.id, s.name, s.code
            ORDER BY revenue DESC
        ");

        // Hourly sales pattern (Today)
        $hourlySales = $posOrders->query("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as orders,
                SUM(grand_total) as revenue
            FROM pos_orders
            WHERE DATE(created_at) = CURDATE()
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ");

        // Returns analysis (Last 7 days)
        $returnsData = $returns->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_returns,
                SUM(quantity) as items_returned,
                SUM(refund_amount) as refund_total
            FROM returns
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");

        // Return methods breakdown
        $returnMethods = $returns->query("
            SELECT 
                refund_method,
                COUNT(*) as count,
                SUM(refund_amount) as total
            FROM returns
            WHERE DATE(created_at) = CURDATE()
            GROUP BY refund_method
        ");

        // Average transaction value
        $avgTransaction = $posOrders->query("
            SELECT 
                AVG(grand_total) as avg_order_value,
                MAX(grand_total) as max_order_value,
                MIN(grand_total) as min_order_value,
                COUNT(*) as total_orders
            FROM pos_orders
            WHERE DATE(created_at) = CURDATE()
        ")[0] ?? ['avg_order_value' => 0, 'max_order_value' => 0, 'min_order_value' => 0, 'total_orders' => 0];

        // Low stock alerts
        $lowStock = $products->query("
            SELECT 
                p.name,
                p.id,
                p.stock_quantity,
                COALESCE(s.name, 'Central Stock') as store_name
            FROM products p
            LEFT JOIN stores s ON p.store_id = s.id
            WHERE p.stock_quantity < 10 
            AND p.no_store_stock = 0
            AND p.is_active = 1
            ORDER BY p.stock_quantity ASC
            LIMIT 10
        ");

        // Calculate growth percentages
        $yesterdayRevenue = $posOrders->query("
            SELECT COALESCE(SUM(grand_total), 0) as revenue
            FROM pos_orders
            WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ")[0]['revenue'] ?? 0;

        $todayRevenue = $posOrders->query("
            SELECT COALESCE(SUM(grand_total), 0) as revenue
            FROM pos_orders
            WHERE DATE(created_at) = CURDATE()
        ")[0]['revenue'] ?? 0;

        $todayOrders = $posOrders->query("
            SELECT COUNT(*) as count
            FROM pos_orders
            WHERE DATE(created_at) = CURDATE()
        ")[0]['count'] ?? 0;

        $yesterdayOrders = $posOrders->query("
            SELECT COUNT(*) as count
            FROM pos_orders
            WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ")[0]['count'] ?? 0;

        $revenueGrowth = $yesterdayRevenue > 0 
            ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 
            : 0;

        $ordersGrowth = $yesterdayOrders > 0 
            ? (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100 
            : 0;

        // Peak hour
        $peakHour = $posOrders->query("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as orders
            FROM pos_orders
            WHERE DATE(created_at) = CURDATE()
            GROUP BY HOUR(created_at)
            ORDER BY orders DESC
            LIMIT 1
        ")[0] ?? ['hour' => 0, 'orders' => 0];

        return [
            'revenue_trend' => $revenueTrend,
            'payment_methods' => $paymentMethods,
            'top_products' => $topProducts,
            'store_performance' => $storePerformance,
            'hourly_sales' => $hourlySales,
            'returns_data' => $returnsData,
            'return_methods' => $returnMethods,
            'avg_transaction' => $avgTransaction,
            'low_stock' => $lowStock,
            'revenue_growth' => round($revenueGrowth, 1),
            'orders_growth' => round($ordersGrowth, 1),
            'yesterday_revenue' => $yesterdayRevenue,
            'today_revenue' => $todayRevenue,
            'today_orders' => $todayOrders,
            'yesterday_orders' => $yesterdayOrders,
            'peak_hour' => $peakHour
        ];
    }
}