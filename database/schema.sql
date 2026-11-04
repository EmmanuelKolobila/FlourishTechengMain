-- Flourish Techeng Limited Database Schema
-- Database Name: flourish_techeng

CREATE DATABASE IF NOT EXISTS flourish_techeng;
USE flourish_techeng;

-- Disable foreign key checks for clean drops
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS certificates;
DROP TABLE IF EXISTS training_materials;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS course_categories;
DROP TABLE IF EXISTS quotations;
DROP TABLE IF EXISTS service_requests;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS wishlist;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Admins Table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'superadmin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Customers Table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_id INT DEFAULT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Brands Table
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Products Table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    brand_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    specifications TEXT, -- Detailed tech specs
    price DECIMAL(10,2) NOT NULL,
    discount_price DECIMAL(10,2) DEFAULT NULL, -- Optional discounted price
    stock_quantity INT DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL, -- Primary product image path
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Product Images Table (Multi-image support)
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Cart Table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Cart Items Table
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES cart(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. Orders Table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) NOT NULL,
    delivery_address TEXT NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Processing', 'Ready for Delivery', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    payment_method ENUM('Mobile Money', 'Cash on Delivery', 'Bank Transfer') NOT NULL,
    payment_status ENUM('Pending', 'Paid', 'Failed') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Order Items Table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. Payments Table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    transaction_ref VARCHAR(100) DEFAULT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Success', 'Failed') DEFAULT 'Pending',
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. Reviews Table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 13. Wishlist Table
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (customer_id, product_id)
) ENGINE=InnoDB;

-- 14. Services Table
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    price_range VARCHAR(100) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 15. Service Requests Table
CREATE TABLE service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    service_id INT NOT NULL,
    status ENUM('Pending', 'Contacted', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    details TEXT,
    preferred_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 16. Quotation Requests Table
CREATE TABLE quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL, -- Null if guest, but user requires registration
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    company_name VARCHAR(100) DEFAULT NULL,
    details TEXT NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL, -- Uploaded BOM/Requirements doc
    status ENUM('Pending', 'Responded', 'Closed') DEFAULT 'Pending',
    response_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 17. Course Categories Table
CREATE TABLE course_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 18. Courses Table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    outline TEXT, -- Syllabus outline
    duration VARCHAR(50) NOT NULL, -- e.g., '6 Weeks'
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 19. Students Table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL, -- Links to shop customer if applicable
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 20. Enrollments Table
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('Enrolled', 'In Progress', 'Completed', 'Dropped') DEFAULT 'Enrolled',
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('Pending', 'Paid', 'Free') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
) ENGINE=InnoDB;

-- 21. Training Materials Table
CREATE TABLE training_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL, -- e.g., 'pdf', 'mp4', 'docx'
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 22. Certificates Table
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    certificate_number VARCHAR(50) NOT NULL UNIQUE,
    issue_date DATE NOT NULL,
    file_path VARCHAR(255) NOT NULL, -- PDF generated certificate path
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 23. Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('admin', 'customer', 'student') NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 24. Contact Messages Table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 25. Settings Table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ========================================================
-- SEED DATA
-- ========================================================

-- 1. Default Admin User (Password is 'Admin@2026')
INSERT INTO admins (username, email, password, full_name, role, status) VALUES
('admin', 'admin@flourishtecheng.com', '$2y$10$.sIbOfAp8hj28UDeXqWdVOZbcS/xY8MCZ67aaBAnGrQX9EaMkJVbu', 'Engr. Flourish Admin', 'superadmin', 'active');

-- 2. Categories Seed
-- Root Categories
INSERT INTO categories (id, name, parent_id, slug, description) VALUES
(1, 'Solar Products', NULL, 'solar-products', 'Solar panels, inverters, batteries and controllers'),
(2, 'Electrical Products', NULL, 'electrical-products', 'Switches, wiring, cable, breakers, and installation components'),
(3, 'Technology Products', NULL, 'technology-products', 'CCTV, networks, peripherals, and security equipment');

-- Subcategories under Solar (parent_id = 1)
INSERT INTO categories (name, parent_id, slug, description) VALUES
('Solar Panels', 1, 'solar-panels', 'High efficiency monocrystalline and polycrystalline panels'),
('Solar Batteries', 1, 'solar-batteries', 'Deep cycle Gel and Lithium LiFePO4 batteries'),
('Solar Inverters', 1, 'solar-inverters', 'Pure sine wave hybrid solar inverters'),
('Solar Charge Controllers', 1, 'solar-charge-controllers', 'MPPT and PWM charge controllers'),
('Solar Street Lights', 1, 'solar-street-lights', 'All-in-one solar street luminaires'),
('Solar Flood Lights', 1, 'solar-flood-lights', 'High-lumens outdoor security floodlights');

