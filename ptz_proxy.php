<?php
// ptz_proxy.php - PHP ONVIF Proxy (ผสานโค้ดเวอร์ชันซัพ + ปลดล็อค CORS สำหรับ React)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 1. จัดการ Preflight Request จาก React ไม่ต้องไปถามหา Session
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200);
    exit(0); 
}

// 2. อ่านข้อมูลที่ส่งมาจาก React (รองรับทั้งแบบ JSON และ FormData)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';
$ptz_ip = $input['ptz_ip'] ?? '';
$ptz_port = intval($input['ptz_port'] ?? 80);
$username = $input['ptz_username'] ?? 'admin';
$password = $input['ptz_password'] ?? '';
$command = $input['command'] ?? '';
$speed = floatval($input['speed'] ?? 0.5);
$profileToken = $input['profile_token'] ?? 'Profile_1';

if (!$ptz_ip) {
    echo json_encode(['success' => false, 'error' => 'Missing ptz_ip']);
    exit;
}

// ใช้ URL เดิมของซัพที่ทดสอบแล้วว่าผ่าน
$onvifUrl = "http://{$ptz_ip}:{$ptz_port}/onvif/device_service";

// ============================================
// SOAP Request Function (เหมือนต้นฉบับเป๊ะ)
// ============================================
function sendSOAP($url, $user, $pass, $soapAction, $body) {
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
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/soap+xml; charset=utf-8',
        'SOAPAction: ' . $soapAction
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
}

// ============================================
// Handle Actions (โครงสร้างเดิมของซัพ)
// ============================================
try {
    switch ($action) {
        case 'test':
            $result = sendSOAP(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver10/device/wsdl/GetDeviceInformation',
                '<tds:GetDeviceInformation xmlns:tds="http://www.onvif.org/ver10/device/wsdl"/>'
            );
            
            if ($result['httpCode'] == 200) {
                $manufacturer = $model = $firmware = '';
                if (preg_match('/<tt:Manufacturer>(.*?)<\/tt:Manufacturer>/', $result['response'], $m)) $manufacturer = $m[1];
                if (preg_match('/<tt:Model>(.*?)<\/tt:Model>/', $result['response'], $m)) $model = $m[1];
                if (preg_match('/<tt:FirmwareVersion>(.*?)<\/tt:FirmwareVersion>/', $result['response'], $m)) $firmware = $m[1];
                
                echo json_encode([
                    'success' => true,
                    'ready' => true,
                    'device' => [
                        'manufacturer' => $manufacturer,
                        'model' => $model,
                        'firmware' => $firmware
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'ready' => false,
                    'error' => "HTTP {$result['httpCode']}: " . ($result['error'] ?: 'Connection failed')
                ]);
            }
            break;
            
        case 'getProfiles':
            $result = sendSOAP(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/GetProfiles',
                '<tptz:GetProfiles xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl"/>'
            );
            
            $profiles = [];
            if ($result['httpCode'] == 200) {
                if (preg_match_all('/<trt:Profiles[^>]*token="([^"]+)"[^>]*name="([^"]+)"/', $result['response'], $m, PREG_SET_ORDER)) {
                    foreach ($m as $match) {
                        $profiles[] = ['token' => $match[1], 'name' => $match[2]];
                    }
                }
            }
            echo json_encode(['success' => true, 'profiles' => $profiles, 'raw' => $result['response']]);
            break;
            
        case 'move':
            if (!$command) {
                echo json_encode(['success' => false, 'error' => 'Missing command']);
                exit;
            }
            
            $panTiltXml = '';
            if ($command === 'left' || $command === 'right') {
                $x = $command === 'right' ? $speed : -$speed;
                $panTiltXml = '<tt:PanTilt xmlns:tt="http://www.onvif.org/ver10/schema" x="' . $x . '" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/VelocityGenericSpace"/>';
            } elseif ($command === 'up' || $command === 'down') {
                $y = $command === 'up' ? $speed : -$speed;
                $panTiltXml = '<tt:PanTilt xmlns:tt="http://www.onvif.org/ver10/schema" y="' . $y . '" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/VelocityGenericSpace"/>';
            } elseif ($command === 'zoomin' || $command === 'zoomout') {
                $z = $command === 'zoomin' ? $speed : -$speed;
                $panTiltXml = '<tt:Zoom xmlns:tt="http://www.onvif.org/ver10/schema" x="' . $z . '" space="http://www.onvif.org/ver10/tptz/ZoomSpaces/VelocityGenericSpace"/>';
            }
            
            $body = '<tptz:ContinuousMove xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl">
                <tptz:ProfileToken>' . htmlspecialchars($profileToken) . '</tptz:ProfileToken>
                <tptz:Velocity>' . $panTiltXml . '</tptz:Velocity>
            </tptz:ContinuousMove>';
            
            $result = sendSOAP(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/ContinuousMove',
                $body
            );
            
            if ($result['httpCode'] == 200) {
                // คืนค่า command กลับไปให้ตรงกับสเปคเดิม
                echo json_encode(['success' => true, 'command' => $command]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'error' => "HTTP {$result['httpCode']} - " . $result['error'], // เพิ่ม curl_error เข้าไป
                    'response' => $result['response']
                ]);
            }
            break;
            
        case 'stop':
            $body = '<tptz:Stop xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl">
                <tptz:ProfileToken>' . htmlspecialchars($profileToken) . '</tptz:ProfileToken>
                <tptz:PanTilt>true</tptz:PanTilt>
                <tptz:Zoom>true</tptz:Zoom>
            </tptz:Stop>';
            
            $result = sendSOAP(
                $onvifUrl, $username, $password,
                'http://www.onvif.org/ver20/ptz/wsdl/Stop',
                $body
            );
            
            if ($result['httpCode'] == 200) {
                // คืนค่า command กลับไปให้ตรงกับสเปคเดิม
                echo json_encode(['success' => true, 'command' => 'stop']);
            } else {
                echo json_encode(['success' => false, 'error' => "HTTP {$result['httpCode']} - " . $result['error']]);
            }
            break;
            
        case 'snapshot':
            $snapshotUrl = "http://{$username}:{$password}@{$ptz_ip}/ISAPI/Streaming/channels/102/picture";
            $ch = curl_init($snapshotUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $img = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            if ($httpCode == 200 && $img) {
                header('Content-Type: ' . ($contentType ?: 'image/jpeg'));
                echo $img;
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Snapshot failed']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>