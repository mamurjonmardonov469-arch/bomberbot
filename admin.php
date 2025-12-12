<?php
session_start();

// =================== Fayl yo‘llari ===================
$usersFile = "data/users.txt";
$logsFile = "data/logs.txt";
$channelsFile = "data/channels.txt";
$excludedFile = "data/excluded_numbers.txt";
$sessionDir = "sessions";

// Papkalarni yaratish
if (!file_exists($sessionDir)) mkdir($sessionDir, 0777, true);
if (!file_exists("data")) mkdir("data", 0777, true);

// Fayllarni yaratish
if (!file_exists($usersFile)) file_put_contents($usersFile, "");
if (!file_exists($logsFile)) file_put_contents($logsFile, "");
if (!file_exists($channelsFile)) file_put_contents($channelsFile, "");
if (!file_exists($excludedFile)) file_put_contents($excludedFile, "");

// =================== Admin login ===================
$adminUser = "Mardonov";
$adminPass = "Mardonov055";
$botToken = "8083527676:AAG4tIQ_av4cfo_9bmLZ_do4fSzadW1cgpA";
$telegramUrl = "https://api.telegram.org/bot$botToken/";

// =================== Fayl operatsiyalari uchun funksiyalar ===================

// Users.txt dan ma'lumot o'qish
function getUsers() {
    global $usersFile;
    $users = [];
    if(file_exists($usersFile)) {
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $data = explode("|", $line);
            if(count($data) >= 6) {
                $chatId = $data[0];
                $users[$chatId] = [
                    'vip' => $data[1],
                    'limit' => intval($data[2]),
                    'banned' => $data[3] === 'true',
                    'channels' => !empty($data[4]) ? explode(",", $data[4]) : [],
                    'username' => $data[5],
                    'first_name' => $data[6] ?? '-'
                ];
            }
        }
    }
    return $users;
}

// Users.txt ga ma'lumot yozish
function saveUsers($users) {
    global $usersFile;
    $lines = [];
    foreach($users as $chatId => $user) {
        $channelsStr = implode(",", $user['channels']);
        $lines[] = implode("|", [
            $chatId,
            $user['vip'],
            $user['limit'],
            $user['banned'] ? 'true' : 'false',
            $channelsStr,
            $user['username'],
            $user['first_name']
        ]);
    }
    file_put_contents($usersFile, implode(PHP_EOL, $lines), LOCK_EX);
}

// Logs.txt dan o'qish
function getLogs() {
    global $logsFile;
    $logs = [];
    if(file_exists($logsFile)) {
        $lines = file($logsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $data = explode("|", $line);
            if(count($data) >= 4) {
                $logs[] = [
                    'chat_id' => $data[0],
                    'username' => $data[1],
                    'first_name' => $data[2],
                    'message' => $data[3],
                    'time' => $data[4] ?? date("Y-m-d H:i:s")
                ];
            }
        }
    }
    return $logs;
}

