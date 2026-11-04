<?php
/**
 * Authentication and Session Management Configuration
 * Flourish Techeng Limited Shop & SkillUp Platform
 */

// Enable output buffering to prevent headers already sent errors
ob_start();

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Clean input data for security (prevent XSS)
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token if not exists
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * Check if Admin is logged in
 * @return bool
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if Customer is logged in
 * @return bool
 */
function isCustomerLoggedIn() {
    return isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
}

/**
 * Check if Student is logged in
 * @return bool
 */
function isStudentLoggedIn() {
    return isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true;
}

/**
 * Redirect to Login if Admin is not logged in
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        $_SESSION['error_msg'] = "Please log in to access the admin area.";
        header("Location: ../login.php?role=admin");
        exit;
    }
}

/**
 * Redirect to Login if Customer is not logged in
 */
function requireCustomer() {
    if (!isCustomerLoggedIn()) {
        $_SESSION['error_msg'] = "Please log in to continue.";
        header("Location: login.php?role=customer");
        exit;
    }
}

/**
 * Redirect to Login if Student is not logged in
 */
function requireStudent() {
    if (!isStudentLoggedIn()) {
        $_SESSION['error_msg'] = "Please log in to access training courses.";
        header("Location: login.php?role=student");
        exit;
    }
}

/**
 * Get logged-in Admin data
 * @return array|null
 */
function getLoggedInAdmin() {
    if (isAdminLoggedIn()) {
        return [
            'id' => $_SESSION['admin_id'],
            'email' => $_SESSION['admin_email'],
            'name' => $_SESSION['admin_name'],
            'role' => $_SESSION['admin_role']
        ];
    }
    return null;
}

/**
 * Get logged-in Customer data
 * @return array|null
 */
function getLoggedInCustomer() {
    if (isCustomerLoggedIn()) {
        return [
            'id' => $_SESSION['customer_id'],
            'email' => $_SESSION['customer_email'],
            'name' => $_SESSION['customer_name']
        ];
    }
    return null;
}

/**
 * Get logged-in Student data
 * @return array|null
 */
function getLoggedInStudent() {
    if (isStudentLoggedIn()) {
        return [
            'id' => $_SESSION['student_id'],
            'email' => $_SESSION['student_email'],
            'name' => $_SESSION['student_name']
        ];
    }
    return null;
}

/**
 * Flash messages helper
 */
function setFlashMsg($type, $msg) {
    $_SESSION[$type . '_msg'] = $msg;
}

function displayFlashMsg() {
    $html = "";
    if (isset($_SESSION['success_msg'])) {
        $html .= "<script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '" . addslashes($_SESSION['success_msg']) . "',
                confirmButtonColor: '#28a745'
            });
        </script>";
        unset($_SESSION['success_msg']);
    }
    if (isset($_SESSION['error_msg'])) {
        $html .= "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '" . addslashes($_SESSION['error_msg']) . "',
                confirmButtonColor: '#dc3545'
            });
        </script>";
        unset($_SESSION['error_msg']);
    }
    return $html;
}
?>
