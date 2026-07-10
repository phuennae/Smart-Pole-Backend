<?php
// playback_ws.php - WebSocket server สำหรับ playback
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ตรวจสอบ WebSocket upgrade
if (!isset($_SERVER['HTTP_UPGRADE']) || strtolower($_SERVER['HTTP_UPGRADE']) !== 'websocket') {
    http_response_code(400);
    echo "WebSocket connection required";
    exit;
}

// รับ parameters
$cameraId = $_GET['camera_id'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$cameraId || !$start || !$end) {
    http_response_code(400);
    echo "Missing parameters";
    exit;
}

require_once 'config.php';
$stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
$stmt->execute([$cameraId]);
$cam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cam) {
    http_response_code(404);
    echo "Camera not found";
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

$rtspUrl = "rtsp://{$username}:{$password}@{$ptzIp}:554/Streaming/tracks/101?starttime={$startFmt}&endtime={$endFmt}";

// หา ffmpeg
$ffmpegPath = 'ffmpeg';
$whereResult = @shell_exec('where ffmpeg 2>&1');
if ($whereResult && strpos($whereResult, 'ffmpeg.exe') !== false) {
    $lines = explode("\n", trim($whereResult));
    $ffmpegPath = trim($lines[0]);
}

// ส่ง WebSocket handshake
$headers = [
    'HTTP/1.1 101 Switching Protocols',
    'Upgrade: websocket',
    'Connection: Upgrade',
    'Sec-WebSocket-Accept: ' . base64_encode(sha1($_SERVER['HTTP_SEC_WEBSOCKET_KEY'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true))
];

foreach ($headers as $header) {
    header($header);
}

// เปิด FFmpeg
$cmd = sprintf(
    '"%s" -rtsp_transport tcp -i "%s" -f mpegts -codec:v mpeg1video -b:v 800k -r 20 -codec:a mp2 -b:a 128k - 2>NUL',
    $ffmpegPath,
    $rtspUrl
);

$proc = popen($cmd, 'r');
if (!$proc) {
    echo "Failed to start FFmpeg";
    exit;
}

// ส่งข้อมูลผ่าน WebSocket
while (!feof($proc)) {
    $data = fread($proc, 4096);
    if ($data === false || $data === '') break;
    
    // WebSocket frame
    $frame = pack('CC', 0x82, strlen($data) & 0x7F) . $data;
    echo $frame;
    flush();
    
    if (connection_aborted()) break;
}

pclose($proc);