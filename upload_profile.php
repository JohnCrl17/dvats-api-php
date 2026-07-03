<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

include 'db_connection.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = isset($_POST['client_id']) ? $_POST['client_id'] : null;

    if (isset($_FILES['profile_image']) && $client_id && $client_id !== "undefined") {
        
        // ✅ FIX: Use /tmp/ which is writable on Render
        $target_dir = "/tmp/uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_extension, $allowed)) {
            echo json_encode(["success" => false, "message" => "Invalid file type"]);
            exit;
        }

        $new_filename = "driver_" . $client_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            
            // ✅ Save as base64 in database instead of file path
            $imageData = base64_encode(file_get_contents($target_file));
            $base64Image = 'data:image/' . $file_extension . ';base64,' . $imageData;
            
            $stmt = $conn->prepare("UPDATE clients SET profile_path = ?, face_data = ? WHERE client_id = ?");
            $stmt->bind_param("ssi", $base64Image, $base64Image, $client_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true, 
                    "new_path" => $base64Image,
                    "message" => "Profile updated successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Database Update Failed: " . $conn->error]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Failed to move file."]);
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