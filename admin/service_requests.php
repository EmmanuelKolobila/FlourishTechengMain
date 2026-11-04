<?php
/**
 * Service Request and Quotation Management Panel
 * Flourish Techeng Limited
 */

require_once 'includes/admin_header.php';

$action = sanitize($_GET['action'] ?? '');
$db = getDB();
$errors = [];

// -----------------------------------------------------------------------------
// POST HANDLER: UPDATE SERVICE REQUEST STATUS
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_service') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMsg('error', "Security validation failed.");
    } else {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'Pending');

        if ($reqId > 0) {
            try {
                $stmt = $db->prepare("UPDATE service_requests SET status = ? WHERE id = ?");
                $stmt->execute([$status, $reqId]);

                // Notify customer
                $cId = $db->query("SELECT customer_id FROM service_requests WHERE id = $reqId")->fetchColumn();
                if ($cId) {
                    $db->prepare("
                        INSERT INTO notifications (user_id, user_type, title, message) 
                        VALUES (?, 'customer', ?, ?)
                    ")->execute([
                        $cId, 
                        "Service Request Update", 
                        "Your service request #$reqId status has been updated to '$status'."
                    ]);
                }

                setFlashMsg('success', "Service request updated successfully.");
            } catch (PDOException $e) {
                setFlashMsg('error', "Failed to update service request: " . $e->getMessage());
            }
        }
    }
    header("Location: service_requests.php");
    exit;
}

// -----------------------------------------------------------------------------
// POST HANDLER: UPDATE QUOTATION STATUS & RESPONSE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_quotation') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMsg('error', "Security validation failed.");
    } else {
        $qId = (int)($_POST['quotation_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'Pending');
        $response_details = sanitize($_POST['response_details'] ?? '');

        if ($qId > 0) {
            try {
                $stmt = $db->prepare("UPDATE quotations SET status = ?, response_details = ? WHERE id = ?");
                $stmt->execute([$status, $response_details, $qId]);

                // Notify customer if applicable
                $cId = $db->query("SELECT customer_id FROM quotations WHERE id = $qId")->fetchColumn();
                if ($cId) {
                    $db->prepare("
                        INSERT INTO notifications (user_id, user_type, title, message) 
                        VALUES (?, 'customer', ?, ?)
                    ")->execute([
                        $cId, 
                        "Quotation Request Update", 
                        "Your quotation request #$qId status has been updated to '$status'. Response: $response_details"
                    ]);
                }

                setFlashMsg('success', "Quotation status updated successfully.");
            } catch (PDOException $e) {
                setFlashMsg('error', "Failed to update quotation: " . $e->getMessage());
            }
        }
    }
    header("Location: service_requests.php");
    exit;
}

