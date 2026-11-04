<?php
/**
 * SkillUp Courses Administration Panel
 * Flourish Techeng Limited
 */

// Load config and authentication
$configPath = dirname(__DIR__) . '/config/';
require_once $configPath . 'db.php';
require_once $configPath . 'auth.php';

// Enforce admin login
requireAdmin();

$action = sanitize($_GET['action'] ?? '');
$db = getDB();
$errors = [];
$success = "";

// Ensure directories exist
$courseUploadDir = '../uploads/courses/';
$materialsUploadDir = '../uploads/materials/';
if (!is_dir($courseUploadDir)) mkdir($courseUploadDir, 0755, true);
if (!is_dir($materialsUploadDir)) mkdir($materialsUploadDir, 0755, true);

// -----------------------------------------------------------------------------
// POST HANDLER: ADD / EDIT COURSE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Please reload the page.";
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = sanitize($_POST['title'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = $_POST['description'] ?? '';
        $outline = $_POST['outline'] ?? '';
        $duration = sanitize($_POST['duration'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');

        if (empty($title)) $errors[] = "Course title is required.";
        if ($category_id <= 0) $errors[] = "Please select a category.";
        if (empty($duration)) $errors[] = "Course duration is required.";
        if ($price < 0) $errors[] = "Price cannot be negative.";

        // Image banner handling
        $image_path = "";
        if (empty($errors)) {
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['image']['tmp_name'];
                $fileName = $_FILES['image']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = uniqid('course_', true) . '.' . $fileExtension;
                    $destPath = $courseUploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $image_path = 'uploads/courses/' . $newFileName;
                        // Delete old banner if editing
                        if ($action === 'edit' && $id > 0) {
                            $old_img = $db->query("SELECT image FROM courses WHERE id = $id")->fetchColumn();
                            if ($old_img && file_exists('../' . $old_img)) {
                                unlink('../' . $old_img);
                            }
                        }
                    } else {
                        $errors[] = "Error moving uploaded banner image.";
                    }
                } else {
                    $errors[] = "Invalid image format. Allowed: " . implode(', ', $allowedExtensions);
                }
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $sql = "INSERT INTO courses (category_id, title, description, outline, duration, price, image, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$category_id, $title, $description, $outline, $duration, $price, $image_path, $status]);
                    setFlashMsg('success', "Course successfully added.");
                } else {
                    $sql = "UPDATE courses SET category_id = ?, title = ?, description = ?, outline = ?, duration = ?, price = ?, status = ?";
                    $params = [$category_id, $title, $description, $outline, $duration, $price, $status];

                    if (!empty($image_path)) {
                        $sql .= ", image = ?";
                        $params[] = $image_path;
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $id;

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    setFlashMsg('success', "Course successfully updated.");
                }
                header("Location: courses.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database save failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: ADD / EDIT COURSE CATEGORY
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add_category', 'edit_category'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security validation failed.";
    } else {
        $id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name)) $errors[] = "Category name is required.";

        if (empty($errors)) {
            try {
                if ($action === 'add_category') {
                    $stmt = $db->prepare("INSERT INTO course_categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    setFlashMsg('success', "Category successfully added.");
                } else {
                    $stmt = $db->prepare("UPDATE course_categories SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    setFlashMsg('success', "Category successfully updated.");
                }
                header("Location: courses.php?tab=categories");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Category save failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: ADD TRAINING MATERIAL
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_material') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security validation failed.";
    } else {
        $course_id = (int)$_POST['course_id'];
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if ($course_id <= 0) $errors[] = "Please select a course.";
        if (empty($title)) $errors[] = "Material title is required.";

        $material_path = "";
        $file_type = "";
        
        if (empty($errors)) {
            if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['material_file']['tmp_name'];
                $fileName = $_FILES['material_file']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'mp4', 'doc', 'docx', 'ppt', 'pptx', 'zip'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFileName = uniqid('mat_', true) . '.' . $fileExtension;
                    $destPath = $materialsUploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $material_path = 'uploads/materials/' . $newFileName;
                        $file_type = $fileExtension;
                    } else {
                        $errors[] = "Failed to save file to destination.";
                    }
                } else {
                    $errors[] = "Invalid file type. Allowed formats: " . implode(', ', $allowedExtensions);
                }
            } else {
                $errors[] = "Please upload a training material file.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO training_materials (course_id, title, description, file_path, file_type, status) 
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$course_id, $title, $description, $material_path, $file_type]);
                setFlashMsg('success', "Training material uploaded.");
                header("Location: courses.php?tab=materials");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Material upload save failed: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: UPDATE ENROLLMENT & GRADE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_enrollment') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch.";
    } else {
        $enrollmentId = (int)$_POST['enrollment_id'];
        $status = sanitize($_POST['status'] ?? 'Enrolled');
        $paymentStatus = sanitize($_POST['payment_status'] ?? 'Pending');

        if ($enrollmentId > 0) {
            try {
                // Update Enrollment record
                $stmt = $db->prepare("UPDATE enrollments SET status = ?, payment_status = ? WHERE id = ?");
                $stmt->execute([$status, $paymentStatus, $enrollmentId]);

                // Fetch details for notification & certificate
                $eQuery = $db->prepare("
                    SELECT e.*, s.full_name as student_name, s.id as student_id, c.title as course_title, c.duration 
                    FROM enrollments e
                    JOIN students s ON e.student_id = s.id
                    JOIN courses c ON e.course_id = c.id
                    WHERE e.id = ?
                ");
                $eQuery->execute([$enrollmentId]);
                $enrollment = $eQuery->fetch();

                if ($enrollment) {
                    // Send notification to student
                    $msg = "Your enrollment in '" . $enrollment['course_title'] . "' status was updated to '" . $status . "' (Payment: " . $paymentStatus . ").";
                    $db->prepare("
                        INSERT INTO notifications (user_id, user_type, title, message) 
                        VALUES (?, 'student', 'Enrollment Status Update', ?)
                    ")->execute([$enrollment['student_id'], $msg]);

                    // Generate Certificate if status is Completed
                    if ($status === 'Completed') {
                        // Check if certificate already exists
                        $cCheck = $db->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
                        $cCheck->execute([$enrollment['student_id'], $enrollment['course_id']]);
                        
                        if (!$cCheck->fetch()) {
                            $certNum = "FT-2026-" . strtoupper(substr(md5(uniqid()), 0, 6));
                            $issueDate = date('Y-m-d');
                            $certPath = 'uploads/certificates/' . $certNum . '.html';
                            
                            // Generate static certificate document
                            $certDir = '../uploads/certificates/';
                            if (!is_dir($certDir)) mkdir($certDir, 0755, true);

                            $certHTML = "
                            <!DOCTYPE html>
                            <html lang='en'>
                            <head>
                                <meta charset='UTF-8'>
                                <title>Certificate of Completion - $certNum</title>
                                <style>
                                    body { font-family: 'Outfit', sans-serif; text-align: center; padding: 50px; background-color: #f3f4f6; }
                                    .cert-frame { border: 15px solid #111827; padding: 40px; background: white; max-width: 800px; margin: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
                                    h1 { color: #10B981; font-size: 2.5rem; text-transform: uppercase; margin-bottom: 5px; }
                                    h2 { color: #111827; font-size: 2rem; border-bottom: 2px solid #eee; display: inline-block; padding-bottom: 5px; margin: 20px 0; }
                                    .footer-text { margin-top: 50px; display: flex; justify-content: space-between; font-size: 0.85rem; color: #4b5563; }
                                </style>
                            </head>
                            <body>
                                <div class='cert-frame'>
                                    <h1>Certificate of Completion</h1>
                                    <p style='font-style: italic; color: #6b7280;'>This is proudly presented to</p>
                                    <h2>" . htmlspecialchars($enrollment['student_name']) . "</h2>
                                    <p>for successfully completing technical skills training in</p>
                                    <h3>" . htmlspecialchars($enrollment['course_title']) . " (" . htmlspecialchars($enrollment['duration']) . ")</h3>
                                    <div class='footer-text'>
                                        <div>Cert No: <strong>$certNum</strong></div>
                                        <div>Issue Date: <strong>" . date('d M, Y', strtotime($issueDate)) . "</strong></div>
                                        <div>Flourish Techeng Limited</div>
                                    </div>
                                </div>
                            </body>
                            </html>";

                            file_put_contents('../' . $certPath, $certHTML);

                            // Insert into DB
                            $insCert = $db->prepare("
                                INSERT INTO certificates (student_id, course_id, certificate_number, issue_date, file_path) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $insCert->execute([
                                $enrollment['student_id'],
                                $enrollment['course_id'],
                                $certNum,
                                $issueDate,
                                $certPath
                            ]);

                            // Send Certificate Notification
                            $db->prepare("
                                INSERT INTO notifications (user_id, user_type, title, message) 
                                VALUES (?, 'student', 'Certificate Issued!', ?)
                            ")->execute([
                                $enrollment['student_id'], 
                                "Congratulations! Your technical completion certificate has been generated. Certificate Reference: $certNum. You can print it directly from your dashboard."
                            ]);
                        }
                    }
                }

                setFlashMsg('success', "Enrollment record updated.");
                header("Location: courses.php?tab=enrollments");
                exit;

            } catch (PDOException $e) {
                $errors[] = "Failed to update enrollment: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// GET HANDLER: DELETE ACTIONS (Course / Category / Material)
// -----------------------------------------------------------------------------
if ($action === 'delete') {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        try {
            // Delete image file first
            $img = $db->query("SELECT image FROM courses WHERE id = $id")->fetchColumn();
            if ($img && file_exists('../' . $img)) unlink('../' . $img);

            $db->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
            setFlashMsg('success', "Course deleted successfully.");
        } catch (PDOException $e) {
            setFlashMsg('error', "Could not delete course: " . $e->getMessage());
        }
    }
    header("Location: courses.php");
    exit;
}

if ($action === 'delete_category') {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        try {
            $db->prepare("DELETE FROM course_categories WHERE id = ?")->execute([$id]);
            setFlashMsg('success', "Category deleted.");
        } catch (PDOException $e) {
            setFlashMsg('error', "Could not delete category: " . $e->getMessage());
        }
    }
    header("Location: courses.php?tab=categories");
    exit;
}

if ($action === 'delete_material') {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        try {
            $file = $db->query("SELECT file_path FROM training_materials WHERE id = $id")->fetchColumn();
            if ($file && file_exists('../' . $file)) unlink('../' . $file);

            $db->prepare("DELETE FROM training_materials WHERE id = ?")->execute([$id]);
            setFlashMsg('success', "Material removed.");
        } catch (PDOException $e) {
            setFlashMsg('error', "Could not remove material: " . $e->getMessage());
        }
    }
    header("Location: courses.php?tab=materials");
    exit;
}

// -----------------------------------------------------------------------------
// GET: LOAD DATA FOR VIEWS
// -----------------------------------------------------------------------------
try {
    $categories = $db->query("SELECT * FROM course_categories ORDER BY name ASC")->fetchAll();
    
    $courses = $db->query("
        SELECT c.*, cc.name as category_name 
        FROM courses c
        JOIN course_categories cc ON c.category_id = cc.id
        ORDER BY c.id DESC
    ")->fetchAll();

    $materials = $db->query("
        SELECT tm.*, c.title as course_title 
        FROM training_materials tm
        JOIN courses c ON tm.course_id = c.id
        ORDER BY tm.id DESC
    ")->fetchAll();

    $enrollments = $db->query("
        SELECT e.id as enrollment_id, e.status as enrollment_status, e.payment_status, e.enrollment_date,
               s.full_name as student_name, s.email as student_email, c.title as course_title
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN courses c ON e.course_id = c.id
        ORDER BY e.id DESC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Error loading admin data: " . $e->getMessage());
}

$activeTab = sanitize($_GET['tab'] ?? 'courses');

// Include admin header which outputs HTML layout
require_once 'includes/admin_header.php';

// -----------------------------------------------------------------------------
// RENDER ADD / EDIT FORM
// -----------------------------------------------------------------------------
if (in_array($action, ['add', 'edit'])):
    $course = ['id' => 0, 'title' => '', 'category_id' => 0, 'description' => '', 'outline' => '', 'duration' => '', 'price' => 0, 'status' => 'active', 'image' => ''];
    if ($action === 'edit') {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();
        if (!$course) {
            setFlashMsg('error', "Course record not found.");
            header("Location: courses.php");
            exit;
        }
    }
    $csrf = generateCSRFToken();
?>
    <div class="card card-custom border-0 p-4 shadow-sm">
        <h4 class="fw-bold mb-4">
            <i class="fa-solid fa-graduation-cap text-primary-custom me-2"></i>
            <?php echo $action === 'add' ? 'Add New Training Course' : 'Edit Course: ' . sanitize($course['title']); ?>
        </h4>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="courses.php?action=<?php echo $action; ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="id" value="<?php echo $course['id']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo sanitize($course['title']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Training Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $course['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Duration (e.g. 4 Weeks)</label>
                    <input type="text" name="duration" class="form-control" value="<?php echo sanitize($course['duration']); ?>" placeholder="e.g., 4 Weeks" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Price ($)</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo (float)$course['price']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?php echo $course['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $course['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo sanitize($course['description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Syllabus Outline (Markdown/Text)</label>
                    <textarea name="outline" class="form-control" rows="4" placeholder="Module 1: ...&#10;Module 2: ..."><?php echo sanitize($course['outline']); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course Banner/Image</label>
                    <input type="file" name="image" class="form-control">
                    <?php if (!empty($course['image'])): ?>
                        <small class="text-muted d-block mt-1">Current Banner: <?php echo sanitize($course['image']); ?></small>
                    <?php endif; ?>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="courses.php" class="btn btn-light border px-4 me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary-custom px-4">Save Course</button>
                </div>
            </div>
        </form>
    </div>

<?php else: 
    $csrf = generateCSRFToken();
?>
    <!-- Top Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="admin-card">
                <h6 class="text-muted uppercase small">Total Courses</h6>
                <h3 class="fw-bold mb-0 text-dark"><?php echo count($courses); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card orders">
                <h6 class="text-muted uppercase small">Materials Uploaded</h6>
                <h3 class="fw-bold mb-0 text-dark"><?php echo count($materials); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card students">
                <h6 class="text-muted uppercase small">Total Enrollments</h6>
                <h3 class="fw-bold mb-0 text-dark"><?php echo count($enrollments); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-card revenue">
                <h6 class="text-muted uppercase small">Categories</h6>
                <h3 class="fw-bold mb-0 text-dark"><?php echo count($categories); ?></h3>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <ul class="nav nav-tabs border-bottom mb-4" id="coursesAdminTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'courses' ? 'active' : ''; ?>" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses-pane" type="button" role="tab">
                <i class="fa-solid fa-graduation-cap me-1.5"></i>Manage Courses
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab">
                <i class="fa-solid fa-tags me-1.5"></i>Categories
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'materials' ? 'active' : ''; ?>" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials-pane" type="button" role="tab">
                <i class="fa-solid fa-folder-open me-1.5"></i>Training Materials
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold py-2.5 px-4 <?php echo $activeTab === 'enrollments' ? 'active' : ''; ?>" id="enrollments-tab" data-bs-toggle="tab" data-bs-target="#enrollments-pane" type="button" role="tab">
                <i class="fa-solid fa-users me-1.5"></i>Enrollments & Grading
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="coursesAdminTabsContent">
        
        <!-- Tab 1: Manage Courses -->
        <div class="tab-pane fade <?php echo $activeTab === 'courses' ? 'show active' : ''; ?>" id="courses-pane" role="tabpanel" aria-labelledby="courses-tab">
            <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Academy Course Catalog</h5>
                    <a href="courses.php?action=add" class="btn btn-primary-custom btn-sm"><i class="fa-solid fa-plus me-1"></i>Add Course</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="coursesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Image</th>
                                <th>Course Title</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($c['image'])): ?>
                                            <img src="../<?php echo sanitize($c['image']); ?>" width="60" class="rounded border" alt="">
                                        <?php else: ?>
                                            <div class="bg-light border text-center text-muted rounded" style="width:60px; height:40px; line-height:40px;"><i class="fa-solid fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong class="text-dark"><?php echo sanitize($c['title']); ?></strong></td>
                                    <td><?php echo sanitize($c['category_name']); ?></td>
                                    <td><?php echo sanitize($c['duration']); ?></td>
                                    <td><span class="fw-bold text-success"><?php echo $c['price'] > 0 ? '$' . number_format($c['price'], 2) : 'Free'; ?></span></td>
                                    <td>
                                        <span class="badge <?php echo $c['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($c['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="courses.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-light border me-1"><i class="fa-solid fa-pencil"></i></a>
                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-type="course" data-id="<?php echo $c['id']; ?>"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Categories -->
        <div class="tab-pane fade <?php echo $activeTab === 'categories' ? 'show active' : ''; ?>" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h5 class="fw-bold mb-3" id="categoryFormTitle">Add Course Category</h5>
                        <form method="POST" id="categoryForm" action="courses.php?action=add_category">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="category_id" id="categoryId" value="0">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category Name</label>
                                <input type="text" name="name" id="categoryName" class="form-control" required>
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
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h5 class="fw-bold mb-3">Active Training Categories</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><strong class="text-dark"><?php echo sanitize($cat['name']); ?></strong></td>
                                            <td class="text-muted small"><?php echo sanitize($cat['description']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-light border edit-cat-btn" 
                                                        data-id="<?php echo $cat['id']; ?>" 
                                                        data-name="<?php echo sanitize($cat['name']); ?>" 
                                                        data-desc="<?php echo sanitize($cat['description']); ?>">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-btn" data-type="category" data-id="<?php echo $cat['id']; ?>"><i class="fa-solid fa-trash"></i></button>
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

        <!-- Tab 3: Training Materials -->
        <div class="tab-pane fade <?php echo $activeTab === 'materials' ? 'show active' : ''; ?>" id="materials-pane" role="tabpanel" aria-labelledby="materials-tab">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h5 class="fw-bold mb-3">Upload Course Materials</h5>
                        <form method="POST" action="courses.php?action=add_material" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Course</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">-- Choose Course --</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Material Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Lecture Notes 1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Short Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select File</label>
                                <input type="file" name="material_file" class="form-control" required>
                                <small class="text-muted d-block mt-1">Allowed: PDF, MP4, Word, PowerPoint, ZIP</small>
                            </div>
                            <button type="submit" class="btn btn-primary-custom w-100 py-2"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Document</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h5 class="fw-bold mb-3">Learning Library Inventory</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="materialsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course</th>
                                        <th>Material Title</th>
                                        <th>Format</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $mat): ?>
                                        <tr>
                                            <td><small class="fw-bold text-muted"><?php echo sanitize($mat['course_title']); ?></small></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo sanitize($mat['title']); ?></div>
                                                <small class="text-muted d-block"><?php echo sanitize($mat['description']); ?></small>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo strtoupper($mat['file_type']); ?></span></td>
                                            <td>
                                                <a href="../<?php echo sanitize($mat['file_path']); ?>" target="_blank" class="btn btn-sm btn-light border me-1"><i class="fa-solid fa-download"></i></a>
                                                <button class="btn btn-sm btn-outline-danger delete-btn" data-type="material" data-id="<?php echo $mat['id']; ?>"><i class="fa-solid fa-trash"></i></button>
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

        <!-- Tab 4: Enrollments & Grading -->
        <div class="tab-pane fade <?php echo $activeTab === 'enrollments' ? 'show active' : ''; ?>" id="enrollments-pane" role="tabpanel" aria-labelledby="enrollments-tab">
            <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                <h5 class="fw-bold mb-3">Student Enrolled Classes</h5>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="enrollmentsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student Details</th>
                                <th>Course Title</th>
                                <th>Enrollment Date</th>
                                <th>Payment</th>
                                <th>Learning Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $e): 
                                $statusClass = 'bg-secondary';
                                if ($e['enrollment_status'] === 'Enrolled') $statusClass = 'bg-warning text-dark';
                                elseif ($e['enrollment_status'] === 'In Progress') $statusClass = 'bg-info';
                                elseif ($e['enrollment_status'] === 'Completed') $statusClass = 'bg-success';
                                elseif ($e['enrollment_status'] === 'Dropped') $statusClass = 'bg-danger';

                                $payClass = 'bg-secondary';
                                if ($e['payment_status'] === 'Pending') $payClass = 'bg-danger text-white';
                                elseif ($e['payment_status'] === 'Paid') $payClass = 'bg-success text-white';
                                elseif ($e['payment_status'] === 'Free') $payClass = 'bg-info text-white';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo sanitize($e['student_name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($e['student_email']); ?></small>
                                    </td>
                                    <td><strong class="text-secondary"><?php echo sanitize($e['course_title']); ?></strong></td>
                                    <td><?php echo date('d M, Y', strtotime($e['enrollment_date'])); ?></td>
                                    <td><span class="badge <?php echo $payClass; ?> px-2.5 py-1.5 rounded"><?php echo $e['payment_status']; ?></span></td>
                                    <td><span class="badge <?php echo $statusClass; ?> px-2.5 py-1.5 rounded-pill"><?php echo $e['enrollment_status']; ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-light border edit-enrollment-btn" 
                                                data-id="<?php echo $e['enrollment_id']; ?>" 
                                                data-name="<?php echo sanitize($e['student_name']); ?>"
                                                data-course="<?php echo sanitize($e['course_title']); ?>"
                                                data-status="<?php echo $e['enrollment_status']; ?>" 
                                                data-pay="<?php echo $e['payment_status']; ?>">
                                            <i class="fa-solid fa-sliders-h me-1"></i>Manage
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
<?php endif; ?>

<!-- Enrollment Grading/Status Modal -->
<div class="modal fade" id="enrollmentModal" tabindex="-1" aria-labelledby="enrollmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary-custom text-white border-0 py-3.5 rounded-top-4">
                <h5 class="modal-title fw-bold" id="enrollmentModalLabel"><i class="fa-solid fa-graduation-cap me-2"></i>Grading & Student Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="courses.php?action=update_enrollment">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="enrollment_id" id="modalEnrollmentId" value="0">

                    <div class="mb-3 bg-light p-3 rounded-3">
                        <div class="small text-muted mb-1">Student</div>
                        <h6 class="fw-bold text-dark mb-2" id="modalStudentName">Student Name</h6>
                        <div class="small text-muted mb-1">Course</div>
                        <h6 class="fw-bold text-secondary mb-0" id="modalCourseTitle">Course Title</h6>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Status</label>
                        <select name="payment_status" id="modalPaymentStatus" class="form-select" required>
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Free">Free</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Learning Progress Status</label>
                        <select name="status" id="modalEnrollmentStatus" class="form-select" required>
                            <option value="Enrolled">Enrolled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed (Auto-generates Certificate)</option>
                            <option value="Dropped">Dropped</option>
                        </select>
                        <small class="text-danger d-block mt-1"><i class="fa-solid fa-circle-exclamation me-1 small"></i>Selecting 'Completed' generates verification record & signed document.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary btn-sm px-3 rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom btn-sm px-4 rounded-pill fw-bold">Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialise DataTables if library loaded
    if ($.fn.DataTable) {
        $('#coursesTable').DataTable({ pageLength: 10, ordering: true });
        $('#materialsTable').DataTable({ pageLength: 10, ordering: true });
        $('#enrollmentsTable').DataTable({ pageLength: 10, ordering: true });
    }

    // Category Edit inline prefiller
    $(document).on('click', '.edit-cat-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var desc = $(this).data('desc');

        $('#categoryId').val(id);
        $('#categoryName').val(name);
        $('#categoryDescription').val(desc);
        $('#categoryFormTitle').text("Edit Category: " + name);
        $('#categoryForm').attr('action', 'courses.php?action=edit_category');
        $('#cancelCatEdit').removeClass('d-none');
    });

    $('#cancelCatEdit').on('click', function() {
        $('#categoryId').val(0);
        $('#categoryName').val('');
        $('#categoryDescription').val('');
        $('#categoryFormTitle').text("Add Course Category");
        $('#categoryForm').attr('action', 'courses.php?action=add_category');
        $(this).addClass('d-none');
    });

    // Enrollment grading modal prefiller
    $(document).on('click', '.edit-enrollment-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var course = $(this).data('course');
        var status = $(this).data('status');
        var pay = $(this).data('pay');

        $('#modalEnrollmentId').val(id);
        $('#modalStudentName').text(name);
        $('#modalCourseTitle').text(course);
        $('#modalEnrollmentStatus').val(status);
        $('#modalPaymentStatus').val(pay);

        var modal = new bootstrap.Modal(document.getElementById('enrollmentModal'));
        modal.show();
    });

    // Delete Confirmation
    $(document).on('click', '.delete-btn', function() {
        var type = $(this).data('type');
        var id = $(this).data('id');
        var deleteUrl = 'courses.php?action=delete&id=' + id;
        
        if (type === 'category') {
            deleteUrl = 'courses.php?action=delete_category&id=' + id;
        } else if (type === 'material') {
            deleteUrl = 'courses.php?action=delete_material&id=' + id;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    });

    // Handle switching tabs based on query parameters
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

<?php 
require_once 'includes/admin_footer.php';
?>
