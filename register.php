<?php
/**
 * Registration Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

// Generate CSRF token
$csrf_token = generateCSRFToken();

// If already logged in, redirect
if (isCustomerLoggedIn()) {
    header("Location: index.php");
    exit;
} elseif (isStudentLoggedIn()) {
    header("Location: student_dashboard.php");
    exit;
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Please reload the page and try again.";
    } else {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $register_student = isset($_POST['register_student']);

        // Input Validation
        if (empty($full_name)) $errors[] = "Full name is required.";
        if (!$email) $errors[] = "A valid email address is required.";
        if (empty($phone)) $errors[] = "Phone number is required.";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

        if (empty($errors)) {
            try {
                $db = getDB();
                
                // Check if email already exists in customers
                $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "Email is already registered as a customer.";
                }

                // Check if email already exists in students if registering as student
                if ($register_student) {
                    $stmt = $db->prepare("SELECT id FROM students WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $errors[] = "Email is already registered as a student.";
                    }
                }

                // If no duplicates, proceed to insert
                if (empty($errors)) {
                    $db->beginTransaction();

                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // Insert Customer
                    $stmt = $db->prepare("INSERT INTO customers (full_name, email, phone, password, address) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$full_name, $email, $phone, $hashed_password, $address]);
                    $customerId = $db->lastInsertId();

                    // Insert Student if checked
                    if ($register_student) {
                        $stmt = $db->prepare("INSERT INTO students (customer_id, full_name, email, password, phone) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$customerId, $full_name, $email, $hashed_password, $phone]);
                    }

                    $db->commit();
                    setFlashMsg('success', "Registration successful! You can now log in.");
                    header("Location: login.php");
                    exit;
                }

            } catch (PDOException $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card card-custom border-0 shadow-lg">
                <div class="card-header bg-primary-custom text-white text-center py-4 border-0">
                    <h3 class="mb-0 fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Create An Account</h3>
                    <p class="mb-0 mt-1 opacity-75">Join Flourish Techeng for Shop & SkillUp</p>
                </div>
                <div class="card-body p-5">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger border-0 shadow-sm" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="register.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-user text-muted"></i></span>
                                <input type="text" class="form-control bg-light border-0" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? sanitize($_POST['full_name']) : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Email Address -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" class="form-control bg-light border-0" id="email" name="email" value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Phone Number -->
                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-phone text-muted"></i></span>
                                <input type="tel" class="form-control bg-light border-0" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? sanitize($_POST['phone']) : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Delivery Address -->
                        <div class="mb-3">
                            <label for="address" class="form-label fw-semibold">Delivery Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-map-location-dot text-muted"></i></span>
                                <textarea class="form-control bg-light border-0" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? sanitize($_POST['address']) : ''; ?></textarea>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" class="form-control bg-light border-0" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-shield text-muted"></i></span>
                                    <input type="password" class="form-control bg-light border-0" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>

                        <!-- Enroll in SkillUp Checkbox -->
                        <div class="mb-4 form-check bg-light p-3 rounded border border-dashed">
                            <input type="checkbox" class="form-check-input ms-0 me-2" id="register_student" name="register_student" <?php echo isset($_POST['register_student']) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold text-secondary" for="register_student">
                                I also want to register for a SkillUp Student account!
                            </label>
                            <small class="d-block text-muted mt-1 ps-4">
                                This creates a student dashboard where you can view courses, training materials, and certificates.
                            </small>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold shadow-sm mb-3">
                            <i class="fa-solid fa-user-plus me-2"></i>Register Now
                        </button>

                        <div class="text-center mt-3">
                            <p class="mb-0 text-muted">Already have an account? <a href="login.php" class="text-primary-custom fw-semibold text-decoration-none">Login here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
