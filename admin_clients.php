<?php
// 1. Database Connection
$host = "localhost";
$db_name = "lto_system";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Search Logic
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $query = "SELECT * FROM clients WHERE fullname LIKE :s OR license_no LIKE :s ORDER BY reg_date DESC";
    $stmt = $conn->prepare($query);
    $searchTerm = "%$search%";
    $stmt->bindParam(':s', $searchTerm);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTO Admin | Client Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .table thead { background-color: #f8fafc; }
        .client-img { width: 45px; height: 45px; object-fit: cover; border-radius: 10px; border: 2px solid #e2e8f0; }
        .status-badge { font-size: 0.75rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; }
    </style>
</head>
<body>

<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-md-11">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">Registered Clients</h2>
                    <p class="text-muted">Manage and verify driver biometric records.</p>
                </div>
                <form class="d-flex gap-2" method="GET">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search Name or License..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary px-4">Filter</button>
                </form>
            </div>

            <div class="card p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Full Name</th>
                                <th>License No.</th>
                                <th>Contact Info</th>
                                <th>QR Token</th>
                                <th>Biometrics</th>
                                <th>Registration Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($clients) > 0): ?>
                                <?php foreach ($clients as $row): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $row['face_date'] ? $row['face_date'] : 'https://via.placeholder.com/150'; ?>" class="client-img shadow-sm">
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo $row['fullname']; ?></div>
                                        <small class="text-muted"><?php echo $row['age']; ?> yrs old | <?php echo $row['gender']; ?></small>
                                    </td>
                                    <td><code class="text-primary fw-bold"><?php echo $row['license_no']; ?></code></td>
                                    <td>
                                        <div class="small"><i class="bi bi-envelope me-1"></i> <?php echo $row['email']; ?></div>
                                        <div class="small text-muted"><i class="bi bi-phone me-1"></i> <?php echo $row['phone_number']; ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $row['qr_token']; ?></span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <i class="bi bi-person-bounding-box <?php echo $row['face_date'] ? 'text-success' : 'text-danger'; ?>" title="Face Scan"></i>
                                            <i class="bi bi-fingerprint <?php echo $row['finger_data'] ? 'text-success' : 'text-danger'; ?>" title="Fingerprint"></i>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?php echo date('M d, Y', strtotime($row['reg_date'])); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewClient(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted">No clients found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body p-4 text-center">
                <img id="modal-img" src="" class="rounded-circle border mb-3 shadow" style="width: 120px; height: 120px; object-fit: cover;">
                <h4 id="modal-name" class="fw-bold mb-1"></h4>
                <p id="modal-license" class="text-primary fw-bold mb-4"></p>
                
                <div class="row g-3 text-start bg-light p-3 rounded-4 mb-3">
                    <div class="col-6 small"><strong>Age:</strong> <span id="modal-age"></span></div>
                    <div class="col-6 small"><strong>Gender:</strong> <span id="modal-gender"></span></div>
                    <div class="col-12 small"><strong>Email:</strong> <span id="modal-email"></span></div>
                    <div class="col-12 small"><strong>Fingerprint Hash:</strong> <br><small class="text-break text-muted" id="modal-finger"></small></div>
                </div>

                <button type="button" class="btn btn-secondary w-100 py-2 rounded-3" data-bs-dismiss="modal">Close Profile</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
    
    function viewClient(client) {
        document.getElementById('modal-img').src = client.face_date ? client.face_date : 'https://via.placeholder.com/150';
        document.getElementById('modal-name').innerText = client.fullname;
        document.getElementById('modal-license').innerText = client.license_no;
        document.getElementById('modal-age').innerText = client.age;
        document.getElementById('modal-gender').innerText = client.gender;
        document.getElementById('modal-email').innerText = client.email;
        document.getElementById('modal-finger').innerText = client.finger_data;
        
        viewModal.show();
    }
</script>
</body>
</html>