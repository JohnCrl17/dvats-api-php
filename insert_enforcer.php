<?php
// 1. PINAKAMAHALAGA: Headers para sa Cross-Origin at Ngrok bypass
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// FIX: Siguraduhing magre-respond ng 200 OK sa OPTIONS bago mag-exit para makalusot sa CORS ng Browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Error Reporting para sa Debugging habang gumagawa ng Thesis
// Pwede mong iwan itong E_ALL habang nagte-test para sa console logs natin
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 3. Database Connection - Ituturo natin sa lto_system dahil ito ang Admin Database
    $conn = new mysqli("localhost", "root", "", "lto_system");

    if ($conn->connect_error) {
        throw new Exception("Database Connection Failed: " . $conn->connect_error);
    }

    // Siguraduhin na POST request ang pumapasok
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method. POST only.");
    }

    // 4. Saluhin ang mga Text Fields mula sa FormData ng HTML Form natin
    $fullname     = isset($_POST['fullname']) ? $conn->real_escape_string($_POST['fullname']) : '';
    $badge_no     = isset($_POST['badge_no']) ? $conn->real_escape_string($_POST['badge_no']) : '';
    $unit         = isset($_POST['unit']) ? $conn->real_escape_string($_POST['unit']) : '';
    $password     = isset($_POST['password']) ? $_POST['password'] : ''; 
    $dob          = isset($_POST['dob']) ? $conn->real_escape_string($_POST['dob']) : '';
    $gender       = isset($_POST['gender']) ? $conn->real_escape_string($_POST['gender']) : '';
    $email        = isset($_POST['email']) ? $conn->real_escape_string($_POST['email']) : '';
    $phone_number = isset($_POST['phone_number']) ? $conn->real_escape_string($_POST['phone_number']) : '';

    // Validation para walang blangkong makalusot
    if (empty($fullname) || empty($badge_no) || empty($unit) || empty($password)) {
        throw new Exception("Required credentials (Name, Badge, Unit, Password) are missing.");
    }

    // I-secure ang password gamit ang BCRYPT para kahit buksan ang database, tago ang password ng enforcer
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 5. Pag-handle sa mga Biometric at QR Images (Base64)
    $face_data = isset($_POST['face_data']) ? $_POST['face_data'] : null;
    $qr_image  = isset($_POST['qr_image']) ? $_POST['qr_image'] : null;

    // 6. SQL Query - Isasalpak na natin sa mga enforcers table ng lto_system
    $sql = "INSERT INTO enforcers (full_name, badge_number, unit, password, dob, gender, email, phone_number, face_token, qr_code_token, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query Preparation Failed: " . $conn->error);
    }

    // I-bind ang mga parameters ("ssssssssss" nangangahulugang 10 string values)
    $stmt->bind_param("ssssssssss", $fullname, $badge_no, $unit, $hashed_password, $dob, $gender, $email, $phone_number, $face_data, $qr_image);

    if (!$stmt->execute()) {
        // Kung may error gaya ng Duplicate Badge Number
        if ($conn->errno == 1062) {
            throw new Exception("Enrollment Rejected: Badge Number or Email already registered.");
        }
        throw new Exception("Execution Failed: " . $stmt->error);
    }

    // 7. Success Output
    echo json_encode([
        "status" => "success",
        "message" => "Enforcer successfully enrolled and activated."
    ]);

} catch (Exception $e) {
    // Error Output
    // Ginawa nating 200 para kahit mag-error ang business logic (gaya ng duplicate badge), 
    // mababasa pa rin ng fetch wrapper mo yung JSON structure nang maayos nang hindi hinaharang ng browser network layer.
    http_response_code(200); 
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

// Isara ang koneksyon
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?>