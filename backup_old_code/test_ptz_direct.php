<?php
// test_ptz_direct.php - PTZ + Live Stream (ไม่ต้องใช้ server.js)

$cameraIP = '192.168.3.64';
$port = 80;
$username = 'admin1';
$password = '*sS5383160';
$passwordEncoded = urlencode($password);
$onvifUrl = "http://{$cameraIP}:{$port}/onvif/device_service";

// 🎥 MJPEG Stream URLs (Hikvision)
$mjpegMain = "http://{$username}:{$passwordEncoded}@{$cameraIP}/Streaming/channels/101/httpPreview";
$mjpegSub  = "http://{$username}:{$passwordEncoded}@{$cameraIP}/Streaming/channels/102/httpPreview";
$snapshotUrl = "http://{$cameraIP}/ISAPI/Streaming/channels/102/picture";

$debugLog = [];

function addLog($msg, $type = 'info', $data = null) {
    global $debugLog;
    $debugLog[] = [
        'time' => date('H:i:s'),
        'msg' => $msg,
        'type' => $type,
        'data' => $data
    ];
}

function sendSOAPRequest($url, $username, $password, $soapAction, $body) {
    $envelope = '<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" 
            xmlns:a="http://www.w3.org/2005/08/addressing">
    <s:Header>
        <a:Action>' . $soapAction . '</a:Action>
    </s:Header>
    <s:Body>' . $body . '</s:Body>
</s:Envelope>';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $envelope);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/soap+xml; charset=utf-8',
        'SOAPAction: ' . $soapAction
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $curlError,
        'request' => $envelope
    ];
}

$action = $_GET['action'] ?? null;
$streamMode = $_GET['stream'] ?? 'mjpeg_sub';

if ($action) {
    addLog("🎯 Action: $action", 'info');

    switch ($action) {
        case 'test':
            addLog("🔍 Testing connection to $onvifUrl", 'info');
            $result = sendSOAPRequest(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver10/device/wsdl/GetDeviceInformation',
                '<tds:GetDeviceInformation xmlns:tds="http://www.onvif.org/ver10/device/wsdl"/>'
            );
            addLog("📡 HTTP Code: {$result['httpCode']}", $result['httpCode'] == 200 ? 'success' : 'error');
            if ($result['httpCode'] == 200) {
                addLog("✅ ONVIF Connected!", 'success');
                if (preg_match('/<tt:Manufacturer>(.*?)<\/tt:Manufacturer>/', $result['response'], $m))
                    addLog("📷 Manufacturer: {$m[1]}", 'success');
                if (preg_match('/<tt:Model>(.*?)<\/tt:Model>/', $result['response'], $m))
                    addLog("📷 Model: {$m[1]}", 'success');
            }
            addLog("📥 Response", 'info', $result['response']);
            break;

        case 'move':
            $direction = $_GET['dir'] ?? 'right';
            $speed = 0.5;
            addLog("🎮 Moving: $direction", 'info');

            $panTiltXml = '';
            if ($direction === 'left' || $direction === 'right') {
                $x = $direction === 'right' ? $speed : -$speed;
                $panTiltXml = '<tt:PanTilt xmlns:tt="http://www.onvif.org/ver10/schema" x="' . $x . '" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/VelocityGenericSpace"/>';
            } elseif ($direction === 'up' || $direction === 'down') {
                $y = $direction === 'up' ? $speed : -$speed;
                $panTiltXml = '<tt:PanTilt xmlns:tt="http://www.onvif.org/ver10/schema" y="' . $y . '" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/VelocityGenericSpace"/>';
            }

            $body = '<tptz:ContinuousMove xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl">
                <tptz:ProfileToken>Profile_1</tptz:ProfileToken>
                <tptz:Velocity>' . $panTiltXml . '</tptz:Velocity>
            </tptz:ContinuousMove>';

            $result = sendSOAPRequest(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/ContinuousMove', $body
            );
            addLog("📡 HTTP Code: {$result['httpCode']}", $result['httpCode'] == 200 ? 'success' : 'error');
            if ($result['httpCode'] == 200) addLog("✅ Move sent!", 'success');
            else addLog("❌ Error", 'error', $result['response']);
            break;

        case 'stop':
            addLog("🛑 Stopping PTZ", 'info');
            $body = '<tptz:Stop xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl">
                <tptz:ProfileToken>Profile_1</tptz:ProfileToken>
                <tptz:PanTilt>true</tptz:PanTilt>
                <tptz:Zoom>true</tptz:Zoom>
            </tptz:Stop>';
            $result = sendSOAPRequest(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/Stop', $body
            );
            addLog("📡 HTTP Code: {$result['httpCode']}", $result['httpCode'] == 200 ? 'success' : 'error');
            if ($result['httpCode'] == 200) addLog("✅ Stop sent!", 'success');
            break;

        case 'zoom':
            $dir = $_GET['dir'] ?? 'in';
            $z = $dir === 'in' ? 0.5 : -0.5;
            addLog("🔍 Zoom: $dir", 'info');
            $body = '<tptz:ContinuousMove xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl">
                <tptz:ProfileToken>Profile_1</tptz:ProfileToken>
                <tptz:Velocity>
                    <tt:Zoom xmlns:tt="http://www.onvif.org/ver10/schema" x="' . $z . '" space="http://www.onvif.org/ver10/tptz/ZoomSpaces/VelocityGenericSpace"/>
                </tptz:Velocity>
            </tptz:ContinuousMove>';
            $result = sendSOAPRequest(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/ContinuousMove', $body
            );
            addLog("📡 HTTP Code: {$result['httpCode']}", $result['httpCode'] == 200 ? 'success' : 'error');
            if ($result['httpCode'] == 200) addLog("✅ Zoom sent!", 'success');
            break;

        case 'getProfiles':
            addLog("📋 Getting Profiles", 'info');
            $body = '<tptz:GetProfiles xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl"/>';
            $result = sendSOAPRequest(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/GetProfiles', $body
            );
            addLog("📡 HTTP Code: {$result['httpCode']}", $result['httpCode'] == 200 ? 'success' : 'error');
            if ($result['httpCode'] == 200 && preg_match_all('/token="(.*?)"/', $result['response'], $m)) {
                addLog("📋 Profile Tokens: " . implode(', ', $m[1]), 'success');
            }
            addLog("📥 Response", 'info', $result['response']);
            break;
    }
}

