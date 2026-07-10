<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';

$id = isset($_POST['id']) ? $_POST['id'] : '';

if ($id != '') {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["status" => "success", "message" => "ลบผู้ใช้เรียบร้อย"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการลบ"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ไม่มีรหัสผู้ใช้"]);
}
?>