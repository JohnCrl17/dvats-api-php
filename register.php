<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// 1. Connection settings
$host = "localhost";
$user = "root";
$pass = ""; 
$dbname = "dvats_db"; 

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database Connection Failed"]);
    exit();
}

// Kunin ang JSON data mula sa request body
$data = json_decode(file_get_contents("php://input"));

// 2. I-check kung kumpleto ang data (Kasama na ang email at district_office)
if (
    isset($data->fullName) && 
    isset($data->badgeNo) && 
    isset($data->unit) && 
    isset($data->password) &&
    isset($data->profilePic) &&
    isset($data->email) &&
    isset($data->district_office)
) {
    
    $name = $data->fullName;
    $badge = $data->badgeNo;
    $unit = $data->unit;
    $password = $data->password; 
    $profilePic = $data->profilePic;
    $email = $data->email;
    $district = $data->district_office;

    // 3. I-check muna kung existing na ang Badge Number
    $checkSql = "SELECT badge_number FROM enforcers WHERE badge_number = ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("s", $badge);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Badge Number already registered"]);
    } else {
        // 4. SQL Query: Idinagdag ang email, district_office, at status (default 'pending')
        $sql = "INSERT INTO enforcers (full_name, badge_number, unit, password, profile_pic, email, district_office, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        // "sssssss" = 7 strings
        $stmt->bind_param("sssssss", $name, $badge, $unit, $password, $profilePic, $email, $district);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success", 
                "message" => "Account created successfully. Please wait for Admin approval."
            ]);
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "Database Error: " . $stmt->error
            ]);
        }
        $stmt->close();
    }
    $stmtCheck->close();
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "All fields (Fullname, Badge, Unit, Password, ProfilePic, Email, District) are required"
    ]);
}

$conn->close();
?>