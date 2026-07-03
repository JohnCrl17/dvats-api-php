<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

try {
    // ✅ FIX: Use db_connection.php (MySQLi)
    include 'db_connection.php';

    // Kunin ang data mula sa POST request
    $fullname       = $_POST['fullname'] ?? null;
    $license_no     = trim($_POST['license_no'] ?? null);
    $password_db    = $_POST['password'] ?? null;
    $date_of_birth  = $_POST['date_of_birth'] ?? null;
    $license_expiry = $_POST['license_expiry'] ?? null;
    $gender         = $_POST['gender'] ?? null;
    $email          = $_POST['email'] ?? null;
    $phone          = $_POST['phone_number'] ?? null;
    $face_data      = $_POST['face_data'] ?? null;
    $finger_data    = $_POST['finger_data'] ?? null;
    $qr_image       = $_POST['qr_image'] ?? null;
    
    // Generate Token
    $qr_token = "LTO-" . strtoupper(bin2hex(random_bytes(4)));

    // 1. INSERT SA CLIENTS TABLE (MySQLi version)
    $stmt = $conn->prepare("INSERT INTO lto_system.clients 
        (fullname, license_no, email, phone_number, qr_token, profile_path, face_data, password, date_of_birth, license_expiry, gender, finger_data, qr_image, reg_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("sssssssssssss", 
        $fullname, $license_no, $email, $phone, $qr_token, 
        $face_data, $face_data, $password_db, $date_of_birth, 
        $license_expiry, $gender, $finger_data, $qr_image
    );
    
    $stmt->execute();
    $new_user_id = $conn->insert_id;

    // 2. AUTO-SYNC VIOLATIONS
    $stmt2 = $conn->prepare("UPDATE lto_system.violations 
                SET client_id = ?, is_registered = 1, driver_name = ?
                WHERE TRIM(license_no) = ? AND client_id IS NULL");
    $stmt2->bind_param("iss", $new_user_id, $fullname, $license_no);
    $stmt2->execute();

    // 3. AUTO-SYNC APPREHENSIONS
    $stmt3 = $conn->prepare("UPDATE dvats_db.apprehensions 
                SET client_id = ?, is_registered = 1, driver_name = ?
                WHERE TRIM(license_no) = ? AND client_id IS NULL");
    $stmt3->bind_param("iss", $new_user_id, $fullname, $license_no);
    $stmt3->execute();

    echo json_encode([
        "status" => "success", 
        "qr_token" => $qr_token, 
        "message" => "Account created and violations synced successfully!"
    ]);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>