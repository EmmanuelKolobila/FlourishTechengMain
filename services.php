<?php
/**
 * Services and Quotations Request Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

$db = getDB();
$errors = [];
$success = "";

// Ensure upload directory exists
$uploadDir = 'uploads/quotations/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Fetch all services
try {
    $services = $db->query("SELECT * FROM services WHERE status = 'active'")->fetchAll();
} catch (PDOException $e) {
    die("Database error loading services: " . $e->getMessage());
}

// Auto-select service from request ID parameter
$reqServiceId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

// -----------------------------------------------------------------------------
// POST HANDLER: SERVICE REQUEST
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_service_request'])) {
    if (!isCustomerLoggedIn()) {
        $errors[] = "You must log in to submit a service request.";
    } elseif (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Try again.";
    } else {
        $customerId = $_SESSION['customer_id'];
        $service_id = (int)$_POST['service_id'];
        $details = sanitize($_POST['details'] ?? '');
        $preferred_date = sanitize($_POST['preferred_date'] ?? '');

        if ($service_id <= 0) $errors[] = "Please select a service.";
        if (empty($details)) $errors[] = "Please provide project requirements or details.";
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO service_requests (customer_id, service_id, details, preferred_date, status) 
                    VALUES (?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$customerId, $service_id, $details, !empty($preferred_date) ? $preferred_date : null]);
                
                // Add notification
                $db->prepare("
                    INSERT INTO notifications (user_id, user_type, title, message) 
                    VALUES (?, 'customer', 'Service Requested', 'Your service booking request has been submitted successfully!')
                ")->execute([$customerId]);

                setFlashMsg('success', "Service request submitted! We will contact you soon.");
                header("Location: customer_dashboard.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Failed to submit request: " . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: GENERAL QUOTATION REQUEST
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quotation'])) {
    if (!isCustomerLoggedIn()) {
        $errors[] = "You must log in to request a quotation.";
    } elseif (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Try again.";
    } else {
        $customerId = $_SESSION['customer_id'];
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $company_name = sanitize($_POST['company_name'] ?? '');
        $details = sanitize($_POST['details'] ?? '');

        if (empty($full_name)) $errors[] = "Your name is required.";
        if (empty($email)) $errors[] = "Your email is required.";
        if (empty($phone)) $errors[] = "Your phone number is required.";
        if (empty($details)) $errors[] = "Project specifications/details are required.";

        // Document Upload Handling
        $file_path = null;
        if (empty($errors) && isset($_FILES['project_doc']) && $_FILES['project_doc']['error'] === UPLOAD_ERR_OK) {
            $fileName = $_FILES['project_doc']['name'];
            $fileTmp = $_FILES['project_doc']['tmp_name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];

            if (in_array($fileExt, $allowedExts)) {
                $newFileName = uniqid('bom_', true) . '.' . $fileExt;
                $dest = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $dest)) {
                    $file_path = $uploadDir . $newFileName;
                } else {
                    $errors[] = "Failed to upload requirements file.";
                }
            } else {
                $errors[] = "Invalid file type. Allowed: " . implode(', ', $allowedExts);
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO quotations (customer_id, full_name, email, phone, company_name, details, file_path, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $stmt->execute([$customerId, $full_name, $email, $phone, $company_name, $details, $file_path]);

                setFlashMsg('success', "Quotation request successfully submitted!");
                header("Location: services.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Failed to submit quotation: " . $e->getMessage();
            }
        }
    }
}

// Fetch user profile if logged in for form pre-filling
$userProfile = ['full_name' => '', 'email' => '', 'phone' => '', 'company_name' => ''];
if (isCustomerLoggedIn()) {
    try {
        $pStmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $pStmt->execute([$_SESSION['customer_id']]);
        $userProfile = $pStmt->fetch() ?: $userProfile;
    } catch (PDOException $e) {}
}
?>

<div class="container py-5">
    <div class="row text-center mb-5">
        <div class="col-lg-8 mx-auto">
            <span class="badge bg-success-subtle text-primary-custom px-3 py-2 mb-3 fw-bold rounded-pill text-uppercase">
                Engineering Services
            </span>
            <h1 class="fw-bold mb-3">Our Professional Engineering & IT Services</h1>
            <p class="text-muted leading-relaxed">
                Flourish Techeng Limited provides certified contracting services. Browse our catalog, request a site assessment, or upload a custom Bill of Materials (BOM) for project pricing.
            </p>
        </div>
    </div>

    <!-- Services Grid -->
    <div class="row g-4 mb-5">
        <?php foreach ($services as $s): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card card-custom border-0 shadow-sm h-100 p-4 bg-white border-bottom border-4 border-success">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success-subtle text-primary-custom p-3 rounded-circle me-3">
                            <i class="fa-solid fa-screwdriver-wrench fs-4"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0"><?php echo sanitize($s['name']); ?></h5>
                    </div>
                    <p class="text-muted small leading-relaxed mb-4"><?php echo sanitize($s['description']); ?></p>
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-semibold">Est: <?php echo sanitize($s['price_range']); ?></span>
                        <a href="services.php?request_id=<?php echo $s['id']; ?>#requestFormBox" class="btn btn-primary-custom btn-sm fw-semibold">Book Service</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Booking / Quotation forms -->
    <div class="row g-5 mt-5 border-top pt-5" id="requestFormBox">
        
        <!-- Form 1: Book Specific Service -->
        <div class="col-lg-6">
            <div class="card card-custom border-0 p-4 shadow bg-light h-100">
                <h4 class="fw-bold text-secondary-custom mb-3"><i class="fa-solid fa-calendar-check text-primary-custom me-2"></i>Book a Technical Service</h4>
                <p class="text-muted small mb-4">Select a specific installation or engineering service and request a booking or site survey.</p>

                <?php if (!empty($errors) && isset($_POST['submit_service_request'])): ?>
                    <div class="alert alert-danger border-0 small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err) echo '<li>' . $err . '</li>'; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!isCustomerLoggedIn()): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-lock text-muted fs-1 mb-3"></i>
                        <h5>Authentication Required</h5>
                        <p class="text-muted small">You must log in to place service bookings.</p>
                        <a href="login.php" class="btn btn-primary-custom btn-sm fw-semibold">Login to Account</a>
                    </div>
                <?php else: ?>
                    <form action="services.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Select Service -->
                        <div class="mb-3">
                            <label for="service_id" class="form-label fw-semibold">Select Service <span class="text-danger">*</span></label>
                            <select name="service_id" id="service_id" class="form-select bg-white border" required>
                                <option value="">-- Choose Service --</option>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($reqServiceId === (int)$s['id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Preferred Date -->
                        <div class="mb-3">
                            <label for="preferred_date" class="form-label fw-semibold">Preferred Schedule Date</label>
                            <input type="date" name="preferred_date" id="preferred_date" class="form-select bg-white border" min="<?php echo date('Y-m-d'); ?>">
                            <small class="text-muted">Optional. Suggest a date when you will be available for inspection.</small>
                        </div>

                        <!-- Request Details -->
                        <div class="mb-3">
                            <label for="details" class="form-label fw-semibold">Project / Problem Details <span class="text-danger">*</span></label>
                            <textarea name="details" id="details" class="form-control bg-white border" rows="4" placeholder="Describe the installation details, physical location parameters, or faults..." required></textarea>
                        </div>

                        <button type="submit" name="submit_service_request" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-sm">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit Service Request
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form 2: Request Custom Quotation / BOM Upload -->
        <div class="col-lg-6 mt-5 mt-lg-0">
            <div class="card card-custom border-0 p-4 shadow bg-light h-100">
                <h4 class="fw-bold text-secondary-custom mb-3"><i class="fa-solid fa-file-invoice-dollar text-primary-custom me-2"></i>Request Custom Quotation (BOM)</h4>
                <p class="text-muted small mb-4">Have an engineering list, drawing plan, or Bill of Materials? Upload details and get a quick custom project quote.</p>

                <?php if (!empty($errors) && isset($_POST['submit_quotation'])): ?>
                    <div class="alert alert-danger border-0 small">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err) echo '<li>' . $err . '</li>'; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!isCustomerLoggedIn()): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-lock text-muted fs-1 mb-3"></i>
                        <h5>Authentication Required</h5>
                        <p class="text-muted small">You must log in to submit quotation requests.</p>
                        <a href="login.php" class="btn btn-primary-custom btn-sm fw-semibold">Login to Account</a>
                    </div>
                <?php else: ?>
                    <form action="services.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="row">
                            <!-- Full name -->
                            <div class="col-md-6 mb-3">
                                <label for="q_name" class="form-label fw-semibold">Your Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" id="q_name" class="form-control bg-white border" value="<?php echo sanitize($userProfile['full_name']); ?>" required>
                            </div>
                            <!-- Email -->
                            <div class="col-md-6 mb-3">
                                <label for="q_email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="q_email" class="form-control bg-white border" value="<?php echo sanitize($userProfile['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Phone -->
                            <div class="col-md-6 mb-3">
                                <label for="q_phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" id="q_phone" class="form-control bg-white border" value="<?php echo sanitize($userProfile['phone']); ?>" required>
                            </div>
                            <!-- Company -->
                            <div class="col-md-6 mb-3">
                                <label for="q_company" class="form-label fw-semibold">Company Name</label>
                                <input type="text" name="company_name" id="q_company" class="form-control bg-white border" placeholder="Optional">
                            </div>
                        </div>

                        <!-- Specifications -->
                        <div class="mb-3">
                            <label for="q_details" class="form-label fw-semibold">Project Specifications <span class="text-danger">*</span></label>
                            <textarea name="details" id="q_details" class="form-control bg-white border" rows="3" placeholder="Provide list of materials required, project timelines, drawing files description..." required></textarea>
                        </div>

                        <!-- File Upload -->
                        <div class="mb-4">
                            <label for="project_doc" class="form-label fw-semibold">Upload BOM / Drawings (File)</label>
                            <input type="file" name="project_doc" id="project_doc" class="form-control bg-white border" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                            <small class="text-muted">Allowed types: PDF, Word, Excel, ZIP (Max 5MB).</small>
                        </div>

                        <button type="submit" name="submit_quotation" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-sm">
                            <i class="fa-solid fa-file-arrow-up me-1"></i> Submit Quotation Request
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
