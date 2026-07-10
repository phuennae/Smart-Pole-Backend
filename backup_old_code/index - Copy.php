<?php
// 1. กำหนด IP ของ ESP32 ทุกเครื่อง
$nodes = [
    ["ip" => "192.168.1.120", "name" => "ลำโพงห้องโถง"],
    ["ip" => "192.168.1.121", "name" => "ลำโพงทางเดิน"],
    ["ip" => "192.168.1.122", "name" => "ลำโพงหน้าอาคาร"]
];

// ใช้ file_get_contents แทน cURL เพื่อป้องกัน Error บน NAS
function sendToESP($ip, $path) {
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    return @file_get_contents("http://" . $ip . $path, false, $ctx);
}

function checkStatus($ip) {
    $ctx = stream_context_create(['http' => ['timeout' => 1]]);
    $result = @file_get_contents("http://" . $ip . "/status", false, $ctx);
    
    if ($result) {
        return json_decode($result, true);
    }
    return false;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'play') {
        $file = urlencode($_POST['filename']);
        foreach ($nodes as $node) { sendToESP($node['ip'], "/play?path=" . $file); }
        $message = "✅ สั่งเล่นเพลง <b>" . $_POST['filename'] . "</b> ทุกเครื่องแล้ว!";
    } 
    elseif ($action == 'stop') {
        foreach ($nodes as $node) { sendToESP($node['ip'], "/stop"); }
        $message = "⏹ สั่งหยุดเพลงทุกเครื่องแล้ว!";
    }
    elseif ($action == 'vol') {
        $vol = (int)$_POST['volume'];
        foreach ($nodes as $node) { sendToESP($node['ip'], "/vol?v=" . $vol); }
        $message = "🔊 ปรับเสียงทุกเครื่องเป็น <b>$vol</b> แล้ว!";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Jukebox Central Control</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; text-align: center; padding: 20px; }
        .card { background: white; max-width: 600px; margin: auto; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px;}
        button { padding: 10px 20px; margin: 10px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn-play { background: #007bff; color: white; }
        .btn-stop { background: #dc3545; color: white; }
        .btn-vol { background: #28a745; color: white; }
        input[type="text"], input[type="number"] { padding: 10px; width: 80%; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .alert { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background: #f8f9fa; }
        .online { color: green; font-weight: bold; }
        .offline { color: red; font-weight: bold; }
        a { text-decoration: none; color: #007bff; }
    </style>
</head>
<body>

    <div class="card">
        <h2>📡 สถานะอุปกรณ์ (Node Status)</h2>
        <table>
            <tr><th>จุดติดตั้ง</th><th>สถานะ</th><th>กำลังเล่น</th><th>ระดับเสียง</th><th>จัดการ</th></tr>
            <?php
            foreach ($nodes as $node) {
                $statusData = checkStatus($node['ip']);
                if ($statusData && isset($statusData['status'])) {
                    echo "<tr><td>{$node['name']} <br><small>({$node['ip']})</small></td>";
                    echo "<td class='online'>🟢 Online</td>";
                    echo "<td><small>" . htmlspecialchars($statusData['song']) . "</small></td>";
                    echo "<td>" . $statusData['volume'] . "</td>";
                    echo "<td><a href='http://{$node['ip']}' target='_blank'>⚙️ ตั้งเวลา</a></td></tr>";
                } else {
                    echo "<tr><td>{$node['name']} <br><small>({$node['ip']})</small></td>";
                    echo "<td class='offline'>🔴 Offline</td><td>-</td><td>-</td><td>-</td></tr>";
                }
            }
            ?>
        </table>
        <br><button onclick="location.reload()" style="padding: 5px 15px; font-size: 14px; background: #6c757d; color: white;">🔄 รีเฟรชสถานะ</button>
    </div>

    <div class="card">
        <h2>📢 ระบบควบคุมรวม (Broadcast)</h2>
        <?php if($message != "") echo "<div class='alert'>$message</div>"; ?>
        <form method="POST"><input type="hidden" name="action" value="play"><input type="text" name="filename" placeholder="ใส่ชื่อไฟล์ เช่น /1.mp3" required><br><button type="submit" class="btn-play">📡 ส่งสัญญาณ Broadcast</button></form>
        <hr>
        <div style="display: flex; justify-content: center; align-items: center;">
            <form method="POST" style="margin-right: 20px;"><input type="hidden" name="action" value="vol"><input type="number" name="volume" min="0" max="21" placeholder="ระดับเสียง" required style="width: 100px;"><button type="submit" class="btn-vol">💾 ปรับเสียง</button></form>
            <form method="POST"><input type="hidden" name="action" value="stop"><button type="submit" class="btn-stop">⏹ หยุดเล่นทุกเครื่อง</button></form>
        </div>
    </div>

</body>
</html>