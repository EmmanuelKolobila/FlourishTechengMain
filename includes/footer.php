</div> <!-- Close main-content-wrapper -->

<!-- Footer Section -->
<footer class="footer-custom mt-5">
    <div class="container">
        <div class="row g-4">
            <!-- Company Info -->
            <div class="col-lg-4 col-md-6">
                <h5 class="d-flex align-items-center">
                    <i class="fa-solid fa-solar-panel text-primary-custom me-2 fs-4"></i>
                    <span>FLOURISH TECHENG</span>
                </h5>
                <p class="mt-3">
                    Providing top-tier solar solutions, electrical contracting, technology networking, and professional industrial skills training (SkillUp) to drive industry growth.
                </p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="fs-5 text-light hover-primary"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="fs-5 text-light hover-primary"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" class="fs-5 text-light hover-primary"><i class="fa-brands fa-linkedin"></i></a>
                    <a href="#" class="fs-5 text-light hover-primary"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>

            <!-- Shop Categories -->
            <div class="col-lg-2 col-md-6">
                <h5>Our Shop</h5>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="<?php echo BASE_URL; ?>shop.php?category=solar"><i class="fa-solid fa-chevron-right me-1 small"></i> Solar Products</a></li>
                    <li><a href="<?php echo BASE_URL; ?>shop.php?category=electrical"><i class="fa-solid fa-chevron-right me-1 small"></i> Electrical Products</a></li>
                    <li><a href="<?php echo BASE_URL; ?>shop.php?category=tech"><i class="fa-solid fa-chevron-right me-1 small"></i> Technology Products</a></li>
                    <li><a href="<?php echo BASE_URL; ?>shop.php"><i class="fa-solid fa-chevron-right me-1 small"></i> Accessories</a></li>
                </ul>
            </div>

            <!-- Training Modules -->
            <div class="col-lg-3 col-md-6">
                <h5>SkillUp Training</h5>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="<?php echo BASE_URL; ?>training.php"><i class="fa-solid fa-chevron-right me-1 small"></i> Solar Installation</a></li>
                    <li><a href="<?php echo BASE_URL; ?>training.php"><i class="fa-solid fa-chevron-right me-1 small"></i> House Wiring & Safety</a></li>
                    <li><a href="<?php echo BASE_URL; ?>training.php"><i class="fa-solid fa-chevron-right me-1 small"></i> CCTV & Network Setup</a></li>
                    <li><a href="<?php echo BASE_URL; ?>training.php"><i class="fa-solid fa-chevron-right me-1 small"></i> Web Development</a></li>
                </ul>
            </div>

            <!-- Contact & Support -->
            <div class="col-lg-3 col-md-6">
                <h5>Contact Info</h5>
                <ul class="list-unstyled d-flex flex-column gap-3 text-light-50">
                    <li class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-location-dot mt-1 text-primary-custom"></i>
                        <span>12 Flourish Plaza, Tech Avenue, Industrial Area</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-phone text-primary-custom"></i>
                        <span>+234 812 345 6789</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-envelope text-primary-custom"></i>
                        <span>info@flourishtecheng.com</span>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4 border-secondary">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Flourish Techeng Limited. All Rights Reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <a href="#" class="me-3">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<!-- JS Script CDNs -->
<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- Flash Alerts Processing -->
<?php echo displayFlashMsg(); ?>

</body>
</html>
