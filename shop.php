<?php
/**
 * Shop Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

try {
    $db = getDB();

    // Fetch filters options
    $allCategories = $db->query("SELECT * FROM categories ORDER BY parent_id ASC, name ASC")->fetchAll();
    $allBrands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();

    // Filter values from GET request
    $search = sanitize($_GET['search'] ?? '');
    $catSlug = sanitize($_GET['category'] ?? '');
    $brandId = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
    $minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
    $maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 2000;
    $sort = sanitize($_GET['sort'] ?? 'latest');

    // Build query
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, b.name as brand_name 
            FROM products p
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.status = 'active'";
    
    $params = [];

    // Search query
    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    // Category filter (handles parent category select as well)
    if (!empty($catSlug)) {
        // Find if this slug matches a parent category
        $catStmt = $db->prepare("SELECT id, parent_id FROM categories WHERE slug = ?");
        $catStmt->execute([$catSlug]);
        $catData = $catStmt->fetch();

        if ($catData) {
            if ($catData['parent_id'] === null) {
                // If it is a parent category, fetch all products under this parent
                $sql .= " AND (p.category_id = ? OR c.parent_id = ?)";
                $params[] = $catData['id'];
                $params[] = $catData['id'];
            } else {
                // If subcategory
                $sql .= " AND p.category_id = ?";
                $params[] = $catData['id'];
            }
        }
    }

    // Brand filter
    if ($brandId > 0) {
        $sql .= " AND p.brand_id = ?";
        $params[] = $brandId;
    }

    // Price range filters (consider discount price if active)
    $sql .= " AND COALESCE(p.discount_price, p.price) BETWEEN ? AND ?";
    $params[] = $minPrice;
    $params[] = $maxPrice;

    // Sorting
    if ($sort === 'price_asc') {
        $sql .= " ORDER BY COALESCE(p.discount_price, p.price) ASC";
    } elseif ($sort === 'price_desc') {
        $sql .= " ORDER BY COALESCE(p.discount_price, p.price) DESC";
    } else {
        $sql .= " ORDER BY p.id DESC"; // Default: latest
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Shop listing error: " . $e->getMessage());
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Filters Column -->
        <div class="col-lg-3 col-md-4">
            <div class="card card-custom border-0 p-4 shadow-sm bg-light">
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-filter text-primary-custom me-2"></i>Filter Products</h5>
                
                <form action="shop.php" method="GET" id="filterForm">
                    <!-- Search Input -->
                    <div class="mb-4">
                        <label for="search" class="form-label fw-semibold">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control border-0" id="search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search name...">
                            <button class="btn btn-primary-custom border-0" type="submit"><i class="fa-solid fa-search"></i></button>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Category</label>
                        <select class="form-select border-0 bg-white" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php 
                            // Render category select hierarchy
                            foreach ($allCategories as $cat) {
                                $indent = ($cat['parent_id'] !== null) ? '&nbsp;&nbsp;-- ' : '';
                                $selected = ($catSlug === $cat['slug']) ? 'selected' : '';
                                echo '<option value="' . $cat['slug'] . '" ' . $selected . '>' . $indent . sanitize($cat['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Brand Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Brand</label>
                        <select class="form-select border-0 bg-white" name="brand" onchange="this.form.submit()">
                            <option value="">All Brands</option>
                            <?php foreach ($allBrands as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo ($brandId === (int)$b['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold d-flex justify-content-between">
                            <span>Price Range ($)</span>
                            <span class="text-primary-custom fw-bold" id="priceDisplay">$<?php echo $minPrice; ?> - $<?php echo $maxPrice; ?></span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" name="min_price" class="form-control border-0 bg-white" placeholder="Min" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" name="max_price" class="form-control border-0 bg-white" placeholder="Max" value="<?php echo $maxPrice < 2000 ? $maxPrice : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Filters -->
                    <button type="submit" class="btn btn-primary-custom w-100 mb-2">Apply Filters</button>
                    <a href="shop.php" class="btn btn-outline-secondary w-100">Clear All</a>
                </form>
            </div>
        </div>

        <!-- Products Grid Column -->
        <div class="col-lg-9 col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <p class="mb-0 text-muted">Showing <strong><?php echo count($products); ?></strong> products</p>
                
                <!-- Sort dropdown -->
                <div class="d-flex align-items-center gap-2">
                    <label for="sort" class="text-nowrap small fw-semibold text-muted mb-0">Sort By:</label>
                    <select class="form-select form-select-sm border-0 shadow-sm" style="width: 160px;" id="sort" onchange="location = this.value;">
                        <option value="shop.php?<?php echo http_build_query(array_merge($_GET, ['sort' => 'latest'])); ?>" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest Arrivals</option>
                        <option value="shop.php?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_asc'])); ?>" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="shop.php?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_desc'])); ?>" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Products Row -->
            <?php if (empty($products)): ?>
                <div class="card card-custom border-0 p-5 text-center">
                    <i class="fa-regular fa-folder-open fs-1 text-muted mb-3"></i>
                    <h4 class="fw-bold">No Products Found</h4>
                    <p class="text-muted">Try adjusting your filters or search keywords.</p>
                    <div>
                        <a href="shop.php" class="btn btn-primary-custom">Clear All Filters</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="card card-custom h-100 border-0 p-3">
                                <?php if ($p['discount_price']): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-3 z-3">
                                        -<?php echo round((($p['price'] - $p['discount_price']) / $p['price']) * 100); ?>%
                                    </span>
                                <?php endif; ?>
                                
                                <div class="text-center mb-3 overflow-hidden rounded bg-white position-relative" style="height: 180px;">
                                    <a href="product.php?slug=<?php echo $p['slug']; ?>">
                                        <?php if ($p['image'] && file_exists($p['image'])): ?>
                                            <img src="<?php echo $p['image']; ?>" class="img-fluid h-100" style="object-fit: contain;" alt="">
                                        <?php else: ?>
                                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                                <i class="fa-solid fa-box text-muted fs-2"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                
                                <div class="card-body p-0 d-flex flex-column">
                                    <span class="text-muted small mb-1"><?php echo sanitize($p['category_name']); ?></span>
                                    <h6 class="card-title-custom fw-bold text-secondary text-truncate mb-2" title="<?php echo sanitize($p['name']); ?>">
                                        <a href="product.php?slug=<?php echo $p['slug']; ?>" class="text-decoration-none text-dark"><?php echo sanitize($p['name']); ?></a>
                                    </h6>
                                    
                                    <div class="d-flex align-items-center gap-1 mb-3 text-warning" style="font-size: 0.8rem;">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                        <span class="text-muted small ms-1">(5.0)</span>
                                    </div>
                                    
                                    <div class="mt-auto d-flex justify-content-between align-items-center pt-2">
                                        <div>
                                            <?php if ($p['discount_price']): ?>
                                                <span class="fw-bold text-danger fs-5">$<?php echo number_format($p['discount_price'], 2); ?></span>
                                                <span class="text-muted text-decoration-line-through small ms-1">$<?php echo number_format($p['price'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="fw-bold text-dark fs-5">$<?php echo number_format($p['price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="product.php?slug=<?php echo $p['slug']; ?>" class="btn btn-outline-success btn-sm rounded-circle p-2" style="width: 36px; height: 36px; line-height: 1.2;">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
