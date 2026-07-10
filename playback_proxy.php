<?php
// playback_proxy.php - API ค้นหาไฟล์วิดีโอย้อนหลัง
while (ob_get_level()) ob_end_flush();
ini_set('display_errors', 0);

function jsonResp($data, $httpCode = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResp(['status' => 'ok']);
}

function loadDatabase() {
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) return $pdo;
    require_once __DIR__ . '/config.php';
    return $pdo;
}

function extractIP($url) {
    if (preg_match('/^https?:\/\/([^\/:]+)/', $url, $m)) return $m[1];
    return $url;
}

function utcToLocal($utcString) {
    try {
        $dt = new DateTime($utcString);
        $dt->setTimezone(new DateTimeZone('Asia/Bangkok'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $utcString;
    }
}

$action = $_REQUEST['action'] ?? '';

// ============================================
// Action: Search Recordings (ค้นหาวิดีโอย้อนหลัง)
// ============================================
if ($action === 'search') {
    try {
        $cameraId = intval($_REQUEST['camera_id'] ?? 0);
        $date = $_REQUEST['date'] ?? date('Y-m-d');
        
        if (!$cameraId) jsonResp(['success' => false, 'error' => 'Missing camera_id'], 400);
        
        $pdo = loadDatabase();
        $stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
        $stmt->execute([$cameraId]);
        $cam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cam) jsonResp(['success' => false, 'error' => 'Camera not found'], 404);
        
        $ptzIp = !empty($cam['ptz_ip']) ? $cam['ptz_ip'] : $cam['ip_address'];
        $ptzIp = extractIP($ptzIp);
        
        $username = $cam['ptz_username'] ?? 'admin';
        $password = $cam['ptz_password'] ?? '';
        $ptzPort = intval($cam['ptz_port'] ?? 80);
        
        $startTime = "{$date}T00:00:00Z";
        $endTime = "{$date}T23:59:59Z";
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<CMSearchDescription>
    <searchID>1</searchID>
    <trackIDList><trackID>101</trackID></trackIDList>
    <timeSpanList>
        <timeSpan>
            <startTime>' . $startTime . '</startTime>
            <endTime>' . $endTime . '</endTime>
        </timeSpan>
    </timeSpanList>
    <maxResults>100</maxResults>
    <searchResultPostion>0</searchResultPostion>
</CMSearchDescription>';
        
        $ch = curl_init("http://{$ptzIp}:{$ptzPort}/ISAPI/ContentMgmt/search");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/xml']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $recordings = [];
        if ($httpCode == 200 && $response) {
            if (preg_match_all('/<startTime>(.*?)<\/startTime>.*?<endTime>(.*?)<\/endTime>/s', $response, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $recordings[] = [
                        'id' => uniqid('rec_'), // ✅ สิ่งที่เติมเข้าไปให้ React ใช้เป็น Key และเช็คการคลิกเลือก
                        'start' => $match[1],
                        'end' => $match[2],
                        'startLocal' => utcToLocal($match[1]),
                        'endLocal' => utcToLocal($match[2])
                    ];
                }
            }
        }
        
        jsonResp([
            'success' => true,
            'date' => $date,
            'recordings' => $recordings,
            'count' => count($recordings)
        ]);
        
    } catch (Exception $e) {
        jsonResp(['success' => false, 'error' => $e->getMessage()], 500);
    }
} else {
    jsonResp(['success' => false, 'error' => 'Unknown action or missing action'], 400);
}
?>