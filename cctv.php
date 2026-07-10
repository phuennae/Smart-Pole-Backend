<?php
//session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? 'user';

try {
    $stmt = $pdo->query("SELECT * FROM cameras ORDER BY id DESC");
    $cameras = $stmt->fetchAll();
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CCTV Test - Smart Pole Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jmuxer@2.0.5/dist/jmuxer.min.js"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Kanit', sans-serif; }
        
        /* Mode Switcher */
        .mode-switcher {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .mode-btn {
            flex: 1;
            padding: 12px;
            background: #fff;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
            font-weight: 500;
        }
        .mode-btn.active {
            border-color: #0d6efd;
            background: #e7f1ff;
            color: #0d6efd;
        }
        .mode-btn:hover { border-color: #0d6efd; }
        
        /* Camera Card */
        .camera-card { border: none; border-radius: 12px; overflow: hidden; background: #fff; transition: 0.3s; height: 100%; }
        .camera-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        
        .video-wrapper { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000; display: flex; align-items: center; justify-content: center; }
        .video-wrapper iframe { width: 100%; height: 100%; border: none; z-index: 5; }
        .video-wrapper img.snapshot { width: 100%; height: 100%; object-fit: cover; }
        
        .video-placeholder { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #666; z-index: 1; }
        
        .cam-overlay { position: absolute; top: 10px; left: 10px; z-index:10; background: rgba(0,0,0,0.6); color: #fff; padding: 2px 12px; border-radius: 4px; font-size: 0.85rem; pointer-events: none; }
        .cam-controls { position: absolute; bottom: 10px; right: 10px; z-index: 20; display: flex; gap: 6px; }
        .btn-cam { padding: 5px 9px; font-size: 0.85rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        
        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
        
        /* PTZ Control Panel - เล็กลง */
        .ptz-panel {
            position: absolute;
            bottom: 50px;
            left: 10px;
            z-index: 25;
            background: rgba(0,0,0,0.9);
            padding: 8px;
            border-radius: 10px;
            display: none;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            /* ✅ ป้องกัน touch scroll/zoom */
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        .ptz-panel.show { display: block; }
        .ptz-grid {
            display: grid;
            grid-template-columns: repeat(3, 32px);
            grid-template-rows: repeat(3, 32px);
            gap: 4px;
        }
        .ptz-btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            /* ✅ ป้องกัน touch delay และ scroll */
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }
        .ptz-btn:hover { background: rgba(13,110,253,0.6); }
        .ptz-btn:active, .ptz-btn.active { background: rgba(13,110,253,0.9); transform: scale(0.95); }
        .ptz-btn.center { background: rgba(220,53,69,0.7); }
        .ptz-btn.center:hover, .ptz-btn.center:active { background: rgba(220,53,69,0.9); }
        .ptz-status {
            font-size: 0.65rem;
            color: #adb5bd;
            text-align: center;
            margin-top: 5px;
            padding: 2px 4px;
            border-radius: 3px;
            background: rgba(0,0,0,0.3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ptz-status.ready { color: #75b798; }
        .ptz-status.error { color: #ea868f; }
        .ptz-status.loading { color: #ffc107; }
        
        /* Playback Container */
        .playback-container {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
        }
        .playback-container.show { display: block; }
        
        .playback-video {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 16/9;
            position: relative;
        }
        .playback-video canvas {
            width: 100%;
            height: 100%;
            display: block;
        }
        .playback-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .playback-time-display {
            font-family: 'Consolas', monospace;
            font-size: 1.1rem;
            color: #0d6efd;
            font-weight: bold;
        }
        
        /* Timeline */
        .timeline-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .timeline-bar {
            height: 50px;
            background: #e9ecef;
            border-radius: 6px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .timeline-segment {
            position: absolute;
            height: 100%;
            background: linear-gradient(90deg, #0d6efd, #6610f2);
            opacity: 0.7;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .timeline-segment:hover { opacity: 1; }
        .timeline-segment.selected {
            opacity: 1;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #0d6efd;
        }
        
        /* Debug Panel */
        .debug-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            cursor: pointer;
            font-size: 18px;
        }
        .debug-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            max-height: 45vh;
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            z-index: 9999;
            display: none;
            flex-direction: column;
            border-top: 3px solid #0d6efd;
        }
        .debug-panel.show { display: flex; }
        .debug-header {
            background: #252526;
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #3c3c3c;
        }
        .debug-header h6 { margin: 0; color: #4ec9b0; font-size: 14px; }
        .debug-body { flex: 1; overflow-y: auto; padding: 10px 15px; }
        .debug-entry {
            margin-bottom: 8px;
            padding: 8px 10px;
            background: #2d2d2d;
            border-left: 3px solid #0d6efd;
            border-radius: 4px;
        }
        .debug-entry.success { border-left-color: #4ec9b0; }
        .debug-entry.error { border-left-color: #f48771; }
        .debug-entry.info { border-left-color: #ffc107; }
        .debug-time { color: #608b4e; }
        .debug-json {
            background: #1e1e1e;
            padding: 6px;
            margin-top: 5px;
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

<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-video text-danger me-2"></i> ระบบกล้องวงจรปิด Smart City</h3>
            <small class="text-muted">📄 cctv.php | 🎯 Direct PHP Mode</small>
        </div>
        <?php if ($role == 'admin'): ?>
            <button class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#addCamModal">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มกล้องใหม่
            </button>
        <?php endif; ?>
    </div>

    <!-- Mode Switcher -->
    <div class="mode-switcher">
        <div class="mode-btn active" onclick="switchMode('live')" id="mode-live">
            <i class="fas fa-broadcast-tower"></i>  Live View
        </div>
        <div class="mode-btn" onclick="switchMode('playback')" id="mode-playback">
            <i class="fas fa-history"></i> 📼 Playback ย้อนหลัง
        </div>
    </div>

    <!-- Live View Container -->
    <div id="liveContainer">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (count($cameras) > 0): ?>
                <?php foreach ($cameras as $cam): ?>
                <?php
                    $ptzIp = !empty($cam['ptz_ip']) ? $cam['ptz_ip'] : '';
                    $videoUrl = $cam['ip_address'];
                    $snapshotIp = $ptzIp;
                    if (!$snapshotIp && preg_match('/^https?:\/\/([^\/:]+)/', $videoUrl, $m)) {
                        $snapshotIp = $m[1];
                    }
                ?>
                <div class="col">
                    <div class="card camera-card shadow-sm">
                        <div class="video-wrapper">
                            <div class="cam-overlay">
                                <i class="fas fa-circle text-danger me-1 small blink"></i> LIVE: <?= htmlspecialchars($cam['camera_name']) ?>
                            </div>
                            
                            <?php 
                            if (strpos($videoUrl, 'stream.html') !== false || strpos($videoUrl, 'bmatraffic.com') !== false): 
                            ?>
                                <iframe src="<?= htmlspecialchars($videoUrl) ?>" allow="autoplay; fullscreen"></iframe>
                            
                            <?php elseif (strpos($videoUrl, 'rtsp://') === 0): ?>
                                <?php if ($snapshotIp && !empty($cam['ptz_username'])): ?>
                                    <img src="ptz_proxy.php?action=snapshot&ptz_ip=<?= urlencode($snapshotIp) ?>&ptz_username=<?= urlencode($cam['ptz_username']) ?>&ptz_password=<?= urlencode($cam['ptz_password'] ?? '') ?>"
                                         id="cam-img-<?= $cam['id'] ?>"
                                         class="snapshot"
                                         onerror="this.src='https://via.placeholder.com/640x360?text=Camera+Offline'">
                                <?php else: ?>
                                    <div class="video-placeholder bg-dark text-white text-center w-100 h-100">
                                        <i class="fas fa-video-slash fa-2x mb-2 text-secondary"></i>
                                        <p class="small">RTSP Stream<br>ต้องตั้งค่า PTZ credentials</p>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <img src="<?= htmlspecialchars($videoUrl) ?>" 
                                     id="cam-img-<?= $cam['id'] ?>"
                                     class="img-fluid w-100 h-100" 
                                     style="object-fit: cover;"
                                     onerror="this.src='https://via.placeholder.com/640x360?text=Camera+Offline'">
                            <?php endif; ?>

                            <!-- PTZ Control Panel (เล็กลง ไม่มี Zoom) -->
                            <?php if ($ptzIp): ?>
                            <div class="ptz-panel" id="ptz-panel-<?= $cam['id'] ?>" 
                                 data-ptz-ip="<?= htmlspecialchars($ptzIp) ?>"
                                 data-ptz-port="<?= htmlspecialchars($cam['ptz_port'] ?? 80) ?>"
                                 data-ptz-user="<?= htmlspecialchars($cam['ptz_username'] ?? 'admin') ?>"
                                 data-ptz-pass="<?= htmlspecialchars($cam['ptz_password'] ?? '') ?>"
                                 data-cam-id="<?= $cam['id'] ?>"
                                 data-cam-name="<?= htmlspecialchars($cam['camera_name']) ?>"
                                 data-profile-token="<?= htmlspecialchars($cam['profile_token'] ?? 'Profile_1') ?>">
                                
                                <div class="ptz-grid">
                                    <div></div>
                                    <button class="ptz-btn" 
                                            onpointerdown="ptzMove(<?= $cam['id'] ?>, 'up')" 
                                            onpointerup="ptzStop(<?= $cam['id'] ?>)" 
                                            onpointerleave="ptzStop(<?= $cam['id'] ?>)"
                                            ontouchstart="event.preventDefault()"
                                            title="ขึ้น">
                                        <i class="fas fa-arrow-up"></i>
                                    </button>
                                    <div></div>
                                    <button class="ptz-btn" 
                                            onpointerdown="ptzMove(<?= $cam['id'] ?>, 'left')" 
                                            onpointerup="ptzStop(<?= $cam['id'] ?>)" 
                                            onpointerleave="ptzStop(<?= $cam['id'] ?>)"
                                            ontouchstart="event.preventDefault()"
                                            title="ซ้าย">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                    <button class="ptz-btn center" 
                                            onpointerdown="ptzStop(<?= $cam['id'] ?>)"
                                            ontouchstart="event.preventDefault()"
                                            title="หยุด">
                                        <i class="fas fa-stop"></i>
                                    </button>
                                    <button class="ptz-btn" 
                                            onpointerdown="ptzMove(<?= $cam['id'] ?>, 'right')" 
                                            onpointerup="ptzStop(<?= $cam['id'] ?>)" 
                                            onpointerleave="ptzStop(<?= $cam['id'] ?>)"
                                            ontouchstart="event.preventDefault()"
                                            title="ขวา">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                    <div></div>
                                    <button class="ptz-btn" 
                                            onpointerdown="ptzMove(<?= $cam['id'] ?>, 'down')" 
                                            onpointerup="ptzStop(<?= $cam['id'] ?>)" 
                                            onpointerleave="ptzStop(<?= $cam['id'] ?>)"
                                            ontouchstart="event.preventDefault()"
                                            title="ลง">
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                    <div></div>
                                </div>
                                <div class="ptz-status" id="ptz-status-<?= $cam['id'] ?>">
                                    <i class="fas fa-circle-question"></i> ยังไม่ได้ตรวจสอบ
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="cam-controls">
                                <?php if ($ptzIp): ?>
                                <button class="btn btn-info btn-cam border-light shadow" 
                                        onclick="togglePTZ(<?= $cam['id'] ?>)" title="PTZ Control">
                                    <i class="fas fa-arrows-alt"></i>
                                </button>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" class="btn btn-dark btn-cam border-light shadow">
                                    <i class="fas fa-expand-alt"></i>
                                </a>
                                <?php if ($role == 'admin'): ?>
                                    <button type="button" class="btn btn-warning btn-cam shadow" 
                                            onclick='openEditModal(<?= json_encode($cam) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="process_camera.php?action=delete&id=<?= $cam['id'] ?>" class="btn btn-danger btn-cam shadow" 
                                       onclick="return confirm('ยืนยันการลบกล้องนี้?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body py-2 px-3">
                            <div class="fw-bold"><?= htmlspecialchars($cam['camera_name']) ?></div>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-map-marker-alt text-primary me-1"></i> 
                                <?= htmlspecialchars($cam['location'] ?: 'ไม่ระบุพิกัด') ?>
                                <?php if ($ptzIp): ?>
                                    <span class="badge bg-info ms-2">
                                        <i class="fas fa-gamepad"></i> PTZ: <?= htmlspecialchars($ptzIp) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5"><p class="text-muted">ไม่พบข้อมูลกล้อง</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Playback Container -->
    <div class="playback-container" id="playbackContainer">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search"></i> ค้นหาวิดีโอ</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">เลือกกล้อง</label>
                            <select class="form-select" id="playbackCamera">
                                <?php foreach ($cameras as $cam): ?>
                                    <option value="<?= $cam['id'] ?>" 
                                        data-ptz-ip="<?= htmlspecialchars($cam['ptz_ip'] ?: $cam['ip_address']) ?>"
                                        data-name="<?= htmlspecialchars($cam['camera_name']) ?>">
                                        <?= htmlspecialchars($cam['camera_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">วันที่</label>
                            <input type="date" class="form-control" id="playbackDate" 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <button class="btn btn-primary w-100" onclick="searchRecordings()">
                            <i class="fas fa-search"></i> ค้นหาวิดีโอ
                        </button>
                        <div id="searchResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-play-circle"></i> Playback: 
                            <span id="playbackTitle">-</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="playback-video">
                            <canvas id="playbackCanvas"></canvas>
                            <div id="playbackOverlay" class="cam-overlay" style="display:none;">
                                <i class="fas fa-history text-info me-1"></i>
                                <span id="playbackTimeDisplay">00:00:00</span>
                            </div>
                        </div>
                        
                        <div class="playback-controls">
                            <button class="btn btn-success" onclick="playSelectedSegment()" id="playBtn" disabled>
                                <i class="fas fa-play"></i> Play
                            </button>
                            <button class="btn btn-danger" onclick="stopPlayback()" id="stopBtn" disabled>
                                <i class="fas fa-stop"></i> Stop
                            </button>
                       
                            <div class="playback-time-display ms-auto">
                                <i class="fas fa-clock"></i> <span id="currentTime">--:--:--</span>
                            </div>
                        </div>
                        
                        <div class="timeline-container">
                            <div class="d-flex justify-content-between mb-2">
                                <small class="text-muted">Timeline (คลิกช่วงสีฟ้าเพื่อเล่น)</small>
                                <small class="text-muted" id="timelineDate">-</small>
                            </div>
                            <div class="timeline-bar" id="timelineBar">
                                <div class="text-center text-muted py-2" id="timelinePlaceholder">
                                    กด "ค้นหาวิดีโอ" เพื่อแสดง timeline
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">00:00</small>
                                <small class="text-muted">06:00</small>
                                <small class="text-muted">12:00</small>
                                <small class="text-muted">18:00</small>
                                <small class="text-muted">24:00</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Camera Modal -->
<div class="modal fade" id="addCamModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_camera.php?action=add" method="POST" class="modal-content">
            <div class="modal-header bg-success text-white"><h5>เพิ่มกล้องใหม่</h5></div>
            <div class="modal-body">
                <div class="mb-3"><label>ชื่อกล้อง</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label>URL/IP (สำหรับดูภาพ)</label><input type="text" name="ip" class="form-control" required placeholder="http://... หรือ rtsp://..."></div>
                
                <hr>
                <h6 class="text-primary"><i class="fas fa-gamepad"></i> ตั้งค่า PTZ (ONVIF)</h6>
                <div class="mb-3">
                    <label>IP สำหรับ PTZ (ONVIF)</label>
                    <input type="text" name="ptz_ip" class="form-control" placeholder="เช่น 192.168.3.64">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Port ONVIF</label>
                        <input type="number" name="ptz_port" class="form-control" value="80">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Profile Token</label>
                        <input type="text" name="profile_token" class="form-control" value="Profile_1">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Username</label>
                        <input type="text" name="ptz_username" class="form-control" value="admin">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Password</label>
                        <input type="password" name="ptz_password" class="form-control">
                    </div>
                </div>
                
                <div class="mb-3"><label>สถานที่</label><input type="text" name="location" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success w-100">บันทึก</button></div>
        </form>
    </div>
</div>

<!-- Edit Camera Modal -->
<div class="modal fade" id="editCamModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_camera.php?action=edit" method="POST" class="modal-content">
            <div class="modal-header bg-warning"><h5>แก้ไขข้อมูลกล้อง</h5></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3"><label>ชื่อกล้อง</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="mb-3"><label>URL/IP (ดูภาพ)</label><input type="text" name="ip" id="edit_ip" class="form-control" required></div>
                
                <hr>
                <h6 class="text-primary"><i class="fas fa-gamepad"></i> ตั้งค่า PTZ</h6>
                <div class="mb-3">
                    <label>IP สำหรับ PTZ (ONVIF)</label>
                    <input type="text" name="ptz_ip" id="edit_ptz_ip" class="form-control">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Port ONVIF</label>
                        <input type="number" name="ptz_port" id="edit_ptz_port" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Profile Token</label>
                        <input type="text" name="profile_token" id="edit_profile_token" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Username</label>
                        <input type="text" name="ptz_username" id="edit_ptz_username" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Password</label>
                        <input type="password" name="ptz_password" id="edit_ptz_password" class="form-control" placeholder="เว้นว่างถ้าไม่แก้">
                    </div>
                </div>
                
                <div class="mb-3"><label>สถานที่</label><input type="text" name="location" id="edit_location" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning w-100">บันทึกการแก้ไข</button></div>
        </form>
    </div>
</div>

<!-- Debug Toggle -->
<button class="debug-toggle" onclick="toggleDebug()" title="Debug Panel">
    <i class="fas fa-bug"></i>
</button>

<!-- Debug Panel -->
<div class="debug-panel" id="debugPanel">
    <div class="debug-header">
        <h6><i class="fas fa-bug me-2"></i>PTZ Debug Console</h6>
        <div>
            <button class="btn btn-sm btn-outline-warning me-2" onclick="clearDebug()">
                <i class="fas fa-trash"></i> Clear
            </button>
            <button class="btn btn-sm btn-outline-light" onclick="toggleDebug()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="debug-body" id="debugBody">
        <div class="text-center text-muted py-3">
            <i class="fas fa-info-circle"></i> กดปุ่ม PTZ เพื่อดู log การทำงาน
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const PTZ_PROXY = 'ptz_proxy.php';

// ============================================
// Debug Functions
// ============================================
function toggleDebug() {
    document.getElementById('debugPanel').classList.toggle('show');
}

function clearDebug() {
    document.getElementById('debugBody').innerHTML = `
        <div class="text-center text-muted py-3">
            <i class="fas fa-info-circle"></i> กดปุ่ม PTZ เพื่อดู log การทำงาน
        </div>`;
}

function debugLog(msg, type = 'info', data = null) {
    const body = document.getElementById('debugBody');
    if (body.querySelector('.text-center')) body.innerHTML = '';
    
    const time = new Date().toLocaleTimeString();
    let html = `<div class="debug-entry ${type}">
        <span class="debug-time">[${time}]</span> ${msg}`;
    if (data) {
        html += `<div class="debug-json">${escapeHtml(typeof data === 'string' ? data : JSON.stringify(data, null, 2))}</div>`;
    }
    html += `</div>`;
    body.insertAdjacentHTML('afterbegin', html);
    console.log(`[PTZ ${type.toUpperCase()}]`, msg, data || '');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// Mode Switcher
// ============================================
function switchMode(mode) {
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('mode-' + mode).classList.add('active');
    
    const liveContainer = document.getElementById('liveContainer');
    const playbackContainer = document.getElementById('playbackContainer');
    
    if (mode === 'live') {
        liveContainer.style.display = 'block';
        playbackContainer.classList.remove('show');
        stopPlayback();
    } else {
        liveContainer.style.display = 'none';
        playbackContainer.classList.add('show');
    }
}

// ============================================
// Get camera PTZ config from DOM
// ============================================
function getCamPTZConfig(cameraId) {
    const panel = document.getElementById('ptz-panel-' + cameraId);
    if (!panel) return null;
    return {
        ptz_ip: panel.dataset.ptzIp || '',
        ptz_port: parseInt(panel.dataset.ptzPort) || 80,
        ptz_username: panel.dataset.ptzUser || 'admin',
        ptz_password: panel.dataset.ptzPass || '',
        profile_token: panel.dataset.profileToken || 'Profile_1',
        cam_name: panel.dataset.camName || `Camera #${cameraId}`
    };
}

// ============================================
// PTZ Control
// ============================================
function togglePTZ(cameraId) {
    const panel = document.getElementById('ptz-panel-' + cameraId);
    panel.classList.toggle('show');
    if (panel.classList.contains('show')) {
        checkPTZStatus(cameraId);
    }
}

async function checkPTZStatus(cameraId) {
    const config = getCamPTZConfig(cameraId);
    const statusEl = document.getElementById('ptz-status-' + cameraId);
    
    if (!config.ptz_ip) {
        statusEl.className = 'ptz-status error';
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ไม่ได้ตั้งค่า PTZ IP';
        debugLog(`⚠️ Camera #${cameraId} (${config.cam_name}) - ไม่มี PTZ IP`, 'error');
        return;
    }
    
    statusEl.className = 'ptz-status loading';
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังตรวจสอบ...';
    debugLog(`🔍 Checking PTZ: ${config.ptz_ip}:${config.ptz_port}`, 'info', config);
    
    try {
        const res = await fetch(PTZ_PROXY, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'test',
                ptz_ip: config.ptz_ip,
                ptz_port: config.ptz_port,
                ptz_username: config.ptz_username,
                ptz_password: config.ptz_password
            })
        });
        
        const data = await res.json();
        debugLog(`📡 PTZ Status [${config.ptz_ip}]`, data.ready ? 'success' : 'error', data);
        
        if (data.ready) {
            statusEl.className = 'ptz-status ready';
            const model = data.device?.model || 'Ready';
            const mfr = data.device?.manufacturer || '';
            statusEl.innerHTML = `<i class="fas fa-check-circle"></i> ${mfr} ${model}`.trim();
        } else {
            statusEl.className = 'ptz-status error';
            statusEl.innerHTML = `<i class="fas fa-times-circle"></i> ${data.error || 'Not Ready'}`;
        }
    } catch (err) {
        statusEl.className = 'ptz-status error';
        statusEl.innerHTML = '<i class="fas fa-times-circle"></i> PHP Proxy Error';
        debugLog(`❌ Proxy error: ${err.message}`, 'error');
    }
}

async function ptzMove(cameraId, direction) {
    const config = getCamPTZConfig(cameraId);
    
    if (!config.ptz_ip) {
        alert('กล้องนี้ไม่ได้ตั้งค่า PTZ IP\nกรุณาแก้ไขข้อมูลกล้อง');
        return;
    }
    
    debugLog(`📤 PTZ Move: ${direction} → ${config.ptz_ip}`, 'info', {
        camera: config.cam_name,
        direction,
        ptz_ip: config.ptz_ip
    });
    
    try {
        const res = await fetch(PTZ_PROXY, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'move',
                ptz_ip: config.ptz_ip,
                ptz_port: config.ptz_port,
                ptz_username: config.ptz_username,
                ptz_password: config.ptz_password,
                profile_token: config.profile_token,
                command: direction,
                speed: 0.5
            })
        });
        
        const data = await res.json();
        
        if (data.success) {
            debugLog(`✅ PTZ ${direction} sent`, 'success', data);
        } else {
            debugLog(`❌ PTZ Error: ${data.error}`, 'error', data);
        }
    } catch (err) {
        debugLog(`❌ Request failed: ${err.message}`, 'error');
    }
}

async function ptzStop(cameraId) {
    const config = getCamPTZConfig(cameraId);
    if (!config.ptz_ip) return;
    
    try {
        await fetch(PTZ_PROXY, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'stop',
                ptz_ip: config.ptz_ip,
                ptz_port: config.ptz_port,
                ptz_username: config.ptz_username,
                ptz_password: config.ptz_password,
                profile_token: config.profile_token
            })
        });
    } catch (err) {
        debugLog(`❌ Stop error: ${err.message}`, 'error');
    }
}

// ============================================
// Playback Functions
// ============================================
let playbackJmuxer = null;
let currentSegment = null;
let playbackTimer = null;
let currentPlaybackStream = null;

async function searchRecordings() {
    const cameraId = document.getElementById('playbackCamera').value;
    const date = document.getElementById('playbackDate').value;
    const resultDiv = document.getElementById('searchResult');
    
    resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> กำลังค้นหา...</div>';
    
    try {
        const formData = new FormData();
        formData.append('action', 'search');
        formData.append('camera_id', cameraId);
        formData.append('date', date);
        
        const res = await fetch('playback_proxy.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if (data.success && data.recordings.length > 0) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> พบ ${data.recordings.length} ช่วงวิดีโอ
                </div>
            `;
            renderTimeline(data.recordings, date);
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> ไม่พบวิดีโอในวันที่เลือก
                </div>
            `;
            document.getElementById('timelineBar').innerHTML = `
                <div class="text-center text-muted py-2">ไม่พบวิดีโอ</div>
            `;
        }
    } catch (err) {
        resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
    }
}

function renderTimeline(recordings, date) {
    const bar = document.getElementById('timelineBar');
    bar.innerHTML = '';
    document.getElementById('timelineDate').textContent = date;
    
    const totalMinutes = 24 * 60;
    
    recordings.forEach((rec, idx) => {
        const start = new Date(rec.start);
        const end = new Date(rec.end);
        const startMin = start.getHours() * 60 + start.getMinutes();
        const endMin = end.getHours() * 60 + end.getMinutes();
        
        const leftPercent = (startMin / totalMinutes) * 100;
        const widthPercent = ((endMin - startMin) / totalMinutes) * 100;
        
        const segment = document.createElement('div');
        segment.className = 'timeline-segment';
        segment.style.left = leftPercent + '%';
        segment.style.width = Math.max(widthPercent, 0.5) + '%';
        segment.title = `${start.toLocaleTimeString()} - ${end.toLocaleTimeString()}`;
        segment.onclick = () => selectSegment(rec, idx, segment);
        
        bar.appendChild(segment);
    });
    
    window.currentRecordings = recordings;
}

function selectSegment(recording, idx, element) {
    currentSegment = recording;
    
    // Highlight selected
    document.querySelectorAll('.timeline-segment').forEach(s => s.classList.remove('selected'));
    if (element) element.classList.add('selected');
    
    const start = new Date(recording.start);
    const end = new Date(recording.end);
    
    document.getElementById('playbackTitle').textContent = 
        `${start.toLocaleTimeString()} - ${end.toLocaleTimeString()}`;
    
    document.getElementById('playBtn').disabled = false;
    
    debugLog(`📼 Selected: ${start.toLocaleString()} → ${end.toLocaleString()}`, 'info');
}

async function playSelectedSegment() {
    if (!currentSegment) {
        alert('กรุณาเลือกช่วงเวลาก่อน');
        return;
    }
    
    const cameraId = document.getElementById('playbackCamera').value;
    const cameraSelect = document.getElementById('playbackCamera');
    const cameraName = cameraSelect.options[cameraSelect.selectedIndex].dataset.name;
    const start = currentSegment.start;
    const end = currentSegment.end;
    
    // เปิด player window
    const url = `playback/player.php?camera_id=${cameraId}&camera_name=${encodeURIComponent(cameraName)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    window.open(url, '_blank', 'width=1280,height=800');
    
    debugLog(`▶️ เปิด playback: ${cameraName} (${start} → ${end})`, 'success');
}

function stopPlayback() {
    if (playbackTimer) {
        clearInterval(playbackTimer);
        playbackTimer = null;
    }
    
    // ลบ stream ใน go2rtc
    if (currentPlaybackStream) {
        fetch(`playback_proxy.php?action=delete-stream&stream_name=${currentPlaybackStream}`);
        currentPlaybackStream = null;
    }
    
    if (playbackJmuxer) {
        try { playbackJmuxer.destroy(); } catch(e) {}
        playbackJmuxer = null;
    }
    
    // คืน iframe เป็น canvas
    const videoContainer = document.querySelector('.playback-video');
    if (videoContainer && !document.getElementById('playbackCanvas')) {
        videoContainer.innerHTML = '<canvas id="playbackCanvas"></canvas>';
    }
    
    document.getElementById('stopBtn').disabled = true;
    document.getElementById('playbackOverlay').style.display = 'none';
    document.getElementById('currentTime').textContent = '--:--:--';
    
    debugLog('🛑 Playback stopped', 'info');
}

// ============================================
// Edit Modal
// ============================================
function openEditModal(camData) {
    document.getElementById('edit_id').value = camData.id;
    document.getElementById('edit_name').value = camData.camera_name;
    document.getElementById('edit_ip').value = camData.ip_address;
    document.getElementById('edit_ptz_ip').value = camData.ptz_ip || '';
    document.getElementById('edit_ptz_port').value = camData.ptz_port || 80;
    document.getElementById('edit_profile_token').value = camData.profile_token || 'Profile_1';
    document.getElementById('edit_ptz_username').value = camData.ptz_username || 'admin';
    document.getElementById('edit_ptz_password').value = '';
    document.getElementById('edit_location').value = camData.location || '';
    new bootstrap.Modal(document.getElementById('editCamModal')).show();
}

// ============================================
// Auto Refresh Snapshot
// ============================================
setInterval(function(){
    document.querySelectorAll('img[id^="cam-img-"]').forEach(img => {
        if (img.src.indexOf('ptz_proxy.php?action=snapshot') !== -1) {
            let base = img.src.split(/&t=|&nocache=/)[0];
            img.src = base + '&nocache=' + new Date().getTime();
        }
        else if (img.src.indexOf('http') === 0 && img.src.indexOf('stream.html') === -1 && img.src.indexOf('bmatraffic.com') === -1) {
            let base = img.src.split(/[?&]t=/)[0];
            img.src = base + (base.indexOf('?') > -1 ? '&' : '?') + 't=' + new Date().getTime();
        }
    });
}, 3000);

// ============================================
// ✅ Touch Events Prevention (สำหรับ Android/iOS)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // ป้องกัน double-tap zoom บน PTZ panel
    document.querySelectorAll('.ptz-panel').forEach(panel => {
        let lastTouchEnd = 0;
        panel.addEventListener('touchend', function(e) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, { passive: false });
        
        // ป้องกัน scroll ขณะกดปุ่ม PTZ
        panel.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
    });
    
    // ป้องกัน context menu (กดค้าง) บน PTZ buttons
    document.querySelectorAll('.ptz-btn').forEach(btn => {
        btn.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        
        // เพิ่ม visual feedback สำหรับ touch
        btn.addEventListener('pointerdown', function() {
            this.classList.add('active');
        });
        
        btn.addEventListener('pointerup', function() {
            this.classList.remove('active');
        });
        
        btn.addEventListener('pointerleave', function() {
            this.classList.remove('active');
        });
        
        btn.addEventListener('pointercancel', function() {
            this.classList.remove('active');
        });
    });
});

// ============================================
// Init
// ============================================
window.addEventListener('load', () => {
    debugLog('🎯 cctv.php พร้อมใช้งาน (Direct PHP Mode)', 'success');
    debugLog('📡 ใช้ ptz_proxy.php แทน Node.js server', 'info');
    debugLog('📱 รองรับ Touch Events สำหรับ Android/iOS', 'success');
});
</script>
</body>
</html>