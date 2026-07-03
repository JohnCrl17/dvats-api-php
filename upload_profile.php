<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

include 'db_connection.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang client_id mula sa POST
    $client_id = isset($_POST['client_id']) ? $_POST['client_id'] : null;

    // Check kung may file na inupload
    if (isset($_FILES['profile_image']) && $client_id && $client_id !== "undefined") {
        
        // ✅ FIX: Use absolute path with __DIR__
        $target_dir = __DIR__ . "/uploads/profiles/";
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                echo json_encode(["success" => false, "message" => "Failed to create directory"]);
                exit;
            }
        }

        // ✅ Check if writable
        if (!is_writable($target_dir)) {
            echo json_encode(["success" => false, "message" => "Directory not writable"]);
            exit;
        }

        $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        // ✅ Only allow image files
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_extension, $allowed)) {
            echo json_encode(["success" => false, "message" => "Invalid file type"]);
            exit;
        }

        $new_filename = "driver_" . $client_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // ✅ Update database with relative path for web access
            $db_path = "uploads/profiles/" . $new_filename;
            $sql = "UPDATE clients SET profile_path = '$db_path' WHERE client_id = '$client_id'";
            
            if ($conn->query($sql)) {
                echo json_encode([
                    "success" => true, 
                    "new_path" => $db_path,
                    "message" => "Profile updated successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Database Update Failed: " . $conn->error]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Failed to move file. Check folder permissions."]);
        }
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Missing data", 
            "debug_id" => $client_id, 
            "has_file" => isset($_FILES['profile_image'])
        ]);
    }
}
?>