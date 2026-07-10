<?php
include 'config.php';

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// 2. ฟังก์ชันดึงรายชื่อไฟล์จาก ESP32 (ใช้ cURL)
function getFileList($ip, $port = 80) {
    global $api_key;
    $url = "http://{$ip}:{$port}/listfiles?key=" . urlencode($api_key);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);        
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $result) {
        $decoded = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }
    return false;
}

// 3. ดึงข้อมูล Nodes ทั้งหมดจากฐานข้อมูล
$stmt = $pdo->query("SELECT * FROM nodes ORDER BY id DESC");
$nodes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Pole - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .btn-alarm { background-color: #ffc107; color: #000; font-weight: bold; border: 2px solid #dc3545; transition: 0.3s; }
        .btn-alarm:hover { background-color: #dc3545; color: #fff; }
        .btn-stop-all { background-color: #212529; color: #fff; font-weight: bold; border: 2px solid #000; }
        .btn-stop-all:hover { background-color: #000; color: #ff0000; }
        .progress { height: 10px; border-radius: 5px; display: none; margin-top: 8px; }
        code { color: #d63384; font-weight: bold; }
        /* สไตล์พิเศษสำหรับปุ่ม Live */
        .btn-live { border-width: 2px; font-weight: bold; transition: all 0.2s; }
        .btn-live:active { transform: scale(0.95); }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-microchip me-2"></i> รายชื่อ Node และการจัดการไฟล์</h5>
                    <span class="badge bg-light text-primary">จำนวน <?= count($nodes) ?> โหนด</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">ชื่อจุดติดตั้ง</th>
                                    <th>IP / Port</th>
                                    <th style="width: 300px;">จัดการเพลง (.mp3)</th>
                                    <th style="width: 300px;">อัปโหลดไฟล์</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $node): 
                                    $node_port = $node['port'] ?? 80;
                                    $files = getFileList($node['ip_address'], $node_port); 
                                ?>
                                <tr>
                                    <td class="ps-3"><strong><?= htmlspecialchars($node['name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($node['ip_address']) ?>:<?= $node_port ?></code></td>
                                    <td>
                                        <?php if ($files && is_array($files)): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <select class="form-select form-select-sm" id="file_<?= $node['id'] ?>">
                                                    <?php 
                                                    foreach ($files as $f): 
                                                        $fname = $f['name'];
                                                        if (strtolower($fname) === 'alarm007.mp3' || strtolower($fname) === 'vol.txt') continue;
                                                        if (strtolower(pathinfo($fname, PATHINFO_EXTENSION)) === 'mp3'): 
                                                    ?>
                                                        <option value="<?= htmlspecialchars($fname) ?>"><?= htmlspecialchars($fname) ?></option>
                                                    <?php endif; endforeach; ?>
                                                </select>
                                                <div class="btn-group w-100">
                                                    <button class="btn btn-sm btn-success" onclick="playFile('<?= $node['ip_address'] ?>', '<?= $node_port ?>', 'file_<?= $node['id'] ?>')">เล่น</button>
                                                    <button class="btn btn-sm btn-danger" onclick="stopFile('<?= $node['ip_address'] ?>', '<?= $node_port ?>')">หยุด</button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small italic"><i class="fas fa-exclamation-circle"></i> Offline หรือไม่พบไฟล์</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="file" id="input_<?= $node['id'] ?>" class="form-control">
                                            <button class="btn btn-primary" type="button" onclick="uploadToNode(<?= $node['id'] ?>, '<?= $node['ip_address'] ?>', <?= $node_port ?>)">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                        </div>
                                        <div class="progress" id="pg_container_<?= $node['id'] ?>">
                                            <div id="bar_<?= $node['id'] ?>" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small id="status_<?= $node['id'] ?>" class="text-muted d-block mt-1" style="font-size: 11px;"></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-dark"><i class="fas fa-signal me-2 text-success"></i>สถานะปัจจุบัน (Auto Refresh)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Node</th>
                                <th>สถานะ</th>
                                <th>กำลังเล่น</th>
                                <th class="text-center">เข้าหน้าหลัก</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nodes as $node): ?>
                            <tr>
                                <td class="ps-3"><?= htmlspecialchars($node['name']) ?></td>
                                <td><span id="online-<?= $node['id'] ?>"><small class="text-muted">กำลังตรวจสอบ...</small></span></td>
                                <td id="song-<?= $node['id'] ?>">-</td>
                                <td class="text-center">
                                    <a href="http://<?= $node['ip_address'] ?>:<?= $node['port'] ?? 80 ?>" target="_blank" class="text-secondary fs-5" title="ตั้งค่าอุปกรณ์">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white text-center py-3">
                    <h5 class="mb-0">🚨 Broadcast & Emergency</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-3">
                        <button type="button" onclick="sendAlarmBroadcast()" class="btn btn-alarm py-2">
                            <i class="fas fa-exclamation-triangle me-2"></i> SEND ALARM (ALL)
                        </button>
                        <button type="button" onclick="stopAllBroadcast()" class="btn btn-stop-all py-2">
                            <i class="fas fa-hand-paper me-2"></i> STOP ALL (EMERGENCY)
                        </button>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-2 text-danger fw-bold"><i class="fas fa-headset me-1"></i> ระบบประกาศเสียงสด (Live)</h6>
                    <div class="d-grid gap-2 mb-3">
                        <button id="btnLive" class="btn btn-outline-danger btn-live py-2" 
                                onmousedown="startLive()" onmouseup="stopLive()" 
                                ontouchstart="startLive()" ontouchend="stopLive()">
                            <i class="fas fa-microphone"></i> กดค้างเพื่อพูดสด
                        </button>
                        <small class="text-muted text-center" style="font-size: 11px;">(ต้องตั้งค่า Node.js Server ก่อนใช้งาน)</small>
                    </div>

                    <hr>

                    <form action="process_broadcast.php" method="POST">
                        <label class="small text-muted mb-1">สั่งเล่นไฟล์ระบุชื่อ (ทุกเครื่อง):</label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">/</span>
                            <input type="text" name="filename" class="form-control" placeholder="song.mp3" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="play" class="btn btn-danger btn-sm">สั่งเล่นทุกเครื่อง</button>
                            <button type="submit" name="action" value="stop" class="btn btn-dark btn-sm">หยุดทั้งหมด</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// 1. ฟังก์ชันเล่นไฟล์รายโหนด
function playFile(ip, port, selectId) {
    var file = document.getElementById(selectId).value;
    var path = file.startsWith('/') ? file : '/' + file;
    window.location.href = `process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent(path)}`;
}

// 2. ฟังก์ชันหยุดรายโหนด
function stopFile(ip, port) {
    window.location.href = `process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`;
}

// 3. ฟังก์ชันอัปโหลดไฟล์ผ่าน AJAX (เพื่อให้เห็นความคืบหน้าจริง)
function uploadToNode(nodeId, ip, port) {
    var fileInput = document.getElementById('input_' + nodeId);
    var file = fileInput.files[0];
    if (!file) { alert("กรุณาเลือกไฟล์ก่อนครับ"); return; }

    var formData = new FormData();
    formData.append('fileToUpload', file);

    var xhr = new XMLHttpRequest();
    var bar = document.getElementById('bar_' + nodeId);
    var container = document.getElementById('pg_container_' + nodeId);
    var statusText = document.getElementById('status_' + nodeId);

    container.style.display = 'flex';
    statusText.innerText = "กำลังเชื่อมต่อ...";

    // ติดตาม Progress การส่งจาก Browser ไป PHP
    xhr.upload.addEventListener("progress", function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            bar.style.width = percent + '%';
            statusText.innerText = "กำลังส่งไปที่ Server: " + percent + "%";
        }
    });

    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200 && xhr.responseText.includes("success")) {
                statusText.className = "text-success fw-bold";
                statusText.innerText = "✅ อัปโหลดสำเร็จ!";
                setTimeout(() => { location.reload(); }, 1500);
            } else {
                statusText.className = "text-danger fw-bold";
                statusText.innerText = "❌ อัปโหลดล้มเหลว (เช็คการเชื่อมต่อ)";
                bar.classList.add('bg-danger');
            }
        }
    };

    // ส่งไปที่ process_broadcast.php โดยใส่ Header พิเศษเพื่อให้ PHP รู้ว่าเป็น AJAX
    xhr.open("POST", `process_broadcast.php?action=upload_single&ip=${ip}&port=${port}`, true);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.send(formData);
}

