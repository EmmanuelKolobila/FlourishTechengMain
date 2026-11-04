<?php
/**
 * Order Management Panel
 * Flourish Techeng Limited
 */

require_once 'includes/admin_header.php';

$action = sanitize($_GET['action'] ?? '');
$db = getDB();
$errors = [];

// -----------------------------------------------------------------------------
// POST HANDLER: UPDATE ORDER & PAYMENT STATUS
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMsg('error', "Security token expired. Try again.");
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $order_status = sanitize($_POST['status'] ?? '');
        $payment_status = sanitize($_POST['payment_status'] ?? '');

        if ($orderId > 0) {
            try {
                $db->beginTransaction();

                // Update Order Status
                $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
                $stmt->execute([$order_status, $payment_status, $orderId]);

                // Update Payment Status in payments table as well
                $payStmt = $db->prepare("UPDATE payments SET status = ? WHERE order_id = ?");
                $payStatusVal = 'Pending';
                if ($payment_status === 'Paid') $payStatusVal = 'Success';
                elseif ($payment_status === 'Failed') $payStatusVal = 'Failed';
                $payStmt->execute([$payStatusVal, $orderId]);

                // Create alert notification for the customer
                // Fetch customer ID
                $cId = $db->query("SELECT customer_id FROM orders WHERE id = $orderId")->fetchColumn();
                if ($cId) {
                    $notifStmt = $db->prepare("
                        INSERT INTO notifications (user_id, user_type, title, message) 
                        VALUES (?, 'customer', ?, ?)
                    ");
                    $notifStmt->execute([
                        $cId, 
                        "Order Update: " . $order_status, 
                        "Your order status has been updated to '" . $order_status . "'."
                    ]);
                }

                $db->commit();
                setFlashMsg('success', "Order status successfully updated.");
            } catch (PDOException $e) {
                $db->rollBack();
                setFlashMsg('error', "Failed to update order: " . $e->getMessage());
            }
        }
    }
    header("Location: orders.php");
    exit;
}

