<?php
// playback_vlc.php - เปิด VLC สำหรับ playback
session_start();
if (!isset($_SESSION['user_id'])) exit;

$cameraId = $_GET['camera_id'] ?? null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$cameraId || !$start || !$end) exit;

require_once 'config.php';
$stmt = $pdo->prepare("SELECT * FROM cameras WHERE id = ?");
$stmt->execute([$cameraId]);
$cam = $stmt->fetch(PDO::FETCH_ASSOC);

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

// สร้างไฟล์ M3U สำหรับ VLC
$m3uContent = "#EXTM3U\n#EXTINF:-1,Playback {$cam['camera_name']}\n{$rtspUrl}";
$m3uFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'playback_' . time() . '.m3u';
file_put_contents($m3uFile, $m3uContent);

// เปิด VLC
exec("start vlc \"{$m3uFile}\"");

echo "<h2>🎬 เปิด VLC แล้ว</h2>";
echo "<p>RTSP URL: <code>$rtspUrl</code></p>";
echo "<p>ไฟล์ M3U: <code>$m3uFile</code></p>";
echo "<script>setTimeout(() => window.close(), 2000);</script>";