<?php
// ไฟล์: api/add_log.php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// เรียกใช้ไฟล์ตั้งค่า Database ของคุณ
include 'config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $input['username'] ?? 'Unknown';
    $action = $input['action'] ?? '';
    $node_name = $input['node_name'] ?? '-';

    if (empty($action)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing action']);
        exit;
    }

    // 1. บันทึก Log ใหม่ลง Database
    $stmt = $pdo->prepare("INSERT INTO activity_logs (username, action, node_name) VALUES (?, ?, ?)");
    $stmt->execute([$username, $action, $node_name]);

    // 2. เคลียร์ Log เก่าที่อายุเกิน 7 วัน
    $stmtDelete = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL 7 DAY");
    $stmtDelete->execute();

    echo json_encode(['status' => 'success', 'message' => 'Log saved and old logs purged']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>