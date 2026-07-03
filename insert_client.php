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
    // ✅ FIX: Use db_connection.php instead of hardcoded localhost
    include 'db_connection.php';
    
    // Convert MySQLi to PDO for this script
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";port=" . getenv('DB_PORT'),
        getenv('DB_USER'),
        getenv('DB_PASS')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

    // 1. INSERT SA CLIENTS TABLE
    $sql = "INSERT INTO lto_system.clients 
    (fullname, license_no, email, phone_number, qr_token, profile_path, face_data, password, date_of_birth, license_expiry, gender, finger_data, qr_image, reg_date)
    VALUES 
    (:fullname, :license, :email, :phone, :token, :profile, :face, :password, :dob, :expiry, :gender, :finger, :qr_img, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fullname' => $fullname,
        ':license'  => $license_no,
        ':email'    => $email,
        ':phone'    => $phone,
        ':token'    => $qr_token,
        ':profile'  => $face_data,
        ':face'     => $face_data,
        ':password' => $password_db,
        ':dob'      => $date_of_birth,
        ':expiry'   => $license_expiry,
        ':gender'   => $gender,
        ':finger'   => $finger_data,
        ':qr_img'   => $qr_image
    ]);

    $new_user_id = $pdo->lastInsertId();

    // 2. AUTO-SYNC VIOLATIONS (lto_system database)
    $syncSql = "UPDATE lto_system.violations 
                SET client_id = :user_id, 
                    is_registered = 1,
                    driver_name = :fullname
                WHERE TRIM(license_no) = :license
                AND client_id IS NULL";
    
    $syncStmt = $pdo->prepare($syncSql);
    $syncStmt->execute([
        ':user_id'  => $new_user_id,
        ':fullname' => $fullname,
        ':license'  => $license_no
    ]);

    // 3. AUTO-SYNC APPREHENSIONS (dvats_db database)
    $syncApp = "UPDATE dvats_db.apprehensions 
                SET client_id = :user_id, 
                    is_registered = 1,
                    driver_name = :fullname
                WHERE TRIM(license_no) = :license
                AND client_id IS NULL";
    
    $pdo->prepare($syncApp)->execute([
        ':user_id'  => $new_user_id,
        ':fullname' => $fullname,
        ':license'  => $license_no
    ]);

    echo json_encode([
        "status" => "success", 
        "qr_token" => $qr_token, 
        "message" => "Account created and violations synced successfully!"
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Database Error: " . $e->getMessage()
    ]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "System Error: " . $e->getMessage()
    ]);
}
?>