<?php
require_once 'config.php'; 

// 1. ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// 2. ดึงข้อมูล Nodes ทั้งหมดจากฐานข้อมูล
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
        /* ปรับเป็นโทนสีสว่างตาม index.php */
        body { background-color: #f1f5f9; color: #334155; font-family: 'Sarabun', sans-serif; }
        .card { background-color: #ffffff; border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative; overflow: hidden; }
        
        .welcome-card { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; border-radius: 15px; padding: 30px; margin-bottom: 25px; }

        /* สไตล์รายชื่อ Node */
        .node-item { 
            background: #ffffff; border-radius: 12px; padding: 15px; margin-bottom: 10px; 
            transition: 0.3s; cursor: pointer; border: 2px solid transparent;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .node-item:hover { background: #f8fafc; transform: translateY(-2px); }
        .node-item.selected { border-color: #3b82f6; background: #eff6ff; }
        
        /* ระบบนับถอยหลัง Overlay */
        #countdownOverlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        #countdownNumber { font-size: 6rem; font-weight: bold; color: #1e40af; line-height: 1; }
        
        /* สถานะ Live */
        .live-indicator {
            width: 15px; height: 15px; background-color: #ef4444; border-radius: 50%;
            display: inline-block; margin-right: 10px; display: none;
        }
        .is-streaming .live-indicator { display: inline-block; animation: pulse 1s infinite; }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .mic-status-label { font-weight: bold; padding: 8px 20px; border-radius: 50px; margin-top: 15px; display: inline-block; }
        .status-muted { background-color: #94a3b8; color: #fff; }
        .status-active { background-color: #ef4444; color: #fff; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0.7; } }

        .btn-main { width: 100%; padding: 18px; font-size: 1.4rem; font-weight: bold; border-radius: 12px; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-main:active { transform: scale(0.98); }
        .status-badge { font-size: 0.8rem; padding: 4px 8px; border-radius: 5px; }

        /* ส่วนแนะนำ Chrome Flag */
        .setup-guide { background-color: #fff; border-left: 5px solid #f59e0b; border-radius: 8px; padding: 20px; margin-top: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        code { background: #f1f5f9; padding: 2px 5px; color: #e11d48; border-radius: 4px; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="welcome-card shadow-sm text-center">
        <h2 class="fw-bold mb-1">Live Broadcast Center</h2>
        <p class="mb-0 opacity-75">ประกาศเสียงสดไปยังสถานีปลายทางที่เลือก</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            
            <div class="card mb-4 text-center p-4 shadow-sm" id="liveControlCard">
                <div id="countdownOverlay">
                    <div id="countdownNumber">5</div>
                    <div class="mt-3 text-primary fw-bold" id="countdownStatus">กำลังเตรียมระบบ...</div>
                </div>

                <div class="mb-2">
                    <span class="live-indicator"></span>
                    <h3 class="d-inline align-middle fw-bold text-dark">ระบบประกาศเสียงสด</h3>
                </div>
                
                <div id="statusText" class="text-muted mb-2">สถานะ: พร้อมประกาศ</div>
                <div id="micLabel" class="mic-status-label d-none"></div>

                <div class="d-grid gap-3 mt-4">
                    <button id="btnStart" class="btn btn-primary btn-main" onclick="toggleLive()">
                        <i class="fas fa-play me-2"></i> เริ่มการประกาศ (START)
                    </button>
                    <button id="btnStop" class="btn btn-danger btn-main d-none" onclick="toggleLive()">
                        <i class="fas fa-stop me-2"></i> หยุดการประกาศ (STOP)
                    </button>
                </div>
                
                <div class="mt-4 border-top pt-3">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="selectAllNodes(true)">เลือกทั้งหมด</button>
                        <button class="btn btn-outline-secondary" onclick="selectAllNodes(false)">ยกเลิกทั้งหมด</button>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-tower-cell me-2 text-primary"></i> เลือกสถานีปลายทาง</h5>
                <span class="badge bg-primary rounded-pill"><?= count($nodes) ?> โหนด</span>
            </div>

            <div id="nodeList">
                <?php foreach ($nodes as $node): ?>
                <div class="node-item d-flex justify-content-between align-items-center shadow-sm" 
                     onclick="toggleNodeSelect(this, <?= $node['id'] ?>)">
                    <div>
                        <input type="checkbox" class="node-check d-none" value="<?= $node['id'] ?>" 
                               data-ip="<?= $node['ip_address'] ?>" data-port="<?= $node['port'] ?? 80 ?>">
                        <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($node['name']) ?></span>

                    </div>
                    <div id="stat-<?= $node['id'] ?>">
                        <span class="badge bg-secondary status-badge">Checking...</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="setup-guide">
                <h6 class="fw-bold text-warning mb-2"><i class="fas fa-exclamation-triangle me-2"></i> สำหรับผู้ใช้งานผ่าน HTTP (ไม่มี SSL)</h6>
                <p class="small text-muted mb-2">หากปุ่มกดใช้งานไมค์ไม่ทำงาน ให้ตั้งค่า Chrome เพื่ออนุญาตการใช้งานไมค์ผ่าน IP ดังนี้:</p>
                <ol class="small text-muted">
                    <li>พิมพ์ <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code> ในช่อง URL</li>
                    <li>ในหัวข้อ <strong>"Insecure origins treated as secure"</strong> ให้เลือกเป็น <strong>Enabled</strong></li>
                    <li>นำ URL ของหน้าเว็บนี้ไปใส่ในช่องว่าง เช่น: <code>http://<?= $_SERVER['HTTP_HOST'] ?></code></li>
                    <li>กดปุ่ม <strong>Relaunch</strong> ด้านล่างเพื่อเริ่มการทำงานใหม่</li>
                </ol>
            </div>

        </div>
    </div>
</div>

<script>
let isStreaming = false;
let isStopping = false; 
let isMicMuted = true;
let audioContext, ws, processor, micInput;
let countdownInterval;
let activeNodes = [];

function toggleNodeSelect(el, id) {
    if(isStreaming || isStopping) return; 
    const checkbox = el.querySelector('.node-check');
    checkbox.checked = !checkbox.checked;
    el.classList.toggle('selected', checkbox.checked);
}

function selectAllNodes(status) {
    if(isStreaming || isStopping) return;
    document.querySelectorAll('.node-item').forEach(el => {
        const cb = el.querySelector('.node-check');
        cb.checked = status;
        el.classList.toggle('selected', status);
    });
}

async function toggleLive() {
    if (!isStreaming) { await startLive(); } else { await stopLive(); }
}

async function startLive() {
    const selectedNodes = Array.from(document.querySelectorAll('.node-check:checked'));
    if (selectedNodes.length === 0) {
        alert("กรุณาเลือกโหนดอย่างน้อย 1 เครื่องก่อนเริ่มประกาศ");
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("เบราว์เซอร์ไม่รองรับการใช้ไมค์ (กรุณาดูวิธีตั้งค่าด้านล่างหน้าจอ)");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        activeNodes = selectedNodes.map(n => ({ip: n.dataset.ip, port: n.dataset.port}));

        // สั่งโหนดปลายทางให้เตรียมรับ Stream
        fetch('process_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=play_live_selected&nodes=${JSON.stringify(activeNodes)}`
        });

        isMicMuted = true;
        isStreaming = true;

        // เชื่อมต่อ WebSocket
        ws = new WebSocket(`ws://${window.location.hostname}:3000`);
        
        ws.onopen = async () => {
            audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
            micInput = audioContext.createMediaStreamSource(stream);
            processor = audioContext.createScriptProcessor(2048, 1, 1);
            
            micInput.connect(processor);
            processor.connect(audioContext.destination);

            processor.onaudioprocess = (e) => {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    if (isMicMuted) {
                        ws.send(new Int16Array(2048).buffer);
                    } else {
                        let inputData = e.inputBuffer.getChannelData(0);
                        let pcmData = new Int16Array(inputData.length);
                        for (let i = 0; i < inputData.length; i++) {
                            pcmData[i] = Math.max(-1, Math.min(1, inputData[i])) * 0x7FFF;
                        }
                        ws.send(pcmData.buffer);
                    }
                }
            };

            updateUI(true);
            updateMicLabel(true);
            
            runCountdown(5, "กำลังเชื่อมต่อโหนดและเตรียมสัญญาณ...", "#1e40af", () => {
                isMicMuted = false;
                updateMicLabel(false);
            });
        };

        ws.onclose = () => { if(isStreaming && !isStopping) stopLive(); };

    } catch (err) {
        alert("ไม่สามารถเข้าถึงไมโครโฟนได้: " + err);
    }
}

async function stopLive() {
    if (isStopping) return;
    isStopping = true;
    isMicMuted = true;
    updateMicLabel(true);
    
    runCountdown(5, "กำลังส่งสัญญาณชุดสุดท้ายและปิดระบบ...", "#dc2626", async () => {
        if (processor) processor.disconnect();
        if (micInput) micInput.disconnect();
        if (audioContext) audioContext.close();
        if (ws) ws.close();
        
        try {
            await fetch('process_broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=stop_selected&nodes=${JSON.stringify(activeNodes)}`
            });
        } catch (err) { console.error("Error stopping nodes:", err); }

        isStreaming = false;
        isStopping = false;
        activeNodes = [];
        updateUI(false);
        document.getElementById('micLabel').classList.add('d-none');
    });
}

function runCountdown(seconds, text, color, callback) {
    const overlay = document.getElementById('countdownOverlay');
    const numEl = document.getElementById('countdownNumber');
    const textEl = document.getElementById('countdownStatus');
    
    overlay.style.display = 'flex';
    numEl.style.color = color;
    textEl.innerText = text;
    
    let timeleft = seconds;
    numEl.innerText = timeleft;

    clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        timeleft--;
        if (timeleft <= 0) {
            clearInterval(countdownInterval);
            overlay.style.display = 'none';
            callback();
        } else {
            numEl.innerText = timeleft;
        }
    }, 1000);
}

function updateUI(streaming) {
    document.getElementById('liveControlCard').classList.toggle('is-streaming', streaming);
    document.getElementById('btnStart').classList.toggle('d-none', streaming);
    document.getElementById('btnStop').classList.toggle('d-none', !streaming);
    document.getElementById('statusText').innerText = streaming ? "สถานะ: กำลังประกาศ..." : "สถานะ: พร้อมประกาศ";
}

function updateMicLabel(muted) {
    const label = document.getElementById('micLabel');
    label.classList.remove('d-none');
    if (isStopping) {
        label.className = "mic-status-label status-muted";
        label.innerHTML = '<i class="fas fa-hourglass-end me-2"></i> CLOSING... (กำลังปิดเสียง)';
    } else if (muted) {
        label.className = "mic-status-label status-muted";
        label.innerHTML = '<i class="fas fa-microphone-slash me-2"></i> BUFFERING... (กรุณารอ)';
    } else {
        label.className = "mic-status-label status-active";
        label.innerHTML = '<i class="fas fa-microphone me-2"></i> LIVE ACTIVE - ประกาศได้เลย';
    }
}

function checkNodesStatus() {
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

setInterval(checkNodesStatus, 10000);
checkNodesStatus();
</script>

</body>
</html>