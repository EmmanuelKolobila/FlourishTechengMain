<?php
/**
 * Product Detail Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

$slug = sanitize($_GET['slug'] ?? '');
$db = getDB();

if (empty($slug)) {
    header("Location: shop.php");
    exit;
}

try {
    // 1. Fetch Product
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, b.name as brand_name 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.slug = ? AND p.status = 'active'
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        setFlashMsg('error', "Product not found or currently unavailable.");
        header("Location: shop.php");
        exit;
    }

    $product_id = $product['id'];

    // 2. Fetch Secondary Images
    $imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $imgStmt->execute([$product_id]);
    $secondaryImages = $imgStmt->fetchAll();

    // 3. Fetch Reviews
    $revStmt = $db->prepare("
        SELECT r.*, c.full_name as reviewer_name 
        FROM reviews r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $revStmt->execute([$product_id]);
    $reviews = $revStmt->fetchAll();

    // Calculate rating averages
    $avgRating = 5.0;
    if (count($reviews) > 0) {
        $sum = 0;
        foreach ($reviews as $rev) {
            $sum += (int)$rev['rating'];
        }
        $avgRating = round($sum / count($reviews), 1);
    }

} catch (PDOException $e) {
    die("Error loading product: " . $e->getMessage());
}

// 4. Handle Review Submission (POST)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isCustomerLoggedIn()) {
        $errors[] = "You must be logged in as a customer to submit a review.";
    } elseif (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Try again.";
    } else {
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = sanitize($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) $errors[] = "Rating must be between 1 and 5.";
        if (empty($comment)) $errors[] = "Please add a review comment.";

        if (empty($errors)) {
            try {
                $customerId = $_SESSION['customer_id'];
                $insStmt = $db->prepare("INSERT INTO reviews (customer_id, product_id, rating, comment, status) VALUES (?, ?, ?, ?, 'approved')");
                $insStmt->execute([$customerId, $product_id, $rating, $comment]);
                
                setFlashMsg('success', "Review submitted successfully!");
                header("Location: product.php?slug=" . $product['slug']);
                exit;
            } catch (PDOException $e) {
                $errors[] = "Failed to submit review: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="container py-5">
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-primary-custom text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-primary-custom text-decoration-none">Shop</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($product['name']); ?></li>
        </ol>
    </nav>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 mb-4">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Images Gallery Column -->
        <div class="col-md-6">
            <div class="card card-custom p-3 border-0 bg-white shadow-sm text-center mb-3">
                <div style="height: 380px;" class="d-flex align-items-center justify-content-center bg-white rounded overflow-hidden">
                    <?php if ($product['image'] && file_exists($product['image'])): ?>
                        <img id="mainGalleryImage" src="<?php echo $product['image']; ?>" class="img-fluid h-100" style="object-fit: contain;" alt="">
                    <?php else: ?>
                        <i class="fa-solid fa-box text-muted fs-1"></i>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gallery Thumbnails -->
            <?php if (!empty($secondaryImages) || ($product['image'] && file_exists($product['image']))): ?>
                <div class="d-flex gap-2 justify-content-start flex-wrap">
                    <!-- Main image thumb -->
                    <?php if ($product['image'] && file_exists($product['image'])): ?>
                        <img src="<?php echo $product['image']; ?>" class="img-thumbnail thumb-gallery active-thumb" style="width: 70px; height: 70px; object-fit: cover; cursor: pointer;" onclick="switchGalleryImage(this.src)">
                    <?php endif; ?>
                    
                    <!-- Secondary image thumbs -->
                    <?php foreach ($secondaryImages as $sec): ?>
                        <?php if (file_exists($sec['image_path'])): ?>
                            <img src="<?php echo $sec['image_path']; ?>" class="img-thumbnail thumb-gallery" style="width: 70px; height: 70px; object-fit: cover; cursor: pointer;" onclick="switchGalleryImage(this.src)">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Product Purchase Information Column -->
        <div class="col-md-6">
            <span class="badge bg-success-subtle text-primary-custom px-2.5 py-1.5 rounded mb-2 text-uppercase font-monospace" style="font-size: 0.75rem; background-color: rgba(16, 185, 129, 0.15);">
                <?php echo sanitize($product['category_name']); ?>
            </span>
            <h2 class="fw-bold mb-2 text-secondary-custom"><?php echo sanitize($product['name']); ?></h2>
            
            <?php if ($product['brand_name']): ?>
                <p class="text-muted mb-2">Brand: <strong class="text-dark"><?php echo sanitize($product['brand_name']); ?></strong></p>
            <?php endif; ?>

            <!-- Rating Summary -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="text-warning small">
                    <?php
                    $fullStars = floor($avgRating);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $fullStars) echo '<i class="fa-solid fa-star"></i>';
                        else echo '<i class="fa-regular fa-star"></i>';
                    }
                    ?>
                </div>
                <span class="fw-semibold text-secondary small"><?php echo $avgRating; ?> Rating Average</span>
                <span class="text-muted small">|</span>
                <span class="text-muted small"><?php echo count($reviews); ?> Reviews</span>
            </div>

            <!-- Price -->
            <div class="mb-4 bg-light p-3 rounded">
                <?php if ($product['discount_price']): ?>
                    <span class="text-muted text-decoration-line-through fs-5">$<?php echo number_format($product['price'], 2); ?></span>
                    <h3 class="text-danger fw-bold mb-0 mt-1">$<?php echo number_format($product['discount_price'], 2); ?></h3>
                    <small class="text-success fw-semibold"><i class="fa-solid fa-tags me-1"></i>You save $<?php echo number_format($product['price'] - $product['discount_price'], 2); ?></small>
                <?php else: ?>
                    <h3 class="text-dark fw-bold mb-0">$<?php echo number_format($product['price'], 2); ?></h3>
                <?php endif; ?>
            </div>

            <!-- Stock Status -->
            <div class="mb-4">
                <span class="fw-semibold text-secondary d-block mb-1">Availability:</span>
                <?php if ($product['stock_quantity'] <= 0): ?>
                    <span class="badge bg-danger px-3 py-2"><i class="fa-solid fa-ban me-1"></i>Out of Stock</span>
                <?php elseif ($product['stock_quantity'] <= 5): ?>
                    <span class="badge bg-warning text-dark px-3 py-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock - Only <?php echo $product['stock_quantity']; ?> left</span>
                <?php else: ?>
                    <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-check me-1"></i>In Stock (<?php echo $product['stock_quantity']; ?> Available)</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="mb-4">
                <h5 class="fw-bold text-dark mb-2">Product Description</h5>
                <p class="text-muted"><?php echo nl2br(sanitize($product['description'])); ?></p>
            </div>

            <!-- Add to Cart Form -->
            <?php if ($product['stock_quantity'] > 0): ?>
                <form action="cart.php" method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 120px;">
                            <label for="quantity" class="visually-hidden">Quantity</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary border-0 bg-light px-2" onclick="adjustQty(-1)"><i class="fa-solid fa-minus fs-6"></i></button>
                                <input type="number" id="quantity" name="quantity" class="form-control text-center border-0 bg-light fw-bold" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" readonly>
                                <button type="button" class="btn btn-outline-secondary border-0 bg-light px-2" onclick="adjustQty(1)"><i class="fa-solid fa-plus fs-6"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-custom py-2.5 px-4 fw-bold">
                            <i class="fa-solid fa-shopping-cart me-2"></i>Add to Cart
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Specifications & Reviews Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs border-bottom" id="detailsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-dark" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs-pane" type="button" role="tab" aria-controls="specs-pane" aria-selected="true">
                        Technical Specs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews-pane" type="button" role="tab" aria-controls="reviews-pane" aria-selected="false">
                        Customer Reviews (<?php echo count($reviews); ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content py-4" id="detailsTabsContent">
                <!-- Technical specs tab pane -->
                <div class="tab-pane fade show active" id="specs-pane" role="tabpanel" aria-labelledby="specs-tab">
                    <?php if (empty($product['specifications'])): ?>
                        <p class="text-muted">No specifications listed for this product.</p>
                    <?php else: ?>
                        <div class="table-responsive col-lg-8">
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    <?php 
                                    $lines = explode("\n", $product['specifications']);
                                    foreach ($lines as $line) {
                                        if (strpos($line, ':') !== false) {
                                            list($key, $val) = explode(':', $line, 2);
                                            echo '<tr>
                                                <td class="fw-bold bg-light" style="width: 30%;">' . sanitize($key) . '</td>
                                                <td>' . sanitize($val) . '</td>
                                            </tr>';
                                        } else if (trim($line) !== '') {
                                            echo '<tr><td colspan="2">' . sanitize($line) . '</td></tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reviews Tab Pane -->
                <div class="tab-pane fade" id="reviews-pane" role="tabpanel" aria-labelledby="reviews-tab">
                    <div class="row g-4">
                        <!-- Review list -->
                        <div class="col-lg-7">
                            <h5 class="fw-bold text-secondary-custom mb-3">Trainee & Customer Reviews</h5>
                            <?php if (empty($reviews)): ?>
                                <p class="text-muted">No reviews submitted yet. Be the first to review this product!</p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($reviews as $rev): ?>
                                        <div class="card p-3 border-0 bg-light rounded shadow-sm">
                                            <div class="d-flex justify-content-between mb-2">
                                                <h6 class="fw-bold mb-0 text-dark"><?php echo sanitize($rev['reviewer_name']); ?></h6>
                                                <small class="text-muted"><?php echo date('d M, Y', strtotime($rev['created_at'])); ?></small>
                                            </div>
                                            <div class="text-warning small mb-2">
                                                <?php 
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= (int)$rev['rating']) echo '<i class="fa-solid fa-star"></i>';
                                                    else echo '<i class="fa-regular fa-star"></i>';
                                                }
                                                ?>
                                            </div>
                                            <p class="mb-0 text-secondary"><?php echo nl2br(sanitize($rev['comment'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Review submission form -->
                        <div class="col-lg-5">
                            <div class="card p-4 border border-dashed rounded bg-light">
                                <h5 class="fw-bold mb-3">Add Your Review</h5>
                                <?php if (!isCustomerLoggedIn()): ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-3">You must be logged in to leave reviews.</p>
                                        <a href="login.php" class="btn btn-primary-custom btn-sm fw-semibold">Login to Account</a>
                                    </div>
                                <?php else: ?>
                                    <form action="product.php?slug=<?php echo $product['slug']; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        
                                        <!-- Rating select -->
                                        <div class="mb-3">
                                            <label for="rating" class="form-label fw-semibold">Rating</label>
                                            <select class="form-select bg-white border" id="rating" name="rating" required>
                                                <option value="5">5 Stars (Excellent)</option>
                                                <option value="4">4 Stars (Good)</option>
                                                <option value="3">3 Stars (Average)</option>
                                                <option value="2">2 Stars (Poor)</option>
                                                <option value="1">1 Star (Very Bad)</option>
                                            </select>
                                        </div>

                                        <!-- Comment textarea -->
                                        <div class="mb-3">
                                            <label for="comment" class="form-label fw-semibold">Review Details</label>
                                            <textarea class="form-control bg-white border" id="comment" name="comment" rows="4" required></textarea>
                                        </div>

                                        <button type="submit" name="submit_review" class="btn btn-primary-custom w-100 fw-bold">
                                            Submit Review
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Switcher script -->
<script>
function switchGalleryImage(src) {
    const mainImg = document.getElementById('mainGalleryImage');
    if (mainImg) {
        mainImg.src = src;
    }
}

function adjustQty(change) {
    const qtyInput = document.getElementById('quantity');
    if (qtyInput) {
        let current = parseInt(qtyInput.value);
        let max = parseInt(qtyInput.max);
        let target = current + change;
        if (target >= 1 && target <= max) {
            qtyInput.value = target;
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
