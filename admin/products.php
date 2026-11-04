<?php
/**
 * Product, Category, and Brand Management Panel
 * Flourish Techeng Limited
 */

require_once 'includes/admin_header.php';

$action = sanitize($_GET['action'] ?? '');
$db = getDB();
$errors = [];

// Ensure upload directory exists
$uploadDir = '../uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// -----------------------------------------------------------------------------
// POST HANDLER: ADD / EDIT PRODUCT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Please reload the page.";
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $slug = sanitize($_POST['slug'] ?? '');
        $description = $_POST['description'] ?? '';
        $specifications = $_POST['specifications'] ?? '';
        $price = (float)($_POST['price'] ?? 0);
        $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $status = sanitize($_POST['status'] ?? 'active');

        // Validation
        if (empty($name)) $errors[] = "Product name is required.";
        if (empty($slug)) $errors[] = "Product slug is required.";
        if ($price <= 0) $errors[] = "Price must be greater than 0.";
        if ($discount_price !== null && $discount_price >= $price) $errors[] = "Discount price must be less than regular price.";
        if ($stock_quantity < 0) $errors[] = "Stock quantity cannot be negative.";
        if ($category_id <= 0) $errors[] = "Please select a category.";

        // Slug uniqueness check
        if (empty($errors)) {
            $slug_check_sql = "SELECT id FROM products WHERE slug = ? AND id != ?";
            $stmt = $db->prepare($slug_check_sql);
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $errors[] = "Slug already exists. Please modify the slug or product name.";
            }
        }

        // Image Handling
        $primary_image_path = "";
        if (empty($errors)) {
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['primary_image']['tmp_name'];
                $fileName = $_FILES['primary_image']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = uniqid('primary_', true) . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $primary_image_path = 'uploads/products/' . $newFileName;
                        if ($action === 'edit' && $id > 0) {
                            $old_img = $db->query("SELECT image FROM products WHERE id = $id")->fetchColumn();
                            if ($old_img && file_exists('../' . $old_img)) {
                                unlink('../' . $old_img);
                            }
                        }
                    } else {
                        $errors[] = "Error moving uploaded primary image.";
                    }
                } else {
                    $errors[] = "Invalid primary image format. Allowed: " . implode(', ', $allowedExtensions);
                }
            }
        }

        // Process Database Save
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                if ($action === 'add') {
                    $sql = "INSERT INTO products (category_id, brand_id, name, slug, description, specifications, price, discount_price, stock_quantity, image, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $category_id, $brand_id, $name, $slug, $description, $specifications, 
                        $price, $discount_price, $stock_quantity, $primary_image_path, $status
                    ]);
                    $product_id = $db->lastInsertId();
                    $success_msg = "Product successfully created!";
                } else {
                    $product_id = $id;
                    $sql = "UPDATE products SET category_id = ?, brand_id = ?, name = ?, slug = ?, description = ?, specifications = ?, price = ?, discount_price = ?, stock_quantity = ?, status = ?";
                    $params = [$category_id, $brand_id, $name, $slug, $description, $specifications, $price, $discount_price, $stock_quantity, $status];

                    if (!empty($primary_image_path)) {
                        $sql .= ", image = ?";
                        $params[] = $primary_image_path;
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $product_id;

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $success_msg = "Product successfully updated!";
                }

                // Handle Secondary Images Uploads
                if (isset($_FILES['secondary_images']) && !empty($_FILES['secondary_images']['name'][0])) {
                    $filesCount = count($_FILES['secondary_images']['name']);
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    
                    for ($i = 0; $i < $filesCount; $i++) {
                        if ($_FILES['secondary_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $fileTmpPath = $_FILES['secondary_images']['tmp_name'][$i];
                            $fileName = $_FILES['secondary_images']['name'][$i];
                            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                            if (in_array($fileExtension, $allowedExtensions)) {
                                $newFileName = uniqid('sec_' . $product_id . '_', true) . '.' . $fileExtension;
                                $destPath = $uploadDir . $newFileName;
                                if (move_uploaded_file($fileTmpPath, $destPath)) {
                                    $sec_image_path = 'uploads/products/' . $newFileName;
                                    $sec_stmt = $db->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                                    $sec_stmt->execute([$product_id, $sec_image_path]);
                                }
                            }
                        }
                    }
                }

                $db->commit();
                setFlashMsg('success', $success_msg);
                header("Location: products.php");
                exit;

            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = "Database Transaction Error: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: ADD / EDIT CATEGORY
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_category', 'edit_category'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security validation failed.";
    } else {
        $id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $slug = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name)) $errors[] = "Category name is required.";
        if (empty($slug)) $errors[] = "Category slug is required.";

        // Slug check
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $errors[] = "Slug already exists. Please modify the category name or slug.";
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'add_category') {
                    $stmt = $db->prepare("INSERT INTO categories (name, parent_id, slug, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $parent_id, $slug, $description]);
                    setFlashMsg('success', "Category successfully added.");
                } else {
                    $stmt = $db->prepare("UPDATE categories SET name = ?, parent_id = ?, slug = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $parent_id, $slug, $description, $id]);
                    setFlashMsg('success', "Category successfully updated.");
                }
                header("Location: products.php?tab=categories");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Category save failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: ADD / EDIT BRAND
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_brand', 'edit_brand'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security validation failed.";
    } else {
        $id = isset($_POST['brand_id']) ? (int)$_POST['brand_id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name)) $errors[] = "Brand name is required.";

        if (empty($errors)) {
            try {
                if ($action === 'add_brand') {
                    $stmt = $db->prepare("INSERT INTO brands (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    setFlashMsg('success', "Brand successfully added.");
                } else {
                    $stmt = $db->prepare("UPDATE brands SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    setFlashMsg('success', "Brand successfully updated.");
                }
                header("Location: products.php?tab=brands");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Brand save failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// DELETE HANDLER (Product)
// -----------------------------------------------------------------------------
if ($action === 'delete') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        try {
            $prod = $db->query("SELECT image FROM products WHERE id = $id")->fetch();
            if ($prod) {
                if ($prod['image'] && file_exists('../' . $prod['image'])) {
                    unlink('../' . $prod['image']);
                }

                $sec_imgs = $db->query("SELECT image_path FROM product_images WHERE product_id = $id")->fetchAll();
                foreach ($sec_imgs as $img) {
                    if ($img['image_path'] && file_exists('../' . $img['image_path'])) {
                        unlink('../' . $img['image_path']);
                    }
                }

                $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                setFlashMsg('success', "Product successfully deleted.");
            }
        } catch (PDOException $e) {
            setFlashMsg('error', "Failed to delete product: " . $e->getMessage());
        }
    }
    header("Location: products.php");
    exit;
}

// -----------------------------------------------------------------------------
// DELETE CATEGORY HANDLER
// -----------------------------------------------------------------------------
if ($action === 'delete_category') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMsg('success', "Category deleted.");
        } catch (PDOException $e) {
            setFlashMsg('error', "Could not delete category (it may be referenced by products): " . $e->getMessage());
        }
    }
    header("Location: products.php?tab=categories");
    exit;
}

// -----------------------------------------------------------------------------
// DELETE BRAND HANDLER
// -----------------------------------------------------------------------------
if ($action === 'delete_brand') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMsg('success', "Brand deleted.");
        } catch (PDOException $e) {
            setFlashMsg('error', "Could not delete brand: " . $e->getMessage());
        }
    }
    header("Location: products.php?tab=brands");
    exit;
}

