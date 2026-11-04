<?php
/**
 * Checkout Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

// Enforce customer login
requireCustomer();

$customerId = $_SESSION['customer_id'];
$db = getDB();
$errors = [];
$success = false;
$orderNumber = "";

try {
    // 1. Fetch tax rate from settings
    $taxStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
    $taxStmt->execute();
    $taxRate = (float)($taxStmt->fetchColumn() ?? 7.5);

    // 2. Fetch cart items
    $cartStmt = $db->prepare("
        SELECT ci.id as item_id, ci.quantity, p.id as product_id, p.name, p.price, p.discount_price, p.stock_quantity
        FROM cart_items ci
        JOIN cart c ON ci.cart_id = c.id
        JOIN products p ON ci.product_id = p.id
        WHERE c.customer_id = ?
    ");
    $cartStmt->execute([$customerId]);
    $cartItems = $cartStmt->fetchAll();

    if (empty($cartItems)) {
        header("Location: cart.php");
        exit;
    }

    // Calculate Totals
    $subtotal = 0.00;
    foreach ($cartItems as $item) {
        $price = ($item['discount_price'] !== null) ? (float)$item['discount_price'] : (float)$item['price'];
        $subtotal += $price * (int)$item['quantity'];
    }

    $taxAmount = $subtotal * ($taxRate / 100);
    $totalAmount = $subtotal + $taxAmount;

    // Fetch customer details for pre-filling
    $custStmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $custStmt->execute([$customerId]);
    $customerDetails = $custStmt->fetch();

} catch (PDOException $e) {
    die("Checkout initialization error: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// POST PROCESSOR: CREATE ORDER
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Please reload page.";
    } else {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $payment_method = sanitize($_POST['payment_method'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        if (empty($full_name)) $errors[] = "Delivery recipient name is required.";
        if (empty($phone)) $errors[] = "Contact phone number is required.";
        if (empty($address)) $errors[] = "Delivery address is required.";
        if (!in_array($payment_method, ['Mobile Money', 'Cash on Delivery', 'Bank Transfer'])) {
            $errors[] = "Invalid payment method selected.";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // 1. Double check and deduct stock for each product
                foreach ($cartItems as $item) {
                    $pCheck = $db->prepare("SELECT stock_quantity, name FROM products WHERE id = ? FOR UPDATE");
                    $pCheck->execute([$item['product_id']]);
                    $prod = $pCheck->fetch();

                    if (!$prod || (int)$prod['stock_quantity'] < (int)$item['quantity']) {
                        throw new Exception("Product '" . ($prod['name'] ?? 'Unknown') . "' is out of stock or does not have enough quantities.");
                    }
                }

                // Deduct stocks
                $updStock = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                foreach ($cartItems as $item) {
                    $updStock->execute([$item['quantity'], $item['product_id']]);
                }

                // 2. Generate unique order number (e.g., FT-20260804-1234)
                $orderNumber = "FT-" . date('Ymd') . "-" . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

                // 3. Insert into orders table
                $insOrder = $db->prepare("
                    INSERT INTO orders (customer_id, order_number, total_amount, discount_amount, net_amount, delivery_address, status, payment_method, payment_status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, 'Pending', ?)
                ");
                $insOrder->execute([
                    $customerId, $orderNumber, $subtotal, $taxAmount, $totalAmount, 
                    $address, $payment_method, $notes
                ]);
                $orderId = $db->lastInsertId();

                // 4. Insert into order items table
                $insItem = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, price, quantity, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($cartItems as $item) {
                    $price = ($item['discount_price'] !== null) ? (float)$item['discount_price'] : (float)$item['price'];
                    $itemSub = $price * $item['quantity'];
                    $insItem->execute([$orderId, $item['product_id'], $price, $item['quantity'], $itemSub]);
                }

                // 5. Insert into payments table
                $insPay = $db->prepare("
                    INSERT INTO payments (order_id, payment_method, amount, status)
                    VALUES (?, ?, ?, 'Pending')
                ");
                $insPay->execute([$orderId, $payment_method, $totalAmount]);
                $paymentId = $db->lastInsertId();

                // 6. Clear user cart items
                // Find cart ID
                $cartIdStmt = $db->prepare("SELECT id FROM cart WHERE customer_id = ?");
                $cartIdStmt->execute([$customerId]);
                $cartId = $cartIdStmt->fetchColumn();
                if ($cartId) {
                    $delCartItems = $db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                    $delCartItems->execute([$cartId]);
                }

                $db->commit();
                
                // Store order completion data in session for receipt display
                $_SESSION['last_order'] = [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'amount' => $totalAmount,
                    'payment_method' => $payment_method,
                    'payment_id' => $paymentId
                ];

                setFlashMsg('success', "Order placed successfully!");
                header("Location: checkout.php?step=success");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Order creation failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// STEP: SUCCESS RENDER
// -----------------------------------------------------------------------------
$step = sanitize($_GET['step'] ?? '');
if ($step === 'success' && isset($_SESSION['last_order'])) {
    $lastOrder = $_SESSION['last_order'];
    // Clear it so it only displays once
    unset($_SESSION['last_order']);
    
    // Save transaction ref handling if user submits
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ref'])) {
        $ref = sanitize($_POST['transaction_ref'] ?? '');
        $payId = (int)$_POST['payment_id'];
        if (!empty($ref) && $payId > 0) {
            try {
                $updPay = $db->prepare("UPDATE payments SET transaction_ref = ?, status = 'Pending' WHERE id = ?");
                $updPay->execute([$ref, $payId]);
                setFlashMsg('success', "Payment transaction reference submitted! Our admin will confirm it.");
            } catch (PDOException $e) {
                setFlashMsg('error', "Failed to save reference.");
            }
        }
        header("Location: customer_dashboard.php");
        exit;
    }
    ?>
    <div class="container py-5 text-center">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card card-custom border-0 p-5 shadow-lg bg-white">
                    <div class="text-success mb-3"><i class="fa-solid fa-circle-check fs-1" style="font-size: 5rem !important;"></i></div>
                    <h2 class="fw-bold mb-2 text-dark">Order Confirmed!</h2>
                    <p class="text-muted mb-4">Your order number is <strong class="text-dark"><?php echo $lastOrder['order_number']; ?></strong>. We will process your items shortly.</p>

                    <?php if ($lastOrder['payment_method'] === 'Cash on Delivery'): ?>
                        <div class="alert alert-info border-0 text-start">
                            <h6 class="fw-bold mb-1"><i class="fa-solid fa-truck-fast me-2"></i>Cash on Delivery Info</h6>
                            <p class="mb-0 small text-secondary">Please prepare the total sum of <strong>$<?php echo number_format($lastOrder['amount'], 2); ?></strong> in cash. Our rider will collect it upon delivery.</p>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Money / Bank Transfer Instructions -->
                        <div class="card bg-light border-0 p-4 text-start mb-4">
                            <h6 class="fw-bold mb-3"><i class="fa-solid fa-credit-card me-2"></i>How to Pay: $<?php echo number_format($lastOrder['amount'], 2); ?></h6>
                            
                            <?php if ($lastOrder['payment_method'] === 'Mobile Money'): ?>
                                <p class="small text-secondary mb-1"><strong>Step 1:</strong> Transfer the total to our MTN MoMo Merchant account:</p>
                                <ul class="small text-secondary ps-3 mb-3">
                                    <li>Merchant Name: <strong>Flourish Techeng Limited</strong></li>
                                    <li>Merchant ID / Till No: <strong>556677</strong></li>
                                </ul>
                            <?php else: ?>
                                <p class="small text-secondary mb-1"><strong>Step 1:</strong> Transfer the total to our Zenith Bank Corporate Account:</p>
                                <ul class="small text-secondary ps-3 mb-3">
                                    <li>Account Name: <strong>Flourish Techeng Limited</strong></li>
                                    <li>Account Number: <strong>1011223344</strong></li>
                                    <li>Bank Name: <strong>Zenith Bank PLC</strong></li>
                                </ul>
                            <?php endif; ?>
                            
                            <p class="small text-secondary mb-2"><strong>Step 2:</strong> Input your payment receipt transaction reference number below to verify your payment:</p>
                            
                            <form action="checkout.php?step=success" method="POST">
                                <input type="hidden" name="payment_id" value="<?php echo $lastOrder['payment_id']; ?>">
                                <div class="input-group">
                                    <input type="text" name="transaction_ref" class="form-control" placeholder="Enter Txn Ref (e.g. TXN9876543)" required>
                                    <button type="submit" name="submit_ref" class="btn btn-primary-custom">Submit Ref</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-center gap-3">
                        <a href="customer_dashboard.php" class="btn btn-outline-success fw-semibold">Track My Orders</a>
                        <a href="shop.php" class="btn btn-primary-custom">Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}
?>

<div class="container py-5">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-credit-card text-primary-custom me-2"></i>Checkout Details</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 mb-4">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="row g-4">
            <!-- Billing Details Column -->
            <div class="col-lg-7">
                <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                    <h5 class="fw-bold mb-4 text-secondary-custom"><i class="fa-solid fa-truck-ramp-box me-2"></i>Shipping & Contact Info</h5>
                    
                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">Recipient Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light border-0 py-2.5" id="full_name" name="full_name" value="<?php echo sanitize($customerDetails['full_name']); ?>" required>
                    </div>

                    <!-- Email & Phone -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-semibold">Recipient Email</label>
                            <input type="email" class="form-control bg-light border-0 py-2.5" id="email" name="email" value="<?php echo sanitize($customerDetails['email']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label fw-semibold">Recipient Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control bg-light border-0 py-2.5" id="phone" name="phone" value="<?php echo sanitize($customerDetails['phone']); ?>" required>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold">Delivery Address <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-light border-0" id="address" name="address" rows="3" required><?php echo sanitize($customerDetails['address']); ?></textarea>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">Additional Delivery Notes</label>
                        <textarea class="form-control bg-light border-0" id="notes" name="notes" rows="2" placeholder="Gate codes, nearby landmarks, or delivery schedules..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Checkout Order Summary Column -->
            <div class="col-lg-5">
                <div class="card card-custom border-0 p-4 shadow-sm bg-light mb-4">
                    <h5 class="fw-bold mb-4 text-secondary-custom">Review Your Items</h5>
                    
                    <div class="d-flex flex-column gap-2 mb-4">
                        <?php foreach ($cartItems as $item): 
                            $price = ($item['discount_price'] !== null) ? $item['discount_price'] : $item['price'];
                            ?>
                            <div class="d-flex justify-content-between text-secondary">
                                <span class="text-truncate" style="max-width: 250px;"><?php echo sanitize($item['name']); ?> <strong class="text-dark">x <?php echo $item['quantity']; ?></strong></span>
                                <span>$<?php echo number_format($price * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between mb-2 text-secondary">
                        <span>Cart Subtotal</span>
                        <span>$<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-secondary">
                        <span>Tax Rate (<?php echo $taxRate; ?>%)</span>
                        <span>$<?php echo number_format($taxAmount, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 text-secondary">
                        <span>Shipping</span>
                        <span class="text-success fw-bold">FREE</span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between mb-3 align-items-center">
                        <span class="fw-bold fs-5">Order Total</span>
                        <span class="fw-bold fs-4 text-primary-custom">$<?php echo number_format($totalAmount, 2); ?></span>
                    </div>
                </div>

                <!-- Payment Methods Card -->
                <div class="card card-custom border-0 p-4 shadow-sm bg-white border-top border-5 border-success">
                    <h5 class="fw-bold mb-3">Select Payment Method</h5>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="payCOD" value="Cash on Delivery" checked>
                        <label class="form-check-label fw-semibold" for="payCOD">
                            <i class="fa-solid fa-hand-holding-dollar text-primary-custom me-1"></i> Cash on Delivery
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="payMoMo" value="Mobile Money">
                        <label class="form-check-label fw-semibold" for="payMoMo">
                            <i class="fa-solid fa-mobile-screen-button text-primary-custom me-1"></i> MTN Mobile Money / Orange Money
                        </label>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="radio" name="payment_method" id="payBank" value="Bank Transfer">
                        <label class="form-check-label fw-semibold" for="payBank">
                            <i class="fa-solid fa-building-columns text-primary-custom me-1"></i> Corporate Bank Transfer
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-check-double me-2"></i>Place Secure Order
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