// 4. ฟังก์ชัน Broadcast
function sendAlarmBroadcast() {
    if (confirm("⚠️ ยืนยันการส่งสัญญาณเตือนภัยไปยังทุกจุด?")) {
        submitBroadcast('play', '/alarm007.mp3');
    }
}

function stopAllBroadcast() {
    submitBroadcast('stop', '');
}

function submitBroadcast(action, filename) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_broadcast.php';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = action;
    const fileInput = document.createElement('input');
    fileInput.type = 'hidden'; fileInput.name = 'filename'; fileInput.value = filename;

    form.appendChild(actionInput); form.appendChild(fileInput);
    document.body.appendChild(form);
    form.submit();
}

// 5. ฟังก์ชันดึงสถานะ Real-time (AJAX)
function updateStatuses() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            const onlineBadge = data.online ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-danger">Offline</span>';
            document.getElementById('online-<?= $node['id'] ?>').innerHTML = onlineBadge;
            
            const songName = (data.song && data.song !== "None" && data.song !== "--") 
                ? '<span class="text-primary fw-bold"><i class="fas fa-play-circle"></i> ' + data.song + '</span>' 
                : '<span class="text-muted small">⏹️ Standby</span>';
            document.getElementById('song-<?= $node['id'] ?>').innerHTML = songName;
        })
        .catch(err => {
            document.getElementById('online-<?= $node['id'] ?>').innerHTML = '<span class="badge bg-secondary">Error</span>';
        });
    <?php endforeach; ?>
}

