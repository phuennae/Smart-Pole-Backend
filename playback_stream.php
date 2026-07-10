<?php
// playback_stream.php - สร้าง stream สำหรับ playback ผ่าน go2rtc
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$cameraId = $_GET['camera_id'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$cameraId || !$start || !$end) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

require_once 'config.php';
$stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
$stmt->execute([$cameraId]);
$cam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cam) {
    http_response_code(404);
    echo json_encode(['error' => 'Camera not found']);
    exit;
}

$ptzIp = !empty($cam['ptz_ip']) ? $cam['ptz_ip'] : $cam['ip_address'];
if (preg_match('/^https?:\/\/([^\/:]+)/', $ptzIp, $m)) $ptzIp = $m[1];

$username = $cam['ptz_username'] ?? 'admin';
$password = $cam['ptz_password'] ?? '';

// Format time
$formatTime = function($iso) {
    return str_replace(['-', ':'], '', substr($iso, 0, 19)) . 'Z';
};

$startFmt = $formatTime($start);
$endFmt = $formatTime($end);

$rtspUrl = "rtsp://{$username}:" . urlencode($password) . "@{$ptzIp}:554/Streaming/tracks/101?starttime={$startFmt}&endtime={$endFmt}";

// สร้าง stream name แบบ unique
$streamName = 'playback_' . $cameraId . '_' . time();

// เรียก go2rtc API เพื่อสร้าง stream
$go2rtcApi = 'http://localhost:1984/api/streams';

$ch = curl_init($go2rtcApi . '/' . $streamName);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $streamName,
    'sources' => [
        'ffmpeg:' . $rtspUrl . '#video=copy#audio=opus'
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ส่ง URL กลับ
echo json_encode([
    'success' => $httpCode == 200 || $httpCode == 201,
    'stream_name' => $streamName,
    'webrtc_url' => "http://localhost:1984/stream.html?src={$streamName}&mode=webrtc",
    'mse_url' => "http://localhost:1984/stream.html?src={$streamName}&mode=mse",
    'hls_url' => "http://localhost:1984/stream.html?src={$streamName}&mode=hls",
    'rtsp_url' => $rtspUrl,
    'http_code' => $httpCode
]);