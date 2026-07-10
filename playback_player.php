<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playback Player</title>
    <script src="https://cdn.jsdelivr.net/npm/jsmpeg@1.0.0/dist/jsmpeg.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
            font-family: 'Segoe UI', sans-serif;
        }
        .player-container {
            max-width: 1280px;
            margin: 0 auto;
        }
        .video-wrapper {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        #videoCanvas {
            width: 100%;
            height: auto;
            display: block;
        }
        .controls {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            background: #2a2a2a;
            border-radius: 8px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-primary:hover { background: #0b5ed7; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #bb2d3b; }
        .speed-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        .speed-control label {
            font-size: 14px;
            color: #adb5bd;
        }
        .speed-control select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #444;
            background: #1a1a1a;
            color: #fff;
            cursor: pointer;
        }
        .status {
            padding: 10px 15px;
            background: #2a2a2a;
            border-radius: 6px;
            margin-top: 15px;
            font-family: 'Consolas', monospace;
            font-size: 13px;
        }
        .status.connecting { border-left: 3px solid #ffc107; }
        .status.playing { border-left: 3px solid #198754; }
        .status.error { border-left: 3px solid #dc3545; }
        .time-display {
            font-size: 18px;
            font-weight: bold;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="player-container">
        <h2>🎬 Playback: <?= htmlspecialchars($_GET['camera_name'] ?? 'Camera') ?></h2>
        
        <div class="video-wrapper">
            <canvas id="videoCanvas"></canvas>
        </div>
        
        <div class="controls">
            <button class="btn btn-primary" onclick="togglePlay()" id="playBtn">
                <i class="fas fa-pause"></i> Pause
            </button>
            <button class="btn btn-danger" onclick="stopPlayback()">
                <i class="fas fa-stop"></i> Stop
            </button>
            
            <div class="time-display" id="timeDisplay">00:00:00</div>
            
            <div class="speed-control">
                <label>🎬 Speed:</label>
                <select id="speedSelect" onchange="changeSpeed()">
                    <option value="0.5">0.5x</option>
                    <option value="0.75">0.75x</option>
                    <option value="1" selected>1.0x (Normal)</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2">2.0x</option>
                    <option value="4">4.0x</option>
                </select>
            </div>
        </div>
        
        <div class="status connecting" id="status">
            <i class="fas fa-spinner fa-spin"></i> Connecting...
        </div>
    </div>

    <script>
        const cameraId = <?= json_encode($_GET['camera_id'] ?? null) ?>;
        const startTime = <?= json_encode($_GET['start'] ?? null) ?>;
        const endTime = <?= json_encode($_GET['end'] ?? null) ?>;
        
        let player = null;
        let ws = null;
        let isPlaying = true;
        let startTimeMs = Date.now();
        let speed = 1.0;
        
        function connect() {
            const statusEl = document.getElementById('status');
            statusEl.className = 'status connecting';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
            
            const wsUrl = `ws://localhost:8080/playback_ws.php?camera_id=${cameraId}&start=${encodeURIComponent(startTime)}&end=${encodeURIComponent(endTime)}`;
            
            ws = new WebSocket(wsUrl);
            ws.binaryType = 'arraybuffer';
            
            ws.onopen = () => {
                console.log('WebSocket connected');
                statusEl.className = 'status playing';
                statusEl.innerHTML = '<i class="fas fa-play"></i> Playing';
                startTimeMs = Date.now();
                updateTimer();
            };
            
            ws.onmessage = (event) => {
                if (player) {
                    player.feed(new Uint8Array(event.data));
                }
            };
            
            ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                statusEl.className = 'status error';
                statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Connection error';
            };
            
            ws.onclose = () => {
                console.log('WebSocket closed');
                statusEl.className = 'status error';
                statusEl.innerHTML = '<i class="fas fa-stop"></i> Disconnected';
            };
            
            // สร้าง JSMpeg player
            player = new JSMpeg.Player(ws, {
                canvas: document.getElementById('videoCanvas'),
                autoplay: true,
                audio: true
            });
        }
        
        function togglePlay() {
            if (!player) return;
            
            if (isPlaying) {
                player.pause();
                document.getElementById('playBtn').innerHTML = '<i class="fas fa-play"></i> Play';
            } else {
                player.play();
                document.getElementById('playBtn').innerHTML = '<i class="fas fa-pause"></i> Pause';
                startTimeMs = Date.now();
            }
            isPlaying = !isPlaying;
        }
        
        function stopPlayback() {
            if (ws) ws.close();
            if (player) player.destroy();
            window.close();
        }
        
        function changeSpeed() {
            speed = parseFloat(document.getElementById('speedSelect').value);
            if (player) {
                // JSMpeg ไม่มี method changeSpeed โดยตรง
                // ต้อง destroy และสร้างใหม่
                const wasPlaying = isPlaying;
                player.destroy();
                connect();
                if (!wasPlaying) {
                    setTimeout(() => {
                        player.pause();
                        isPlaying = false;
                        document.getElementById('playBtn').innerHTML = '<i class="fas fa-play"></i> Play';
                    }, 1000);
                }
            }
        }
        
        function updateTimer() {
            if (!isPlaying) {
                requestAnimationFrame(updateTimer);
                return;
            }
            
            const elapsed = (Date.now() - startTimeMs) / 1000 * speed;
            const start = new Date(startTime);
            const current = new Date(start.getTime() + elapsed * 1000);
            
            document.getElementById('timeDisplay').textContent = current.toLocaleTimeString();
            requestAnimationFrame(updateTimer);
        }
        
        // เริ่มเชื่อมต่อเมื่อโหลดหน้า
        window.addEventListener('load', connect);
        
        // ปิด connection เมื่อปิดหน้า
        window.addEventListener('beforeunload', () => {
            if (ws) ws.close();
            if (player) player.destroy();
        });
    </script>
</body>
</html>