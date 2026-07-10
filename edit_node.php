<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include 'config.php';

$id   = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$ip   = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? '80';
$lat  = $_POST['lat'] ?? 0;
$lng  = $_POST['lng'] ?? 0;

if ($id != '') {
    try {
        // อัปเดตข้อมูลตาม ID
        $stmt = $pdo->prepare("UPDATE nodes SET name=?, ip_address=?, port=?, latitude=?, longitude=? WHERE id=?");
        $stmt->execute([$name, $ip, $port, $lat, $lng, $id]);
        echo json_encode(["status" => "success", "message" => "แก้ไขสำเร็จ"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>