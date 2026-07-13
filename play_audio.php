<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php'; // ดึง $api_key มาจากไฟล์นี้

$node_id = $_POST['node_id'] ?? '';
$ip = $_POST['ip'] ?? '';
$file = $_POST['file'] ?? 'alarm.mp3';
$port = $_POST['port'] ?? 80;

if (!empty($node_id) && !empty($ip)) {
    try {
        global $api_key; // เรียกใช้ตัวแปรจาก config.php

        // 🔥 เพิ่ม &key=... ต่อท้าย URL เพื่อยืนยันตัวตนกับ ESP32
        $url = "http://{$ip}:{$port}/play?file=" . urlencode($file) . "&key=" . urlencode($api_key);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1500); // เผื่อเวลาเน็ตช้านิดหน่อย
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);              
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo json_encode([
            "status" => "success", 
            "message" => "ส่งคำสั่งเล่นเสียงไปที่เสาเรียบร้อย",
            "http_code" => $http_code 
        ]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ข้อมูล IP หรือ Node ID ไม่ครบถ้วน"]);
}
?>