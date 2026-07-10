<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';

// รับค่า id ที่ส่งมาจาก React
$id = isset($_POST['id']) ? $_POST['id'] : '';

if ($id != '') {
    try {
        // คำสั่ง SQL ลบข้อมูลตาม ID
        $stmt = $pdo->prepare("DELETE FROM nodes WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(["status" => "success", "message" => "ลบ Node เรียบร้อย"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "ลบไม่สำเร็จ: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ไม่พบ ID ของ Node"]);
}
?>