// Logs.txt ga yozish
function addLog($logData) {
    global $logsFile;
    $line = implode("|", [
        $logData['chat_id'],
        $logData['username'] ?? '-',
        $logData['first_name'] ?? '-',
        $logData['message'] ?? '',
        date("Y-m-d H:i:s")
    ]);
    file_put_contents($logsFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Channels.txt dan o'qish
function getChannels() {
    global $channelsFile;
    $channels = [];
    if(file_exists($channelsFile)) {
        $lines = file($channelsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $channels[] = trim($line);
        }
    }
    return $channels;
}

// Channels.txt ga yozish
function saveChannels($channels) {
    global $channelsFile;
    file_put_contents($channelsFile, implode(PHP_EOL, $channels), LOCK_EX);
}

// Excluded numbers dan o'qish
function getExcludedNumbers() {
    global $excludedFile;
    $numbers = [];
    if(file_exists($excludedFile)) {
        $lines = file($excludedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $numbers[] = trim($line);
        }
    }
    return $numbers;
}

// Excluded numbers ga yozish
function saveExcludedNumbers($numbers) {
    global $excludedFile;
    file_put_contents($excludedFile, implode(PHP_EOL, $numbers), LOCK_EX);
}

// SMS loglarini o'qish
function getSmsLogs() {
    $smsLogsFile = "data/sms_logs.txt";
    if(!file_exists($smsLogsFile)) file_put_contents($smsLogsFile, "");
    
    $smsLogs = [];
    if(file_exists($smsLogsFile)) {
        $lines = file($smsLogsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $data = explode("|", $line);
            if(count($data) >= 5) {
                $smsLogs[] = [
                    'chat_id' => $data[0],
                    'username' => $data[1],
                    'first_name' => $data[2],
                    'number' => $data[3],
                    'sms_count' => intval($data[4]),
                    'time' => $data[5] ?? date("Y-m-d H:i:s")
                ];
            }
        }
    }
    return $smsLogs;
}

// Ma'lumotlarni yuklash
$users = getUsers();
$logs = getLogs();
$channels = getChannels();
$excludedNumbers = getExcludedNumbers();

// Login
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['username'], $_POST['password'])) {
        if ($_POST['username'] == $adminUser && $_POST['password'] == $adminPass) {
            $_SESSION['logged_in'] = true;
        } else {
            $error = "Login yoki parol xato!";
        }
    } else {
        echo '<!DOCTYPE html>
        <html lang="uz">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    height: 100vh;
                    display: flex;
                    align-items: center;
                }
                .login-container {
                    max-width: 400px;
                    width: 100%;
                    padding: 30px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                }
            </style>
        </head>
        <body>
            <div class="container d-flex justify-content-center">
                <div class="login-container">
                    <h2 class="text-center mb-4">Admin Login</h2>
                    <form method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Foydalanuvchi nomi</label>
                            <input type="text" name="username" class="form-control" id="username" placeholder="Username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Parol</label>
                            <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Kirish</button>
                    </form>';
        if (isset($error)) echo '<div class="alert alert-danger mt-3">' . $error . '</div>';
        echo '</div></div></body></html>';
        exit;
    }
}

// =================== Admin panel funksiyalari ===================

// Foydalanuvchi qo'shish / yangilash
if (isset($_POST['chat_id'])) {
    $cid = $_POST['chat_id'];
    $vip = $_POST['vip'] ?? 'oddiy';
    $limit = intval($_POST['limit'] ?? 10);
    $banned = isset($_POST['ban']) ? ($_POST['ban'] == '1' ? true : false) : false;
    
    $users[$cid] = [
        'vip' => $vip, 
        'limit' => $limit, 
        'banned' => $banned, 
        'channels' => $users[$cid]['channels'] ?? [],
        'username' => $users[$cid]['username'] ?? '-',
        'first_name' => $users[$cid]['first_name'] ?? '-'
    ];
    
    saveUsers($users);
    header("Location: admin.php");
    exit;
}

// Kanal qo'shish / o'chirish
if (isset($_POST['add_channel'])) {
    if (count($channels) < 5) {
        $channels[] = $_POST['add_channel'];
        saveChannels($channels);
    }
    header("Location: admin.php");
    exit;
}
if (isset($_POST['del_channel'])) {
    $key = array_search($_POST['del_channel'], $channels);
    if ($key !== false) {
        unset($channels[$key]);
        $channels = array_values($channels);
        saveChannels($channels);
    }
    header("Location: admin.php");
    exit;
}

// Istisno raqam qo'shish / o'chirish
if (isset($_POST['add_excluded'])) {
    $num = $_POST['add_excluded'];
    if (!in_array($num, $excludedNumbers)) {
        $excludedNumbers[] = $num;
        saveExcludedNumbers($excludedNumbers);
    }
    header("Location: admin.php");
    exit;
}
if (isset($_POST['del_excluded'])) {
    $key = array_search($_POST['del_excluded'], $excludedNumbers);
    if ($key !== false) {
        unset($excludedNumbers[$key]);
        $excludedNumbers = array_values($excludedNumbers);
        saveExcludedNumbers($excludedNumbers);
    }
    header("Location: admin.php");
    exit;
}

// Barchani unban qilish
if (isset($_POST['unban_all'])) {
    foreach ($users as $cid => &$user) {
        $user['banned'] = false;
    }
    saveUsers($users);
    header("Location: admin.php");
    exit;
}

// Limit qo'shish barchaga
if (isset($_POST['add_limit_all'])) {
    $plus = intval($_POST['plus_limit'] ?? 0);
    foreach ($users as $cid => &$u) {
        $u['limit'] += $plus;
        @file_get_contents($telegramUrl . "sendMessage?chat_id=$cid&text=" . urlencode("Sizning hisobingizga +$plus limit qo'shildi. Yangi limit: " . $u['limit']));
    }
    saveUsers($users);
    header("Location: admin.php");
    exit;
}

