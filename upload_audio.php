<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// จัดการ Pre-flight request ของ Browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php'; // ดึงค่า $api_key

$ip = $_POST['ip'] ?? '';
$port = $_POST['port'] ?? 80;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audioFile']) && !empty($ip)) {
    
    $file = $_FILES['audioFile'];
    
    // 1. ตรวจสอบว่าไฟล์อัปโหลดมาถึง PHP สมบูรณ์ไหม
    if ($file['error'] !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการรับไฟล์บน Server']);
        exit;
    }

    // 2. ตรวจสอบนามสกุลไฟล์ (บังคับเฉพาะ .mp3 ตามที่ปรับแก้ล่าสุด)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'mp3') {
        echo json_encode(['status' => 'error', 'message' => 'ระบบรองรับเฉพาะไฟล์ .mp3 เท่านั้น']);
        exit;
    }

    // 3. เตรียมส่งต่อไฟล์ข้ามไปที่ ESP32 
    global $api_key;
    $esp32_url = "http://{$ip}:{$port}/upload";
    
    // แนบ API Key ถ้ามีตั้งค่าไว้ใน config
    if (!empty($api_key)) {
        $esp32_url .= "?key=" . urlencode($api_key);
    }

    // 🔥 จุดสำคัญ: ดึงไฟล์จาก โฟลเดอร์ชั่วคราว ($file['tmp_name']) มาส่งต่อตรงๆ โดยไม่ใช้ move_uploaded_file()
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    $post_data = array('file' => $cfile); 

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $esp32_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600); // เผื่อเวลาให้ WiFi ส่งไฟล์และ ESP32 เขียนลง SD Card จนเสร็จ

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // หลังจบกระบวนการนี้ PHP จะลบไฟล์ Temp ในโฟลเดอร์ XAMPP ทิ้งให้ทันที
    if ($http_code === 200) {
        echo json_encode(['status' => 'success', 'message' => 'อัปโหลดลง SD Card ของเสาสำเร็จ!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ESP32 ปฏิเสธการรับไฟล์ (HTTP Code: ' . $http_code . ')']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'ส่งข้อมูลมาไม่ครบถ้วน']);
}
?>