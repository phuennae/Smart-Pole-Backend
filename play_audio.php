<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// จัดการ CORS Preflight (สำหรับ React)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'config.php';

// รับค่าที่ส่งมาจาก React
$node_id = $_POST['node_id'] ?? '';
$ip = $_POST['ip'] ?? '';
$file = $_POST['file'] ?? 'alarm.mp3';
$port = $_POST['port'] ?? 80;

if (!empty($node_id) && !empty($ip)) {
    try {
        // ---------------------------------------------------------
        // จุดสำคัญ: เปลี่ยน URL ตรงนี้ให้ตรงกับคำสั่งที่ ESP32 ของคุณรับ
        // สมมติว่า ESP32 รับคำสั่งเล่นไฟล์ผ่าน path /play?file=...
        // ---------------------------------------------------------
        $url = "http://{$ip}:{$port}/play?file=" . urlencode($file);
        
        // ใช้ cURL ยิงคำสั่งไปหา ESP32 (ผ่าน WireGuard IP)
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000); // ให้เวลาเชื่อมต่อ 1 วินาที
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);              // รอให้ ESP32 ตอบกลับสูงสุด 2 วินาที
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // (Optional) คุณอาจจะเขียนโค้ดบันทึก Log ลง Database ตรงนี้ได้ว่าใครกดปุ่ม Alarm ตอนกี่โมง

        // ตอบกลับ React ว่าส่งคำสั่งสำเร็จ
        echo json_encode([
            "status" => "success", 
            "message" => "ส่งคำสั่ง Alarm ไปที่เสาเรียบร้อย",
            "http_code" => $http_code // ส่งค่ากลับไปเช็คว่า ESP32 ตอบกลับ 200 OK ไหม
        ]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "ข้อมูล IP หรือ Node ID ไม่ครบถ้วน"]);
}
?>