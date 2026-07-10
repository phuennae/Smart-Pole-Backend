<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'config.php';

try {
    $stmt = $pdo->query("SELECT * FROM cameras ORDER BY id DESC");
    $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // แปลงให้เป็นรูปแบบที่ React ใช้ง่ายขึ้น
    $formatted = array();
    foreach ($cameras as $row) {
        $formatted[] = [
            'id' => $row['id'],
            'name' => $row['camera_name'],
            'ip' => $row['ip_address'],
            'ptz_ip' => $row['ptz_ip'],
            'ptz_port' => $row['ptz_port'] ?: 80,
            'ptz_username' => $row['ptz_username'],
            'ptz_password' => $row['ptz_password'],
            'location' => $row['location'],
            // สมมติพิกัดชั่วคราว ถ้าใน DB ยังไม่มีคอลัมน์ lat/lng (เดี๋ยวเราค่อยไปเพิ่มทีหลังได้ครับ)
            'lat' => isset($row['latitude']) ? $row['latitude'] : 18.7953,
            'lng' => isset($row['longitude']) ? $row['longitude'] : 98.9529,
        ];
    }
    
    echo json_encode(["status" => "success", "data" => $formatted]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>