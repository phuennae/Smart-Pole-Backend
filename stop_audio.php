<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php'; // 🔥 ต้อง Include เข้ามาด้วย

$ip = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? 80;

if (!empty($ip)) {
    try {
        global $api_key;

        // 🔥 เพิ่ม ?key=... ต่อท้าย URL 
        $url = "http://{$ip}:{$port}/stop?key=" . urlencode($api_key); 
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1500);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo json_encode([
            "status" => "success", 
            "message" => "ส่งคำสั่งหยุดเล่นเสียงเรียบร้อย",
            "http_code" => $http_code
        ]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ข้อมูล IP ไม่ครบถ้วน"]);
}
?>