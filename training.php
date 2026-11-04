<?php
/**
 * SkillUp Training Courses Page
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

$db = getDB();
$errors = [];
$success = "";

// Fetch categories and courses
try {
    $categories = $db->query("SELECT * FROM course_categories ORDER BY name ASC")->fetchAll();
    
    // Fetch courses with category info
    $courses = $db->query("
        SELECT c.*, cc.name as category_name 
        FROM courses c
        JOIN course_categories cc ON c.category_id = cc.id
        WHERE c.status = 'active'
        ORDER BY c.title ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database error loading courses: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// POST PROCESSOR: ENROLL COURSE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    if (!isStudentLoggedIn()) {
        setFlashMsg('error', "You must be logged in as a student to enroll in training courses.");
        header("Location: login.php?role=student");
        exit;
    }

    $course_id = (int)$_POST['course_id'];
    $student_id = $_SESSION['student_id'];

    if ($course_id > 0) {
        try {
            // Check if already enrolled
            $check = $db->prepare("SELECT id, status FROM enrollments WHERE student_id = ? AND course_id = ?");
            $check->execute([$student_id, $course_id]);
            $enrollment = $check->fetch();

            if ($enrollment) {
                setFlashMsg('error', "You are already enrolled in this course. Status: " . $enrollment['status']);
                header("Location: student_dashboard.php");
                exit;
            }

            // Fetch course price to determine payment status
            $priceStmt = $db->prepare("SELECT price FROM courses WHERE id = ?");
            $priceStmt->execute([$course_id]);
            $price = (float)$priceStmt->fetchColumn();
            $payment_status = ($price <= 0) ? 'Free' : 'Pending';

            // Insert enrollment
            $stmt = $db->prepare("
                INSERT INTO enrollments (student_id, course_id, status, payment_status) 
                VALUES (?, ?, 'Enrolled', ?)
            ");
            $stmt->execute([$student_id, $course_id, $payment_status]);

            // Add notification
            $db->prepare("
                INSERT INTO notifications (user_id, user_type, title, message) 
                VALUES (?, 'student', 'Course Enrolled', 'You have successfully enrolled in the course. Please complete payment if applicable.')
            ")->execute([$student_id]);

            setFlashMsg('success', "Enrolled successfully! Welcome to the class.");
            header("Location: student_dashboard.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Failed to enroll: " . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <div class="row text-center mb-5">
        <div class="col-lg-8 mx-auto">
            <span class="badge bg-success-subtle text-primary-custom px-3 py-2 mb-3 fw-bold rounded-pill text-uppercase">
                SkillUp Training Academy
            </span>
            <h1 class="fw-bold mb-3">SkillUp For Industry Technical Training</h1>
            <p class="text-muted leading-relaxed">
                Unlock your career potential with certified technical skills. Register as a student, enroll in our courses, download course materials, and receive a merchant-signed certificate upon completion.
            </p>
        </div>
    </div>
    <!-- Render Category sections -->
    <?php foreach ($categories as $cat): 
        // Filter courses for this category
        $catCourses = array_filter($courses, function($c) use ($cat) {
            return $c['category_id'] == $cat['id'];
        });
        
        if (empty($catCourses)) continue;
        ?>
        <div class="mb-5">
            <h3 class="fw-bold mb-3 text-secondary-custom"><i class="fa-solid fa-graduation-cap text-primary-custom me-2"></i><?php echo sanitize($cat['name']); ?></h3>
            <p class="text-muted small mb-4"><?php echo sanitize($cat['description']); ?></p>

            <div class="row g-4">
                <?php foreach ($catCourses as $c): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card card-custom border-0 shadow-sm h-100 p-3 bg-white">
                            <div class="text-center mb-3 bg-light overflow-hidden rounded position-relative" style="height: 160px;">
                                <?php if ($c['image'] && file_exists($c['image'])): ?>
                                    <img src="<?php echo $c['image']; ?>" class="img-fluid h-100 w-100" style="object-fit: cover;" alt="">
                                <?php else: ?>
                                    <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                        <i class="fa-solid fa-graduation-cap text-muted fs-1"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body p-0 d-flex flex-column">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-light text-dark border"><?php echo sanitize($c['duration']); ?></span>
                                    <span class="fw-bold text-success">$<?php echo number_format($c['price'], 2); ?></span>
                                </div>
                                
                                <h5 class="fw-bold text-dark mb-2"><?php echo sanitize($c['title']); ?></h5>
                                <p class="text-muted small leading-relaxed mb-4"><?php echo sanitize($c['description']); ?></p>
                                
                                <div class="mt-auto pt-3 border-top">
                                    <!-- Syllabi / Outline Accordion Link -->
                                    <button class="btn btn-link btn-xs text-primary-custom p-0 text-decoration-none fw-semibold mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#outline-<?php echo $c['id']; ?>">
                                        <i class="fa-solid fa-circle-info me-1"></i> View Course Syllabus
                                    </button>
                                    
                                    <div class="collapse mb-3" id="outline-<?php echo $c['id']; ?>">
                                        <div class="p-3 bg-light rounded text-secondary" style="font-size: 0.8rem; white-space: pre-line;">
                                            <strong>Syllabus Outline:</strong><br>
                                            <?php echo sanitize($c['outline']); ?>
                                        </div>
                                    </div>

                                    <!-- Enrollment Submit Form -->
                                    <form action="training.php" method="POST">
                                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="enroll_course" class="btn btn-primary-custom w-100 py-2 fw-bold">
                                            <i class="fa-solid fa-user-plus me-1"></i> Enroll in Course
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/header.php'; ?>