// -----------------------------------------------------------------------------
// GET: INVOICE GENERATOR VIEW (PRINT FRIENDLY)
// -----------------------------------------------------------------------------
if ($action === 'invoice') {
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    try {
        // Fetch Order info
        $stmt = $db->prepare("
            SELECT o.*, c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            echo "<div class='alert alert-danger'>Order not found.</div>";
            exit;
        }

        // Fetch Order items
        $itemStmt = $db->prepare("
            SELECT oi.*, p.name as product_name, p.slug
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll();

        // Fetch transaction reference if any
        $txnRef = $db->query("SELECT transaction_ref FROM payments WHERE order_id = $orderId")->fetchColumn() ?? 'N/A';

    } catch (PDOException $e) {
        die("Error loading invoice: " . $e->getMessage());
    }

    // Render print layout (override sidebar styling)
    ?>
    <style>
        /* Override admin sidebar & header styles for pure printable page */
        .admin-sidebar, .admin-topbar, #sidebarToggle, footer, .btn-print-action {
            display: none !important;
        }
        .admin-main {
            margin-left: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }
        body {
            background: #ffffff !important;
            font-size: 0.95rem;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        @media print {
            .invoice-box {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>

    <div class="container py-4">
        <div class="d-flex justify-content-between mb-4 no-print">
            <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Orders</a>
            <button onclick="window.print()" class="btn btn-primary-custom btn-sm"><i class="fa-solid fa-print me-1"></i> Print Invoice</button>
        </div>

        <div class="invoice-box bg-white">
            <div class="row align-items-center mb-4 pb-3 border-bottom">
                <div class="col-6">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-solar-panel text-success me-2 fs-3"></i>
                        <h4 class="fw-bold mb-0 text-dark">FLOURISH TECHENG</h4>
                    </div>
                    <small class="text-muted d-block mt-1">Solar energy, Technology & SkillUp Systems</small>
                </div>
                <div class="col-6 text-end">
                    <h3 class="fw-bold mb-0 text-secondary">INVOICE</h3>
                    <span class="text-muted">Order No: #<?php echo sanitize($order['order_number']); ?></span>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-6">
                    <h6 class="fw-bold text-dark">From (Merchant):</h6>
                    <p class="mb-1"><strong>Flourish Techeng Limited</strong></p>
                    <p class="text-muted mb-0 small">
                        12 Flourish Plaza, Tech Avenue,<br>
                        Industrial Area, Nigeria<br>
                        info@flourishtecheng.com | +234 812 345 6789
                    </p>
                </div>
                <div class="col-6 text-end">
                    <h6 class="fw-bold text-dark">To (Customer):</h6>
                    <p class="mb-1"><strong><?php echo sanitize($order['customer_name']); ?></strong></p>
                    <p class="text-muted mb-0 small">
                        <?php echo nl2br(sanitize($order['delivery_address'])); ?><br>
                        Phone: <?php echo sanitize($order['customer_phone']); ?><br>
                        Email: <?php echo sanitize($order['customer_email']); ?>
                    </p>
                </div>
            </div>

            <div class="row g-4 mb-4 bg-light p-3 rounded">
                <div class="col-4">
                    <span class="text-muted small d-block">ORDER DATE</span>
                    <strong class="text-dark"><?php echo date('d M, Y H:i', strtotime($order['created_at'])); ?></strong>
                </div>
                <div class="col-4 text-center">
                    <span class="text-muted small d-block">PAYMENT METHOD</span>
                    <strong class="text-dark"><?php echo sanitize($order['payment_method']); ?></strong>
                </div>
                <div class="col-4 text-end">
                    <span class="text-muted small d-block">TRANSACTION REF</span>
                    <strong class="text-dark"><?php echo sanitize($txnRef); ?></strong>
                </div>
            </div>

            <h6 class="fw-bold mb-3">Order Items</h6>
            <table class="table table-bordered mb-4 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Item Description</th>
                        <th class="text-center" style="width: 120px;">Unit Price</th>
                        <th class="text-center" style="width: 80px;">Qty</th>
                        <th class="text-end" style="width: 120px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?php echo sanitize($item['product_name']); ?></strong></td>
                            <td class="text-center">$<?php echo number_format($item['price'], 2); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end">$<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Cart Subtotal:</td>
                        <td class="text-end fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">VAT & Taxes:</td>
                        <td class="text-end fw-bold">$<?php echo number_format($order['discount_amount'], 2); ?></td>
                    </tr>
                    <tr class="table-success">
                        <td colspan="3" class="text-end fw-bold text-dark">Total Amount Due:</td>
                        <td class="text-end fw-bold text-dark fs-5">$<?php echo number_format($order['net_amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="row g-4 mt-3">
                <div class="col-6">
                    <p class="small text-muted mb-0">Invoice generated electronically. Thank you for your business!</p>
                </div>
                <div class="col-6 text-end">
                    <div class="d-inline-block text-center border-top pt-2" style="width: 150px;">
                        <span class="small text-muted">Authorized Signee</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once 'includes/admin_footer.php';
    exit;
}

// -----------------------------------------------------------------------------
// LIST VIEW (DATATABLES)
// -----------------------------------------------------------------------------
try {
    $orders = $db->query("
        SELECT o.*, c.full_name as customer_name, c.email as customer_email, p.transaction_ref
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        LEFT JOIN payments p ON o.id = p.order_id
        ORDER BY o.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Error loading orders: " . $e->getMessage());
}
?>

<div class="card card-custom border-0 p-4 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-bold"><i class="fa-solid fa-cart-shopping text-primary-custom me-2"></i>Customer Orders Management</h4>
    </div>

    <div class="table-responsive mt-3">
        <table class="table table-striped table-hover align-middle w-100" id="ordersTable">
            <thead>
                <tr>
                    <th>Order No</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Payment Status</th>
                    <th>Order Status</th>
                    <th>Txn Ref</th>
                    <th>Date</th>
                    <th style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong class="text-secondary"><?php echo sanitize($o['order_number']); ?></strong></td>
                        <td>
                            <div class="fw-bold"><?php echo sanitize($o['customer_name']); ?></div>
                            <small class="text-muted"><?php echo sanitize($o['customer_email']); ?></small>
                        </td>
                        <td><span class="fw-bold">$<?php echo number_format($o['net_amount'], 2); ?></span></td>
                        <td><?php echo sanitize($o['payment_method']); ?></td>
                        <td>
                            <?php 
                            $payBadge = 'bg-secondary';
                            if ($o['payment_status'] === 'Pending') $payBadge = 'bg-warning text-dark';
                            elseif ($o['payment_status'] === 'Paid') $payBadge = 'bg-success';
                            elseif ($o['payment_status'] === 'Failed') $payBadge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $payBadge; ?>"><?php echo $o['payment_status']; ?></span>
                        </td>
                        <td>
                            <?php
                            $badge = 'bg-secondary';
                            if ($o['status'] === 'Pending') $badge = 'bg-warning text-dark';
                            elseif (in_array($o['status'], ['Confirmed', 'Processing'])) $badge = 'bg-info';
                            elseif ($o['status'] === 'Ready for Delivery') $badge = 'bg-primary';
                            elseif ($o['status'] === 'Delivered') $badge = 'bg-success';
                            elseif ($o['status'] === 'Cancelled') $badge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo $o['status']; ?></span>
                        </td>
                        <td><code class="text-dark"><?php echo $o['transaction_ref'] ? sanitize($o['transaction_ref']) : 'None'; ?></code></td>
                        <td><?php echo date('d M, Y H:i', strtotime($o['created_at'])); ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <!-- Status Update Trigger Button -->
                                <button class="btn btn-sm btn-outline-primary edit-status-btn" 
                                        data-id="<?php echo $o['id']; ?>" 
                                        data-num="<?php echo $o['order_number']; ?>" 
                                        data-status="<?php echo $o['status']; ?>" 
                                        data-pay="<?php echo $o['payment_status']; ?>"
                                        title="Change Status">
                                    <i class="fa-solid fa-sliders"></i>
                                </button>
                                <!-- View / Print Invoice -->
                                <a href="orders.php?action=invoice&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Invoice" target="_blank">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for changing status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="orders.php?action=update" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="statusModalLabel">Update Order #<span id="modalOrderNumber"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="order_id" id="modalOrderId">

                    <!-- Order Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold">Order Fulfillment Status</label>
                        <select name="status" id="modalOrderStatus" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Processing">Processing</option>
                            <option value="Ready for Delivery">Ready for Delivery</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Payment Status -->
                    <div class="mb-3">
                        <label for="payment_status" class="form-label fw-semibold">Payment Status</label>
                        <select name="payment_status" id="modalPaymentStatus" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Init DataTable
    $('#ordersTable').DataTable({
        order: [[7, 'desc']], // Order by date
        pageLength: 10,
        responsive: true
    });

    // Populate Status update modal
    $('.edit-status-btn').on('click', function() {
        const id = $(this).data('id');
        const num = $(this).data('num');
        const status = $(this).data('status');
        const pay = $(this).data('pay');

        $('#modalOrderId').val(id);
        $('#modalOrderNumber').text(num);
        $('#modalOrderStatus').val(status);
        $('#modalPaymentStatus').val(pay);

        const myModal = new bootstrap.Modal(document.getElementById('statusModal'));
        myModal.show();
    });
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