// ID orqali 1 odamga limit qo'shish
if (isset($_POST['add_limit_single'])) {
    $cid = $_POST['single_user_id'] ?? '';
    $plus = intval($_POST['single_plus_limit'] ?? 0);
    
    if ($cid && $plus > 0 && isset($users[$cid])) {
        $users[$cid]['limit'] += $plus;
        saveUsers($users);
        
        // Telegramga xabar yuborish
        @file_get_contents($telegramUrl . "sendMessage?chat_id=$cid&text=" . urlencode("Sizning hisobingizga +$plus limit qo'shildi. Yangi limit: " . $users[$cid]['limit']));
        
        $singleLimitSuccess = "Foydalanuvchi ID: $cid ga +$plus limit muvaffaqiyatli qo'shildi!";
    } else {
        $singleLimitError = "Xatolik: Foydalanuvchi topilmadi yoki noto'g'ri ma'lumot kiritildi!";
    }
}

// Limit reset barchaga
if (isset($_POST['reset_limit_all'])) {
    foreach ($users as $cid => &$u) {
        $u['limit'] = 10; // default
        @file_get_contents($telegramUrl . "sendMessage?chat_id=$cid&text=" . urlencode("Sizning limitlaringiz qayta tiklandi (10)."));
    }
    saveUsers($users);
    header("Location: admin.php");
    exit;
}

// Foydalanuvchilarga xabar yuborish
$sentCount = 0;
if (isset($_POST['send_message'])) {
    $msg = $_POST['message'] ?? '';
    $target = $_POST['target'] ?? 'all';
    if ($msg) {
        if ($target == 'all') {
            foreach ($users as $cid => $u) {
                $res = @file_get_contents($telegramUrl . "sendMessage?chat_id=$cid&text=" . urlencode($msg));
                if ($res) {
                    $sentCount++;
                    addLog([
                        'chat_id' => $cid,
                        'username' => $u['username'],
                        'first_name' => $u['first_name'],
                        'message' => $msg
                    ]);
                }
            }
        } else {
            if (isset($users[$target])) {
                $res = @file_get_contents($telegramUrl . "sendMessage?chat_id=$target&text=" . urlencode($msg));
                if ($res) {
                    $sentCount++;
                    addLog([
                        'chat_id' => $target,
                        'username' => $users[$target]['username'],
                        'first_name' => $users[$target]['first_name'],
                        'message' => $msg
                    ]);
                }
            }
        }
    }
}

// Statistika
$totalUsers = count($users);
$bannedUsers = count(array_filter($users, fn($u) => $u['banned']));
$vipUsers = count(array_filter($users, fn($u) => $u['vip'] == "vip" || $u['vip'] == "ultra"));
$totalLogs = count($logs);
$totalChannels = count($channels);
$totalExcluded = count($excludedNumbers);

