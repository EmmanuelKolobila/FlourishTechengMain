<?php
/**
 * Shopping Cart Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

// Enforce login for shopping cart
requireCustomer();

$customerId = $_SESSION['customer_id'];
$db = getDB();
$errors = [];
$success = "";

// -----------------------------------------------------------------------------
// POST HANDLER FOR ADD, UPDATE, DELETE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    // Get or Create Cart ID
    try {
        $cartStmt = $db->prepare("SELECT id FROM cart WHERE customer_id = ?");
        $cartStmt->execute([$customerId]);
        $cart = $cartStmt->fetch();
        
        if (!$cart) {
            $insCart = $db->prepare("INSERT INTO cart (customer_id) VALUES (?)");
            $insCart->execute([$customerId]);
            $cartId = $db->lastInsertId();
        } else {
            $cartId = $cart['id'];
        }

        if ($action === 'add') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 1);

            if ($productId > 0 && $qty > 0) {
                // Check stock
                $pStmt = $db->prepare("SELECT price, discount_price, stock_quantity FROM products WHERE id = ? AND status = 'active'");
                $pStmt->execute([$productId]);
                $product = $pStmt->fetch();

                if ($product) {
                    // Check if already in cart
                    $itemStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
                    $itemStmt->execute([$cartId, $productId]);
                    $existingItem = $itemStmt->fetch();

                    $newQty = $qty;
                    if ($existingItem) {
                        $newQty += (int)$existingItem['quantity'];
                    }

                    if ($newQty > (int)$product['stock_quantity']) {
                        setFlashMsg('error', "Cannot add more items. Exceeds available stock quantity.");
                    } else {
                        if ($existingItem) {
                            $upd = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                            $upd->execute([$newQty, $existingItem['id']]);
                        } else {
                            $ins = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                            $ins->execute([$cartId, $productId, $newQty]);
                        }
                        setFlashMsg('success', "Product added to cart!");
                    }
                }
            }
            header("Location: cart.php");
            exit;

        } elseif ($action === 'update') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 1);

            if ($itemId > 0 && $qty > 0) {
                // Fetch product stock through cart_item connection
                $itemCheck = $db->prepare("
                    SELECT ci.id, p.stock_quantity 
                    FROM cart_items ci
                    JOIN products p ON ci.product_id = p.id
                    WHERE ci.id = ? AND ci.cart_id = ?
                ");
                $itemCheck->execute([$itemId, $cartId]);
                $item = $itemCheck->fetch();

                if ($item) {
                    if ($qty > (int)$item['stock_quantity']) {
                        setFlashMsg('error', "Quantity exceeds available stock.");
                    } else {
                        $upd = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                        $upd->execute([$qty, $itemId]);
                        setFlashMsg('success', "Cart updated.");
                    }
                }
            }
            header("Location: cart.php");
            exit;

        } elseif ($action === 'delete') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId > 0) {
                $del = $db->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
                $del->execute([$itemId, $cartId]);
                setFlashMsg('success', "Item removed from cart.");
            }
            header("Location: cart.php");
            exit;
        }

    } catch (PDOException $e) {
        $errors[] = "Cart error: " . $e->getMessage();
    }
}

// -----------------------------------------------------------------------------
// GET HANDLER: FETCH CART ITEMS
// -----------------------------------------------------------------------------
$cartItems = [];
$totalAmount = 0.00;

try {
    $cartStmt = $db->prepare("
        SELECT ci.id as item_id, ci.quantity, p.id as product_id, p.name, p.price, p.discount_price, p.image, p.stock_quantity, p.slug
        FROM cart_items ci
        JOIN cart c ON ci.cart_id = c.id
        JOIN products p ON ci.product_id = p.id
        WHERE c.customer_id = ?
    ");
    $cartStmt->execute([$customerId]);
    $cartItems = $cartStmt->fetchAll();

    foreach ($cartItems as $item) {
        $unitPrice = ($item['discount_price'] !== null) ? (float)$item['discount_price'] : (float)$item['price'];
        $totalAmount += $unitPrice * (int)$item['quantity'];
    }

} catch (PDOException $e) {
    $errors[] = "Could not fetch cart items: " . $e->getMessage();
}
?>

<div class="container py-5">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-cart-shopping text-primary-custom me-2"></i>Your Shopping Cart</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 mb-4">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <div class="card card-custom border-0 p-5 text-center shadow-sm">
            <i class="fa-solid fa-basket-shopping text-muted mb-3" style="font-size: 4rem;"></i>
            <h4 class="fw-bold">Your cart is empty!</h4>
            <p class="text-muted">Browse our collection of premium solar, electrical, and technology products.</p>
            <div class="mt-3">
                <a href="shop.php" class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-shopping-bag me-1"></i> Go Shopping</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Items Column -->
            <div class="col-lg-8">
                <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Product</th>
                                    <th>Details</th>
                                    <th>Price</th>
                                    <th style="width: 150px;">Quantity</th>
                                    <th>Total</th>
                                    <th style="width: 50px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): 
                                    $price = ($item['discount_price'] !== null) ? $item['discount_price'] : $item['price'];
                                    $itemSubtotal = $price * $item['quantity'];
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['image'] && file_exists($item['image'])): ?>
                                                <img src="<?php echo $item['image']; ?>" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;" alt="">
                                            <?php else: ?>
                                                <div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                    <i class="fa-solid fa-box text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <h6 class="mb-0 fw-semibold text-secondary-custom">
                                                <a href="product.php?slug=<?php echo $item['slug']; ?>" class="text-decoration-none text-dark"><?php echo sanitize($item['name']); ?></a>
                                            </h6>
                                            <small class="text-muted">Stock limit: <?php echo $item['stock_quantity']; ?></small>
                                        </td>
                                        <td>
                                            $<?php echo number_format($price, 2); ?>
                                        </td>
                                        <td>
                                            <form action="cart.php" method="POST" class="d-flex align-items-center">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                
                                                <select name="quantity" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <?php for ($i = 1; $i <= min(10, $item['stock_quantity']); $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo $item['quantity'] == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="fw-bold">$<?php echo number_format($itemSubtotal, 2); ?></span>
                                        </td>
                                        <td>
                                            <form action="cart.php" method="POST" onsubmit="return confirm('Remove item from cart?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0;">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Column -->
            <div class="col-lg-4">
                <div class="card card-custom border-0 p-4 shadow-sm bg-light">
                    <h5 class="fw-bold mb-4">Order Summary</h5>
                    
                    <div class="d-flex justify-content-between mb-3 text-secondary">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($totalAmount, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 text-secondary">
                        <span>Estimated Shipping</span>
                        <span class="text-success fw-bold">FREE</span>
                    </div>
                    
                    <hr class="my-4">

                    <div class="d-flex justify-content-between mb-4 align-items-center">
                        <span class="fw-bold fs-5">Total</span>
                        <span class="fw-bold fs-4 text-primary-custom">$<?php echo number_format($totalAmount, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="btn btn-primary-custom w-100 py-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-credit-card me-2"></i>Proceed to Checkout
                    </a>

                    <div class="text-center mt-3">
                        <a href="shop.php" class="text-secondary small fw-semibold text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i> Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