-- Subcategories under Electrical (parent_id = 2)
INSERT INTO categories (name, parent_id, slug, description) VALUES
('LED Bulbs', 2, 'led-bulbs', 'Energy-saving LED indoor and outdoor lights'),
('Switches', 2, 'switches', 'Modern light switches, dimmers and controls'),
('Sockets', 2, 'sockets', 'Wall power sockets, extensions and industrial outlets'),
('Electrical Cables', 2, 'electrical-cables', 'Single core, multi-core, conduit wiring and flexible cords'),
('Circuit Breakers', 2, 'circuit-breakers', 'MCB, MCCB, RCD protection units'),
('Fans', 2, 'fans', 'Ceiling, wall and standing cooling fans'),
('Electrical Accessories', 2, 'electrical-accessories', 'Conduits, junction boxes, tapes and fitting adapters');

-- Subcategories under Technology (parent_id = 3)
INSERT INTO categories (name, parent_id, slug, description) VALUES
('CCTV Cameras', 3, 'cctv-cameras', 'IP, Analog, PTZ security cameras and DVRs/NVRs'),
('Networking Equipment', 3, 'networking-equipment', 'Routers, switches, patch panels, Cat6 cables and racks'),
('Computer Accessories', 3, 'computer-accessories', 'Keyboards, mice, adapters, external HDDs and hubs'),
('Printers', 3, 'printers', 'Laser, Inkjet, thermal and 3D printers'),
('Computer Hardware', 3, 'computer-hardware', 'RAM, SSD, Motherboards, power supplies and casings');

-- 3. Brands Seed
INSERT INTO brands (name, description) VALUES
('Flourish Solar', 'In-house premium quality solar panels and power solutions'),
('Sneider Tech', 'Industrial switches, breakers, and networking cabling'),
('Luminary Power', 'High efficiency solar batteries and smart inverters'),
('ElectroLink', 'Reliable copper conduits, electrical wires and distribution components'),
('SecureEye', 'Enterprise IP security cameras and network video recorders'),
('TechGear', 'Professional grade networking accessories, cables and hubs');

-- 4. Sample Products
INSERT INTO products (category_id, brand_id, name, slug, description, specifications, price, discount_price, stock_quantity, image) VALUES
(4, 1, 'Flourish 450W Monocrystalline Solar Panel', 'flourish-450w-mono-solar-panel', 'High-efficiency monocrystalline solar panel designed for residential and commercial off-grid and hybrid installations. Features tempered impact-resistant glass and durable aluminum frames.', 'Max Power: 450W\nCell Type: Monocrystalline\nEfficiency: 20.8%\nOpen Circuit Voltage: 49.6V\nDimensions: 2008 x 1002 x 35 mm\nWeight: 22kg', 180.00, 165.00, 45, 'uploads/products/solar_panel_450w.jpg'),
(5, 3, 'Luminary 200Ah 12V Deep Cycle Gel Battery', 'luminary-200ah-12v-gel-battery', 'Premium deep cycle gel battery with long service life and high stability. Ideal for solar backup power storage.', 'Voltage: 12V\nCapacity: 200Ah\nType: Gel Deep Cycle\nLife Cycles: 1200 at 50% DoD\nWeight: 58kg', 320.00, 299.99, 20, 'uploads/products/gel_battery_200ah.jpg'),
(6, 3, 'Luminary 5KVA 48V Hybrid Solar Inverter', 'luminary-5kva-48v-hybrid-inverter', 'Smart hybrid solar inverter with built-in 80A MPPT charge controller. Supports parallel operations and smart utility charging prioritization.', 'Power Rating: 5000VA / 5000W\nSystem Voltage: 48VDC\nMPPT Controller: 80A\nAC Charge Current: 60A\nWaveform: Pure Sine Wave', 650.00, NULL, 12, 'uploads/products/hybrid_inverter_5kva.jpg'),
(10, 2, 'Smart LED Bulb 15W Cool White', 'smart-led-bulb-15w-cool-white', 'Super bright, energy-saving LED light bulb with standard B22 base. Outlasts traditional bulbs by up to 10 times.', 'Wattage: 15W\nLumen: 1500lm\nColor Temperature: 6500K Cool White\nBase: B22 / E27\nLifespan: 25,000 Hours', 8.50, 6.99, 150, 'uploads/products/smart_led_15w.jpg'),
(17, 5, 'SecureEye 4MP Outdoor IP Dome Camera', 'secureeye-4mp-outdoor-ip-dome-camera', 'Weatherproof security dome camera with night vision up to 30 meters, motion detection, and power-over-Ethernet (PoE) support.', 'Resolution: 4 Megapixels (2560x1440)\nLens: 2.8mm fixed\nIR Distance: 30m\nWaterproof: IP67\nConnection: RJ45 PoE', 75.00, 68.00, 35, 'uploads/products/ip_camera_dome.jpg'),
(18, 6, 'TechGear Cat6 UTP Ethernet Cable 305m', 'techgear-cat6-utp-cable-305m', 'High-quality copper conductor solid networking cable. Standard roll size for commercial and structural networking installations.', 'Length: 305 meters (1000ft)\nType: Cat6 UTP\nConductor: 23AWG Solid Bare Copper\nSpeed: Up to 10Gbps\nColor: Blue', 120.00, 110.00, 5, 'uploads/products/cat6_cable_305m.jpg');

