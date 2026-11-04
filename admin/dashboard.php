<?php
/**
 * Admin Dashboard Page
 * Flourish Techeng Limited
 */

require_once 'includes/admin_header.php';

try {
    $db = getDB();

    // 1. Total Products
    $totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

    // 2. Total Customers
    $totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();

    // 3. Total Orders
    $totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // 4. Total Revenue
    $totalRevenue = $db->query("SELECT SUM(net_amount) FROM orders WHERE status != 'Cancelled'")->fetchColumn();
    $totalRevenue = $totalRevenue ? (float)$totalRevenue : 0.00;

    // 5. Total Students
    $totalStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();

    // 6. Pending Service Requests
    $pendingServices = $db->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Pending'")->fetchColumn();

    // 7. Low Stock Products Count
    $lowStockCount = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5")->fetchColumn();

    // --- REVENUE CHART DATA (Monthly for current year) ---
    $revenueStmt = $db->query("
        SELECT MONTH(created_at) as month_num, MONTHNAME(created_at) as month_name, SUM(net_amount) as total
        FROM orders
        WHERE status != 'Cancelled' AND YEAR(created_at) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(created_at)
        ORDER BY MONTH(created_at)
    ");
    $revenueData = $revenueStmt->fetchAll();
    
    // Fill in month labels and amounts
    $months = [];
    $revenues = [];
    // Pre-populate with current and past months of current year
    for ($i = 1; $i <= (int)date('m'); $i++) {
        $dateObj = DateTime::createFromFormat('!m', $i);
        $months[$i] = $dateObj->format('F');
        $revenues[$i] = 0;
    }
    foreach ($revenueData as $row) {
        $revenues[(int)$row['month_num']] = (float)$row['total'];
    }
    $monthsJson = json_encode(array_values($months));
    $revenuesJson = json_encode(array_values($revenues));

    // --- COURSE ENROLLMENT CHART DATA (Grouped by Category) ---
    $enrollmentStmt = $db->query("
        SELECT cc.name as category_name, COUNT(e.id) as enrollments_count
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN course_categories cc ON c.category_id = cc.id
        GROUP BY cc.id
    ");
    $enrollmentData = $enrollmentStmt->fetchAll();
    $courseCategories = [];
    $enrollmentCounts = [];
    foreach ($enrollmentData as $row) {
        $courseCategories[] = $row['category_name'];
        $enrollmentCounts[] = (int)$row['enrollments_count'];
    }
    $categoriesJson = json_encode($courseCategories);
    $countsJson = json_encode($enrollmentCounts);

    // --- RECENT ORDERS ---
    $recentOrders = $db->query("
        SELECT o.*, c.full_name as customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // --- LOW STOCK PRODUCTS LIST ---
    $lowStockProducts = $db->query("
        SELECT p.*, c.name as category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.stock_quantity <= 5
        ORDER BY p.stock_quantity ASC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    die("Error loading dashboard data: " . $e->getMessage());
}
?>

<div class="row g-4 mb-4">
    <!-- Total Revenue -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card revenue h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px;">Total Revenue</h6>
                    <h3 class="mb-0 fw-bold">$<?php echo number_format($totalRevenue, 2); ?></h3>
                </div>
                <div class="bg-primary-light text-primary-custom p-3 rounded-circle">
                    <i class="fa-solid fa-wallet fs-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-success small fw-semibold"><i class="fa-solid fa-arrow-trend-up me-1"></i>Live</span>
                <span class="text-muted small ms-1">Store net earnings</span>
            </div>
        </div>
    </div>

    <!-- Total Orders -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card orders h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px;">Total Orders</h6>
                    <h3 class="mb-0 fw-bold"><?php echo $totalOrders; ?></h3>
                </div>
                <div class="bg-warning-light text-accent-custom p-3 rounded-circle" style="background-color: #FEF3C7;">
                    <i class="fa-solid fa-shopping-cart fs-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-warning small fw-semibold"><i class="fa-regular fa-clock me-1"></i>All time</span>
                <span class="text-muted small ms-1">Customer checkouts</span>
            </div>
        </div>
    </div>

    <!-- Total Students -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card students h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px;">Total Students</h6>
                    <h3 class="mb-0 fw-bold"><?php echo $totalStudents; ?></h3>
                </div>
                <div class="bg-indigo-light text-purple p-3 rounded-circle" style="background-color: #EDE9FE; color: #8B5CF6;">
                    <i class="fa-solid fa-graduation-cap fs-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-purple small fw-semibold" style="color: #8B5CF6;"><i class="fa-solid fa-users me-1"></i>SkillUp</span>
                <span class="text-muted small ms-1">Enrolled trainees</span>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-card h-100 <?php echo $lowStockCount > 0 ? 'border-danger' : 'border-success'; ?>" style="border-left-width: 5px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.8rem; letter-spacing: 0.5px;">Low Stock Alerts</h6>
                    <h3 class="mb-0 fw-bold <?php echo $lowStockCount > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $lowStockCount; ?></h3>
                </div>
                <div class="p-3 rounded-circle <?php echo $lowStockCount > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?>">
                    <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <?php if ($lowStockCount > 0): ?>
                    <span class="text-danger small fw-semibold"><i class="fa-solid fa-arrow-down-wide-short me-1"></i>Needs Restock</span>
                <?php else: ?>
                    <span class="text-success small fw-semibold"><i class="fa-solid fa-check-double me-1"></i>Stock Healthy</span>
                <?php endif; ?>
                <span class="text-muted small ms-1">(Qty <= 5)</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Service requests count & products count sub-stats -->
    <div class="col-md-6">
        <div class="card card-custom p-4 h-100">
            <h5 class="card-title fw-bold mb-3"><i class="fa-solid fa-chart-line text-primary-custom me-2"></i>Sales & Revenue Trend</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card card-custom p-4 h-100">
            <h5 class="card-title fw-bold mb-3"><i class="fa-solid fa-chart-pie text-primary-custom me-2"></i>SkillUp Course Category Enrolments</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="enrollmentChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders table -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title fw-bold mb-0"><i class="fa-solid fa-list-check text-primary-custom me-2"></i>Recent Customer Orders</h5>
                <a href="orders.php" class="btn btn-outline-success btn-sm fw-semibold">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Order No</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong class="text-secondary"><?php echo sanitize($order['order_number']); ?></strong></td>
                                    <td><?php echo sanitize($order['customer_name']); ?></td>
                                    <td>$<?php echo number_format($order['net_amount'], 2); ?></td>
                                    <td>
                                        <?php
                                        $badge = 'bg-secondary';
                                        if ($order['status'] === 'Pending') $badge = 'bg-warning text-dark';
                                        elseif (in_array($order['status'], ['Confirmed', 'Processing'])) $badge = 'bg-info text-white';
                                        elseif ($order['status'] === 'Ready for Delivery') $badge = 'bg-primary text-white';
                                        elseif ($order['status'] === 'Delivered') $badge = 'bg-success text-white';
                                        elseif ($order['status'] === 'Cancelled') $badge = 'bg-danger text-white';
                                        ?>
                                        <span class="badge <?php echo $badge; ?> px-2 py-1.5"><?php echo $order['status']; ?></span>
                                    </td>
                                    <td><?php echo date('d M, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="orders.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light border" title="View details">
                                            <i class="fa-regular fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low stock list alerts -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 h-100">
            <h5 class="card-title fw-bold mb-3 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Low Stock Alert (Qty <= 5)</h5>
            <div class="list-group list-group-flush">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-regular fa-circle-check fs-2 text-success mb-2 d-block"></i>
                        All products are sufficiently stocked.
                    </div>
                <?php else: ?>
                    <?php foreach ($lowStockProducts as $prod): ?>
                        <div class="list-group-item px-0 py-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <?php if ($prod['image'] && file_exists('../' . $prod['image'])): ?>
                                    <img src="../<?php echo $prod['image']; ?>" alt="" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                        <i class="fa-solid fa-box text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="ms-3">
                                    <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 160px;" title="<?php echo sanitize($prod['name']); ?>">
                                        <?php echo sanitize($prod['name']); ?>
                                    </h6>
                                    <small class="text-muted"><?php echo sanitize($prod['category_name']); ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-danger rounded-pill px-2.5 py-1.5"><?php echo $prod['stock_quantity']; ?> left</span>
                                <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="d-block text-primary-custom small mt-1 text-decoration-none fw-semibold">Update Stock</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Inline Javascript charts configs -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Revenue Chart
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    const revenueLabels = <?php echo $monthsJson; ?>;
    const revenueValues = <?php echo $revenuesJson; ?>;
    
    new Chart(ctxRevenue, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Monthly Revenue ($)',
                data: revenueValues,
                borderColor: '#10B981', // Emerald green
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // 2. Enrollment Chart
    const ctxEnroll = document.getElementById('enrollmentChart').getContext('2d');
    const enrollLabels = <?php echo $categoriesJson; ?>;
    const enrollCounts = <?php echo $countsJson; ?>;

    if(enrollLabels.length === 0) {
        // Fallback for no data
        enrollLabels.push("No Enrollments");
        enrollCounts.push(0);
    }
    
    new Chart(ctxEnroll, {
        type: 'doughnut',
        data: {
            labels: enrollLabels,
            datasets: [{
                data: enrollCounts,
                backgroundColor: [
                    '#10B981', // Green
                    '#F59E0B', // Orange
                    '#3B82F6', // Blue
                    '#8B5CF6', // Purple
                    '#EC4899'  // Pink
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: {
                            family: 'Outfit'
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
