<?php
require_once 'config.php'; 

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// 2. ดึงข้อมูล Nodes ทั้งหมด
$stmt = $pdo->query("SELECT * FROM nodes ORDER BY name ASC");
$nodes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Broadcast - Smart Pole</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Sarabun', sans-serif; }
        .card { background-color: #1e293b; border: none; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .node-item { 
            background: #334155; border-radius: 10px; padding: 15px; margin-bottom: 10px; 
            transition: 0.3s; cursor: pointer; border: 2px solid transparent;
        }
        .node-item:hover { background: #475569; }
        .node-item.selected { border-color: #38bdf8; background: #1e293b; }
        
        /* สถานะ Live Pulse */
        .live-indicator {
            width: 15px; height: 15px; background-color: #ef4444; border-radius: 50%;
            display: inline-block; margin-right: 10px; display: none;
        }
        .is-streaming .live-indicator {
            display: inline-block; animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .btn-main { width: 100%; padding: 20px; font-size: 1.5rem; font-weight: bold; border-radius: 12px; }
        .status-badge { font-size: 0.8rem; padding: 4px 8px; border-radius: 5px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            
            <div class="card mb-4 text-center p-4" id="liveControlCard">
                <div class="mb-3">
                    <span class="live-indicator"></span>
                    <h3 class="d-inline align-middle">ระบบประกาศเสียงสด</h3>
                </div>
                
                <div id="statusText" class="text-info mb-4">สถานะ: พร้อมใช้งาน</div>

                <div class="d-grid gap-3">
                    <button id="btnStart" class="btn btn-primary btn-main" onclick="toggleLive()">
                        <i class="fas fa-play me-2"></i> START LIVE
                    </button>
                    <button id="btnStop" class="btn btn-danger btn-main d-none" onclick="toggleLive()">
                        <i class="fas fa-stop me-2"></i> STOP LIVE
                    </button>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-outline-light btn-sm" onclick="selectAllNodes(true)">เลือกทั้งหมด</button>
                    <button class="btn btn-outline-light btn-sm" onclick="selectAllNodes(false)">ยกเลิกทั้งหมด</button>
                </div>
            </div>

            <h5 class="mb-3"><i class="fas fa-list me-2"></i> เลือก Node ที่ต้องการส่งเสียง</h5>
            <div id="nodeList">
                <?php foreach ($nodes as $node): ?>
                <div class="node-item d-flex justify-content-between align-items-center" 
                     onclick="toggleNodeSelect(this, <?= $node['id'] ?>)">
                    <div>
                        <input type="checkbox" class="node-check d-none" value="<?= $node['id'] ?>" 
                               data-ip="<?= $node['ip_address'] ?>" data-port="<?= $node['port'] ?? 80 ?>">
                        <span class="fw-bold fs-5 text-white"><?= htmlspecialchars($node['name']) ?></span>
                        <div class="small text-muted"><?= $node['ip_address'] ?></div>
                    </div>
                    <div id="stat-<?= $node['id'] ?>">
                        <span class="badge bg-secondary status-badge">กำลังโหลด...</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>

<script>
let isStreaming = false;
let audioContext, ws, processor, micInput;

// 1. เลือก/ไม่เลือก Node
function toggleNodeSelect(el, id) {
    if(isStreaming) return; // ห้ามเปลี่ยนระหว่าง Live
    const checkbox = el.querySelector('.node-check');
    checkbox.checked = !checkbox.checked;
    el.classList.toggle('selected', checkbox.checked);
}

function selectAllNodes(status) {
    if(isStreaming) return;
    document.querySelectorAll('.node-item').forEach(el => {
        const cb = el.querySelector('.node-check');
        cb.checked = status;
        el.classList.toggle('selected', status);
    });
}

// 2. เริ่ม/หยุด Live
async function toggleLive() {
    if (!isStreaming) {
        await startLive();
    } else {
        stopLive();
    }
}

async function startLive() {
    const selectedNodes = Array.from(document.querySelectorAll('.node-check:checked'));
    
    if (selectedNodes.length === 0) {
        alert("กรุณาเลือก Node อย่างน้อย 1 เครื่องครับ");
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Browser ไม่รองรับการเข้าถึงไมโครโฟนผ่าน HTTP (ต้องใช้ HTTPS หรือ localhost)");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        // บอก Server (PHP) ให้สั่งเฉพาะโหนดที่เลือก
        const nodeData = selectedNodes.map(n => ({ip: n.dataset.ip, port: n.dataset.port}));
        
        // ส่งข้อมูลแบบ POST ไปที่ process_broadcast เพื่อความเสถียร
        fetch('process_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=play_live_selected&nodes=${JSON.stringify(nodeData)}`
        });

        // ตั้งค่า WebSocket
        ws = new WebSocket(`ws://${window.location.hostname}:3000`);
        
        ws.onopen = async () => {
            audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
            micInput = audioContext.createMediaStreamSource(stream);
            processor = audioContext.createScriptProcessor(2048, 1, 1);
            
            micInput.connect(processor);
            processor.connect(audioContext.destination);

            processor.onaudioprocess = (e) => {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    let inputData = e.inputBuffer.getChannelData(0);
                    let pcmData = new Int16Array(inputData.length);
                    for (let i = 0; i < inputData.length; i++) {
                        pcmData[i] = Math.max(-1, Math.min(1, inputData[i])) * 0x7FFF;
                    }
                    ws.send(pcmData.buffer);
                }
            };

            updateUI(true);
        };

        ws.onclose = () => { if(isStreaming) stopLive(); };

    } catch (err) {
        console.error(err);
        alert("ไม่สามารถเข้าถึงไมค์ได้: " + err);
    }
}

function stopLive() {
    if (processor) { processor.disconnect(); processor = null; }
    if (micInput) { micInput.disconnect(); micInput = null; }
    if (audioContext) { audioContext.close(); audioContext = null; }
    if (ws) { ws.close(); ws = null; }
    
    // สั่งหยุดที่ ESP32 โหนดที่เลือก
    fetch('process_broadcast.php?action=stop');

    updateUI(false);
}

function updateUI(streaming) {
    isStreaming = streaming;
    document.getElementById('liveControlCard').classList.toggle('is-streaming', streaming);
    document.getElementById('btnStart').classList.toggle('d-none', streaming);
    document.getElementById('btnStop').classList.toggle('d-none', !streaming);
    document.getElementById('statusText').innerText = streaming ? "สถานะ: กำลังประกาศเสียงสด..." : "สถานะ: พร้อมใช้งาน";
    document.getElementById('statusText').classList.toggle('text-danger', streaming);
    document.getElementById('statusText').classList.toggle('text-info', !streaming);
}

// 3. ระบบเช็คสถานะ Online/Offline (ความถี่ 5 วินาที)
function updateNodeStatus() {
    <?php foreach ($nodes as $node): ?>
    fetch('get_node_status.php?id=<?= $node['id'] ?>')
        .then(res => res.json())
        .then(data => {
            const badge = document.querySelector('#stat-<?= $node['id'] ?> .badge');
            if (data.online) {
                badge.className = "badge bg-success status-badge";
                badge.innerText = "Online";
            } else {
                badge.className = "badge bg-danger status-badge";
                badge.innerText = "Offline";
            }
        }).catch(() => {});
    <?php endforeach; ?>
}

setInterval(updateNodeStatus, 5000);
updateNodeStatus();
</script>

</body>
</html>