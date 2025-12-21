# Backroar - E-commerce & POS Billing System

A comprehensive E-commerce solution with an integrated Point of Sale (POS) system, designed for mobile accessories retail (specifically back cases). This project features a custom PHP MVC framework, robust inventory management, and a seamless checkout experience for both online and in-store customers.

## 🚀 Features

### 🛒 E-commerce Frontend
*   **Product Browsing:** Filter by Brand, Model, Category, and Subcategory.
*   **User Accounts:** Registration, Login, Profile Management, Order History.
*   **Shopping Cart & Checkout:** Seamless add-to-cart, address management, and secure checkout flow.
*   **Wishlist:** Save products for later.
*   **Reviews:** User-submitted product reviews and ratings.
*   **Responsive Design:** Built with Tailwind CSS for mobile and desktop compatibility.

### 🏪 Point of Sale (POS) System
*   **Fast Billing:** Quick product lookup via barcode, SKU, or search.
*   **Cart Management:** Hold/Recall bills, update quantities, apply discounts.
*   **Multiple Payment Methods:** Cash, Card, UPI, and split payments.
*   **Admin Integration:** Load online orders into POS for processing and billing.
*   **GST Reporting:** Daily/Monthly GST and sales reports.
*   **Cash Drawer Management:** Track opening/closing balances and cash movements.
*   **Returns Management:** Process returns and refunds directly from the POS.

### 🛠 Admin Panel
*   **Dashboard:** Analytics on sales, orders, and stock.
*   **Product Management:** CRUD operations for Products, Brands, Models, Categories.
*   **Inventory Control:** Multi-store stock management, stock transfers between stores.
*   **Order Management:** View, update status, and process online orders.
*   **Staff Management:** Manage staff roles, counter assignments, and permissions.
*   **Coupon System:** Create and manage discount coupons.

## 💻 Tech Stack

*   **Language:** PHP 8.0+
*   **Architecture:** Custom MVC (Model-View-Controller) Framework
*   **Template Engine:** Twig
*   **Database:** MySQL
*   **Frontend:** HTML5, JavaScript, Tailwind CSS
*   **Dependency Management:** Composer

## 📂 Project Structure

```
├── app/
│   ├── config/         # Configuration files (DB, App settings)
│   ├── controllers/    # Application logic (Admin, POS, Frontend)
│   ├── core/           # Framework core (Router, Database, View)
│   ├── models/         # Database models
│   ├── routes/         # Route definitions (web.php)
│   └── views/          # Twig templates
├── public/             # Public entry point (index.php) and assets
├── database/           # SQL schema and migration files
├── vendor/             # Composer dependencies
└── composer.json       # Project dependencies
```

## ⚙️ Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/yourusername/backroar.git
    cd backroar
    ```

2.  **Install Dependencies:**
    ```bash
    composer install
    ```

3.  **Database Setup:**
    *   Create a MySQL database.
    *   Import the SQL files from the `database/` directory (start with `schema.sql` if available, or the latest full dump).
    *   Update database credentials in `app/core/Env.php` or `app/config/database.php`.

4.  **Configure Environment:**
    *   Set up your web server (Apache/Nginx) to point to the `public/` directory.
    *   Ensure `.htaccess` is enabled for URL rewriting.

5.  **Run:**
    *   Access the website at your configured local URL (e.g., `http://localhost/backroar`).

## 🔑 Key Functionalities

*   **Stock Transfer:** Admins can transfer stock between different store locations with a cart-based interface.
*   **Online-to-POS:** Admin can pull an online order into the POS interface to generate a physical bill and print labels.
*   **Dynamic Pricing:** Support for discounts, GST calculations, and service charges.

## 📄 License

This project is proprietary software.
