<?php
/**
 * Admin Panel Header
 * Flourish Techeng Limited
 */

// Define Base URL for absolute path references
if (!defined('BASE_URL')) {
    define('BASE_URL', '/1_DiplomaP/FlourishTecheng/');
}

// Load config and authentication
$configPath = dirname(dirname(__DIR__)) . '/config/';
require_once $configPath . 'db.php';
require_once $configPath . 'auth.php';

// Enforce admin login
requireAdmin();

$admin = getLoggedInAdmin();

// Set active page for sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Flourish Techeng</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap 5 CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- jQuery (loaded in head to support page inline scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    
    <style>
        /* Admin-specific Sidebar layout */
        body {
            background-color: #f4f6f9;
        }
        .admin-sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #111827; /* Rich black/grey */
            color: #ffffff;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }
        .admin-main {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: #9ca3af;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar-link i {
            width: 25px;
            font-size: 1.1rem;
        }
        .sidebar-link:hover, .sidebar-link.active {
            color: #ffffff;
            background-color: rgba(16, 185, 129, 0.15); /* Green hover */
            border-left: 4px solid #10B981;
        }
        .admin-topbar {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        @media (max-width: 991.98px) {
            .admin-sidebar {
                left: -260px;
            }
            .admin-sidebar.active {
                left: 0;
            }
            .admin-main {
                margin-left: 0;
            }
            .admin-main.active {
                margin-left: 260px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="admin-sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?php echo BASE_URL; ?>index.php" class="text-white text-decoration-none d-flex align-items-center">
            <i class="fa-solid fa-solar-panel text-primary-custom me-2 fs-4"></i>
            <span class="fw-bold fs-5">FT Admin Panel</span>
        </a>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="products.php" class="sidebar-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> Products
            </a>
        </li>
        <li>
            <a href="orders.php" class="sidebar-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-cart-shopping"></i> Orders
            </a>
        </li>
        <li>
            <a href="service_requests.php" class="sidebar-link <?php echo $current_page === 'service_requests.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-wrench"></i> Service Bookings
            </a>
        </li>
        <li>
            <a href="courses.php" class="sidebar-link <?php echo $current_page === 'courses.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-graduation-cap"></i> Courses (SkillUp)
            </a>
        </li>
        <li>
            <hr class="border-secondary mx-3">
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-link">
                <i class="fa-solid fa-globe"></i> View Website
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-link text-danger">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>
    </ul>
</div>

<!-- Main Content Area Wrapper -->
<div class="admin-main" id="main-content">
    
    <!-- Topbar -->
    <div class="admin-topbar d-flex justify-content-between align-items-center">
        <button class="btn btn-light d-lg-none" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="d-none d-md-block">
            <h5 class="mb-0 fw-semibold">Flourish Techeng Control Panel</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted"><i class="fa-regular fa-calendar me-1"></i> <?php echo date('d M, Y'); ?></span>
            <div class="vr"></div>
            <span class="fw-semibold"><i class="fa-solid fa-user-circle me-1"></i> <?php echo sanitize($admin['name']); ?></span>
        </div>
    </div>