// เลือก URL stream
$currentStreamUrl = ($streamMode === 'mjpeg_main') ? $mjpegMain : $mjpegSub;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>PTZ + Live - <?= $cameraIP ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0a0a0a; color: #fff; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1400px; }
        .card { background: #1e1e1e; border: 1px solid #333; }
        .card-header { background: #252526; border-bottom: 1px solid #333; }
        
        /* Video Player */
        .video-container {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        .video-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .video-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            padding: 5px 15px;
            border-radius: 6px;
            font-size: 0.85rem;
            z-index: 10;
        }
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
        .video-error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #888;
        }
        
        /* PTZ Grid */
        .ptz-grid {
            display: grid;
            grid-template-columns: repeat(3, 60px);
            grid-template-rows: repeat(3, 60px);
            gap: 6px;
            justify-content: center;
        }
        .ptz-btn {
            background: #2a2a2a;
            border: 2px solid #444;
            color: #fff;
            border-radius: 10px;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.15s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ptz-btn:hover { background: #0d6efd; border-color: #0d6efd; color: #fff; }
        .ptz-btn:active { transform: scale(0.95); background: #0b5ed7; }
        .ptz-btn.stop { background: #dc3545; border-color: #dc3545; }
        .ptz-btn.stop:hover { background: #bb2d3b; color: #fff; }
        
        /* Stream Mode Tabs */
        .stream-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .stream-tab {
            padding: 6px 14px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            color: #aaa;
            font-size: 0.85rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .stream-tab.active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .stream-tab:hover { color: #fff; }
        
        /* Log */
        .log-entry {
            background: #2d2d2d;
            border-left: 3px solid #0d6efd;
            padding: 6px 10px;
            margin-bottom: 6px;
            border-radius: 4px;
            font-family: 'Consolas', monospace;
            font-size: 12px;
        }
        .log-entry.success { border-left-color: #4ec9b0; }
        .log-entry.error { border-left-color: #f48771; }
        .log-entry.info { border-left-color: #ffc107; }
        .log-time { color: #608b4e; }
        .log-data {
            background: #1e1e1e;
            padding: 6px;
            margin-top: 4px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-break: break-all;
            color: #9cdcfe;
            font-size: 11px;
            max-height: 150px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-video text-danger"></i> Live + PTZ: <?= $cameraIP ?></h4>
        <div>
            <span class="badge bg-success"><i class="fas fa-user"></i> <?= $username ?></span>
            <a href="?" class="btn btn-sm btn-outline-light ms-2"><i class="fas fa-sync"></i> Refresh</a>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left: Video + PTZ -->
        <div class="col-lg-8">
            <!-- Stream Mode -->
            <div class="stream-tabs">
                <a href="?stream=mjpeg_sub<?= $action ? '&action='.$action : '' ?>" 
                   class="stream-tab <?= $streamMode === 'mjpeg_sub' ? 'active' : '' ?>">
                    <i class="fas fa-film"></i> Sub Stream (ลื่น)
                </a>
                <a href="?stream=mjpeg_main<?= $action ? '&action='.$action : '' ?>" 
                   class="stream-tab <?= $streamMode === 'mjpeg_main' ? 'active' : '' ?>">
                    <i class="fas fa-hd"></i> Main Stream (ชัด)
                </a>
            </div>

            <!-- Video -->
            <div class="video-container mb-3">
                <div class="video-overlay">
                    <i class="fas fa-circle text-danger blink me-1"></i> 
                    LIVE: <?= $cameraIP ?> (<?= $streamMode === 'mjpeg_sub' ? 'Sub' : 'Main' ?>)
                </div>
                <img src="<?= htmlspecialchars($currentStreamUrl) ?>" 
                     id="liveStream"
                     alt="Live Stream"
                     onerror="this.style.display='none'; document.getElementById('streamError').style.display='flex';"
                     onload="document.getElementById('streamError').style.display='none'; this.style.display='block';">
                <div class="video-error" id="streamError" style="display:none;">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                    <p class="mb-1">ไม่สามารถดึง Stream ได้</p>
                    <small class="text-muted">ตรวจสอบ IP / Username / Password</small>
                    <a href="<?= htmlspecialchars($currentStreamUrl) ?>" target="_blank" class="btn btn-sm btn-outline-info mt-3">
                        <i class="fas fa-external-link-alt"></i> เปิด Stream โดยตรง
                    </a>
                </div>
            </div>

            <!-- PTZ Controls -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-gamepad"></i> PTZ Control</h5>
                    <small class="text-muted">คลิกปุ่มเพื่อสั่งกล้อง</small>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="ptz-grid">
                                <div></div>
                                <a href="?action=move&dir=up&stream=<?= $streamMode ?>" class="ptz-btn"><i class="fas fa-arrow-up"></i></a>
                                <div></div>
                                <a href="?action=move&dir=left&stream=<?= $streamMode ?>" class="ptz-btn"><i class="fas fa-arrow-left"></i></a>
                                <a href="?action=stop&stream=<?= $streamMode ?>" class="ptz-btn stop"><i class="fas fa-stop"></i></a>
                                <a href="?action=move&dir=right&stream=<?= $streamMode ?>" class="ptz-btn"><i class="fas fa-arrow-right"></i></a>
                                <div></div>
                                <a href="?action=move&dir=down&stream=<?= $streamMode ?>" class="ptz-btn"><i class="fas fa-arrow-down"></i></a>
                                <div></div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="d-grid gap-2">
                                <a href="?action=zoom&dir=in&stream=<?= $streamMode ?>" class="btn btn-outline-info">
                                    <i class="fas fa-search-plus"></i> Zoom In
                                </a>
                                <a href="?action=zoom&dir=out&stream=<?= $streamMode ?>" class="btn btn-outline-info">
                                    <i class="fas fa-search-minus"></i> Zoom Out
                                </a>
                                <a href="?action=stop&stream=<?= $streamMode ?>" class="btn btn-outline-danger">
                                    <i class="fas fa-stop-circle"></i> Stop
                                </a>
                            </div>
                            <hr style="border-color:#444">
                            <div class="d-grid gap-2">
                                <a href="?action=test&stream=<?= $streamMode ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-plug"></i> ทดสอบเชื่อมต่อ
                                </a>
                                <a href="?action=getProfiles&stream=<?= $streamMode ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-list"></i> ดู Profiles
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Debug Log -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bug"></i> Debug Log</h5>
                    <a href="?stream=<?= $streamMode ?>" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-trash"></i> Clear
                    </a>
                </div>
                <div class="card-body" style="max-height: 700px; overflow-y: auto;">
                    <?php if (empty($debugLog)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-info-circle fa-2x mb-2"></i>
                            <p class="mb-0">กดปุ่ม PTZ เพื่อดู log</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($debugLog as $log): ?>
                            <div class="log-entry <?= $log['type'] ?>">
                                <span class="log-time">[<?= $log['time'] ?>]</span>
                                <?= htmlspecialchars($log['msg']) ?>
                                <?php if ($log['data']): ?>
                                    <div class="log-data"><?= is_array($log['data']) ? htmlspecialchars(json_encode($log['data'], JSON_PRETTY_PRINT)) : htmlspecialchars($log['data']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto refresh MJPEG every 5 วินาที (กัน stream ค้าง)
setInterval(() => {
    const img = document.getElementById('liveStream');
    if (img && img.src) {
        const base = img.src.split('?t=')[0];
        img.src = base + (base.includes('?') ? '&' : '?') + 't=' + Date.now();
    }
}, 5000);
</script>
</body>
</html>