// 6. ฟังก์ชัน Live Streaming ผ่าน Browser Mic (WebSocket ไปยัง Node.js)
let audioContext;
let ws;
let processor;
let micInput;

async function startLive() {
    event.preventDefault();

    // 1. ตรวจสอบว่า Browser รองรับ getUserMedia ไหม (ถ้าไม่รันผ่าน HTTPS หรือ localhost จะ undefined)
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Browser นี้ไม่รองรับการใช้ไมค์ผ่าน HTTP ปกติ \nกรุณาตั้งค่า chrome://flags ตามที่แนะนำ หรือใช้ HTTPS");
        return;
    }

    const btn = document.getElementById('btnLive');
    btn.classList.add('btn-danger');
    btn.innerHTML = '<i class="fas fa-broadcast-tower fa-beat"></i> กำลังพูด...';

    // 2. บอก ESP32 ให้เตรียมตัว (Fetch แบบไม่รอ)
    fetch('process_broadcast.php?action=play_live').catch(e => console.log(e));

    const wsUrl = "ws://" + window.location.hostname + ":3000";
    ws = new WebSocket(wsUrl);

    ws.onopen = async () => {
        console.log("Connected to Live Stream Server");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            // 3. สร้าง AudioContext และต้อง Resume เสมอ
            audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
            if (audioContext.state === 'suspended') {
                await audioContext.resume();
            }

            micInput = audioContext.createMediaStreamSource(stream);
            processor = audioContext.createScriptProcessor(2048, 1, 1);
            
            micInput.connect(processor);
            processor.connect(audioContext.destination);

            processor.onaudioprocess = (e) => {
                let inputData = e.inputBuffer.getChannelData(0);
                let pcmData = new Int16Array(inputData.length);
                for (let i = 0; i < inputData.length; i++) {
                    pcmData[i] = Math.max(-1, Math.min(1, inputData[i])) * 0x7FFF;
                }
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(pcmData.buffer); // ข้อมูลจะเริ่มส่งตรงนี้
                }
            };
        } catch (err) {
            console.error("Mic Error:", err);
            stopLive();
        }
    };
}

function stopLive() {
    event.preventDefault(); // ป้องกันพฤติกรรมดั้งเดิมบนมือถือ

    // คืนค่าสไตล์ปุ่ม
    const btn = document.getElementById('btnLive');
    btn.classList.remove('btn-danger');
    btn.classList.add('btn-outline-danger');
    btn.innerHTML = '<i class="fas fa-microphone"></i> กดค้างเพื่อพูดสด';

    // ตัดการเชื่อมต่ออุปกรณ์และสตรีม
    if (processor) { processor.disconnect(); processor = null; }
    if (micInput) { micInput.disconnect(); micInput = null; }
    if (audioContext) { audioContext.close(); audioContext = null; }
    if (ws) { ws.close(); ws = null; }
    
    console.log("Live stream stopped");
    
    // (ทางเลือก) สั่งให้ ESP32 ทุกเครื่องหยุดทำงาน
     stopAllBroadcast();
}

// รันสถานะครั้งแรก และตั้งเวลาทุก 5 วินาที
updateStatuses();
setInterval(updateStatuses, 5000);
</script>
</body>
</html>