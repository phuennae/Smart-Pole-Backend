<?php
// เริ่มต้นกำหนดค่า Default
$ip = $_POST['ip'] ?? 'theoneiot.i234.me';
$port = $_POST['port'] ?? '8080';
$apikey = $_POST['apikey'] ?? ''; 
$action = $_POST['action'] ?? '';
$output_log = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    // 1. สร้าง URL ตาม Action ที่เลือก
    $path = ($action === 'list') ? '/listfiles' : '/status';
    $url = "http://{$ip}:{$port}{$path}?key=" . urlencode($apikey);
    
    $output_log .= "> เริ่มต้นการทดสอบเชื่อมต่อ\n";
    $output_log .= "> URL: " . $url . "\n";
    $output_log .= "> Action: " . $action . "\n";
    $output_log .= str_repeat("-", 50) . "\n";

    // 2. ตั้งค่า cURL เพื่อ Debug แบบละเอียด
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // รอเชื่อมต่อ 5 วินาที
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);       // รอโหลดข้อมูลสูงสุด 10 วินาที
    
    // บางครั้ง ESP32 ตอบกลับ HTTP/1.0 การบังคับใช้เวอร์ชัน 1.0 อาจช่วยได้
    // curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); 

    $output_log .= "> กำลังส่ง Request รอสักครู่...\n";
    
    // 3. ยิง Request และจับเวลา
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    // 4. ดึงข้อมูล Error และ Status
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time_took = round($end_time - $start_time, 3);
    
    curl_close($ch);

    // 5. สรุปผลลัพธ์ลง Terminal
    $output_log .= "> ใช้เวลาไป: {$time_took} วินาที\n";
    $output_log .= "> HTTP Code: {$http_code}\n";
    
    if ($curl_errno) {
        $output_log .= "> [ERROR] cURL Error Code: {$curl_errno}\n";
        $output_log .= "> [ERROR] ข้อความ: {$curl_error}\n";
    } else {
        $output_log .= "> [SUCCESS] การเชื่อมต่อสำเร็จ!\n";
        $output_log .= str_repeat("-", 50) . "\n";
        $output_log .= "> ข้อมูลดิบที่ตอบกลับมา (Raw Response):\n\n";
        
        // ลองแปลงเป็น JSON เพื่อดูว่ารูปแบบถูกต้องไหม
        $json_check = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $output_log .= json_encode($json_check, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $output_log .= $response;
            $output_log .= "\n\n> [WARNING] ข้อความที่ได้ ไม่ใช่โครงสร้าง JSON ที่ถูกต้อง!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ESP32 Debugger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .terminal {
            background-color: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', Courier, monospace;
            padding: 15px;
            border-radius: 5px;
            height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <strong>⚙️ ตั้งค่า ESP32 Node</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">IP Address / Domain</label>
                            <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($ip) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Port</label>
                            <input type="text" name="port" class="form-control" value="<?= htmlspecialchars($port) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="apikey" class="form-control" value="<?= htmlspecialchars($apikey) ?>">
                        </div>
                        <hr>
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="status" class="btn btn-info">🔍 ทดสอบดึงสถานะ (/status)</button>
                            <button type="submit" name="action" value="list" class="btn btn-primary">📁 ทดสอบดึงไฟล์ (/listfiles)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <strong>🖥️ Terminal Log</strong>
                </div>
                <div class="card-body p-0">
                    <div class="terminal"><?= $output_log ?: "> รอคำสั่ง..." ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>