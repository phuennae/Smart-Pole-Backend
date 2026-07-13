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
    
    // ตรวจสอบนามสกุล
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3', 'wav', 'ogg'])) {
        echo json_encode(['status' => 'error', 'message' => 'รองรับเฉพาะไฟล์เพลงเท่านั้น']);
        exit;
    }

    // --- ส่วนสำคัญ: ส่งต่อไฟล์ไปที่ ESP32 ---
    global $api_key;
    
    // ⚠️ หมายเหตุ: URL ปลายทางของ ESP32 ปกติมักจะเป็น /upload หรือ /edit
    // สมมติว่า ESP32 ของคุณใช้ Endpoint ชื่อ /upload
    $esp32_url = "http://{$ip}:{$port}/upload?key=" . urlencode($api_key);

    // เตรียมไฟล์เพื่อส่งผ่าน cURL
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    
    // ตั้งชื่อ key ที่จะส่งไป ESP32 (ปกติบอร์ด Arduino มักจะรับชื่อ 'file' หรือ 'data')
    $post_data = array('file' => $cfile); 

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $esp32_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // เผื่อเวลาให้ ESP32 เขียนลง SD Card (ไฟล์ใหญ่อาจจะนาน)

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        echo json_encode(['status' => 'success', 'message' => 'อัปโหลดลง SD Card สำเร็จ!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ESP32 ปฏิเสธการรับไฟล์ (HTTP Code: ' . $http_code . ')']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'ส่งข้อมูลมาไม่ครบ (ต้องการไฟล์ และ IP)']);
}
?>