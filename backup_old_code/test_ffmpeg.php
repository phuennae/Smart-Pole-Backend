<?php
// test_ffmpeg_php.php - Debug FFmpeg ใน PHP แบบละเอียด
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 FFmpeg Debug in PHP</h2>";

// 1. เช็ค PATH ของ PHP
echo "<h3>1. PHP Environment</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "SAPI: " . php_sapi_name() . "<br>";
echo "PATH: <code>" . getenv('PATH') . "</code><br>";

// 2. หา ffmpeg
echo "<h3>2. Find FFmpeg</h3>";
$ffmpegPath = null;

// ลองหลายที่
$possiblePaths = [
    'ffmpeg',
    'C:\\ffmpeg\\bin\\ffmpeg.exe',
    'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
    'C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe',
    'C:\\tools\\ffmpeg\\bin\\ffmpeg.exe',
];

foreach ($possiblePaths as $path) {
    $result = @shell_exec("$path -version 2>&1");
    if ($result && strpos($result, 'ffmpeg version') !== false) {
        $ffmpegPath = $path;
        echo "✅ Found: <code>$path</code><br>";
        break;
    }
}

if (!$ffmpegPath) {
    // ลอง where command
    $whereResult = shell_exec('where ffmpeg 2>&1');
    echo "where ffmpeg result: <pre>$whereResult</pre>";
    
    if (strpos($whereResult, 'ffmpeg.exe') !== false) {
        $lines = explode("\n", trim($whereResult));
        $ffmpegPath = trim($lines[0]);
        echo "✅ Found via where: <code>$ffmpegPath</code><br>";
    } else {
        echo "❌ FFmpeg not found in PHP PATH<br>";
        echo "<strong>แก้ไข:</strong> เพิ่ม path ของ ffmpeg ใน System Environment Variables แล้ว restart Apache";
        exit;
    }
}

// 3. ทดสอบ exec() กับ ffmpeg
echo "<h3>3. Test exec() with FFmpeg</h3>";
$rtspUrl = 'rtsp://admin:*sS5383160@192.168.3.64:554/Streaming/tracks/101?starttime=20260625T055153Z&endtime=20260625T060000Z';

// ทดสอบแบบง่ายก่อน (5 วินาที)
$cmd = sprintf(
    '"%s" -rtsp_transport tcp -i "%s" -t 3 -f null - 2>&1',
    $ffmpegPath,
    $rtspUrl
);

echo "<h4>Command:</h4>";
echo "<pre style='background:#f0f0f0;padding:10px;'>$cmd</pre>";

echo "<h4>Running...</h4>";
$output = [];
$returnCode = -1;
exec($cmd, $output, $returnCode);

echo "<h4>Result:</h4>";
echo "<pre style='background:#f0f0f0;padding:10px;'>";
echo "Return code: $returnCode\n";
echo "Lines: " . count($output) . "\n\n";
echo implode("\n", array_slice($output, -25));
echo "</pre>";

// 4. ทดสอบบันทึกไฟล์
echo "<h3>4. Test Record to File</h3>";
$tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_' . time() . '.mp4';

$cmd2 = sprintf(
    '"%s" -y -rtsp_transport tcp -i "%s" -t 3 -c:v copy -c:a aac "%s" 2>&1',
    $ffmpegPath,
    $rtspUrl,
    $tempFile
);

echo "<h4>Command:</h4>";
echo "<pre style='background:#f0f0f0;padding:10px;'>$cmd2</pre>";

$output2 = [];
$returnCode2 = -1;
exec($cmd2, $output2, $returnCode2);

echo "<h4>Result:</h4>";
echo "<pre style='background:#f0f0f0;padding:10px;'>";
echo "Return code: $returnCode2\n";
echo "File exists: " . (file_exists($tempFile) ? 'Yes' : 'No') . "\n";
if (file_exists($tempFile)) {
    echo "File size: " . filesize($tempFile) . " bytes\n";
    unlink($tempFile);
}
echo "\n";
echo implode("\n", array_slice($output2, -20));
echo "</pre>";

// 5. ทดสอบ popen (สำหรับ stream)
echo "<h3>5. Test popen() for Streaming</h3>";
$cmd3 = sprintf(
    '"%s" -rtsp_transport tcp -i "%s" -t 3 -f mpegts -codec:v mpeg1video -b:v 800k -r 20 - 2>NUL',
    $ffmpegPath,
    $rtspUrl
);

echo "<h4>Command:</h4>";
echo "<pre style='background:#f0f0f0;padding:10px;'>$cmd3</pre>";

$proc = popen($cmd3, 'r');
$totalBytes = 0;
if ($proc) {
    while (!feof($proc)) {
        $data = fread($proc, 4096);
        if ($data === false || $data === '') break;
        $totalBytes += strlen($data);
    }
    pclose($proc);
    echo "<pre style='background:#f0f0f0;padding:10px;'>";
    echo "✅ popen works!\n";
    echo "Total bytes received: $totalBytes\n";
    echo "</pre>";
} else {
    echo "<pre style='background:#ffe0e0;padding:10px;'>";
    echo "❌ popen failed!\n";
    echo "Check disable_functions in php.ini\n";
    echo "</pre>";
}

// 6. เช็ค disable_functions
echo "<h3>6. Check PHP Config</h3>";
$disabled = ini_get('disable_functions');
echo "disable_functions: <code>$disabled</code><br>";

if (strpos($disabled, 'exec') !== false) echo "❌ exec() is disabled<br>";
else echo "✅ exec() is enabled<br>";

if (strpos($disabled, 'popen') !== false) echo "❌ popen() is disabled<br>";
else echo "✅ popen() is enabled<br>";

if (strpos($disabled, 'shell_exec') !== false) echo "❌ shell_exec() is disabled<br>";
else echo "✅ shell_exec() is enabled<br>";
?>