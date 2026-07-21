<?php
// ไฟล์: api/get_logs.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// เรียกใช้ไฟล์ตั้งค่า Database ของคุณ
include 'config.php';

try {
    // ดึงข้อมูลเรียงจากเวลาล่าสุดไปเก่าสุด
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $logs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>