// Telefon raqamlar statistikasi
$smsLogs = getSmsLogs();
$numberStats = [];
foreach ($smsLogs as $log) {
    $num = $log['number'] ?? '-';
    $count = $log['sms_count'] ?? 0;
    if (!isset($numberStats[$num])) {
        $numberStats[$num] = 0;
    }
    $numberStats[$num] += $count;
}
arsort($numberStats);
$topNumbers = array_slice($numberStats, 0, 10, true);
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .btn-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: white;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background-color: var(--primary);
            color: white;
        }
        
        .badge-vip {
            background: linear-gradient(135deg, #ffd700, #ffa500);
            color: black;
        }
        
        .badge-ultra {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .badge-oddiy {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .toggle-btn {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .toggle-btn:hover {
            transform: scale(1.05);
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
        }
        
        .section-title {
            border-left: 4px solid var(--primary);
            padding-left: 10px;
            margin: 20px 0 15px 0;
        }
        
        .user-search {
            position: relative;
        }
        
        .user-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .user-search-result {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .user-search-result:hover {
            background-color: #f5f5f5;
        }
        
        .user-search-result:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-cogs me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin.php?logout=1"><i class="fas fa-sign-out-alt me-1"></i>Chiqish</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Statistika kartalari -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="card stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= $totalUsers ?></div>
                    <div class="stat-label">Jami Foydalanuvchilar</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card stat-card">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-number"><?= $bannedUsers ?></div>
                    <div class="stat-label">Bloklanganlar</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-number"><?= $vipUsers ?></div>
                    <div class="stat-label">VIP Foydalanuvchilar</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-number"><?= $totalLogs ?></div>
                    <div class="stat-label">Yuborilgan Xabarlar</div>
                </div>
            </div>
        </div>

        <?php if ($sentCount > 0): ?>
        <div class="alert alert-success alert-custom">
            <i class="fas fa-check-circle me-2"></i>Xabar <?= $sentCount ?> ta foydalanuvchiga muvaffaqiyatli yuborildi!
        </div>
        <?php endif; ?>

        <?php if (isset($singleLimitSuccess)): ?>
        <div class="alert alert-success alert-custom">
            <i class="fas fa-check-circle me-2"></i><?= $singleLimitSuccess ?>
        </div>
        <?php endif; ?>

        <?php if (isset($singleLimitError)): ?>
        <div class="alert alert-danger alert-custom">
            <i class="fas fa-exclamation-circle me-2"></i><?= $singleLimitError ?>
        </div>
        <?php endif; ?>

        <!-- Foydalanuvchi qo'shish/yangilash -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus me-2"></i>Foydalanuvchi Qo'shish/Yangilash
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="chat_id" class="form-control" placeholder="Telegram Chat ID" required>
                    </div>
                    <div class="col-md-2">
                        <select name="vip" class="form-select">
                            <option value="oddiy">Oddiy</option>
                            <option value="vip">VIP</option>
                            <option value="ultra">Ultra VIP</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="limit" class="form-control" placeholder="Limit" value="10" required>
                    </div>
                    <div class="col-md-2">
                        <select name="ban" class="form-select">
                            <option value="0">Faol</option>
                            <option value="1">Bloklangan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-custom w-100">
                            <i class="fas fa-save me-1"></i>Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ID orqali 1 odamga limit qo'shish -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus me-2"></i>ID Orqali 1 Odamga Limit Qo'shish
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-4 user-search">
                        <input type="text" name="single_user_id" id="userSearch" class="form-control" placeholder="Foydalanuvchi ID sini kiriting" required>
                        <div class="user-search-results" id="userSearchResults"></div>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="single_plus_limit" class="form-control" placeholder="Qo'shiladigan limit" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="add_limit_single" class="btn btn-custom w-100">
                            <i class="fas fa-plus-circle me-1"></i>Limit Qo'shish
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- Kanallar boshqaruvi -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-broadcast-tower me-2"></i>Kanallar Boshqaruvi (maks: 5)
                    </div>
                    <div class="card-body">
                        <?php if (count($channels) > 0): ?>
                        <div class="mb-3">
                            <ul class="list-group">
                                <?php foreach ($channels as $ch): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= $ch ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="del_channel" value="<?= $ch ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">Hozircha kanallar mavjud emas</p>
                        <?php endif; ?>
                        
                        <form method="post" class="row g-2">
                            <div class="col-8">
                                <input type="text" name="add_channel" class="form-control" placeholder="Yangi kanal username">
                            </div>
                            <div class="col-4">
                                <button type="submit" class="btn btn-custom w-100">
                                    <i class="fas fa-plus me-1"></i>Qo'shish
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Istisno raqamlar -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-ban me-2"></i>Istisno Raqamlar
                    </div>
                    <div class="card-body">
                        <?php if (count($excludedNumbers) > 0): ?>
                        <div class="mb-3">
                            <ul class="list-group">
                                <?php foreach ($excludedNumbers as $num): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= $num ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="del_excluded" value="<?= $num ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">Hozircha istisno raqamlar mavjud emas</p>
                        <?php endif; ?>
                        
                        <form method="post" class="row g-2">
                            <div class="col-8">
                                <input type="text" name="add_excluded" class="form-control" placeholder="Yangi istisno raqam">
                            </div>
                            <div class="col-4">
                                <button type="submit" class="btn btn-custom w-100">
                                    <i class="fas fa-plus me-1"></i>Qo'shish
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Limit boshqaruvi -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tachometer-alt me-2"></i>Limit Boshqaruvi
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <form method="post" class="mb-3">
                            <div class="input-group">
                                <input type="number" name="plus_limit" class="form-control" placeholder="Qo'shiladigan limit" min="1">
                                <button type="submit" name="add_limit_all" class="btn btn-custom">
                                    <i class="fas fa-plus-circle me-1"></i>Barchaga qo'shish
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post">
                            <button type="submit" name="reset_limit_all" class="btn btn-warning w-100">
                                <i class="fas fa-redo me-1"></i>Limitlarni qayta tiklash
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post">
                            <button type="submit" name="unban_all" class="btn btn-success w-100">
                                <i class="fas fa-unlock me-1"></i>Barchani blokdan chiqarish
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Xabar yuborish -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-envelope me-2"></i>Xabar Yuborish
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <textarea name="message" class="form-control" placeholder="Xabar matni" rows="3" required></textarea>
                        </div>
                        <div class="col-md-3">
                            <select name="target" class="form-select mb-2">
                                <option value="all">Barcha foydalanuvchilar</option>
                                <?php foreach ($users as $cid => $u): ?>
                                <option value="<?= $cid ?>">ID: <?= $cid ?> (<?= $u['vip'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="send_message" class="btn btn-custom w-100">
                                <i class="fas fa-paper-plane me-1"></i>Yuborish
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Foydalanuvchilar jadvali -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center toggle-btn" onclick="toggleVisibility('usersTable')">
                <span><i class="fas fa-users me-2"></i>Foydalanuvchilar Ro'yxati</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="card-body" id="usersTable" style="display:none;">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Chat ID</th>
                                <th>Status</th>
                                <th>Limit</th>
                                <th>Holati</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $cid => $u): ?>
                            <tr>
                                <td><?= $cid ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['vip'] ?>">
                                        <?= ucfirst($u['vip']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $u['limit'] ?></span>
                                </td>
                                <td>
                                    <?php if ($u['banned']): ?>
                                    <span class="badge bg-danger">Bloklangan</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">Faol</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SMS loglari -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center toggle-btn" onclick="toggleVisibility('logsTable')">
                <span><i class="fas fa-history me-2"></i>SMS Loglari</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="card-body" id="logsTable" style="display:none;">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Chat ID</th>
                                <th>Xabar</th>
                                <th>Vaqt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($logs, -20) as $log): // Oxirgi 20 ta xabarni ko'rsatish ?>
                            <tr>
                                <td><?= $log['chat_id'] ?></td>
                                <td><?= htmlspecialchars($log['message'] ?? '-') ?></td>
                                <td><?= $log['time'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top raqamlar -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center toggle-btn" onclick="toggleVisibility('topNumbersTable')">
                <span><i class="fas fa-chart-bar me-2"></i>Eng Ko'p SMS Yuborilgan Raqamlar</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="card-body" id="topNumbersTable" style="display:none;">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Telefon Raqam</th>
                                <th>Jami SMS Soni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; ?>
                            <?php foreach ($topNumbers as $num => $count): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($num) ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $count ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleVisibility(id) {
            var e = document.getElementById(id);
            var icon = e.previousElementSibling.querySelector('.fa-chevron-down');
            if (e.style.display === 'none') {
                e.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                e.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }
        
        // Foydalanuvchi qidiruv funksiyasi
        document.getElementById('userSearch').addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var resultsContainer = document.getElementById('userSearchResults');
            var users = <?= json_encode($users) ?>;
            
            if (searchTerm.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            var matches = [];
            for (var id in users) {
                if (id.includes(searchTerm)) {
                    matches.push({id: id, user: users[id]});
                }
            }
            
            if (matches.length > 0) {
                var html = '';
                matches.forEach(function(match) {
                    html += '<div class="user-search-result" onclick="selectUser(\'' + match.id + '\')">';
                    html += 'ID: ' + match.id + ' (' + match.user.vip + ') - Limit: ' + match.user.limit;
                    html += '</div>';
                });
                resultsContainer.innerHTML = html;
                resultsContainer.style.display = 'block';
            } else {
                resultsContainer.style.display = 'none';
            }
        });
        
        function selectUser(userId) {
            document.getElementById('userSearch').value = userId;
            document.getElementById('userSearchResults').style.display = 'none';
        }
        
        // Sahifadan tashqariga bosganda qidiruv natijalarini yopish
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-search')) {
                document.getElementById('userSearchResults').style.display = 'none';
            }
        });
        
        // Chiqish funksiyasi
        <?php if(isset($_GET['logout'])): ?>
            window.location.href = 'admin.php';
            <?php 
                session_destroy();
                exit;
            ?>
        <?php endif; ?>
    </script>
</body>
</html>