// -----------------------------------------------------------------------------
// GET: LOAD DATA
// -----------------------------------------------------------------------------
try {
    // Fetch Service Bookings
    $serviceRequests = $db->query("
        SELECT sr.*, c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, s.name as service_name
        FROM service_requests sr
        JOIN customers c ON sr.customer_id = c.id
        JOIN services s ON sr.service_id = s.id
        ORDER BY sr.id DESC
    ")->fetchAll();

    // Fetch Quotation Requests
    $quotations = $db->query("
        SELECT * FROM quotations 
        ORDER BY id DESC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Error loading requests data: " . $e->getMessage());
}
?>

<!-- Section 1: Service requests -->
<div class="card card-custom border-0 p-4 shadow-sm mb-4">
    <h4 class="fw-bold mb-3"><i class="fa-solid fa-calendar-check text-primary-custom me-2"></i>Technical Service Bookings</h4>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle w-100" id="servicesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Requested Service</th>
                    <th>Details</th>
                    <th>Preferred Date</th>
                    <th>Status</th>
                    <th>Date Booked</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($serviceRequests as $sr): ?>
                    <tr>
                        <td>#<?php echo $sr['id']; ?></td>
                        <td>
                            <div class="fw-bold"><?php echo sanitize($sr['customer_name']); ?></div>
                            <small class="text-muted"><?php echo sanitize($sr['customer_phone']); ?></small>
                        </td>
                        <td><strong class="text-secondary"><?php echo sanitize($sr['service_name']); ?></strong></td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;" title="<?php echo sanitize($sr['details']); ?>">
                                <?php echo sanitize($sr['details']); ?>
                            </div>
                        </td>
                        <td><?php echo $sr['preferred_date'] ? date('d M, Y', strtotime($sr['preferred_date'])) : 'Any time'; ?></td>
                        <td>
                            <?php 
                            $badge = 'bg-secondary';
                            if ($sr['status'] === 'Pending') $badge = 'bg-warning text-dark';
                            elseif ($sr['status'] === 'Contacted') $badge = 'bg-info';
                            elseif ($sr['status'] === 'In Progress') $badge = 'bg-primary';
                            elseif ($sr['status'] === 'Completed') $badge = 'bg-success';
                            elseif ($sr['status'] === 'Cancelled') $badge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo $sr['status']; ?></span>
                        </td>
                        <td><?php echo date('d M, Y', strtotime($sr['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary update-service-btn" 
                                    data-id="<?php echo $sr['id']; ?>" 
                                    data-service="<?php echo sanitize($sr['service_name']); ?>" 
                                    data-status="<?php echo $sr['status']; ?>" 
                                    data-details="<?php echo sanitize($sr['details']); ?>"
                                    title="Edit Status">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Quotations requests -->
<div class="card card-custom border-0 p-4 shadow-sm mb-4">
    <h4 class="fw-bold mb-3"><i class="fa-solid fa-file-invoice-dollar text-primary-custom me-2"></i>Custom Quotation Requests (BOMs)</h4>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle w-100" id="quotationsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client Name</th>
                    <th>Company</th>
                    <th>Specifications</th>
                    <th>Attached BOM</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotations as $q): ?>
                    <tr>
                        <td>#<?php echo $q['id']; ?></td>
                        <td>
                            <div class="fw-bold"><?php echo sanitize($q['full_name']); ?></div>
                            <small class="text-muted"><?php echo sanitize($q['email']); ?> | <?php echo sanitize($q['phone']); ?></small>
                        </td>
                        <td><?php echo $q['company_name'] ? sanitize($q['company_name']) : '<em class="text-muted">None</em>'; ?></td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;" title="<?php echo sanitize($q['details']); ?>">
                                <?php echo sanitize($q['details']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($q['file_path'] && file_exists('../' . $q['file_path'])): ?>
                                <a href="../<?php echo $q['file_path']; ?>" class="btn btn-xs btn-outline-success font-monospace" style="font-size: 0.75rem;" download>
                                    <i class="fa-solid fa-download me-1"></i> BOM File
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">No file</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $qBadge = 'bg-secondary';
                            if ($q['status'] === 'Pending') $qBadge = 'bg-warning text-dark';
                            elseif ($q['status'] === 'Responded') $qBadge = 'bg-success';
                            elseif ($q['status'] === 'Closed') $qBadge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $qBadge; ?>"><?php echo $q['status']; ?></span>
                        </td>
                        <td><?php echo date('d M, Y', strtotime($q['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary update-quotation-btn" 
                                    data-id="<?php echo $q['id']; ?>" 
                                    data-name="<?php echo sanitize($q['full_name']); ?>" 
                                    data-status="<?php echo $q['status']; ?>" 
                                    data-details="<?php echo sanitize($q['details']); ?>"
                                    data-response="<?php echo sanitize($q['response_details']); ?>"
                                    title="Respond & Edit">
                                <i class="fa-solid fa-reply"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Update Service Booking Status -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="service_requests.php?action=update_service" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="serviceModalLabel">Manage Service Request #<span id="modalServiceId"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="request_id" id="modalServiceRequestId">

                    <div class="mb-3 bg-light p-3 rounded">
                        <strong class="d-block small text-muted">SERVICE NAME:</strong>
                        <span id="modalServiceName" class="fw-semibold"></span>
                    </div>

                    <div class="mb-3 bg-light p-3 rounded">
                        <strong class="d-block small text-muted">CUSTOMER REQUEST DETAILS:</strong>
                        <span id="modalServiceDetails" class="small text-secondary"></span>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold">Update Status</label>
                        <select name="status" id="modalServiceStatus" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Contacted">Contacted</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Respond & Update Quotation -->
<div class="modal fade" id="quotationModal" tabindex="-1" aria-labelledby="quotationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="service_requests.php?action=update_quotation" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="quotationModalLabel">Respond to Quotation #<span id="modalQuotationId"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="quotation_id" id="modalQuotationIdInput">

                    <div class="mb-3 bg-light p-3 rounded">
                        <strong class="d-block small text-muted">CLIENT:</strong>
                        <span id="modalQuotationClient" class="fw-semibold"></span>
                    </div>

                    <div class="mb-3 bg-light p-3 rounded" style="max-height: 120px; overflow-y: auto;">
                        <strong class="d-block small text-muted">SPECIFICATIONS DETAILS:</strong>
                        <span id="modalQuotationDetails" class="small text-secondary"></span>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="q_status" class="form-label fw-semibold">Quotation Status</label>
                        <select name="status" id="modalQuotationStatus" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Responded">Responded (Sent Quote)</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <!-- Response comment -->
                    <div class="mb-3">
                        <label for="response_details" class="form-label fw-semibold">Quotation Response / Details</label>
                        <textarea name="response_details" id="modalQuotationResponse" class="form-control" rows="4" placeholder="Enter quote price, invoice summary, or custom specifications follow-up..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom">Send Response</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Init tables
    $('#servicesTable').DataTable({ order: [[0, 'desc']], responsive: true });
    $('#quotationsTable').DataTable({ order: [[0, 'desc']], responsive: true });

    // Populate service updates
    $('.update-service-btn').on('click', function() {
        const id = $(this).data('id');
        const serviceName = $(this).data('service');
        const status = $(this).data('status');
        const details = $(this).data('details');

        $('#modalServiceId').text(id);
        $('#modalServiceRequestId').val(id);
        $('#modalServiceName').text(serviceName);
        $('#modalServiceDetails').text(details);
        $('#modalServiceStatus').val(status);

        const myModal = new bootstrap.Modal(document.getElementById('serviceModal'));
        myModal.show();
    });

    // Populate quotation response modal
    $('.update-quotation-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const status = $(this).data('status');
        const details = $(this).data('details');
        const response = $(this).data('response');

        $('#modalQuotationId').text(id);
        $('#modalQuotationIdInput').val(id);
        $('#modalQuotationClient').text(name);
        $('#modalQuotationDetails').text(details);
        $('#modalQuotationStatus').val(status);
        $('#modalQuotationResponse').val(response);

        const myModal = new bootstrap.Modal(document.getElementById('quotationModal'));
        myModal.show();
    });
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
