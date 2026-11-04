<?php
/**
 * Header File
 * Flourish Techeng Limited Shop & SkillUp Platform
 */

// Define Base URL for absolute path references
if (!defined('BASE_URL')) {
    define('BASE_URL', '/1_DiplomaP/FlourishTecheng/');
}

// Include database and authentication helpers
// Dynamically determine the path to config folder
$configPath = dirname(__DIR__) . '/config/';
require_once $configPath . 'db.php';
require_once $configPath . 'auth.php';

// Calculate cart count dynamically if logged in
$cartCount = 0;
if (isCustomerLoggedIn()) {
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(ci.quantity) as count 
            FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.id
            WHERE c.customer_id = ?
        ");
        $stmt->execute([$_SESSION['customer_id']]);
        $res = $stmt->fetch();
        $cartCount = $res['count'] ?? 0;
    } catch (PDOException $e) {
        // Fallback
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flourish Techeng Limited - Shop & SkillUp</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- jQuery (loaded in head to support page inline scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    <!-- Additional page-specific styles can be added here -->
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>index.php">
            <i class="fa-solid fa-solar-panel text-primary-custom me-2 fs-3"></i>
            <div>
                <span class="d-block fw-bold lh-1 text-dark">FLOURISH</span>
                <small class="fs-6 fw-semibold text-secondary-custom" style="font-size: 0.75rem; letter-spacing: 1px;">TECHENG</small>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>shop.php">Shop</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>services.php">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>training.php">SkillUp Training</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <!-- Cart Link -->
                <a href="<?php echo BASE_URL; ?>cart.php" class="text-decoration-none text-dark position-relative me-2">
                    <i class="fa-solid fa-cart-shopping fs-5"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-pill badge-cart-custom">
                            <?php echo $cartCount; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown / Auth Links -->
                <?php if (isAdminLoggedIn()): ?>
                    <div class="dropdown">
                        <button class="btn btn-secondary-custom btn-sm dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-shield me-1"></i> Admin Panel
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-menu-item dropdown-item" href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-menu-item dropdown-item" href="<?php echo BASE_URL; ?>admin/products.php">Products</a></li>
                            <li><a class="dropdown-menu-item dropdown-item" href="<?php echo BASE_URL; ?>admin/orders.php">Orders</a></li>
                            <li><a class="dropdown-menu-item dropdown-item" href="<?php echo BASE_URL; ?>admin/courses.php">Courses</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">Logout</a></li>
                        </ul>
                    </div>
                <?php elseif (isCustomerLoggedIn() || isStudentLoggedIn()): ?>
                    <div class="dropdown">
                        <button class="btn btn-primary-custom btn-sm dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user me-1"></i> 
                            <?php 
                            if (isCustomerLoggedIn()) echo explode(' ', $_SESSION['customer_name'])[0];
                            else echo explode(' ', $_SESSION['student_name'])[0];
                            ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                            <?php if (isCustomerLoggedIn()): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>customer_dashboard.php"><i class="fa-solid fa-bag-shopping me-2 text-muted"></i>My Orders</a></li>
                            <?php endif; ?>
                            <?php if (isStudentLoggedIn()): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>student_dashboard.php"><i class="fa-solid fa-graduation-cap me-2 text-muted"></i>SkillUp Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-dark btn-sm fw-semibold">Login</a>
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary-custom btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Main content container wrapper -->
<div class="main-content-wrapper min-vh-100">
