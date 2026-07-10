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
$stmt = $pdo->query("SELECT * FROM nodes ORDER BY name ASC");
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
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --accent: #38bdf8;
        }
        body { background-color: var(--bg-dark); color: #f8fafc; font-family: 'Sarabun', sans-serif; }
        
        .card { background-color: var(--card-bg); border: none; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); margin-bottom: 1.5rem; }
        .card-header { background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 1.25rem; }
        
        .table { color: #cbd5e1; margin-bottom: 0; }
        .table thead th { background: rgba(0,0,0,0.2); color: #94a3b8; font-weight: 400; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; border: none; }
        .table td { border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1rem; vertical-align: middle; }
        .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.02); }

        .node-name { font-size: 1.1rem; font-weight: 700; color: #fff; }
        .ip-badge { background: #334155; color: var(--accent); padding: 2px 8px; border-radius: 5px; font-family: monospace; font-size: 0.85rem; }
        
        .btn-alarm { background: linear-gradient(45deg, #ef4444, #b91c1c); color: #fff; border: none; font-weight: bold; }
        .btn-stop-all { background: #475569; color: #fff; border: none; font-weight: bold; }
        .btn-live { border: 2px solid #ef4444; color: #ef4444; font-weight: bold; border-radius: 10px; transition: 0.3s; }
        .btn-live:hover { background: #ef4444; color: #fff; }
        .btn-live.active-live { background: #ef4444; color: #fff; box-shadow: 0 0 15px rgba(239, 68, 68, 0.5); }

        .progress { height: 6px; background-color: #334155; border-radius: 10px; display: none; margin-top: 10px; }
        .progress-bar { background-color: var(--accent); }

        .form-select-sm, .form-control-sm { background-color: #0f172a; border: 1px solid #334155; color: #fff; }
        .form-select-sm:focus { background-color: #0f172a; color: #fff; border-color: var(--accent); }

        .status-online { color: #10b981; }
        .status-offline { color: #ef4444; }
        
        .action-icon { width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: 0.2s; background: #334155; color: #fff; text-decoration: none; }
        .action-icon:hover { background: var(--accent); color: #000; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0"><i class="fas fa-layer-group me-2 text-info"></i>Dashboard</h2>
            <p class="text-muted small">ระบบบริหารจัดการเสาอัจฉริยะแบบรวมศูนย์</p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="d-flex gap-2 justify-content-md-end">
                <button onclick="sendAlarmBroadcast()" class="btn btn-alarm px-3">
                    <i class="fas fa-bullhorn me-1"></i> ALARM ALL
                </button>
                <button onclick="stopAllBroadcast()" class="btn btn-stop-all px-3">
                    <i class="fas fa-stop-circle me-1"></i> STOP ALL
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">จุดติดตั้งทั้งหมด</h5>
                    <span class="badge bg-primary rounded-pill"><?= count($nodes) ?> Nodes</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">ข้อมูลโหนด</th>
                                    <th>ควบคุมเสียง</th>
                                    <th class="pe-4">อัปโหลด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $node): 
                                    $node_port = $node['port'] ?? 80;
                                    $files = getFileList($node['ip_address'], $node_port); 
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="node-name"><?= htmlspecialchars($node['name']) ?></div>
                                        <div class="mt-1">
                                            <span class="ip-badge"><i class="fas fa-link me-1"></i><?= $node['ip_address'] ?>:<?= $node_port ?></span>
                                            <span id="online-<?= $node['id'] ?>" class="ms-2 small text-muted">Checking...</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($files && is_array($files)): ?>
                                            <div style="max-width: 200px;">
                                                <select class="form-select form-select-sm mb-2" id="file_<?= $node['id'] ?>">
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
                                                    <button class="btn btn-sm btn-outline-light border-secondary" onclick="stopFile('<?= $node['ip_address'] ?>', '<?= $node_port ?>')">หยุด</button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-danger small"><i class="fas fa-plug-circle-xmark me-1"></i> อุปกรณ์ Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4">
                                        <div class="input-group input-group-sm mb-1" style="max-width: 200px;">
                                            <input type="file" id="input_<?= $node['id'] ?>" class="form-control form-control-sm">
                                            <button class="btn btn-primary" onclick="uploadToNode(<?= $node['id'] ?>, '<?= $node['ip_address'] ?>', <?= $node_port ?>)">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                        </div>
                                        <div class="progress" id="pg_container_<?= $node['id'] ?>">
                                            <div id="bar_<?= $node['id'] ?>" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                                        </div>
                                        <small id="status_<?= $node['id'] ?>" class="text-muted d-block" style="font-size: 10px;"></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                <div class="card-body text-center py-4">
                    <h6 class="text-danger fw-bold mb-3"><i class="fas fa-microphone-lines me-2"></i>ระบบประกาศเสียงสด (Push-to-Talk)</h6>
                    <button id="btnLive" class="btn btn-live btn-lg w-100 py-3 mb-2" 
                            onmousedown="startLive()" onmouseup="stopLive()" 
                            ontouchstart="startLive()" ontouchend="stopLive()">
                        <i class="fas fa-microphone me-2"></i> กดค้างไว้เพื่อพูด
                    </button>
                    <p class="text-muted small mb-0 mt-2" style="font-size: 11px;">สัญญาณจะส่งไปยังทุก Node ที่ออนไลน์อยู่ขณะนี้</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-satellite-dish me-2 text-success"></i>สถานะการทำงานจริง</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="statusList">
                        <?php foreach ($nodes as $node): ?>
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-3 border-bottom border-secondary border-opacity-10">
                            <div>
                                <div class="fw-bold text-white"><?= htmlspecialchars($node['name']) ?></div>
                                <div id="song-<?= $node['id'] ?>" class="small text-muted">-</div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span id="badge-<?= $node['id'] ?>"><i class="fas fa-circle-notch fa-spin text-muted"></i></span>
                                <a href="http://<?= $node['ip_address'] ?>:<?= $node['port'] ?? 80 ?>" target="_blank" class="action-icon" title="Setting">
                                    <i class="fas fa-cog"></i>
                                </a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="text-white mb-3">สั่งงานด่วน (ทุกโหนด)</h6>
                    <form action="process_broadcast.php" method="POST">
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text bg-dark border-secondary text-muted">ชื่อไฟล์</span>
                            <input type="text" name="filename" class="form-control" placeholder="news.mp3" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <button type="submit" name="action" value="play" class="btn btn-primary btn-sm w-100">สั่งเล่น</button>
                            </div>
                            <div class="col-6">
                                <button type="submit" name="action" value="stop" class="btn btn-outline-light btn-sm w-100">หยุดทั้งหมด</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// 1. จัดการ Live Streaming (แบบหน้า live.php)
let audioContext, ws, processor, micInput;

async function startLive() {
    event.preventDefault();
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("ไม่สามารถเข้าถึงไมค์ได้ (ตรวจสอบ HTTPS)"); return;
    }

    const btn = document.getElementById('btnLive');
    btn.classList.add('active-live');
    btn.innerHTML = '<i class="fas fa-broadcast-tower fa-beat me-2"></i> กำลังส่งเสียง...';

    fetch('process_broadcast.php?action=play_live').catch(e => console.log(e));

    ws = new WebSocket("ws://" + window.location.hostname + ":3000");
    ws.onopen = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
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
                if (ws && ws.readyState === WebSocket.OPEN) ws.send(pcmData.buffer);
            };
        } catch (err) { stopLive(); }
    };
}

function stopLive() {
    event.preventDefault();
    const btn = document.getElementById('btnLive');
    btn.classList.remove('active-live');
    btn.innerHTML = '<i class="fas fa-microphone me-2"></i> กดค้างไว้เพื่อพูด';

    if (processor) { processor.disconnect(); processor = null; }
    if (audioContext) { audioContext.close(); audioContext = null; }
    if (ws) { ws.close(); ws = null; }
    fetch('process_broadcast.php?action=stop');
}

// 2. ฟังก์ชันจัดการไฟล์และอัปโหลด
function playFile(ip, port, selectId) {
    const file = document.getElementById(selectId).value;
    const path = file.startsWith('/') ? file : '/' + file;
    window.location.href = `process_broadcast.php?action=play_single&ip=${ip}&port=${port}&file=${encodeURIComponent(path)}`;
}

function stopFile(ip, port) {
    window.location.href = `process_broadcast.php?action=stop_single&ip=${ip}&port=${port}`;
}

function uploadToNode(nodeId, ip, port) {
    const fileInput = document.getElementById('input_' + nodeId);
    const file = fileInput.files[0];
    if (!file) { alert("เลือกไฟล์ก่อนครับ"); return; }

    const formData = new FormData();
    formData.append('fileToUpload', file);

    const xhr = new XMLHttpRequest();
    const bar = document.getElementById('bar_' + nodeId);
    const statusText = document.getElementById('status_' + nodeId);
    document.getElementById('pg_container_' + nodeId).style.display = 'block';

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            let p = Math.round((e.loaded / e.total) * 100);
            bar.style.width = p + '%';
            statusText.innerText = "Uploading: " + p + "%";
        }
    };

    xhr.onreadystatechange = () => {
        if (xhr.readyState == 4 && xhr.status == 200) {
            statusText.innerText = "✅ เสร็จสมบูรณ์";
            setTimeout(() => location.reload(), 1000);
        }
    };
    xhr.open("POST", `process_broadcast.php?action=upload_single&ip=${ip}&port=${port}`, true);
    xhr.send(formData);
}

// 3. ระบบเช็คสถานะ Online/Song
function updateStatuses() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('badge-<?= $node['id'] ?>');
            const onlineText = document.getElementById('online-<?= $node['id'] ?>');
            const songText = document.getElementById('song-<?= $node['id'] ?>');
            
            if (data.online) {
                badge.innerHTML = '<span class="badge bg-success">Online</span>';
                onlineText.className = "ms-2 small status-online";
                onlineText.innerText = "เชื่อมต่ออยู่";
            } else {
                badge.innerHTML = '<span class="badge bg-danger">Offline</span>';
                onlineText.className = "ms-2 small status-offline";
                onlineText.innerText = "ขาดการติดต่อ";
            }

            songText.innerHTML = (data.song && data.song !== "None") 
                ? '<i class="fas fa-play text-info me-1"></i> ' + data.song 
                : '<i class="fas fa-minus me-1"></i> Standby';
        }).catch(() => {});
    <?php endforeach; ?>
}

function sendAlarmBroadcast() { if (confirm("ส่งเสียงเตือนภัยทุกโหนด?")) submitBroadcast('play', '/alarm007.mp3'); }
function stopAllBroadcast() { submitBroadcast('stop', ''); }
function submitBroadcast(action, filename) {
    const f = document.createElement('form');
    f.method = 'POST'; f.action = 'process_broadcast.php';
    const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value=action;
    const n = document.createElement('input'); n.type='hidden'; n.name='filename'; n.value=filename;
    f.appendChild(a); f.appendChild(n); document.body.appendChild(f); f.submit();
}

setInterval(updateStatuses, 5000);
updateStatuses();
</script>
</body>
</html>