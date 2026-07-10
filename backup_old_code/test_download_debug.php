<?php
// test_download_debug.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Download Debug</h2>";

// 1. หา ffmpeg
$ffmpegPath = 'ffmpeg';
$whereResult = shell_exec('where ffmpeg 2>&1');
if ($whereResult && strpos($whereResult, 'ffmpeg.exe') !== false) {
    $lines = explode("\n", trim($whereResult));
    $ffmpegPath = trim($lines[0]);
}
echo "FFmpeg: <code>$ffmpegPath</code><br>";

// 2. สร้าง RTSP URL
$username = 'admin';
$password = '*sS5383160';
$ip = '192.168.3.64';
$rtspUrl = "rtsp://{$username}:{$password}@{$ip}:554/Streaming/tracks/101?starttime=20260625T055153Z&endtime=20260625T060000Z";
echo "RTSP URL: <code>$rtspUrl</code><br>";

// 3. สร้าง temp file
$tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_download_' . time() . '.mp4';
echo "Temp file: <code>$tempFile</code><br>";

// 4. สร้าง command
$cmd = sprintf(
    '"%s" -y -rtsp_transport tcp -i "%s" -c:v copy -c:a aac -movflags +faststart "%s" 2>&1',
    $ffmpegPath,
    $rtspUrl,
    $tempFile
);

echo "<h3>Command:</h3>";
echo "<pre style='background:#f0f0f0;padding:10px;'>$cmd</pre>";

// 5. รัน command
echo "<h3>Running FFmpeg...</h3>";
flush();

$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

echo "<h3>Result:</h3>";
echo "<pre style='background:#f0f0f0;padding:10px;'>";
echo "Return code: $returnCode\n";
echo "File exists: " . (file_exists($tempFile) ? 'Yes ✅' : 'No ❌') . "\n";
if (file_exists($tempFile)) {
    echo "File size: " . filesize($tempFile) . " bytes\n";
}
echo "\nLast 15 lines:\n";
echo implode("\n", array_slice($output, -15));
echo "</pre>";

// 6. ถ้าสำเร็จ → ส่งไฟล์ให้ download
if ($returnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
    echo "<h3>🎉 Success! Starting download...</h3>";
    echo "<p>ไฟล์จะเริ่มดาวน์โหลดใน 3 วินาที...</p>";
    echo "<script>setTimeout(function() { window.location.href = 'test_download_debug.php?download=1&file=" . urlencode($tempFile) . "'; }, 3000);</script>";
    
    if (isset($_GET['download'])) {
        $file = $_GET['file'];
        if (file_exists($file)) {
            header('Content-Type: video/mp4');
            header('Content-Disposition: attachment; filename="test.mp4"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            unlink($file);
            exit;
        }
    }
} else {
    if (file_exists($tempFile)) unlink($tempFile);
    echo "<h3>❌ Failed</h3>";
}
?>