-- 5. Services Seed
INSERT INTO services (name, description, image, price_range) VALUES
('Solar Installation', 'Professional assessment, design, wiring, and commissioning of off-grid and grid-tie solar energy systems.', 'uploads/services/solar_install.jpg', '$500 - $5,000'),
('Electrical Wiring', 'Residential and industrial electrical pipe-laying, conduit wiring, distribution box connection, and certification.', 'uploads/services/elec_wiring.jpg', '$300 - $3,000'),
('CCTV Installation', 'High-definition digital security camera deployment, wiring, setup of DVR/NVR, and configure mobile viewing apps.', 'uploads/services/cctv_install.jpg', '$150 - $1,200'),
('Networking Installation', 'Structured network wiring, rack assembly, switches connection, router configurations, and office internet distribution.', 'uploads/services/net_install.jpg', '$250 - $2,500'),
('Computer Setup', 'OS installations, hardware upgrades, utility software packaging, printer configurations, and office computer migrations.', 'uploads/services/pc_setup.jpg', '$50 - $500'),
('Website Development', 'Interactive, mobile-first websites, corporate profiles, landing pages, and API integrations.', 'uploads/services/web_dev.jpg', '$400 - $4,000'),
('Software Development', 'Tailored business inventory applications, CRM systems, automation scripts, and database applications.', 'uploads/services/soft_dev.jpg', '$800 - $10,000');

-- 6. Course Categories Seed
INSERT INTO course_categories (id, name, description) VALUES
(1, 'Solar Training', 'Courses on design, setup, safety, and repairing solar energy hardware'),
(2, 'Electrical Training', 'Practical classes on house wiring, distribution boxes, safety precautions, and faults'),
(3, 'ICT Training', 'IT industrial courses on networking, web coding, security installations, and support');

-- 7. Courses Seed
INSERT INTO courses (category_id, title, description, outline, duration, price, image) VALUES
(1, 'Solar Installation Basics', 'Learn the introductory concepts of solar photovoltaic cells, panels connection, wire sizes, charge controllers and battery calculations.', 'Module 1: Solar PV Theory\nModule 2: Series & Parallel Panel Connections\nModule 3: Battery Selection & Sizing\nModule 4: Practical Inverter Wiring', '2 Weeks', 150.00, 'uploads/courses/solar_basics.jpg'),
(1, 'Solar System Design', 'Advanced design methods using computer calculators, panel positioning, shade analysis, load estimations, and smart-inverter sizing.', 'Module 1: Load Auditing\nModule 2: AutoCAD for Solar PV\nModule 3: Surge Protectors & AC/DC Combiner Box Design\nModule 4: Net Metering Systems', '4 Weeks', 250.00, 'uploads/courses/solar_design.jpg'),
(2, 'House Wiring & Safety', 'Step-by-step hands-on guide to laying electrical pipes, threading cables, wiring switches, sockets, DB boards, and safety groundings.', 'Module 1: Blueprint Reading\nModule 2: Conduit Pipe Laying\nModule 3: DB Board Cable Connections\nModule 4: Safety Testing & earthing', '4 Weeks', 180.00, 'uploads/courses/house_wiring.jpg'),
(3, 'CCTV Installation Masterclass', 'Comprehensive training on analog and IP camera configuration, NVR/DVR firmware setup, PoE switches, network storage, and port-forwarding for remote access.', 'Module 1: Camera Optics & Placement\nModule 2: Coaxial & Cat6 Crimping\nModule 3: NVR/DVR Setup & Storage HDD Install\nModule 4: Mobile App Cloud Config', '3 Weeks', 130.00, 'uploads/courses/cctv_master.jpg'),
(3, 'Web Development Boot Camp', 'Comprehensive course covering semantic HTML5, styling with modern CSS, JS programming, MySQL databases, and PHP server side processing.', 'Module 1: Frontend (HTML, CSS, JS)\nModule 2: Database Design (MySQL)\nModule 3: Backend programming (PHP)\nModule 4: App Deployment & Hosting', '8 Weeks', 350.00, 'uploads/courses/web_bootcamp.jpg');

-- 8. Sample Customer & Student (Password is 'User@2026')
INSERT INTO customers (id, full_name, email, phone, password, address) VALUES
(1, 'John Doe Client', 'customer@flourishtecheng.com', '08099887766', '$2y$10$Lknt4AftPEPt8S0iMQ6Xz.VfHKJprflqJFev0P9v5nMJsgKfea01a', '15 Garden View Estate, Victoria Island, Lagos');

-- Add John Doe Client as a Student
INSERT INTO students (id, customer_id, full_name, email, password, phone) VALUES
(1, 1, 'John Doe Client', 'student@flourishtecheng.com', '$2y$10$Lknt4AftPEPt8S0iMQ6Xz.VfHKJprflqJFev0P9v5nMJsgKfea01a', '08099887766');

-- 9. Settings Table Seed
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Flourish Techeng Limited'),
('company_email', 'info@flourishtecheng.com'),
('company_phone', '+234 812 345 6789'),
('currency', 'USD'),
('tax_rate', '7.5');
