<?php
/**
 * Customer Dashboard Portal
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

// Enforce login
requireCustomer();

$customerId = $_SESSION['customer_id'];
$db = getDB();
$errors = [];

// Handle Wishlist item deletion
if (isset($_GET['remove_wishlist'])) {
    $wishId = (int)$_GET['remove_wishlist'];
    if ($wishId > 0) {
        try {
            $delWish = $db->prepare("DELETE FROM wishlist WHERE id = ? AND customer_id = ?");
            $delWish->execute([$wishId, $customerId]);
            setFlashMsg('success', "Item removed from wishlist.");
            header("Location: customer_dashboard.php");
            exit;
        } catch (PDOException $e) {
            setFlashMsg('error', "Could not remove item.");
        }
    }
}

try {
    // 1. Fetch Orders History
    $ordersStmt = $db->prepare("
        SELECT * FROM orders 
        WHERE customer_id = ? 
        ORDER BY id DESC
    ");
    $ordersStmt->execute([$customerId]);
    $orders = $ordersStmt->fetchAll();

    // 2. Fetch Wishlist Items
    $wishStmt = $db->prepare("
        SELECT w.id as wish_id, p.id as product_id, p.name, p.price, p.discount_price, p.image, p.slug
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.customer_id = ?
        ORDER BY w.created_at DESC
    ");
    $wishStmt->execute([$customerId]);
    $wishlist = $wishStmt->fetchAll();

    // 3. Fetch Service Requests History
    $servStmt = $db->prepare("
        SELECT sr.id, sr.status, sr.details, sr.preferred_date, sr.created_at, s.name as service_name 
        FROM service_requests sr
        JOIN services s ON sr.service_id = s.id
        WHERE sr.customer_id = ?
        ORDER BY sr.id DESC
    ");
    $servStmt->execute([$customerId]);
    $serviceRequests = $servStmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = "Database error loading portal: " . $e->getMessage();
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3">
            <div class="card card-custom border-0 p-4 shadow-sm bg-light">
                <div class="text-center mb-4">
                    <div class="bg-primary-custom text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 75px; height: 75px;">
                        <i class="fa-solid fa-user fs-2"></i>
                    </div>
                    <h5 class="fw-bold mb-0"><?php echo sanitize($_SESSION['customer_name']); ?></h5>
                    <small class="text-muted"><?php echo sanitize($_SESSION['customer_email']); ?></small>
                </div>
                
                <div class="nav flex-column nav-pills" id="dashboardTabs" role="tablist">
                    <button class="nav-link active text-start fw-semibold py-2.5 mb-2" id="orders-tab" data-bs-toggle="pill" data-bs-target="#orders-pane" type="button" role="tab"><i class="fa-solid fa-bag-shopping me-2"></i>Order History</button>
                    <button class="nav-link text-start fw-semibold py-2.5 mb-2" id="wishlist-tab" data-bs-toggle="pill" data-bs-target="#wishlist-pane" type="button" role="tab"><i class="fa-solid fa-heart me-2"></i>Wishlist (<?php echo count($wishlist); ?>)</button>
                    <button class="nav-link text-start fw-semibold py-2.5 mb-2" id="services-tab" data-bs-toggle="pill" data-bs-target="#services-pane" type="button" role="tab"><i class="fa-solid fa-wrench me-2"></i>Service Bookings</button>
                    <a href="logout.php" class="nav-link text-start text-danger fw-semibold py-2.5"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
                </div>
            </div>
        </div>

        <!-- Portal Panes Column -->
        <div class="col-lg-9">
            <div class="tab-content" id="dashboardTabsContent">
                <!-- 1. Orders History Pane -->
                <div class="tab-pane fade show active" id="orders-pane" role="tabpanel" aria-labelledby="orders-tab">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h4 class="fw-bold mb-4"><i class="fa-solid fa-bag-shopping text-primary-custom me-2"></i>My Orders</h4>
                        
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-solid fa-basket-shopping fs-1 text-muted mb-3 d-block"></i>
                                <h5>No orders placed yet.</h5>
                                <a href="shop.php" class="btn btn-primary-custom mt-3">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order No</th>
                                            <th>Date</th>
                                            <th>Total</th>
                                            <th>Payment Method</th>
                                            <th>Order Status</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): 
                                            $badge = 'bg-secondary';
                                            if ($order['status'] === 'Pending') $badge = 'bg-warning text-dark';
                                            elseif (in_array($order['status'], ['Confirmed', 'Processing'])) $badge = 'bg-info';
                                            elseif ($order['status'] === 'Ready for Delivery') $badge = 'bg-primary';
                                            elseif ($order['status'] === 'Delivered') $badge = 'bg-success';
                                            elseif ($order['status'] === 'Cancelled') $badge = 'bg-danger';
                                            ?>
                                            <tr>
                                                <td><strong class="text-dark"><?php echo sanitize($order['order_number']); ?></strong></td>
                                                <td><?php echo date('d M, Y', strtotime($order['created_at'])); ?></td>
                                                <td><span class="fw-bold">$<?php echo number_format($order['net_amount'], 2); ?></span></td>
                                                <td><?php echo sanitize($order['payment_method']); ?> (<?php echo sanitize($order['payment_status']); ?>)</td>
                                                <td><span class="badge <?php echo $badge; ?>"><?php echo $order['status']; ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-light border view-order-btn" data-id="<?php echo $order['id']; ?>" data-num="<?php echo $order['order_number']; ?>">
                                                        <i class="fa-solid fa-eye me-1"></i>View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Wishlist Pane -->
                <div class="tab-pane fade" id="wishlist-pane" role="tabpanel" aria-labelledby="wishlist-tab">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h4 class="fw-bold mb-4"><i class="fa-solid fa-heart text-danger me-2"></i>My Wishlist</h4>
                        
                        <?php if (empty($wishlist)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-regular fa-heart fs-1 text-muted mb-3 d-block"></i>
                                <h5>Your wishlist is empty.</h5>
                                <a href="shop.php" class="btn btn-primary-custom mt-3">Explore Products</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($wishlist as $w): ?>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="card card-custom border p-3">
                                            <div class="text-center mb-3 bg-white" style="height: 120px; line-height: 120px;">
                                                <?php if ($w['image'] && file_exists($w['image'])): ?>
                                                    <img src="<?php echo $w['image']; ?>" class="img-fluid h-100" style="object-fit: contain;" alt="">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-box text-muted fs-1"></i>
                                                <?php endif; ?>
                                            </div>
                                            <h6 class="fw-bold text-truncate mb-2" title="<?php echo sanitize($w['name']); ?>">
                                                <a href="product.php?slug=<?php echo $w['slug']; ?>" class="text-decoration-none text-dark"><?php echo sanitize($w['name']); ?></a>
                                            </h6>
                                            <div class="mb-3">
                                                <?php if ($w['discount_price']): ?>
                                                    <span class="fw-bold text-danger">$<?php echo number_format($w['discount_price'], 2); ?></span>
                                                    <span class="text-decoration-line-through text-muted small ms-1">$<?php echo number_format($w['price'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="fw-bold text-dark">$<?php echo number_format($w['price'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex justify-content-between gap-2">
                                                <a href="product.php?slug=<?php echo $w['slug']; ?>" class="btn btn-primary-custom btn-sm flex-fill">Buy Now</a>
                                                <a href="customer_dashboard.php?remove_wishlist=<?php echo $w['wish_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this item?');">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Service Bookings Pane -->
                <div class="tab-pane fade" id="services-pane" role="tabpanel" aria-labelledby="services-tab">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h4 class="fw-bold mb-4"><i class="fa-solid fa-wrench text-primary-custom me-2"></i>My Service Requests</h4>
                        
                        <?php if (empty($serviceRequests)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-solid fa-toolbox fs-1 text-muted mb-3 d-block"></i>
                                <h5>No service bookings or quotations requested.</h5>
                                <a href="services.php" class="btn btn-primary-custom mt-3">Request a Service</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Service</th>
                                            <th>Preferred Date</th>
                                            <th>Requested Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($serviceRequests as $req): 
                                            $sBadge = 'bg-secondary';
                                            if ($req['status'] === 'Pending') $sBadge = 'bg-warning text-dark';
                                            elseif ($req['status'] === 'Contacted') $sBadge = 'bg-info';
                                            elseif ($req['status'] === 'In Progress') $sBadge = 'bg-primary';
                                            elseif ($req['status'] === 'Completed') $sBadge = 'bg-success';
                                            elseif ($req['status'] === 'Cancelled') $sBadge = 'bg-danger';
                                            ?>
                                            <tr>
                                                <td>#<?php echo $req['id']; ?></td>
                                                <td><strong class="text-dark"><?php echo sanitize($req['service_name']); ?></strong></td>
                                                <td><?php echo $req['preferred_date'] ? date('d M, Y', strtotime($req['preferred_date'])) : 'Any time'; ?></td>
                                                <td><?php echo date('d M, Y', strtotime($req['created_at'])); ?></td>
                                                <td><span class="badge <?php echo $sBadge; ?>"><?php echo $req['status']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Order Details (AJAX fallback / bootstrap static) -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="orderDetailsModalLabel">Order Details - <span id="modalOrderNumber"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalOrderContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Create a scratch endpoint in this file for details ajax requests -->
<?php 
if (isset($_GET['ajax_order_id'])) {
    // Clear buffer so we only return JSON
    ob_end_clean();
    
    $orderId = (int)$_GET['ajax_order_id'];
    try {
        // Fetch order details
        $oStmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
        $oStmt->execute([$orderId, $customerId]);
        $orderInfo = $oStmt->fetch();

        if ($orderInfo) {
            // Fetch items
            $iStmt = $db->prepare("
                SELECT oi.*, p.name as product_name, p.slug 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $iStmt->execute([$orderId]);
            $items = $iStmt->fetchAll();

            echo '<div class="mb-4">
                <div class="row">
                    <div class="col-md-6">
                        <h6><strong>Billing & Delivery Details:</strong></h6>
                        <p class="text-muted mb-2">' . nl2br(sanitize($orderInfo['delivery_address'])) . '</p>
                        <p class="text-muted mb-0"><strong>Payment Method:</strong> ' . sanitize($orderInfo['payment_method']) . '</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h6><strong>Order Information:</strong></h6>
                        <p class="text-muted mb-1"><strong>Status:</strong> <span class="badge bg-secondary">' . $orderInfo['status'] . '</span></p>
                        <p class="text-muted mb-1"><strong>Payment Status:</strong> <span class="badge bg-secondary">' . $orderInfo['payment_status'] . '</span></p>
                        <p class="text-muted mb-0"><strong>Order Date:</strong> ' . date('d M, Y H:i', strtotime($orderInfo['created_at'])) . '</p>
                    </div>
                </div>
            </div>';

            echo '<h6 class="fw-bold mb-3">Ordered Items:</h6>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th class="text-center" style="width: 120px;">Price</th>
                            <th class="text-center" style="width: 80px;">Quantity</th>
                            <th class="text-end" style="width: 120px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach ($items as $item) {
                        echo '<tr>
                            <td><a href="product.php?slug=' . $item['slug'] . '" target="_blank" class="text-decoration-none text-dark">' . sanitize($item['product_name']) . '</a></td>
                            <td class="text-center">$' . number_format($item['price'], 2) . '</td>
                            <td class="text-center">' . $item['quantity'] . '</td>
                            <td class="text-end fw-semibold">$' . number_format($item['total_price'], 2) . '</td>
                        </tr>';
                    }
                    echo '<tr>
                        <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                        <td class="text-end fw-bold">$' . number_format($orderInfo['total_amount'], 2) . '</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Tax & Shipping:</td>
                        <td class="text-end fw-bold">$' . number_format($orderInfo['discount_amount'], 2) . '</td>
                    </tr>
                    <tr class="table-success">
                        <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                        <td class="text-end fw-bold">$' . number_format($orderInfo['net_amount'], 2) . '</td>
                    </tr>
                    </tbody>
                </table>
            </div>';
        } else {
            echo '<div class="alert alert-danger">Order details could not be found.</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Database error.</div>';
    }
    exit;
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-order-btn');
    const orderNumSpan = document.getElementById('modalOrderNumber');
    const orderContentDiv = document.getElementById('modalOrderContent');
    const modalEl = document.getElementById('orderDetailsModal');
    const bsModal = new bootstrap.Modal(modalEl);

    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const num = this.getAttribute('data-num');
            
            orderNumSpan.textContent = num;
            orderContentDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;
            
            bsModal.show();

            // Fetch order details via AJAX
            fetch('customer_dashboard.php?ajax_order_id=' + id)
                .then(response => response.text())
                .then(html => {
                    orderContentDiv.innerHTML = html;
                })
                .catch(error => {
                    orderContentDiv.innerHTML = '<div class="alert alert-danger">Error loading order information.</div>';
                });
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
