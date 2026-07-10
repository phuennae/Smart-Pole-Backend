<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

$ip = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? 80;

if (!empty($ip)) {
    try {
        // --- จุดสำคัญ: เปลี่ยน URL ให้ตรงกับคำสั่งหยุดของ ESP32 ---
        // (ปกติ ESP32 มักจะใช้ path /stop หรือ /clear เพื่อหยุดเสียง)
        $url = "http://{$ip}:{$port}/stop"; 
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
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