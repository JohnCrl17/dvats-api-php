<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

include 'db_connection.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang client_id mula sa POST
    $client_id = isset($_POST['client_id']) ? $_POST['client_id'] : null;

    // Check kung may file na inupload
    if (isset($_FILES['profile_image']) && $client_id && $client_id !== "undefined") {
        
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "driver_" . $client_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // UPDATE SQL: Siguraduhin na 'client_id' ang column name sa table mo
            $sql = "UPDATE clients SET profile_path = '$target_file' WHERE client_id = '$client_id'";
            
            if ($conn->query($sql)) {
                // I-return din ang full URL para ma-update agad ang UI
                echo json_encode([
                    "success" => true, 
                    "new_path" => $target_file,
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