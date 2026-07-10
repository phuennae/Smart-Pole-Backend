<?php
// 1. นำเข้าไฟล์ตั้งค่าและเช็ค Login (ตามตัวอย่างที่คุณส่งมา)
include 'config.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// ==========================================
// 2. CONFIGURATION (ThingsBoard)
// ==========================================
$tb_url   = "http://theoneiot.i234.me:9090";
$username = "tenant@thingsboard.org";
$password = "tenant";

$node1_id = "bc2557d0-46a8-11f1-8573-dd37fef65191";
$node2_id = "8f745a40-477c-11f1-8573-dd37fef65191";

$node1_dashboard_url = "http://theoneiot.i234.me:9090/dashboard/b328eaf0-4713-11f1-8573-dd37fef65191?publicId=aedfbaf0-8012-11f0-ae11-7f25cda96bc5";
$node2_dashboard_url = "http://theoneiot.i234.me:9090/dashboard/bea1c670-478d-11f1-8573-dd37fef65191?publicId=aedfbaf0-8012-11f0-ae11-7f25cda96bc5";

// ==========================================
// 3. CORE FUNCTIONS (ThingsBoard API)
// ==========================================
function getTBToken($url, $user, $pass) {
    $ch = curl_init($url . "/api/auth/login");
    $payload = json_encode(["username" => $user, "password" => $pass]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['token'] ?? null;
}

function getLatestData($url, $deviceId, $token) {
    $keys = "voltage,current,power,energy,v,c,p,e";
    $endpoint = $url . "/api/plugins/telemetry/DEVICE/$deviceId/values/timeseries?keys=$keys";
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function val($data, $primaryKey, $fallbacks = []) {
    $keys = array_merge([$primaryKey], $fallbacks);
    foreach ($keys as $k) {
        if (isset($data[$k]) && is_array($data[$k]) && !empty($data[$k])) {
            $last = end($data[$k])['value'];
            if ($last !== null && $last !== "") return $last;
        }
    }
    return "0.00";
}

// เริ่มประมวลผลข้อมูล
$jwt_token = getTBToken($tb_url, $username, $password);
$n1 = []; $n2 = [];
if ($jwt_token) {
    $n1 = getLatestData($tb_url, $node1_id, $jwt_token);
    $n2 = getLatestData($tb_url, $node2_id, $jwt_token);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Pole - Energy Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        /* สไตล์ Dashboard Card แบบผสมผสาน */
        .energy-card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff; }
        .node-header { background: #111827; color: #fff; border-radius: 12px 12px 0 0; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .stat-box { background: #f8fafc; border-radius: 8px; padding: 15px; border: 1px solid #e2e8f0; text-align: center; height: 100%; }
        .stat-label { color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .stat-value { font-size: 1.5rem; font-weight: 700; margin-top: 5px; }
        .text-v { color: #10b981; } .text-a { color: #3b82f6; } 
        .text-w { color: #f59e0b; } .text-wh { color: #ef4444; }
        .unit { font-size: 0.8rem; color: #94a3b8; font-weight: normal; margin-left: 3px; }
        .btn-external { font-size: 0.75rem; color: #94a3b8; text-decoration: none; border: 1px solid #334155; padding: 4px 10px; border-radius: 6px; transition: 0.3s; }
        .btn-external:hover { background: #3b82f6; color: white; border-color: #3b82f6; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i> ระบบมอนิเตอร์พลังงาน Real-time</h4>
        <span class="badge bg-white text-dark shadow-sm py-2 px-3 border">
            <i class="fas fa-sync-alt fa-spin me-2 text-primary"></i> อัปเดตทุก 10 วินาที
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="energy-card">
                <div class="node-header">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-info"></i> Node 1: Street Light A1</h6>
                    <a href="<?= $node1_dashboard_url ?>" target="_blank" class="btn-external">กราฟละเอียด <i class="fas fa-external-link-alt ms-1"></i></a>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6"><div class="stat-box"><div class="stat-label">แรงดัน (V)</div><div class="stat-value text-v"><?= val($n1, 'voltage', ['v']) ?><span class="unit">V</span></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="stat-label">กระแส (A)</div><div class="stat-value text-a"><?= val($n1, 'current', ['c']) ?><span class="unit">A</span></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="stat-label">กำลังไฟฟ้า (W)</div><div class="stat-value text-w"><?= val($n1, 'power', ['p']) ?><span class="unit">W</span></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="stat-label">พลังงานรวม</div><div class="stat-value text-wh"><?= val($n1, 'energy', ['e']) ?><span class="unit">Wh</span></div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="energy-card">
                <div class="node-header" style="border-top: 3px solid #10b981;">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-success"></i> Node 2: Street Light A2</h6>
                    <a href="<?= $node2_dashboard_url ?>" target="_blank" class="btn-external">กราฟละเอียด <i class="fas fa-external-link-alt ms-1"></i></a>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6"><div class="stat-box"><div class="stat-label">แรงดัน (V)</div><div class="stat-value text-v"><?= val($n2, 'voltage', ['v']) ?><span class="unit">V</span></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="stat-label">กระแส (A)</div><div class="stat-value text-a"><?= val($n2, 'current', ['c']) ?><span class="unit">A</span></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="stat-label">กำลังไฟฟ้า (W)</div><div class="stat-value text-w"><?= val($n2, 'power', ['p']) ?><span class="unit">W</span></div></div></div>
                        <div class="col-6"><div class="stat-box"><div class="stat-label">พลังงานรวม</div><div class="stat-value text-wh"><?= val($n2, 'energy', ['e']) ?><span class="unit">Wh</span></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5 text-muted small">
        <p>© 2026 Smart Pole Management System | Connected to ThingsBoard Gateway</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ตั้งค่าให้หน้ารีเฟรชตัวเองเพื่ออัปเดตข้อมูลทุก 10 วินาที
    setTimeout(function(){
       location.reload();
    }, 10000);
</script>
</body>
</html>