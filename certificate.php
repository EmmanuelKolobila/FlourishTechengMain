<?php
/**
 * Public Certificate Verification & Dynamic Print View
 * Flourish Techeng Limited
 */

require_once 'config/db.php';
require_once 'config/auth.php';

$db = getDB();
$number = sanitize($_GET['number'] ?? '');
$certificate = null;
$error = "";

if (!empty($number)) {
    try {
        $stmt = $db->prepare("
            SELECT cert.*, s.full_name as student_name, c.title as course_title, c.duration
            FROM certificates cert
            JOIN students s ON cert.student_id = s.id
            JOIN courses c ON cert.course_id = c.id
            WHERE cert.certificate_number = ?
        ");
        $stmt->execute([$number]);
        $certificate = $stmt->fetch();
        
        if (!$certificate) {
            $error = "Certificate record not found. Please verify the certificate number.";
        }
    } catch (PDOException $e) {
        $error = "Database query error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $certificate ? 'Certificate Verification - ' . sanitize($certificate['certificate_number']) : 'Verify Training Certificate'; ?> | Flourish Techeng</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Cinzel+Decorative:wght@700&family=Great+Vibes&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #047857;
            --secondary: #111827;
            --gold: #D4AF37;
            --gold-dark: #AA7C11;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            min-height: 100vh;
        }

        /* Certificate Viewer Frame Styles */
        .cert-outer-border {
            background-color: #ffffff;
            border: 24px solid #111827; /* Dark framing border */
            border-image: linear-gradient(135deg, var(--secondary) 0%, var(--primary-dark) 50%, var(--secondary) 100%) 30;
            padding: 25px;
            position: relative;
            box-shadow: 0 10px 45px rgba(0, 0, 0, 0.12);
            background-image: radial-gradient(circle, rgba(16, 185, 129, 0.02) 0%, rgba(0,0,0,0) 80%);
        }

        .cert-inner-border {
            border: 4px solid var(--gold);
            padding: 40px;
            position: relative;
            text-align: center;
        }

        /* Corner accents inside certificate */
        .cert-corner {
            position: absolute;
            width: 45px;
            height: 45px;
            border: 4px solid var(--gold);
            z-index: 10;
        }
        .cert-corner-tl { top: -4px; left: -4px; border-right: none; border-bottom: none; }
        .cert-corner-tr { top: -4px; right: -4px; border-left: none; border-bottom: none; }
        .cert-corner-bl { bottom: -4px; left: -4px; border-right: none; border-top: none; }
        .cert-corner-br { bottom: -4px; right: -4px; border-left: none; border-top: none; }

        .cert-logo {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 8px rgba(16, 185, 129, 0.15));
        }

        .cert-title {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 700;
            font-size: 2.75rem;
            color: var(--secondary);
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }

        .cert-subtitle {
            font-family: 'Outfit', sans-serif;
            text-transform: uppercase;
            letter-spacing: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 2.5rem;
        }

        .cert-recipient-text {
            font-size: 1.1rem;
            font-style: italic;
            color: #4b5563;
            margin-bottom: 1rem;
        }

        .cert-name {
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            color: var(--secondary);
            border-bottom: 2px solid #e5e7eb;
            display: inline-block;
            padding-bottom: 5px;
            margin-bottom: 1.5rem;
            min-width: 320px;
        }

        .cert-statement {
            font-size: 1.15rem;
            color: #4b5563;
            max-width: 700px;
            margin: 0 auto 2.5rem;
            line-height: 1.6;
        }

        .cert-course {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.25rem;
        }

        .cert-duration {
            font-size: 0.95rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 3.5rem;
        }

        /* Signatures & Seal info */
        .cert-footer-row {
            margin-top: 2rem;
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
        }

        .cert-seal {
            width: 100px;
            height: 100px;
            border: 4px double var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: var(--gold-dark);
            font-weight: bold;
            font-size: 0.7rem;
            letter-spacing: 1px;
            background-color: rgba(245, 158, 11, 0.05);
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.1);
        }

        .signature-font {
            font-family: 'Great Vibes', cursive;
            font-size: 2.5rem;
            color: #1e3a8a;
            line-height: 1;
            margin-bottom: -5px;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            width: 180px;
            margin: 5px auto 0;
            padding-top: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #4b5563;
        }

        .cert-meta-info {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.85rem;
            color: #4b5563;
            display: inline-flex;
            gap: 20px;
            margin-top: 3rem;
        }

        /* Print Media Stylesheet */
        @media print {
            body {
                background-color: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .cert-outer-border {
                border-width: 16px !important;
                box-shadow: none !important;
                padding: 10px !important;
                margin: 0 !important;
                width: 100% !important;
                height: 100% !important;
                page-break-inside: avoid;
            }
            .cert-inner-border {
                padding: 20px !important;
            }
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Topbar/Actions Panel (Not printed) -->
    <div class="bg-white border-bottom py-3 shadow-sm no-print mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="text-decoration-none text-dark d-flex align-items-center">
                <i class="fa-solid fa-arrow-left me-2"></i>
                <span class="fw-bold">Back to Flourish Techeng</span>
            </a>
            <div>
                <?php if ($certificate): ?>
                    <button onclick="window.print()" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-print me-1.5"></i>Print / Save PDF
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container flex-grow-1 d-flex align-items-center justify-content-center py-4">
        <div class="w-100" style="max-width: 1000px;">
            
            <?php if (!$certificate): ?>
                <!-- Search Verification Panel -->
                <div class="card card-custom border-0 shadow-lg p-5 bg-white text-center mx-auto no-print" style="max-width: 550px;">
                    <div class="text-primary-custom mb-3"><i class="fa-solid fa-award fs-1"></i></div>
                    <h3 class="fw-bold text-dark mb-2">Certificate Verification</h3>
                    <p class="text-muted mb-4 small">Verify the authenticity of a technical training certificate issued by Flourish Techeng Limited.</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger border-0 small py-2 mb-4"><i class="fa-solid fa-circle-exclamation me-1.5"></i><?php echo sanitize($error); ?></div>
                    <?php endif; ?>

                    <form method="GET" action="certificate.php">
                        <div class="mb-4">
                            <input type="text" name="number" class="form-control text-center form-control-lg rounded-pill fw-bold" placeholder="e.g. FT-2026-X8Z9" value="<?php echo sanitize($number); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100 rounded-pill py-2.5 fw-bold"><i class="fa-solid fa-circle-check me-2"></i>Verify Certificate</button>
                    </form>
                </div>
            <?php else: ?>
                
                <!-- Display Certificate -->
                <div class="cert-outer-border">
                    <div class="cert-inner-border">
                        <!-- Borders corners -->
                        <div class="cert-corner cert-corner-tl"></div>
                        <div class="cert-corner cert-corner-tr"></div>
                        <div class="cert-corner cert-corner-bl"></div>
                        <div class="cert-corner cert-corner-br"></div>
                        
                        <!-- Logo & Title -->
                        <i class="fa-solid fa-solar-panel cert-logo"></i>
                        <h1 class="cert-title">Certificate of Completion</h1>
                        <div class="cert-subtitle">Flourish Techeng SkillUp Academy</div>

                        <!-- Recipient details -->
                        <p class="cert-recipient-text">This is proudly presented to</p>
                        <h2 class="cert-name"><?php echo sanitize($certificate['student_name']); ?></h2>
                        
                        <!-- Statement -->
                        <p class="cert-statement">
                            for successfully completing all academic syllabus modules, passing industrial tests, and demonstrating outstanding skill proficiency in the specialized technical course:
                        </p>

                        <!-- Course Title -->
                        <h3 class="cert-course"><?php echo sanitize($certificate['course_title']); ?></h3>
                        <div class="cert-duration">Course Duration: <?php echo sanitize($certificate['duration']); ?></div>

                        <!-- Signatures & Seals -->
                        <div class="cert-footer-row">
                            <!-- Left sign -->
                            <div>
                                <div class="signature-font">Engr. Flourish</div>
                                <div class="signature-line">Managing Director</div>
                            </div>
                            
                            <!-- Middle Seal -->
                            <div>
                                <div class="cert-seal">
                                    <i class="fa-solid fa-shield-halved mb-1 fs-5"></i>
                                    <span>VERIFIED</span>
                                    <span style="font-size: 0.5rem; scale: 0.9;">SECURE LOG</span>
                                </div>
                            </div>

                            <!-- Right sign -->
                            <div>
                                <div class="signature-font">Admin Registrar</div>
                                <div class="signature-line">Head of Academy</div>
                            </div>
                        </div>

                        <!-- Certificate Metadata / Authenticity footer -->
                        <div class="cert-meta-info">
                            <span>Certificate No: <strong><?php echo sanitize($certificate['certificate_number']); ?></strong></span>
                            <span>Issue Date: <strong><?php echo date('d M, Y', strtotime($certificate['issue_date'])); ?></strong></span>
                            <span>Verification Portal: <strong>flourishtecheng.com/verify</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Verify status badge (Only visible on screen, not printed) -->
                <div class="text-center mt-4 no-print">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold">
                        <i class="fa-solid fa-circle-check me-1.5"></i>Secured & Verified Document Reference (<?php echo sanitize($certificate['certificate_number']); ?>)
                    </span>
                    <p class="small text-muted mt-2 mb-0">Use the browser print function to output this certificate on premium cardstock.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Simple Footer (Not printed) -->
    <footer class="bg-white border-top py-3 text-center text-muted no-print mt-auto">
        <div class="container small">
            &copy; <?php echo date('Y'); ?> Flourish Techeng Limited. All Rights Reserved.
        </div>
    </footer>

</body>
</html>
