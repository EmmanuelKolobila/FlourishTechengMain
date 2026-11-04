<?php
/**
 * Login Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

// If already logged in, redirect
if (isCustomerLoggedIn()) {
    header("Location: index.php");
    exit;
} elseif (isStudentLoggedIn()) {
    header("Location: student_dashboard.php");
    exit;
} elseif (isAdminLoggedIn()) {
    header("Location: admin/dashboard.php");
    exit;
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Determine role based on GET parameter (pre-select tab)
$active_role = sanitize($_GET['role'] ?? 'customer');
if (!in_array($active_role, ['customer', 'student', 'admin'])) {
    $active_role = 'customer';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token mismatch. Please reload the page and try again.";
    } else {
        $role = sanitize($_POST['role'] ?? 'customer');
        $email_username = sanitize($_POST['email_username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email_username)) $errors[] = "Email/Username is required.";
        if (empty($password)) $errors[] = "Password is required.";

        if (empty($errors)) {
            try {
                $db = getDB();
                
                if ($role === 'admin') {
                    // Admin Auth (Check username or email)
                    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
                    $stmt->execute([$email_username, $email_username]);
                    $admin = $stmt->fetch();

                    if ($admin && password_verify($password, $admin['password'])) {
                        if ($admin['status'] !== 'active') {
                            $errors[] = "Your admin account is currently inactive.";
                        } else {
                            // Set session
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = $admin['id'];
                            $_SESSION['admin_email'] = $admin['email'];
                            $_SESSION['admin_name'] = $admin['full_name'];
                            $_SESSION['admin_role'] = $admin['role'];

                            setFlashMsg('success', "Welcome back, Admin!");
                            header("Location: admin/dashboard.php");
                            exit;
                        }
                    } else {
                        $errors[] = "Invalid administrator credentials.";
                    }

                } elseif ($role === 'student') {
                    // Student Auth (Check email)
                    $stmt = $db->prepare("SELECT * FROM students WHERE email = ?");
                    $stmt->execute([$email_username]);
                    $student = $stmt->fetch();

                    if ($student && password_verify($password, $student['password'])) {
                        if ($student['status'] !== 'active') {
                            $errors[] = "Your student account is currently inactive.";
                        } else {
                            // Set session
                            $_SESSION['student_logged_in'] = true;
                            $_SESSION['student_id'] = $student['id'];
                            $_SESSION['student_email'] = $student['email'];
                            $_SESSION['student_name'] = $student['full_name'];

                            setFlashMsg('success', "Welcome back to SkillUp!");
                            header("Location: student_dashboard.php");
                            exit;
                        }
                    } else {
                        $errors[] = "Invalid student credentials.";
                    }

                } else {
                    // Customer Auth (Check email)
                    $stmt = $db->prepare("SELECT * FROM customers WHERE email = ?");
                    $stmt->execute([$email_username]);
                    $customer = $stmt->fetch();

                    if ($customer && password_verify($password, $customer['password'])) {
                        if ($customer['status'] !== 'active') {
                            $errors[] = "Your account is currently inactive.";
                        } else {
                            // Set session
                            $_SESSION['customer_logged_in'] = true;
                            $_SESSION['customer_id'] = $customer['id'];
                            $_SESSION['customer_email'] = $customer['email'];
                            $_SESSION['customer_name'] = $customer['full_name'];

                            setFlashMsg('success', "Welcome back!");
                            header("Location: index.php");
                            exit;
                        }
                    } else {
                        $errors[] = "Invalid email or password.";
                    }
                }

            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    // Maintain active role tab if error occurred
    $active_role = $_POST['role'] ?? 'customer';
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <!-- own -->
         <!-- <div style="
                             background-image: url('images/so1.png');
                             background-size: cover;
                             background-position: center;
                             background-repeat: no-repeat;
                             min-height: 100vh;" class="col-lg-5 col-md-7">

         </div> -->
         <!-- own -->
        <div class="col-lg-5 col-md-7">
            <div class="card card-custom border-0 shadow-lg">
                <div class="card-header bg-primary-custom text-white text-center py-4 border-0">
                    <h3 class="mb-0 fw-bold"><i class="fa-solid fa-right-to-bracket me-2"></i>Sign In</h3>
                    <p class="mb-0 mt-1 opacity-75">Access Flourish Techeng Online Shop & SkillUp</p>
                </div>
                <div class="card-body p-5">
                    
                    <!-- Tabs for roles -->
                    <ul class="nav nav-pills nav-fill mb-4 p-1 bg-light rounded" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold rounded-pill py-2 <?php echo $active_role === 'customer' ? 'active bg-primary-custom text-white' : 'text-dark'; ?>" id="customer-tab" data-role="customer" type="button"><i class="fa-solid fa-shopping-bag me-1"></i> Shop</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold rounded-pill py-2 <?php echo $active_role === 'student' ? 'active bg-primary-custom text-white' : 'text-dark'; ?>" id="student-tab" data-role="student" type="button"><i class="fa-solid fa-graduation-cap me-1"></i> SkillUp</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold rounded-pill py-2 <?php echo $active_role === 'admin' ? 'active bg-primary-custom text-white' : 'text-dark'; ?>" id="admin-tab" data-role="admin" type="button"><i class="fa-solid fa-user-shield me-1"></i> Admin</button>
                        </li>
                    </ul>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger border-0 shadow-sm" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="role" id="formRole" value="<?php echo $active_role; ?>">

                        <!-- Username / Email Field -->
                        <div class="mb-3">
                            <label for="email_username" class="form-label fw-semibold" id="inputLabel">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-envelope text-muted" id="inputIcon"></i></span>
                                <input type="text" class="form-control bg-light border-0" id="email_username" name="email_username" value="<?php echo isset($_POST['email_username']) ? sanitize($_POST['email_username']) : ''; ?>" placeholder="name@example.com" required>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <label for="password" class="form-label fw-semibold mb-0">Password</label>
                                <a href="#" class="text-primary-custom text-decoration-none small">Forgot Password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" class="form-control bg-light border-0" id="password" name="password" required>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold shadow-sm mb-3">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                        </button>

                        <div class="text-center mt-3" id="registerPrompt">
                            <p class="mb-0 text-muted">Don't have an account? <a href="register.php" class="text-primary-custom fw-semibold text-decoration-none">Register here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> 
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('#loginTabs button');
    const formRole = document.getElementById('formRole');
    const inputLabel = document.getElementById('inputLabel');
    const inputIcon = document.getElementById('inputIcon');
    const emailInput = document.getElementById('email_username');
    const registerPrompt = document.getElementById('registerPrompt');

    function updateFormLabels(role) {
        formRole.value = role;
        
        // Remove active styling from all buttons and add to selected
        tabs.forEach(btn => {
            btn.classList.remove('active', 'bg-primary-custom', 'text-white');
            btn.classList.add('text-dark');
        });
        
        const activeBtn = document.getElementById(role + '-tab');
        activeBtn.classList.remove('text-dark');
        activeBtn.classList.add('active', 'bg-primary-custom', 'text-white');

        if (role === 'admin') {
            inputLabel.textContent = "Username or Email";
            emailInput.placeholder = "Enter admin username or email";
            inputIcon.className = "fa-solid fa-user-shield text-muted";
            registerPrompt.style.display = 'none'; // Hide register prompt for admin
        } else if (role === 'student') {
            inputLabel.textContent = "Student Email";
            emailInput.placeholder = "student@example.com";
            inputIcon.className = "fa-solid fa-graduation-cap text-muted";
            registerPrompt.style.display = 'block';
        } else {
            inputLabel.textContent = "Customer Email";
            emailInput.placeholder = "name@example.com";
            inputIcon.className = "fa-solid fa-envelope text-muted";
            registerPrompt.style.display = 'block';
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const role = this.getAttribute('data-role');
            updateFormLabels(role);
        });
    });

    // Run initial configuration
    updateFormLabels(formRole.value);
});
</script>

<?php require_once 'includes/footer.php'; ?>
