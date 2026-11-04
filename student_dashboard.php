<?php
/**
 * Student Dashboard Portal
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

// Enforce login
requireStudent();

$studentId = $_SESSION['student_id'];
$db = getDB();
$errors = [];
$success = "";

// -----------------------------------------------------------------------------
// POST HANDLER: MARK NOTIFICATIONS AS READ
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'student'");
        $stmt->execute([$studentId]);
        setFlashMsg('success', "All notifications marked as read.");
        header("Location: student_dashboard.php?tab=notifications");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Failed to update notifications: " . $e->getMessage();
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: SIMULATE COURSE PAYMENT
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_course'])) {
    $enrollmentId = (int)$_POST['enrollment_id'];
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'Mobile Money');
    
    if ($enrollmentId > 0) {
        try {
            // Verify enrollment belongs to student
            $check = $db->prepare("
                SELECT e.*, c.title, c.price 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.id 
                WHERE e.id = ? AND e.student_id = ?
            ");
            $check->execute([$enrollmentId, $studentId]);
            $enrollment = $check->fetch();

            if ($enrollment) {
                // Update payment status to Paid, and set status to 'In Progress'
                $update = $db->prepare("
                    UPDATE enrollments 
                    SET payment_status = 'Paid', status = 'In Progress' 
                    WHERE id = ?
                ");
                $update->execute([$enrollmentId]);

                // Create a notification for the student
                $msg = "Payment of $" . number_format($enrollment['price'], 2) . " via " . $paymentMethod . " for the course '" . $enrollment['title'] . "' has been received. You now have full access to all course materials!";
                $db->prepare("
                    INSERT INTO notifications (user_id, user_type, title, message) 
                    VALUES (?, 'student', 'Payment Confirmed', ?)
                ")->execute([$studentId, $msg]);

                setFlashMsg('success', "Payment simulated successfully! Your course is now active.");
                header("Location: student_dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid enrollment record.";
            }
        } catch (PDOException $e) {
            $errors[] = "Payment processing failed: " . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------------------
// POST HANDLER: UPDATE PROFILE info
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($fullName)) {
        $errors[] = "Full Name is required.";
    }
    
    if (empty($errors)) {
        try {
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE students SET full_name = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullName, $phone, $hashedPassword, $studentId]);
            } else {
                $stmt = $db->prepare("UPDATE students SET full_name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$fullName, $phone, $studentId]);
            }
            
            $_SESSION['student_name'] = $fullName; // Update session variable
            setFlashMsg('success', "Profile updated successfully.");
            header("Location: student_dashboard.php?tab=profile");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------------------
// FETCH DASHBOARD DATA
// -----------------------------------------------------------------------------
try {
    // 1. Fetch student info
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    // 2. Fetch Enrollments
    $enrollmentsStmt = $db->prepare("
        SELECT e.id as enrollment_id, e.status as enrollment_status, e.payment_status, e.enrollment_date,
               c.id as course_id, c.title, c.duration, c.price, c.image, c.description, cc.name as category_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN course_categories cc ON c.category_id = cc.id
        WHERE e.student_id = ?
        ORDER BY e.id DESC
    ");
    $enrollmentsStmt->execute([$studentId]);
    $enrollments = $enrollmentsStmt->fetchAll();

    // 3. Fetch training materials for student's courses
    $courseIds = array_column($enrollments, 'course_id');
    $materials = [];
    if (!empty($courseIds)) {
        $inQuery = implode(',', array_fill(0, count($courseIds), '?'));
        $materialsStmt = $db->prepare("
            SELECT * FROM training_materials 
            WHERE course_id IN ($inQuery) AND status = 'active'
            ORDER BY id ASC
        ");
        $materialsStmt->execute($courseIds);
        while ($row = $materialsStmt->fetch()) {
            $materials[$row['course_id']][] = $row;
        }
    }

    // 4. Fetch Certificates
    $certStmt = $db->prepare("
        SELECT * FROM certificates 
        WHERE student_id = ?
    ");
    $certStmt->execute([$studentId]);
    $certs = [];
    while ($row = $certStmt->fetch()) {
        $certs[$row['course_id']] = $row;
    }

    // 5. Fetch Notifications
    $notifStmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND user_type = 'student' 
        ORDER BY id DESC
    ");
    $notifStmt->execute([$studentId]);
    $notifications = $notifStmt->fetchAll();
    
    $unreadCount = 0;
    foreach ($notifications as $n) {
        if (!$n['is_read']) $unreadCount++;
    }

} catch (PDOException $e) {
    $errors[] = "Error loading student portal: " . $e->getMessage();
}

$activeTab = sanitize($_GET['tab'] ?? 'courses');
?>

<div class="container py-5">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong>Error:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err) echo "<li>" . sanitize($err) . "</li>"; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sidebar Column -->
        <div class="col-lg-3">
            <div class="card card-custom border-0 p-4 shadow-sm bg-light text-center">
                <div class="position-relative d-inline-block mx-auto mb-3">
                    <div class="bg-primary-custom text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-graduation-cap fs-1"></i>
                    </div>
                    <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success border border-white" style="padding: 6px;"></span>
                </div>
                <h5 class="fw-bold mb-0 text-dark"><?php echo sanitize($student['full_name']); ?></h5>
                <small class="text-muted d-block mb-3"><?php echo sanitize($student['email']); ?></small>
                
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill mb-4 small fw-bold">
                    <i class="fa-solid fa-circle-check me-1"></i> Student Portal Active
                </span>

                <div class="nav flex-column nav-pills text-start" id="dashboardTabs" role="tablist">
                    <button class="nav-link text-start fw-semibold py-2.5 mb-2 <?php echo $activeTab === 'courses' ? 'active' : ''; ?>" id="courses-tab" data-bs-toggle="pill" data-bs-target="#courses-pane" type="button" role="tab">
                        <i class="fa-solid fa-book-open me-2"></i>My Enrollments
                    </button>
                    <button class="nav-link text-start fw-semibold py-2.5 mb-2 position-relative <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" id="notifications-tab" data-bs-toggle="pill" data-bs-target="#notifications-pane" type="button" role="tab">
                        <i class="fa-solid fa-bell me-2"></i>Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-50 end-0 translate-middle-y badge rounded-pill bg-danger me-3">
                                <?php echo $unreadCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <button class="nav-link text-start fw-semibold py-2.5 mb-2 <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile-pane" type="button" role="tab">
                        <i class="fa-solid fa-user-gear me-2"></i>My Profile
                    </button>
                    <a href="logout.php" class="nav-link text-start text-danger fw-semibold py-2.5 mt-2 border-top pt-3">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Panes Column -->
        <div class="col-lg-9">
            <div class="tab-content" id="dashboardTabsContent">
                
                <!-- 1. Enrolled Courses Pane -->
                <div class="tab-pane fade <?php echo $activeTab === 'courses' ? 'show active' : ''; ?>" id="courses-pane" role="tabpanel" aria-labelledby="courses-tab">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-bookmark text-primary-custom me-2"></i>My SkillUp Courses</h4>
                            <a href="training.php" class="btn btn-outline-success btn-sm rounded-pill fw-semibold"><i class="fa-solid fa-plus me-1"></i>Explore Courses</a>
                        </div>

                        <?php if (empty($enrollments)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-solid fa-graduation-cap fs-1 text-muted-50 mb-3 d-block"></i>
                                <h5>You are not enrolled in any training courses yet.</h5>
                                <p class="small text-muted">Enroll in professional courses and get certified.</p>
                                <a href="training.php" class="btn btn-primary-custom mt-3">Browse Catalog</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($enrollments as $e): 
                                    $statusClass = 'bg-secondary';
                                    if ($e['enrollment_status'] === 'Enrolled') $statusClass = 'bg-warning text-dark';
                                    elseif ($e['enrollment_status'] === 'In Progress') $statusClass = 'bg-info';
                                    elseif ($e['enrollment_status'] === 'Completed') $statusClass = 'bg-success';
                                    elseif ($e['enrollment_status'] === 'Dropped') $statusClass = 'bg-danger';

                                    $payClass = 'bg-secondary';
                                    if ($e['payment_status'] === 'Pending') $payClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    elseif ($e['payment_status'] === 'Paid') $payClass = 'bg-success-subtle text-success border border-success-subtle';
                                    elseif ($e['payment_status'] === 'Free') $payClass = 'bg-info-subtle text-info border border-info-subtle';
                                    
                                    $courseImg = !empty($e['image']) ? $e['image'] : 'assets/css/style.css'; // Fallback
                                    ?>
                                    <div class="col-md-6">
                                        <div class="card h-100 border shadow-sm rounded-4 overflow-hidden position-relative">
                                            <div class="p-4">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-pill small fw-semibold">
                                                        <i class="fa-solid fa-tags me-1 text-muted"></i><?php echo sanitize($e['category_name']); ?>
                                                    </span>
                                                    <span class="badge <?php echo $statusClass; ?> px-2.5 py-1.5 rounded-pill small fw-semibold">
                                                        <?php echo $e['enrollment_status']; ?>
                                                    </span>
                                                </div>
                                                <h5 class="fw-bold mb-2 text-dark mt-2" style="font-size: 1.15rem;"><?php echo sanitize($e['title']); ?></h5>
                                                <p class="text-muted small text-truncate-2 mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?php echo sanitize($e['description']); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
                                                    <div class="small">
                                                        <span class="text-muted d-block">Duration: <strong><?php echo sanitize($e['duration']); ?></strong></span>
                                                        <span class="text-muted">Payment: <span class="badge <?php echo $payClass; ?> rounded px-2 py-0.5" style="font-size: 0.75rem;"><?php echo $e['payment_status']; ?></span></span>
                                                    </div>
                                                    <div class="fw-bold text-success fs-5">
                                                        <?php echo $e['price'] > 0 ? '$' . number_format($e['price'], 2) : 'FREE'; ?>
                                                    </div>
                                                </div>

                                                <!-- Buttons section -->
                                                <div class="mt-4 d-flex flex-wrap gap-2 pt-2 border-top">
                                                    <?php if ($e['payment_status'] === 'Pending'): ?>
                                                        <button class="btn btn-danger btn-sm w-100 fw-bold py-2 rounded-3 pay-now-btn" 
                                                                data-id="<?php echo $e['enrollment_id']; ?>" 
                                                                data-title="<?php echo sanitize($e['title']); ?>" 
                                                                data-price="<?php echo number_format($e['price'], 2); ?>">
                                                            <i class="fa-solid fa-credit-card me-1"></i> Pay Now ($<?php echo number_format($e['price'], 2); ?>)
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Access Materials -->
                                                        <button class="btn btn-primary-custom btn-sm flex-grow-1 fw-bold py-2 rounded-3" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#materialsModal-<?php echo $e['course_id']; ?>">
                                                            <i class="fa-solid fa-graduation-cap me-1"></i> Access Materials
                                                        </button>

                                                        <!-- Certificate Download -->
                                                        <?php if ($e['enrollment_status'] === 'Completed'): ?>
                                                            <?php if (isset($certs[$e['course_id']])): ?>
                                                                <a href="certificate.php?number=<?php echo urlencode($certs[$e['course_id']]['certificate_number']); ?>" 
                                                                   target="_blank" 
                                                                   class="btn btn-success btn-sm fw-bold py-2 rounded-3 px-3">
                                                                    <i class="fa-solid fa-award me-1"></i> Certificate
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-secondary btn-sm fw-bold py-2 rounded-3 px-3" disabled title="Certificate processing by admin">
                                                                    <i class="fa-solid fa-clock me-1"></i> Processing
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Course Materials Modal (Only rendered if paid/free) -->
                                        <?php if ($e['payment_status'] !== 'Pending'): ?>
                                            <div class="modal fade" id="materialsModal-<?php echo $e['course_id']; ?>" tabindex="-1" aria-labelledby="materialsModalLabel-<?php echo $e['course_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-primary-custom text-white border-0 py-3.5 rounded-top-4">
                                                            <h5 class="modal-title fw-bold" id="materialsModalLabel-<?php echo $e['course_id']; ?>">
                                                                <i class="fa-solid fa-folder-open me-2"></i>Learning Materials: <?php echo sanitize($e['title']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <?php if (empty($materials[$e['course_id']])): ?>
                                                                <div class="text-center py-5 text-muted">
                                                                    <i class="fa-regular fa-folder-open fs-1 text-muted-50 mb-3 d-block"></i>
                                                                    <h6 class="fw-bold">No training materials uploaded yet.</h6>
                                                                    <p class="small mb-0">Check back later or contact your course instructor.</p>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="list-group list-group-flush">
                                                                    <?php foreach ($materials[$e['course_id']] as $mat): 
                                                                        $icon = 'fa-file-lines';
                                                                        $btnText = 'View Document';
                                                                        if (in_array(strtolower($mat['file_type']), ['pdf'])) {
                                                                            $icon = 'fa-file-pdf text-danger';
                                                                            $btnText = 'Open PDF';
                                                                        } elseif (in_array(strtolower($mat['file_type']), ['mp4', 'avi', 'mov'])) {
                                                                            $icon = 'fa-circle-play text-primary';
                                                                            $btnText = 'Play Video';
                                                                        } elseif (in_array(strtolower($mat['file_type']), ['doc', 'docx'])) {
                                                                            $icon = 'fa-file-word text-info';
                                                                            $btnText = 'Download Doc';
                                                                        }
                                                                        ?>
                                                                        <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between align-items-sm-center py-3 px-0 border-bottom">
                                                                            <div class="d-flex align-items-start gap-3 mb-2 mb-sm-0">
                                                                                <i class="fa-solid <?php echo $icon; ?> fs-3 mt-1"></i>
                                                                                <div>
                                                                                    <h6 class="fw-bold mb-1 text-dark"><?php echo sanitize($mat['title']); ?></h6>
                                                                                    <p class="text-muted small mb-0"><?php echo sanitize($mat['description']); ?></p>
                                                                                </div>
                                                                            </div>
                                                                            <a href="<?php echo BASE_URL . sanitize($mat['file_path']); ?>" target="_blank" class="btn btn-sm btn-light border fw-semibold rounded-pill px-3 mt-2 mt-sm-0">
                                                                                <i class="fa-solid fa-download me-1 small"></i><?php echo $btnText; ?>
                                                                            </a>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer border-0 p-3">
                                                            <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Notifications Pane -->
                <div class="tab-pane fade <?php echo $activeTab === 'notifications' ? 'show active' : ''; ?>" id="notifications-pane" role="tabpanel" aria-labelledby="notifications-tab">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bell text-primary-custom me-2"></i>My Notifications</h4>
                            <?php if ($unreadCount > 0): ?>
                                <form method="POST" action="student_dashboard.php">
                                    <button type="submit" name="mark_read" class="btn btn-light btn-sm border rounded-pill fw-semibold">
                                        <i class="fa-solid fa-check-double me-1"></i>Mark All as Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-regular fa-bell-slash fs-1 text-muted-50 mb-3 d-block"></i>
                                <h5>All caught up! No notifications yet.</h5>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $n): ?>
                                    <div class="list-group-item py-3 px-0 border-bottom d-flex align-items-start gap-3 <?php echo !$n['is_read'] ? 'bg-light-subtle font-weight-bold' : ''; ?>">
                                        <div class="mt-1">
                                            <?php if (!$n['is_read']): ?>
                                                <i class="fa-solid fa-circle text-primary fs-6"></i>
                                            <?php else: ?>
                                                <i class="fa-regular fa-circle text-muted fs-6"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="fw-bold mb-1 text-dark <?php echo !$n['is_read'] ? 'text-primary-custom' : ''; ?>">
                                                    <?php echo sanitize($n['title']); ?>
                                                </h6>
                                                <small class="text-muted"><?php echo date('d M, H:i', strtotime($n['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-0 text-secondary small leading-relaxed"><?php echo sanitize($n['message']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Profile Management Pane -->
                <div class="tab-pane fade <?php echo $activeTab === 'profile' ? 'show active' : ''; ?>" id="profile-pane" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="card card-custom border-0 p-4 shadow-sm bg-white">
                        <h4 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-user-gear text-primary-custom me-2"></i>Account Profile</h4>
                        
                        <form method="POST" action="student_dashboard.php">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo sanitize($student['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Email Address (Read Only)</label>
                                    <input type="email" class="form-control bg-light text-muted" value="<?php echo sanitize($student['email']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo sanitize($student['phone']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-muted">Reset Password (Leave blank to keep current)</label>
                                    <input type="password" name="password" class="form-control" placeholder="New Password">
                                </div>
                                <div class="col-12 mt-4 pt-2 border-top text-end">
                                    <button type="submit" class="btn btn-primary-custom px-4"><i class="fa-solid fa-save me-1.5"></i>Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Simulated Checkout Payment Modal -->
<div class="modal fade" id="paymentSimulatorModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="paymentSimulatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3.5 rounded-top-4">
                <h5 class="modal-title fw-bold" id="paymentSimulatorModalLabel">
                    <i class="fa-solid fa-credit-card me-2"></i>Course Payment Simulator
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="student_dashboard.php">
                <div class="modal-body p-4">
                    <p class="text-muted small">You are simulating a secure payment for the following technical training course:</p>
                    <div class="p-3 bg-light rounded-3 mb-4">
                        <h6 class="fw-bold mb-1 text-dark" id="modalCourseTitle">Course Title</h6>
                        <div class="text-danger fw-bold fs-5" id="modalCoursePrice">$0.00</div>
                    </div>

                    <input type="hidden" name="pay_course" value="1">
                    <input type="hidden" name="enrollment_id" id="modalEnrollmentId" value="0">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Channel</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Mobile Money">Mobile Money (M-Pesa, Orange, MTN)</option>
                            <option value="Bank Transfer">Bank Wire/Transfer</option>
                            <option value="Card Payment">Simulated Credit/Debit Card</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account / Card Number</label>
                        <input type="text" class="form-control" placeholder="e.g. +234 812 345 6789 or 4242 •••• •••• ••••" required>
                    </div>

                    <div class="form-check bg-warning-subtle p-3 rounded-3 border border-warning-subtle text-dark-emphasis small">
                        <input class="form-check-input ms-0 me-2" type="checkbox" value="" id="simCheck" required>
                        <label class="form-check-label" for="simCheck">
                            I understand that this is a simulated technical environment. Clicking "Confirm Payment" will instantly verify payment in the database.
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary btn-sm px-3 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 rounded-pill fw-bold">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle opening of payment simulator modal
    $('.pay-now-btn').on('click', function() {
        var eId = $(this).data('id');
        var title = $(this).data('title');
        var price = $(this).data('price');

        $('#modalEnrollmentId').val(eId);
        $('#modalCourseTitle').text(title);
        $('#modalCoursePrice').text('$' + price);

        var myModal = new bootstrap.Modal(document.getElementById('paymentSimulatorModal'));
        myModal.show();
    });

    // Make tab switching work correctly with query params if any
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
require_once 'includes/footer.php';
?>
