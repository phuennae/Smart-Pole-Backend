<?php
// --- 1. เปิดอนุญาต CORS ให้ React ยิงเข้ามาได้ ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// จัดการ Preflight Request จาก Browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once 'config.php';

// --- 2. รับข้อมูลจาก React (รับเป็น JSON) ---
$input = json_decode(file_get_contents('php://input'), true);
$camera_id = $input['camera_id'] ?? null;
$action = $input['action'] ?? null;

if (!$camera_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน (ต้องการ camera_id และ action)']);
    exit;
}

// --- 3. ดึงข้อมูลกล้องจาก Database ---
try {
    $stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
    $stmt->execute([$camera_id]);
    $camera = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$camera) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลกล้องในระบบ']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// จัดการ IP ของกล้อง (ตัดคำว่า http หรือ rtsp ออกให้เหลือแค่ IP)
$ip = $camera['ip_address'];
if (strpos($ip, 'http') === 0 || strpos($ip, 'rtsp://') === 0) {
    $parsed = parse_url($ip);
    $ip = $parsed['host'] ?? $ip;
}

// ใช้ Username/Password ของกล้อง
$username = $camera['username'] ?? 'admin';
$password = $camera['password'] ?? '';

// --- 4. ส่งคำสั่งไปยัง Hikvision ISAPI ---
$result = sendPTZCommand($ip, $username, $password, $action);

echo json_encode($result);


// ==========================================
// ฟังก์ชันส่งคำสั่งไปยังกล้อง Hikvision
// ==========================================
function sendPTZCommand($ip, $username, $password, $action) {
    $base_url = "http://{$ip}/ISAPI/PTZCtrl/channels/1";
    
    $ptzData = [
        'up' => ['PanDirection' => 1, 'TiltDirection' => 1, 'ZoomDirection' => 'zoomOut'],
        'down' => ['PanDirection' => 1, 'TiltDirection' => 0, 'ZoomDirection' => 'zoomOut'],
        'left' => ['PanDirection' => 0, 'TiltDirection' => 1, 'ZoomDirection' => 'zoomOut'],
        'right' => ['PanDirection' => 1, 'TiltDirection' => 1, 'ZoomDirection' => 'zoomOut'],
        'zoomin' => ['PanDirection' => 0, 'TiltDirection' => 0, 'ZoomDirection' => 'zoomIn'],
        'zoomout' => ['PanDirection' => 0, 'TiltDirection' => 0, 'ZoomDirection' => 'zoomOut']
    ];
    
    if ($action === 'stop') {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><PTZData><PanDirection>0</PanDirection><TiltDirection>0</TiltDirection><ZoomDirection>zoomOut</ZoomDirection><PanSpeed>0</PanSpeed><TiltSpeed>0</TiltSpeed><ZoomSpeed>0</ZoomSpeed></PTZData>';
        $url = $base_url . '/continuous';
    } else {
        $params = $ptzData[$action] ?? null;
        if (!$params) return ['success' => false, 'message' => 'คำสั่งไม่ถูกต้อง'];
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?><PTZData><PanDirection>' . $params['PanDirection'] . '</PanDirection><TiltDirection>' . $params['TiltDirection'] . '</TiltDirection><ZoomDirection>' . $params['ZoomDirection'] . '</ZoomDirection><PanSpeed>50</PanSpeed><TiltSpeed>50</TiltSpeed><ZoomSpeed>50</ZoomSpeed></PTZData>';
        $url = $base_url . '/continuous';
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); // Hikvision มักใช้ Digest Auth
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/xml',
        'Content-Length: ' . strlen($xml)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'ติดต่อกล้องไม่ได้: ' . $error];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'ส่งคำสั่ง PTZ สำเร็จ'];
    } else {
        return ['success' => false, 'message' => "กล้องตอบกลับ Error Code: $httpCode"];
    }
}
?>