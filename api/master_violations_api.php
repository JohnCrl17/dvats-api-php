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

// Database connection
$host = 'localhost';
$db   = 'lto_system';        // palitan mo kung iba
$user = 'root';              // palitan mo
$pass = '';                  // palitan mo

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

// Get action
$action = $_GET['action'] ?? '';

switch ($action) {

    // ─── READ ALL ───
    case 'read':
        try {
            $stmt = $pdo->query("SELECT id, ordinance_no, violation_name, first_offense, second_offense, third_offense, created_at FROM master_violations ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ─── CREATE ───
    case 'create':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $ordinanceNo   = trim($input['ordinance_no'] ?? '');
        $violationName = trim($input['violation_name'] ?? '');
        $firstOffense  = floatval($input['first_offense'] ?? 0);
        $secondOffense = floatval($input['second_offense'] ?? 0);
        $thirdOffense  = floatval($input['third_offense'] ?? 0);

        // Validation: at least ordinance_no, violation_name, and one penalty
        if (empty($ordinanceNo) || empty($violationName)) {
            echo json_encode(['status' => 'error', 'message' => 'Ordinance No. and Violation Name are required.']);
            exit();
        }

        if ($firstOffense <= 0 && $secondOffense <= 0 && $thirdOffense <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'At least one penalty amount is required.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO master_violations (ordinance_no, violation_name, first_offense, second_offense, third_offense) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ordinanceNo, $violationName, $firstOffense, $secondOffense, $thirdOffense]);
            echo json_encode(['status' => 'success', 'message' => 'Violation added.', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
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

        // Validation
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit();
        }

        if (empty($ordinanceNo) || empty($violationName)) {
            echo json_encode(['status' => 'error', 'message' => 'Ordinance No. and Violation Name are required.']);
            exit();
        }

        if ($firstOffense <= 0 && $secondOffense <= 0 && $thirdOffense <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'At least one penalty amount is required.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE master_violations SET ordinance_no = ?, violation_name = ?, first_offense = ?, second_offense = ?, third_offense = ? WHERE id = ?");
            $stmt->execute([$ordinanceNo, $violationName, $firstOffense, $secondOffense, $thirdOffense, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Violation updated.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ─── DELETE ───
    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM master_violations WHERE id = ?");
            $stmt->execute([$id]);
            
            // Check if any row was actually deleted
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Violation deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Violation not found.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ─── READ SINGLE (optional — for future use) ───
    case 'read_single':
        $id = intval($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT id, ordinance_no, violation_name, first_offense, second_offense, third_offense, created_at FROM master_violations WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Violation not found.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use: read, create, update, delete, read_single']);
        break;
}
?>  