<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ✅ FIX: Use db_connection.php
include 'db_connection.php';

// Get action
$action = $_GET['action'] ?? '';

switch ($action) {

    // ─── READ ALL ───
    case 'read':
        $result = $conn->query("SELECT id, ordinance_no, violation_name, first_offense, second_offense, third_offense, created_at FROM master_violations ORDER BY created_at DESC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    // ─── CREATE ───
    case 'create':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $ordinanceNo   = trim($input['ordinance_no'] ?? '');
        $violationName = trim($input['violation_name'] ?? '');
        $firstOffense  = floatval($input['first_offense'] ?? 0);
        $secondOffense = floatval($input['second_offense'] ?? 0);
        $thirdOffense  = floatval($input['third_offense'] ?? 0);

        if (empty($ordinanceNo) || empty($violationName)) {
            echo json_encode(['status' => 'error', 'message' => 'Ordinance No. and Violation Name are required.']);
            exit();
        }

        if ($firstOffense <= 0 && $secondOffense <= 0 && $thirdOffense <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'At least one penalty amount is required.']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO master_violations (ordinance_no, violation_name, first_offense, second_offense, third_offense) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddd", $ordinanceNo, $violationName, $firstOffense, $secondOffense, $thirdOffense);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Violation added.', 'id' => $conn->insert_id]);
        break;

    // ─── UPDATE ───
    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id            = intval($input['id'] ?? 0);
        $ordinanceNo   = trim($input['ordinance_no'] ?? '');
        $violationName = trim($input['violation_name'] ?? '');
        $firstOffense  = floatval($input['first_offense'] ?? 0);
        $secondOffense = floatval($input['second_offense'] ?? 0);
        $thirdOffense  = floatval($input['third_offense'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit();
        }

        if (empty($ordinanceNo) || empty($violationName)) {
            echo json_encode(['status' => 'error', 'message' => 'Ordinance No. and Violation Name are required.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE master_violations SET ordinance_no = ?, violation_name = ?, first_offense = ?, second_offense = ?, third_offense = ? WHERE id = ?");
        $stmt->bind_param("ssdddi", $ordinanceNo, $violationName, $firstOffense, $secondOffense, $thirdOffense, $id);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Violation updated.']);
        break;

    // ─── DELETE ───
    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM master_violations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Violation deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Violation not found.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use: read, create, update, delete']);
        break;
}

$conn->close();
?>