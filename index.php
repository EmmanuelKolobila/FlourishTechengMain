<?php
/**
 * Homepage
 * Flourish Techeng Limited
 */

require_once 'includes/header.php';

try {
    $db = getDB();

    // Fetch parent categories
    $categories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL LIMIT 3")->fetchAll();

    // Fetch featured/latest products
    $featuredProducts = $db->query("
        SELECT p.*, c.name as category_name 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        ORDER BY p.id DESC
        LIMIT 4
    ")->fetchAll();

    // Fetch some services
    $services = $db->query("SELECT * FROM services WHERE status = 'active' LIMIT 3")->fetchAll();

    // Fetch some courses
    $courses = $db->query("
        SELECT c.*, cc.name as category_name 
        FROM courses c
        JOIN course_categories cc ON c.category_id = cc.id
        WHERE c.status = 'active'
        LIMIT 3
    ")->fetchAll();

} catch (PDOException $e) {
    // Fail silently
    $categories = $featuredProducts = $services = $courses = [];
}
?>

<!-- Hero Banner Section -->
<section class="hero-section text-white d-flex align-items-center py-5">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-success-subtle text-primary-custom px-3 py-2 mb-3 fw-bold rounded-pill text-uppercase" style="background-color: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);">
                    <i class="fa-solid fa-bolt me-1"></i> Engineering & Technology Excellence
                </span>
                <h1 class="hero-title text-white mb-3">Powering Your Future with Solar & Tech Solutions</h1>
                <p class="hero-subtitle mb-4 text-light-50">
                    Get premium solar systems, electrical materials, network infrastructure, and industrial grade SkillUp training—all in one place.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="shop.php" class="btn btn-primary-custom btn-lg"><i class="fa-solid fa-shopping-cart me-2"></i>Shop Products</a>
                    <a href="training.php" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-graduation-cap me-2"></i>SkillUp Training</a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block text-center position-relative">
                <!-- Solar/Tech abstract design -->
                <div class="position-absolute top-50 start-50 translate-middle bg-primary-custom rounded-circle opacity-10 blur" style="width: 400px; height: 400px; filter: blur(50px);"></div>
                <i class="fa-solid fa-solar-panel text-primary-custom" style="font-size: 15rem; filter: drop-shadow(0 0 30px rgba(16,185,129,0.3));"></i>
            </div>
        </div>
    </div>
</section>

<!-- Features Grid (Solar, Tech, Engineering, Training) -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card card-custom p-4 text-center border-0 h-100">
                    <div class="text-primary-custom mb-3"><i class="fa-solid fa-sun fs-2"></i></div>
                    <h5 class="fw-bold mb-2">Solar Energy</h5>
                    <p class="text-muted small mb-0">High-efficiency solar panels, hybrid inverters, & deep-cycle battery setups.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-4 text-center border-0 h-100">
                    <div class="text-primary-custom mb-3"><i class="fa-solid fa-bolt fs-2"></i></div>
                    <h5 class="fw-bold mb-2">Electrical Contracting</h5>
                    <p class="text-muted small mb-0">Standard industrial and home wiring accessories, breakers, switches, and cables.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-4 text-center border-0 h-100">
                    <div class="text-primary-custom mb-3"><i class="fa-solid fa-network-wired fs-2"></i></div>
                    <h5 class="fw-bold mb-2">Technology Solutions</h5>
                    <p class="text-muted small mb-0">Enterprise CCTV security systems, structured networking, hardware, and accessories.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-4 text-center border-0 h-100">
                    <div class="text-primary-custom mb-3"><i class="fa-solid fa-graduation-cap fs-2"></i></div>
                    <h5 class="fw-bold mb-2">SkillUp Training</h5>
                    <p class="text-muted small mb-0">Become an industry-certified expert in solar, electrical engineering, and web development.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-primary-custom fw-bold text-uppercase" style="letter-spacing: 1px;">Categories</span>
                <h2 class="fw-bold mb-0">Browse Our Catalog</h2>
            </div>
            <a href="shop.php" class="btn btn-outline-success fw-semibold">View All Shop</a>
        </div>
        <div class="row g-4">
            <?php foreach ($categories as $cat): 
                $icon = 'fa-box';
                if ($cat['id'] == 1) $icon = 'fa-solar-panel';
                elseif ($cat['id'] == 2) $icon = 'fa-plug';
                elseif ($cat['id'] == 3) $icon = 'fa-network-wired';
                ?>
                <div class="col-md-4">
                    <div class="card card-custom border-0 text-white bg-dark overflow-hidden position-relative" style="height: 220px;">
                        <!-- Overlay background -->
                        <div class="position-absolute w-100 h-100 bg-gradient-custom" style="background: linear-gradient(180deg, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0.8) 100%); z-index: 1;"></div>
                        <div class="card-body p-4 position-relative d-flex flex-column justify-content-end h-100" style="z-index: 2;">
                            <div class="mb-3 text-primary-custom"><i class="fa-solid <?php echo $icon; ?> fs-1"></i></div>
                            <h4 class="fw-bold text-white mb-2"><?php echo sanitize($cat['name']); ?></h4>
                            <p class="text-light-50 small mb-3"><?php echo sanitize($cat['description']); ?></p>
                            <div>
                                <a href="shop.php?category=<?php echo $cat['slug']; ?>" class="text-primary-custom fw-semibold text-decoration-none small">
                                    Browse products <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products Section (Best Sellers / New Arrivals) -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-primary-custom fw-bold text-uppercase" style="letter-spacing: 1px;">Top Arrivals</span>
                <h2 class="fw-bold mb-0">Featured Products</h2>
            </div>
            <a href="shop.php" class="btn btn-outline-success fw-semibold">View All Shop</a>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredProducts as $p): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-custom h-100 border-0 p-3">
                        <span class="badge bg-success position-absolute top-0 start-0 m-3 z-3">NEW</span>
                        <div class="text-center mb-3 overflow-hidden rounded position-relative bg-white" style="height: 180px;">
                            <?php if ($p['image'] && file_exists($p['image'])): ?>
                                <img src="<?php echo $p['image']; ?>" class="img-fluid h-100" style="object-fit: contain;" alt="">
                            <?php else: ?>
                                <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                    <i class="fa-solid fa-box text-muted fs-1"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <span class="text-muted small mb-1"><?php echo sanitize($p['category_name']); ?></span>
                            <h6 class="card-title-custom fw-bold text-secondary text-truncate mb-2" title="<?php echo sanitize($p['name']); ?>">
                                <a href="product.php?slug=<?php echo $p['slug']; ?>" class="text-decoration-none text-dark"><?php echo sanitize($p['name']); ?></a>
                            </h6>
                            <div class="d-flex align-items-center gap-1 mb-2 text-warning" style="font-size: 0.8rem;">
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
                                    <i class="fa-solid fa-cart-shopping"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Services Booking Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-primary-custom fw-bold text-uppercase" style="letter-spacing: 1px;">Expert Services</span>
                <h2 class="fw-bold mb-0">Professional Installations & Development</h2>
            </div>
            <a href="services.php" class="btn btn-outline-success fw-semibold">All Services</a>
        </div>
        <div class="row g-4">
            <?php foreach ($services as $serv): ?>
                <div class="col-md-4">
                    <div class="card card-custom border-0 h-100 p-4 bg-light">
                        <div class="text-primary-custom mb-3"><i class="fa-solid fa-toolbox fs-2"></i></div>
                        <h5 class="fw-bold text-dark mb-2"><?php echo sanitize($serv['name']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo sanitize(substr($serv['description'], 0, 120)) . '...'; ?></p>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Est: <?php echo sanitize($serv['price_range']); ?></span>
                            <a href="services.php?request_id=<?php echo $serv['id']; ?>" class="btn btn-primary-custom btn-sm fw-semibold">Request Quotation</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SkillUp Training Promotion Section -->
<section class="py-5 bg-dark text-white">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-4">
                <span class="text-primary-custom fw-bold text-uppercase" style="letter-spacing: 1px;">SkillUp Academy</span>
                <h2 class="fw-bold text-white mb-3 mt-1">Upgrade Your Technical Skills</h2>
                <p class="text-light-50 mb-4">
                    Join our industry-approved training curriculum. Get hands-on practical experience and certified training under professional engineers.
                </p>
                <a href="training.php" class="btn btn-primary-custom"><i class="fa-solid fa-graduation-cap me-2"></i>Explore Courses</a>
            </div>
            <div class="col-lg-8">
                <div class="row g-4">
                    <?php foreach ($courses as $c): ?>
                        <div class="col-md-6">
                            <div class="card bg-secondary border-0 p-4 text-white h-100" style="background-color: #1F2937 !important;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-success-subtle text-primary-custom px-2 py-1" style="font-size: 0.75rem; background-color: rgba(16, 185, 129, 0.15);"><?php echo sanitize($c['category_name']); ?></span>
                                    <span class="text-muted small"><i class="fa-regular fa-clock me-1"></i><?php echo sanitize($c['duration']); ?></span>
                                </div>
                                <h5 class="fw-bold text-white mb-2"><?php echo sanitize($c['title']); ?></h5>
                                <p class="text-light-50 small mb-3"><?php echo sanitize(substr($c['description'], 0, 110)) . '...'; ?></p>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-success">$<?php echo number_format($c['price'], 2); ?></span>
                                    <a href="training.php?enroll=<?php echo $c['id']; ?>" class="btn btn-outline-light btn-sm fw-semibold">Enroll Now</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">What Our Clients Say</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card card-custom p-4 border-0 h-100 bg-light text-center">
                    <div class="text-warning mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                    <p class="fst-italic text-muted">"Flourish Techeng installed our 10KVA solar inverter backup system. The service was highly professional, and our office now has 24/7 power. Excellent post-installation support!"</p>
                    <h6 class="fw-bold mb-1">Dr. Raymond Adebayo</h6>
                    <small class="text-muted">CEO, Adebayo Healthcare Ltd</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom p-4 border-0 h-100 bg-light text-center">
                    <div class="text-warning mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                    <p class="fst-italic text-muted">"The SkillUp CCTV installation course was very practical. I learned structured cabling and DVR configurations. I got my first security contract 2 weeks after getting certified!"</p>
                    <h6 class="fw-bold mb-1">Chidi Benson</h6>
                    <small class="text-muted">Certified Student</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom p-4 border-0 h-100 bg-light text-center">
                    <div class="text-warning mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                    <p class="fst-italic text-muted">"I purchase all solar components here. Their panels are highly efficient, and the batteries last long under heavy loads. Highly recommended shop for contractors."</p>
                    <h6 class="fw-bold mb-1">Engr. Yusuf Musa</h6>
                    <small class="text-muted">Renewable Energy Installer</small>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
