<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include 'config.php';

// รับค่าจาก FormData
$name = $_POST['name'] ?? '';
$ip   = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? '80';
$lat  = $_POST['lat'] ?? 0;
$lng  = $_POST['lng'] ?? 0;

// DEBUG: ถ้าเพิ่มไม่ได้ ให้ลบ comment บรรทัดล่างนี้เพื่อเช็คค่าที่รับมาได้
// file_put_contents('debug.log', print_r($_POST, true)); 

if (empty($name) || empty($ip)) {
    echo json_encode(["status" => "error", "message" => "ชื่อหรือ IP ห้ามเป็นค่าว่าง"]);
    exit;
}

try {
    // ระวัง: ตรวจสอบให้แน่ใจว่าชื่อ Column ในคำสั่งนี้ ตรงกับผลลัพธ์ของคำสั่ง DESCRIBE nodes
    $sql = "INSERT INTO nodes (name, ip_address, port, latitude, longitude) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $ip, $port, $lat, $lng]);
    
    echo json_encode(["status" => "success", "message" => "เพิ่มสำเร็จ"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>