// -----------------------------------------------------------------------------
// SECONDARY IMAGE REMOVE
// -----------------------------------------------------------------------------
if ($action === 'remove_sec_img') {
    $img_id = isset($_GET['img_id']) ? (int)$_GET['img_id'] : 0;
    $prod_id = isset($_GET['prod_id']) ? (int)$_GET['prod_id'] : 0;
    if ($img_id > 0) {
        $img = $db->query("SELECT image_path FROM product_images WHERE id = $img_id")->fetch();
        if ($img) {
            if ($img['image_path'] && file_exists('../' . $img['image_path'])) {
                unlink('../' . $img['image_path']);
            }
            $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$img_id]);
            setFlashMsg('success', "Image removed.");
        }
    }
    header("Location: products.php?action=edit&id=" . $prod_id);
    exit;
}
?>

<div class="card card-custom border-0 p-4 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-bold">
            <?php 
            if ($action === 'add') echo '<i class="fa-solid fa-plus-circle text-primary-custom me-2"></i>Add New Product';
            elseif ($action === 'edit') echo '<i class="fa-solid fa-edit text-primary-custom me-2"></i>Edit Product';
            else echo '<i class="fa-solid fa-boxes text-primary-custom me-2"></i>Shop Catalog & Inventory';
            ?>
        </h4>
        <?php if (empty($action)): ?>
            <a href="products.php?action=add" class="btn btn-primary-custom btn-sm fw-bold">
                <i class="fa-solid fa-plus me-1"></i> Add Product
            </a>
        <?php else: ?>
            <a href="products.php" class="btn btn-outline-secondary btn-sm fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        <?php endif; ?>
    </div>

    <!-- Error Summary -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 mb-4" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Render Action Area -->
    <?php if (in_array($action, ['add', 'edit'])): 
        $prod = [
            'id' => '', 'name' => '', 'slug' => '', 'description' => '', 'specifications' => '',
            'price' => '', 'discount_price' => '', 'stock_quantity' => '', 'category_id' => '',
            'brand_id' => '', 'image' => '', 'status' => 'active'
        ];

        if ($action === 'edit') {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if (!$prod) {
                header("Location: products.php");
                exit;
            }
            $sec_images = $db->query("SELECT * FROM product_images WHERE product_id = $id")->fetchAll();
        }

        // Fetch subcategories and brands for products
        $categories = $db->query("SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC")->fetchAll();
        $brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
        ?>
        
        <!-- Add / Edit Form -->
        <form action="products.php?action=<?php echo $action; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light border-0 py-2.5" id="name" name="name" value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : sanitize($prod['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label fw-semibold">URL Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light border-0 py-2.5" id="slug" name="slug" value="<?php echo isset($_POST['slug']) ? sanitize($_POST['slug']) : sanitize($prod['slug']); ?>" required readonly>
                        <small class="text-muted">Auto-generated from product name.</small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control bg-light border-0" id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? sanitize($_POST['description']) : $prod['description']; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="specifications" class="form-label fw-semibold">Technical Specifications (Key: Value)</label>
                        <textarea class="form-control bg-light border-0" id="specifications" name="specifications" rows="4" placeholder="Cell Type: Monocrystalline&#10;Power: 450W"><?php echo isset($_POST['specifications']) ? sanitize($_POST['specifications']) : $prod['specifications']; ?></textarea>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="p-4 bg-light rounded border">
                        <div class="mb-3">
                            <label for="price" class="form-label fw-semibold">Regular Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control bg-white py-2" id="price" name="price" value="<?php echo isset($_POST['price']) ? (float)$_POST['price'] : $prod['price']; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="discount_price" class="form-label fw-semibold">Discounted Price ($)</label>
                            <input type="number" step="0.01" class="form-control bg-white py-2" id="discount_price" name="discount_price" value="<?php echo isset($_POST['discount_price']) ? (float)$_POST['discount_price'] : $prod['discount_price']; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label fw-semibold">Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control bg-white py-2" id="stock_quantity" name="stock_quantity" value="<?php echo isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : $prod['stock_quantity']; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select bg-white py-2" id="category_id" name="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) || $prod['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="brand_id" class="form-label fw-semibold">Brand</label>
                            <select class="form-select bg-white py-2" id="brand_id" name="brand_id">
                                <option value="">-- Select Brand --</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>" <?php echo (isset($_POST['brand_id']) && $_POST['brand_id'] == $brand['id']) || $prod['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($brand['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label fw-semibold">Status</label>
                            <select class="form-select bg-white py-2" id="status" name="status">
                                <option value="active" <?php echo $prod['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $prod['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="primary_image" class="form-label fw-semibold">Primary Image</label>
                            <input type="file" class="form-control bg-white" id="primary_image" name="primary_image" accept="image/*">
                            <?php if ($action === 'edit' && $prod['image']): ?>
                                <div class="mt-2">
                                    <img src="../<?php echo $prod['image']; ?>" class="img-thumbnail" style="max-height: 80px;" alt="">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="secondary_images" class="form-label fw-semibold">Secondary Images</label>
                            <input type="file" class="form-control bg-white" id="secondary_images" name="secondary_images[]" accept="image/*" multiple>
                            <?php if ($action === 'edit' && !empty($sec_images)): ?>
                                <div class="row g-2 mt-2">
                                    <?php foreach ($sec_images as $img): ?>
                                        <div class="col-4 position-relative">
                                            <img src="../<?php echo $img['image_path']; ?>" class="img-thumbnail" style="width: 100%; height: 60px; object-fit: cover;" alt="">
                                            <a href="products.php?action=remove_sec_img&img_id=<?php echo $img['id']; ?>&prod_id=<?php echo $prod['id']; ?>" 
                                               class="btn btn-danger btn-xs position-absolute top-0 end-0 p-1 py-0 fs-6 rounded" 
                                               style="font-size: 0.7rem; line-height: 1;"
                                               onclick="return confirm('Remove this image?');">&times;</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex gap-2">
                <button type="submit" class="btn btn-primary-custom fw-bold"><i class="fa-solid fa-save me-1"></i> Save Product</button>
                <a href="products.php" class="btn btn-outline-secondary fw-semibold">Cancel</a>
            </div>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            const slugInput = document.getElementById('slug');

            nameInput.addEventListener('input', function() {
                let slug = nameInput.value.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                slugInput.value = slug.trim();
            });
        });
        </script>

    <?php else: 
        $activeTab = sanitize($_GET['tab'] ?? 'products');
        $csrf = generateCSRFToken();

        try {
            $products = $db->query("
                SELECT p.*, c.name as category_name, b.name as brand_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                ORDER BY p.id DESC
            ")->fetchAll();

            $all_categories = $db->query("
                SELECT c.*, p.name as parent_name 
                FROM categories c 
                LEFT JOIN categories p ON c.parent_id = p.id 
                ORDER BY c.name ASC
            ")->fetchAll();

            $all_brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
            $parent_categories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll();
        } catch (PDOException $e) {
            die("Error loading database tables: " . $e->getMessage());
        }
        ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs border-bottom mb-4" id="productsAdminTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'products' ? 'active' : ''; ?>" id="products-tab" data-bs-toggle="tab" data-bs-target="#products-pane" type="button" role="tab">
                    <i class="fa-solid fa-boxes me-1.5"></i>Products List
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab">
                    <i class="fa-solid fa-tags me-1.5"></i>Product Categories
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'brands' ? 'active' : ''; ?>" id="brands-tab" data-bs-toggle="tab" data-bs-target="#brands-pane" type="button" role="tab">
                    <i class="fa-solid fa-copyright me-1.5"></i>Brands
                </button>
            </li>
        </ul>

        <!-- Tab Contents -->
        <div class="tab-content" id="productsAdminTabsContent">
            
            <!-- Pane 1: Products Inventory -->
            <div class="tab-pane fade <?php echo $activeTab === 'products' ? 'show active' : ''; ?>" id="products-pane" role="tabpanel" aria-labelledby="products-tab">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle w-100" id="productsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td>
                                        <?php if ($p['image'] && file_exists('../' . $p['image'])): ?>
                                            <img src="../<?php echo $p['image']; ?>" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;" alt="">
                                        <?php else: ?>
                                            <div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                <i class="fa-solid fa-box text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-secondary"><?php echo sanitize($p['name']); ?></div>
                                        <small class="text-muted">Slug: <?php echo sanitize($p['slug']); ?></small>
                                        <?php if ($p['brand_name']): ?>
                                            <span class="badge bg-secondary-subtle text-dark border text-uppercase" style="font-size: 0.65rem;"><?php echo sanitize($p['brand_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize($p['category_name']); ?></td>
                                    <td>
                                        <?php if ($p['discount_price']): ?>
                                            <span class="text-danger fw-bold">$<?php echo number_format($p['discount_price'], 2); ?></span>
                                            <span class="text-muted text-decoration-line-through small d-block">$<?php echo number_format($p['price'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold">$<?php echo number_format($p['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['stock_quantity'] <= 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ($p['stock_quantity'] <= 5): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $p['stock_quantity']; ?> (Low)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?php echo $p['stock_quantity']; ?> in stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $p['status'] === 'active' ? 'success' : 'secondary'; ?>-subtle text-<?php echo $p['status'] === 'active' ? 'success' : 'dark'; ?> border">
                                            <?php echo ucfirst($p['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="products.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger delete-btn" data-type="product" data-id="<?php echo $p['id']; ?>" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pane 2: Product Categories -->
            <div class="tab-pane fade <?php echo $activeTab === 'categories' ? 'show active' : ''; ?>" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card card-custom border-0 p-4 shadow-sm bg-light">
                            <h5 class="fw-bold mb-3" id="categoryFormTitle">Add Shop Category</h5>
                            <form method="POST" id="categoryForm" action="products.php?action=add_category">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="category_id" id="categoryId" value="0">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Category Name</label>
                                    <input type="text" name="name" id="categoryName" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Slug</label>
                                    <input type="text" name="slug" id="categorySlug" class="form-control" placeholder="auto-generated-slug" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Parent Category (Optional)</label>
                                    <select name="parent_id" id="categoryParentId" class="form-select">
                                        <option value="">-- None (Root Category) --</option>
                                        <?php foreach ($parent_categories as $pcat): ?>
                                            <option value="<?php echo $pcat['id']; ?>"><?php echo sanitize($pcat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" id="categoryDescription" class="form-control" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary-custom w-100 py-2"><i class="fa-solid fa-save me-1"></i>Save Category</button>
                                <button type="button" class="btn btn-light w-100 mt-2 border d-none" id="cancelCatEdit">Cancel Edit</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle w-100" id="categoriesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Slug</th>
                                        <th>Parent Category</th>
                                        <th>Description</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_categories as $cat): ?>
                                        <tr>
                                            <td><strong class="text-dark"><?php echo sanitize($cat['name']); ?></strong></td>
                                            <td><code><?php echo sanitize($cat['slug']); ?></code></td>
                                            <td><?php echo $cat['parent_name'] ? '<span class="badge bg-light text-dark border">' . sanitize($cat['parent_name']) . '</span>' : '<span class="text-muted small">Root</span>'; ?></td>
                                            <td><small class="text-muted"><?php echo sanitize($cat['description']); ?></small></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-primary edit-cat-btn" 
                                                            data-id="<?php echo $cat['id']; ?>" 
                                                            data-name="<?php echo sanitize($cat['name']); ?>"
                                                            data-slug="<?php echo sanitize($cat['slug']); ?>"
                                                            data-parent="<?php echo $cat['parent_id']; ?>"
                                                            data-desc="<?php echo sanitize($cat['description']); ?>"
                                                            title="Edit">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-type="category" data-id="<?php echo $cat['id']; ?>" title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pane 3: Product Brands -->
            <div class="tab-pane fade <?php echo $activeTab === 'brands' ? 'show active' : ''; ?>" id="brands-pane" role="tabpanel" aria-labelledby="brands-tab">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card card-custom border-0 p-4 shadow-sm bg-light">
                            <h5 class="fw-bold mb-3" id="brandFormTitle">Add Brand</h5>
                            <form method="POST" id="brandForm" action="products.php?action=add_brand">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="brand_id" id="brandId" value="0">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Brand Name</label>
                                    <input type="text" name="name" id="brandName" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" id="brandDescription" class="form-control" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary-custom w-100 py-2"><i class="fa-solid fa-save me-1"></i>Save Brand</button>
                                <button type="button" class="btn btn-light w-100 mt-2 border d-none" id="cancelBrandEdit">Cancel Edit</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle w-100" id="brandsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Brand Name</th>
                                        <th>Description</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_brands as $brand): ?>
                                        <tr>
                                            <td><strong class="text-dark"><?php echo sanitize($brand['name']); ?></strong></td>
                                            <td><small class="text-muted"><?php echo sanitize($brand['description']); ?></small></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-primary edit-brand-btn" 
                                                            data-id="<?php echo $brand['id']; ?>" 
                                                            data-name="<?php echo sanitize($brand['name']); ?>"
                                                            data-desc="<?php echo sanitize($brand['description']); ?>"
                                                            title="Edit">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-type="brand" data-id="<?php echo $brand['id']; ?>" title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Initialize DataTables and Events -->
        <script>
        $(document).ready(function() {
            // Initialize DataTables
            if ($.fn.DataTable) {
                $('#productsTable').DataTable({
                    order: [[1, 'asc']],
                    pageLength: 10,
                    responsive: true
                });
                $('#categoriesTable').DataTable({
                    order: [[0, 'asc']],
                    pageLength: 10,
                    responsive: true
                });
                $('#brandsTable').DataTable({
                    order: [[0, 'asc']],
                    pageLength: 10,
                    responsive: true
                });
            }

            // Slug generator for Category form
            $('#categoryName').on('input', function() {
                let slug = $(this).val().toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                $('#categorySlug').val(slug.trim());
            });

            // Edit Category prefill
            $(document).on('click', '.edit-cat-btn', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var slug = $(this).data('slug');
                var parent = $(this).data('parent');
                var desc = $(this).data('desc');

                $('#categoryId').val(id);
                $('#categoryName').val(name);
                $('#categorySlug').val(slug);
                $('#categoryParentId').val(parent);
                $('#categoryDescription').val(desc);
                $('#categoryFormTitle').text("Edit Category: " + name);
                $('#categoryForm').attr('action', 'products.php?action=edit_category');
                $('#cancelCatEdit').removeClass('d-none');
                
                $('html, body').animate({
                    scrollTop: $("#categoryForm").offset().top - 100
                }, 300);
            });

            $('#cancelCatEdit').on('click', function() {
                $('#categoryId').val(0);
                $('#categoryName').val('');
                $('#categorySlug').val('');
                $('#categoryParentId').val('');
                $('#categoryDescription').val('');
                $('#categoryFormTitle').text("Add Shop Category");
                $('#categoryForm').attr('action', 'products.php?action=add_category');
                $(this).addClass('d-none');
            });

            // Edit Brand prefill
            $(document).on('click', '.edit-brand-btn', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var desc = $(this).data('desc');

                $('#brandId').val(id);
                $('#brandName').val(name);
                $('#brandDescription').val(desc);
                $('#brandFormTitle').text("Edit Brand: " + name);
                $('#brandForm').attr('action', 'products.php?action=edit_brand');
                $('#cancelBrandEdit').removeClass('d-none');

                $('html, body').animate({
                    scrollTop: $("#brandForm").offset().top - 100
                }, 300);
            });

            $('#cancelBrandEdit').on('click', function() {
                $('#brandId').val(0);
                $('#brandName').val('');
                $('#brandDescription').val('');
                $('#brandFormTitle').text("Add Brand");
                $('#brandForm').attr('action', 'products.php?action=add_brand');
                $(this).addClass('d-none');
            });

            // Event Delegated SweetAlert Confirm Delete (fixes paginated click bugs!)
            $(document).on('click', '.delete-btn', function() {
                const id = $(this).data('id');
                const type = $(this).data('type') || 'product';
                
                let title = 'Are you sure?';
                let text = "You won't be able to revert this! All associated item records will be deleted.";
                let deleteUrl = 'products.php?action=delete&id=' + id;
                
                if (type === 'category') {
                    title = 'Delete Category?';
                    text = 'Warning: This will fail if products belong to this category.';
                    deleteUrl = 'products.php?action=delete_category&id=' + id;
                } else if (type === 'brand') {
                    title = 'Delete Brand?';
                    text = 'Warning: This will fail if products are linked to this brand.';
                    deleteUrl = 'products.php?action=delete_brand&id=' + id;
                }
                
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = deleteUrl;
                    }
                });
            });

            // Handle switching tabs based on URL query parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabEl = document.querySelector(`#${tabParam}-tab`);
                if (tabEl) {
                    bootstrap.Tab.getOrCreateInstance(tabEl).show();
                }
            }
        });
        </script>

    <?php endif; ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
