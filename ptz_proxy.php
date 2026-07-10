<?php
// ptz_proxy.php - API สำหรับควบคุมกล้อง (ปรับปรุงสำหรับ React ปลดล็อค Session)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// ไม่ต้องมีเช็ค session_start() เพื่อให้ React เรียกใช้ได้ทันที

$input = json_decode(file_get_contents('php://input'), true);
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

$onvifUrl = "http://{$ptz_ip}:{$ptz_port}/onvif/device_service";

function sendSOAP($url, $user, $pass, $soapAction, $body) {
    $envelope = '<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" 
            xmlns:a="http://www.w3.org/2005/08/addressing">
    <s:Header><a:Action>' . $soapAction . '</a:Action></s:Header>
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

try {
    switch ($action) {
        case 'test':
            $result = sendSOAP($onvifUrl, $username, $password, 'http://www.onvif.org/ver10/device/wsdl/GetDeviceInformation', '<tds:GetDeviceInformation xmlns:tds="http://www.onvif.org/ver10/device/wsdl"/>');
            if ($result['httpCode'] == 200) echo json_encode(['success' => true, 'ready' => true]);
            else echo json_encode(['success' => false, 'error' => "HTTP {$result['httpCode']}"]);
            break;
            
        case 'move':
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
            
            $body = '<tptz:ContinuousMove xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl"><tptz:ProfileToken>' . htmlspecialchars($profileToken) . '</tptz:ProfileToken><tptz:Velocity>' . $panTiltXml . '</tptz:Velocity></tptz:ContinuousMove>';
            $result = sendSOAP($onvifUrl, $username, $password, 'http://www.onvif.org/ver20/ptz/wsdl/ContinuousMove', $body);
            
            if ($result['httpCode'] == 200) echo json_encode(['success' => true]);
            else echo json_encode(['success' => false, 'error' => "HTTP {$result['httpCode']}"]);
            break;
            
        case 'stop':
            $body = '<tptz:Stop xmlns:tptz="http://www.onvif.org/ver20/ptz/wsdl"><tptz:ProfileToken>' . htmlspecialchars($profileToken) . '</tptz:ProfileToken><tptz:PanTilt>true</tptz:PanTilt><tptz:Zoom>true</tptz:Zoom></tptz:Stop>';
            $result = sendSOAP($onvifUrl, $username, $password, 'http://www.onvif.org/ver20/ptz/wsdl/Stop', $body);
            
            if ($result['httpCode'] == 200) echo json_encode(['success' => true]);
            else echo json_encode(['success' => false]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>