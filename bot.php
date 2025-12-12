<?php
// SMS Bomber Bot - Mukammal Birlashtirilgan Versiya
$botToken = "8083527676:AAG4tIQ_av4cfo_9bmLZ_do4fSzadW1cgpA";
$telegramUrl = "https://api.telegram.org/bot$botToken/";
$apiUrl = "https://68f77a7f47cf9.myxvest1.ru/botlarim/Booomber/bomberapi.php?sms="


$TEZAPI_SHOP_ID = ' ';
$TEZAPI_SHOP_KEY = ' ';

// Admin va guruh
$adminChatId = "7524951907";
$groupChatId = "3453091421";
$adminUsername = "Mardonov044";

// Fayl yo'llari
$baseDir = __DIR__;
$dataDir = $baseDir . '/data';
$sessionDir = $baseDir . '/sessions';
$ordersDir = "$sessionDir/orders";

// -------------------------
// INITSIALIZATSIYA FUNKSIYALARI
// -------------------------

// Papka va fayl mavjudligini ta'minlash
function ensureDirectoryExists($dirPath) {
    if(!file_exists($dirPath)) {
        mkdir($dirPath, 0755, true);
    }
    return true;
}

// Fayl mavjudligini ta'minlash
function ensureFileExists($filePath) {
    $dir = dirname($filePath);
    ensureDirectoryExists($dir);
    
    if(!file_exists($filePath)) {
        file_put_contents($filePath, "");
        chmod($filePath, 0644);
    }
    return true;
}

// Sistemani ishga tushirish
function initializeSystem() {
    global $dataDir, $sessionDir, $ordersDir;
    global $usersFile, $logsFile, $channelsFile, $excludedFile;
    
    // Papkalarni yaratish
    ensureDirectoryExists($dataDir);
    ensureDirectoryExists($sessionDir);
    ensureDirectoryExists($ordersDir);
    
    // Asosiy fayllarni yaratish
    ensureFileExists($usersFile);
    ensureFileExists($logsFile);
    ensureFileExists($channelsFile);
    ensureFileExists($excludedFile);
}

// Fayl yo'llari
$usersFile = "$dataDir/users.txt";
$logsFile = "$dataDir/logs.txt";
$channelsFile = "$dataDir/channels.txt";
$excludedFile = "$dataDir/excluded_numbers.txt";

// Sistemani ishga tushirish
initializeSystem();

// -------------------------
// FAYL OPERATSIYALARI UCHUN FUNKSIYALAR
// -------------------------

// Users.txt dan ma'lumot o'qish
function getUsers() {
    global $usersFile;
    $users = [];
    
    ensureFileExists($usersFile);
    
    if(filesize($usersFile) > 0) {
        $content = file_get_contents($usersFile);
        if(!empty(trim($content))) {
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
    }
    return $users;
}

// Users.txt ga ma'lumot yozish
function saveUsers($users) {
    global $usersFile;
    
    ensureFileExists($usersFile);
    
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
    
    // Xavfsiz yozish
    $tempFile = $usersFile . '.tmp';
    file_put_contents($tempFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    rename($tempFile, $usersFile);
}

// Limitni tekshirish va 0 bo'lsa 10 qilish
function checkAndResetLimit($chatId, $users) {
    if(isset($users[$chatId]) && $users[$chatId]['limit'] <= 0) {
        $users[$chatId]['limit'] = 10;
        saveUsers($users);
        return true; // Limit o'zgartirildi
    }
    return false; // Limit o'zgartirilmadi
}

// Logs.txt ga yozish
function addLog($logData) {
    global $logsFile;
    
    ensureFileExists($logsFile);
    
    $line = implode("|", [
        $logData['chat_id'],
        $logData['username'] ?? '-',
        $logData['first_name'] ?? '-',
        $logData['text'] ?? ($logData['number'] ?? ''),
        $logData['sms_count'] ?? '0',
        date("Y-m-d H:i:s")
    ]);
    
    file_put_contents($logsFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Channels.txt dan o'qish
function getChannels() {
    global $channelsFile;
    $channels = [];
    
    ensureFileExists($channelsFile);
    
    if(filesize($channelsFile) > 0) {
        $lines = file($channelsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $channel = trim($line);
            if(!empty($channel)) {
                $channels[] = $channel;
            }
        }
    }
    return $channels;
}

// Excluded numbers dan o'qish
function getExcludedNumbers() {
    global $excludedFile;
    $numbers = [];
    
    ensureFileExists($excludedFile);
    
    if(filesize($excludedFile) > 0) {
        $lines = file($excludedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($lines as $line) {
            $number = trim($line);
            if(!empty($number)) {
                $numbers[] = $number;
            }
        }
    }
    return $numbers;
}

// TezAPI create funksiyasi
function tezapiCreate($amount) {
    global $TEZAPI_SHOP_ID, $TEZAPI_SHOP_KEY;
    $ch = curl_init('https://tezapi.uz/api?method=create');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'shop_id' => $TEZAPI_SHOP_ID,
        'shop_key' => $TEZAPI_SHOP_KEY,
        'amount' => $amount
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if($res === false) return ['ok' => false, 'error' => $err];
    return json_decode($res, true);
}

// TezAPI check funksiyasi
function tezapiCheck($order) {
    $url = 'https://tezapi.uz/api?method=check&order=' . urlencode($order);
    $context = stream_context_create([
        'http' => ['timeout' => 10]
    ]);
    $res = @file_get_contents($url, false, $context);
    if(!$res) return null;
    return json_decode($res, true);
}

// Helper function
function sendRequest($url) {
    $context = stream_context_create([
        'http' => ['timeout' => 5]
    ]);
    @file_get_contents($url, false, $context);
}

// Kanal obuna tekshiruvi
function checkChannels($chatId, $channels, $botToken) {
    if(empty($channels)) return true;
    
    foreach($channels as $ch) {
        $url = "https://api.telegram.org/bot$botToken/getChatMember?chat_id=$ch&user_id=$chatId";
        $context = stream_context_create([
            'http' => ['timeout' => 5]
        ]);
        $res = @file_get_contents($url, false, $context);
        if($res === false) continue;
        
        $resData = json_decode($res, true);
        if(!$resData['ok'] || ($resData['result']['status'] ?? '') == "left") {
            return false;
        }
    }
    return true;
}

function askChannels($chatId, $channels, $telegramUrl) {
    $inlineButtons = [];

    foreach($channels as $ch) {
        $inlineButtons[][] = ['text' => "📌 $ch", 'url' => "https://t.me/" . ltrim($ch, '@')];
    }

    $inlineButtons[][] = ['text' => "✅ Tekshirish", 'callback_data' => "check_channels"];

    $data = json_encode(['inline_keyboard' => $inlineButtons]);
    
    sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("🔔 Iltimos barcha kanallarga obuna bo'ling:") . "&reply_markup=" . $data);
}

// HAR BIR SO'ROVDA KANAL TEKSHIRISH
function checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl) {
    if(empty($channels)) return true;
    
    $joinedAllChannels = checkChannels($chatId, $channels, $botToken);
    if(!$joinedAllChannels) {
        askChannels($chatId, $channels, $telegramUrl);
        return false;
    }
    return true;
}

// Telegram Stars invoice yuborish
function sendStarsInvoice($chatId, $amount, $telegramUrl) {
    // 10 limit = 40 Stars (1 limit = 4 Stars)
    $starsAmount = $amount * 3;
    $description = "⬇️ Botdan $amount limit olasiz (10 limit = 30 Stars)";
    
    $postData = [
        'chat_id' => $chatId,
        'title' => "⭐️ Stars Payment - SMS Bomber",
        'description' => $description,
        'payload' => "buy_limit_$amount",
        'currency' => "XTR",
        'prices' => json_encode([['label' => "⭐️ Stars", 'amount' => $starsAmount]]),
        'start_parameter' => 'start-telegram-stars'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegramUrl . "sendInvoice");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// -------------------------
// ASOSIY KOD BOSHLANISHI
// -------------------------

// Duplicate request protection
$input = file_get_contents("php://input");
if (empty($input)) exit;

$updateHash = md5($input);
$tmpFile = sys_get_temp_dir() . "/tg_update_" . $updateHash;

if (file_exists($tmpFile)) {
    $fileTime = filemtime($tmpFile);
    // 30 soniyadan oldingi fayllarni o'chirish
    if (time() - $fileTime > 30) {
        unlink($tmpFile);
    } else {
        exit; // allaqachon ishlov berilgan
    }
}
file_put_contents($tmpFile, time());

// Ma'lumotlarni yuklash
$users = getUsers();
$channels = getChannels();
$excludedNumbers = getExcludedNumbers();

// Telegram update
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if(!$update) {
    unlink($tmpFile);
    exit;
}

$chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? $update['pre_checkout_query']['from']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');
$callbackData = $update['callback_query']['data'] ?? null;
$preCheckoutQuery = $update['pre_checkout_query'] ?? null;
$successfulPayment = $update['message']['successful_payment'] ?? null;

// ChatId bo'lmasa chiqish
if(!$chatId) {
    unlink($tmpFile);
    exit;
}

// -------------------------
// PRE_CHECKOUT_QUERY HANDLER
// -------------------------
if ($preCheckoutQuery) {
    $preCheckoutQueryId = $preCheckoutQuery['id'];
    
    // Pre-checkout query ni tasdiqlash
    $response = [
        'pre_checkout_query_id' => $preCheckoutQueryId,
        'ok' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegramUrl . "answerPreCheckoutQuery");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
    
    unlink($tmpFile);
    exit;
}

// -------------------------
// SUCCESSFUL PAYMENT HANDLER
// -------------------------
if ($successfulPayment) {
    $invoicePayload = $successfulPayment['invoice_payload'];
    
    // Payload dan limit miqdorini olish
    if (strpos($invoicePayload, 'buy_limit_') === 0) {
        $amount = intval(substr($invoicePayload, 10));
        
        // Limitni qo'shish
        if (isset($users[$chatId])) {
            $users[$chatId]['limit'] += $amount;
            saveUsers($users);
            
            $newLimit = $users[$chatId]['limit'];
            
            // Foydalanuvchiga tasdiqlash xabari
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("🎉 To'lov muvaffaqiyatli amalga oshirildi! Sizga $amount limit qo'shildi. Jami: $newLimit ta"));
            
            // Admin ga xabar
            sendRequest($telegramUrl . "sendMessage?chat_id=$adminChatId&text=" . urlencode("✅ Stars to'lovi tasdiqlandi!\nUser ID: $chatId\nLimit: $amount\nJami: $newLimit"));
        }
    }
    
    unlink($tmpFile);
    exit;
}

// -------------------------
// YANGI FOYDALANUVCHI QO'SHISH
// -------------------------
if($chatId && !isset($users[$chatId])) {
    $users[$chatId] = [
        'vip' => '🟢 Oddiy',
        'limit' => 10,
        'banned' => false,
        'channels' => [],
        'username' => $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? '-',
        'first_name' => $update['message']['from']['first_name'] ?? $update['callback_query']['from']['first_name'] ?? '-'
    ];
    saveUsers($users);
} elseif($chatId) {
    // Username yangilash
    $currentUsername = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? $users[$chatId]['username'];
    $currentFirstName = $update['message']['from']['first_name'] ?? $update['callback_query']['from']['first_name'] ?? $users[$chatId]['first_name'];
    
    if($currentUsername !== $users[$chatId]['username'] || $currentFirstName !== $users[$chatId]['first_name']) {
        $users[$chatId]['username'] = $currentUsername;
        $users[$chatId]['first_name'] = $currentFirstName;
        saveUsers($users);
    }
}

// -------------------------
// LIMITNI TEKSHIRISH VA 0 BO'LSA 10 QILISH
// -------------------------
$limitReset = checkAndResetLimit($chatId, $users);
if($limitReset) {
    // Limit yangilandi, users massivini qayta yuklash
    $users = getUsers();
}

// -------------------------
// BAN TEKSHIRUVI
// -------------------------
if($chatId && $users[$chatId]['banned']) {
    sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("🚫 Siz bu botdan foydalana olmaysiz."));
    unlink($tmpFile);
    exit;
}

// -------------------------
// SESSION FAYLLARINI TEKSHIRISH
// -------------------------
ensureDirectoryExists($sessionDir);

$sessionFile = "$sessionDir/{$chatId}.json";
$session = file_exists($sessionFile) ? json_decode(file_get_contents($sessionFile), true) : [];
$userSessionFile = "$sessionDir/user_{$chatId}.json";
$userSession = file_exists($userSessionFile) ? json_decode(file_get_contents($userSessionFile), true) : [];

// /buy sessiyasi faol bo'lsa, boshqa funksiyalarni bloklash
$buySessionActive = isset($userSession['step']) && $userSession['step'] === 'buy_limit';

// -------------------------
// CALLBACK_QUERY HANDLER
// -------------------------
if(isset($update['callback_query'])) {
    $callbackData = $update['callback_query']['data'];
    $chatId = $update['callback_query']['message']['chat']['id'];
    $messageId = $update['callback_query']['message']['message_id'];

    // CALLBACK DA KANAL TEKSHIRISH
    if(!checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl)) {
        unlink($tmpFile);
        exit;
    }

    if($callbackData === "check_channels") {
        $joinedAllChannels = checkChannels($chatId, $channels, $botToken);
        if($joinedAllChannels) {
            sendRequest($telegramUrl . "deleteMessage?chat_id=$chatId&message_id=$messageId");
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("✅ Barcha kanallarga obuna bo'lgansiz! Endi botdan foydalana olasiz."));
        } else {
            sendRequest($telegramUrl . "answerCallbackQuery?callback_query_id=" . $update['callback_query']['id'] . "&text=" . urlencode("Hali barcha kanallarga obuna bo'lmadingiz!") . "&show_alert=true");
        }
        unlink($tmpFile);
        exit;
    }
    
    // To'lovni tekshirish - AVVAL KANAL TEKSHIRILADI
    if(strpos($callbackData, 'check_') === 0) {
        $order_code = substr($callbackData, 6);
        sendRequest($telegramUrl . "answerCallbackQuery?callback_query_id=" . $update['callback_query']['id'] . "&text=" . urlencode("🔍 To'lov tekshirilmoqda..."));
        
        ensureFileExists("$ordersDir/order_$order_code.json");
        $orderFile = "$ordersDir/order_$order_code.json";
        if(!file_exists($orderFile)) {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ Buyurtma topilmadi! /start"));
            unlink($tmpFile);
            exit;
        }
        
        $orderData = json_decode(file_get_contents($orderFile), true);
        if(!$orderData) {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ Buyurtma o'qib bo'lmadi!"));
            unlink($tmpFile);
            exit;
        }
        
        // Muddat tekshirish
        if(time() > $orderData['expires_at']) {
            $orderData['status'] = 'expired';
            file_put_contents($orderFile, json_encode($orderData), LOCK_EX);
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ To'lov muddati tugagan! Buyurtma bekor qilindi."));
            unlink($tmpFile);
            exit;
        }
        
        $res = tezapiCheck($orderData['order']);
        if(!$res) {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ TezAPI bilan bog'lanib bo'lmadi. Iltimos qayta urinib ko'ring."));
            unlink($tmpFile);
            exit;
        }
        
        if(($res['status'] ?? '') !== 'success') {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ To'lov topilmadi yoki xatolik yuz berdi!"));
            unlink($tmpFile);
            exit;
        }
        
        $data = $res['data'] ?? [];
        $status = $data['status'] ?? '';
        
        if($status === 'paid') {
            // Agar yangi to'lov bo'lsa
            if($orderData['status'] !== 'paid') {
                $users = getUsers();
                if(isset($users[$chatId])) {
                    $users[$chatId]['limit'] += intval($orderData['limit']);
                    saveUsers($users);
                    $orderData['status'] = 'paid';
                    $orderData['paid_at'] = time();
                    file_put_contents($orderFile, json_encode($orderData), LOCK_EX);
                    
                    sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("🎉 To'lov tasdiqlandi! Sizga {$orderData['limit']} limit qo'shildi. Jami: {$users[$chatId]['limit']} ta"));
                    sendRequest($telegramUrl . "sendMessage?chat_id=$adminChatId&text=" . urlencode("✅ To'lov tasdiqlandi!\nUser ID: $chatId\nLimit: {$orderData['limit']}\nOrder: {$orderData['order']}"));
                } else {
                    sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ Foydalanuvchi topilmadi! Admin bilan bog'laning."));
                }
            } else {
                sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("✅ Bu to'lov allaqachon tasdiqlangan."));
            }
        } elseif($status === 'pending') {
            $timeLeft = $orderData['expires_at'] - time();
            $mins = floor($timeLeft / 60);
            $secs = $timeLeft % 60;
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⏳ To'lov hali amalga oshirilmagan. Qolgan vaqt: {$mins} daqiqa {$secs} soniya"));
        } else {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ To'lov holati: $status"));
        }
        unlink($tmpFile);
        exit;
    }
    
    // Karta raqamini nusxalash
    if(strpos($callbackData, 'copy_card_') === 0) {
        $card = '4073 4200 7141 5131';
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("💳 Karta raqami: $card"));
        sendRequest($telegramUrl . "answerCallbackQuery?callback_query_id=" . $update['callback_query']['id'] . "&text=" . urlencode("✅ Karta raqami yuborildi."));
        unlink($tmpFile);
        exit;
    }

    // ID nusxalash
    if($callbackData === 'copy_id') {
        sendRequest($telegramUrl . "answerCallbackQuery?callback_query_id=" . $update['callback_query']['id'] . "&text=" . urlencode("ID nusxalandi!"));
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("🆔 Sizning ID: $chatId"));
        unlink($tmpFile);
        exit;
    }
    
    // TO'LOV USULINI TANLASH CALLBACK
    if($callbackData === 'payment_card') {
        // Bank karti orqali to'lov
        $msg = "💰 *Limit sotib olish*\n\n"
             . "Nechta limit sotib olmoqchisiz?\n\n"
             . "📊 *Minimal:* 10 limit\n"
             . "📈 *Maksimal:* 150 limit\n"
             . "💵 *Narx:* 1 limit = 800 so'm\n\n"
             . "👉 Limit sonini kiriting:";
        
        sendRequest($telegramUrl . "editMessageText?chat_id=$chatId&message_id=$messageId&text=" . urlencode($msg) . "&parse_mode=Markdown");
        file_put_contents("$sessionDir/user_{$chatId}.json", json_encode(['step' => 'buy_limit', 'payment_method' => 'card']), LOCK_EX);
        unlink($tmpFile);
        exit;
    }
    
    if($callbackData === 'payment_stars') {
        // Telegram Stars orqali to'lov
        $msg = "⭐️ *Telegram Stars orqali to'lov*\n\n"
             . "Nechta limit sotib olmoqchisiz?\n\n"
             . "📊 *Minimal:* 10 limit\n"
             . "📈 *Maksimal:* 150 limit\n"
             . "💫 *Narx:* 10 limit = 30 Stars\n"
             . "💰 *Hisob:* 1 limit = 3 Stars\n\n"
             . "👉 Limit sonini kiriting:";
        
        sendRequest($telegramUrl . "editMessageText?chat_id=$chatId&message_id=$messageId&text=" . urlencode($msg) . "&parse_mode=Markdown");
        file_put_contents("$sessionDir/user_{$chatId}.json", json_encode(['step' => 'buy_limit', 'payment_method' => 'stars']), LOCK_EX);
        unlink($tmpFile);
        exit;
    }
}

// -------------------------
// /start – raqam so'rash (faqat buy sessiyasi faol bo'lmaganda)
// -------------------------
if($text === '/start' && !$buySessionActive) {
    // Kanal tekshiruvi
    if(!checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl)) {
        unlink($tmpFile);
        exit;
    }
    
    $sessionFile = "$sessionDir/{$chatId}.json";
    $session = ['step' => 'number'];
    file_put_contents($sessionFile, json_encode($session), LOCK_EX);
    
    $userLimit = $users[$chatId]['limit'];
    sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("👋 Salom! Iltimos, 998 bilan boshlanuvchi raqam kiriting:\n\n📊 Sizda $userLimit ta limit mavjud"));
    unlink($tmpFile);
    exit;
}

// -------------------------  
// /me – foydalanuvchi ma'lumotlari (Motivatsion premium dizayn)  
// -------------------------  
if ($text === '/me') {  
    $user = $users[$chatId];  
    $status = $user['vip'];  
    $limit = $user['limit'];  
    $firstName = $user['first_name'];  
    $username = $user['username'] ? '@' . $user['username'] : 'yo\'q';  
    $id = $chatId;  
  
    // Status belgisi  
    $statusEmoji = ($status === 'vip') ? "💎 VIP foydalanuvchi" : "👤 Oddiy foydalanuvchi";  
  
    // Admin ma'lumotlari  
    $admin = 'arc_gg';  
    $autoText = urlencode("Assalomu alaykum! 😊\nMen VIP obuna sotib olmoqchiman.\nIltimos, to'lov va ulanish haqida ma'lumot bering 💎");  
    $adminUrl = "tg://resolve?domain={$admin}&text={$autoText}";  
  
    // Chiroyli xabar (motivatsion uslubda)  
    $msg  = "🚀 <b>Profilingiz — muvaffaqiyat sari!</b>\n";
    $msg .= "━━━━━━━━━━━━━━━\n";
    $msg .= "👤 <b>Ism:</b> <b>$firstName</b>\n";
    $msg .= "🆔 <b>ID:</b> <code>$id</code>\n";
    $msg .= "🏆 <b>Status:</b> <b>$statusEmoji</b>\n";
    $msg .= "📊 <b>Limit:</b> <b>$limit ta</b>\n";
    $msg .= "━━━━━━━━━━━━━━━\n\n";

    if ($status !== 'vip') {
        $msg .= "🔥 <b>Harakatni to'xtatmang!</b>\n";
        $msg .= "💎 <b>VIP bo'lib, yangi cho'qqilarni zabt eting!</b>\n\n";
        $msg .= "📘 <b>Qo'llanma:</b> <i>Qo'llanma tugmasida</i> 👇";
    } else {
        $msg .= "🏅 <b>Siz allaqachon VIP darajadasiz!</b>\n";
        $msg .= "🧠 <b>Endi siz uchun chegaralar yo'q!</b>";
    }

    // Inline tugmalar (ID nusxalash, Admin va Docs Web App)  
    $keyboard = [  
        'inline_keyboard' => [  
            [  
                ['text' => "ID'ni nusxalash", 'copy_text' => ['text' => "$id"]],  
            ],  
            [  
                ['text' => "📩 Admin bilan bog'lanish", 'url' => $adminUrl]  
            ],  
            [  
                ['text' => "📘 Qo'llanma", 'web_app' => ['url' => "https://xspin.3d.tc"]]  
            ]  
        ]  
    ];  
  
    // Xabarni yuborish  
    $postData = [  
        'chat_id' => $chatId,  
        'text' => $msg,  
        'parse_mode' => 'HTML',  
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)  
    ];  
  
    $ch = curl_init();  
    curl_setopt($ch, CURLOPT_URL, $telegramUrl . "sendMessage");  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
    curl_setopt($ch, CURLOPT_POST, true);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);  
    curl_exec($ch);  
    curl_close($ch);  
      
    exit;  
}

// -------------------------
// /buy – limit sotib olish (har doim ishlaydi)
// -------------------------
if($text === '/buy') {
    // Kanal tekshiruvi
    if(!checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl)) {
        unlink($tmpFile);
        exit;
    }
    
    // To'lov usulini tanlash
    $msg = "💰 *Limit sotib olish*\n\n"
         . "Quyidagi to'lov usullaridan birini tanlang:\n\n"
         . "💳 *Bank card* - O'zbekiston bank kartalari\n"
         . "⭐️ *Telegram Stars* - Telegram ichidagi to'lov\n\n"
         . "Narx: 1 limit = 800 so'm\n"
         . "Stars: 10 limit = 30 Stars\n"
         . "Minimal: 10 limit\n"
         . "Maksimal: 150 limit";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '💳 card [avto]', 'callback_data' => 'payment_card'],
                ['text' => '⭐️ Stars [avto]', 'callback_data' => 'payment_stars']
            ]
        ]
    ];
    
    $postData = [
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegramUrl . "sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_exec($ch);
    curl_close($ch);
    
    unlink($tmpFile);
    exit;
}

// -------------------------
// Raqam qabul qilish (faqat buy sessiyasi faol bo'lmaganda)
// -------------------------
if(isset($session['step']) && $session['step'] == 'number' && !$buySessionActive) {
    // Kanal tekshiruvi
    if(!checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl)) {
        unlink($tmpFile);
        exit;
    }
    
    if(preg_match('/^998\d{9}$/', $text)) {
        $session['number'] = $text;

        // Istisno raqam tekshiruvi
        if(in_array($text, $excludedNumbers)) {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ Ushbu raqam ga, SMS yuborib bo'lmaydi."));
            unlink($sessionFile);
            unlink($tmpFile);
            exit;
        }

        $session['step'] = 'count';
        file_put_contents($sessionFile, json_encode($session), LOCK_EX);
        
        $user = $users[$chatId];
        $maxLimit = $user['limit'];
        $minLimit = 5;
        
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("✉️ Necha SMS yuborilsin? Maksimal $maxLimit ta (Minimal $minLimit ta):"));
    } else {
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ Iltimos 998 bilan boshlanuvchi raqam kiriting:"));
    }
    unlink($tmpFile);
    exit;
}

// -------------------------
// SMS soni qabul qilish (faqat buy sessiyasi faol bo'lmaganda)
// -------------------------
if(isset($session['step']) && $session['step'] == 'count' && !$buySessionActive) {
    // Kanal tekshiruvi
    if(!checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl)) {
        unlink($tmpFile);
        exit;
    }
    
    if(is_numeric($text) && $text > 0) {
        $requested = intval($text);
        $user = $users[$chatId];
        $maxLimit = $user['limit'];
        $minLimit = 5;

        if($requested < $minLimit) {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ Minimal SMS soni $minLimit ta."));
            unlink($tmpFile);
            exit;
        }

        if($requested > $maxLimit) {
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ Siz maksimal $maxLimit ta SMS yuborishingiz mumkin."));
            unlink($tmpFile);
            exit;
        }

        $toSend = $requested;
        $number = $session['number'];
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⏳ SMSlar yuborilmoqda, iltimos kuting...\n@MUHECTUP xar qanday bot yasab beraman"));

        $sent = 0;
        $batchSize = 5;
        while($sent < $toSend) {
            $curBatch = min($batchSize, $toSend - $sent);
            for($i = 0; $i < $curBatch; $i++) {
                @file_get_contents($apiUrl . $number);
                $sent++;
            }
            // Kichik tanaffus - server yukini kamaytirish
            usleep(200000); // 200ms
        }

        // LIMIT KAMAYTIRILMAYDI - FAQAT LOG YOZILADI

        // Logga yozish
        addLog([
            'chat_id' => $chatId,
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'number' => $number,
            'sms_count' => $sent
        ]);

        // FAQAT GRUHGA XABAR YUBORISH
        $username = $user['username'] != '-' ? $user['username'] : 'Yo\'q';
        $firstName = $user['first_name'];
        
        $groupMsg = "📩 Yangi SMS buyurtma:\n";
        $groupMsg .= "👤 Foydalanuvchi: $firstName\n";
        $groupMsg .= "📱 Username: $username\n";
        $groupMsg .= "🆔 Chat ID: $chatId\n";
        $groupMsg .= "📞 Raqam: $number\n";
        $groupMsg .= "✉️ SMS soni: $sent\n";
        $groupMsg .= "📊 Limit: {$user['limit']} ta (kamaymaydi)";

        // Inline tugma
        $inlineBtn = json_encode([
            'inline_keyboard' => [
                [['text' => "👤 Foydalanuvchi", 'url' => "tg://user?id=$chatId"]]
            ]
        ]);

        // GRUHGA YUBORISH
        sendRequest($telegramUrl . "sendMessage?chat_id=$groupChatId&text=" . urlencode($groupMsg) . "&reply_markup=$inlineBtn");

        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("✅ Yuborildi: $sent ta SMS\n📊 Limit: {$user['limit']} ta (kamaymaydi)"));
        
        // Sessionni tozalash
        if(file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    } else {
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ Iltimos, butun son kiriting:"));
    }
    unlink($tmpFile);
    exit;
}

// -------------------------
// Limit sotib olish jarayoni (buy sessiyasi faol bo'lganda)
// -------------------------
if($buySessionActive && is_numeric($text)) {
    // Kanal tekshiruvi
    if(!checkChannelsAndAsk($chatId, $channels, $botToken, $telegramUrl)) {
        unlink($tmpFile);
        exit;
    }
    
    $buyLimit = intval($text);
    
    if($buyLimit < 10) {
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode('❌ Minimal 10 limit sotib olishingiz mumkin!'));
        unlink($userSessionFile);
        unlink($tmpFile);
        exit;
    }
    
    if($buyLimit > 150) {
        sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode('❌ Maksimal 150 limit sotib olishingiz mumkin!'));
        unlink($userSessionFile);
        unlink($tmpFile);
        exit;
    }

    // To'lov usuliga qarab harakat
    $paymentMethod = $userSession['payment_method'] ?? 'card';
    
    if($paymentMethod === 'stars') {
        // Telegram Stars orqali to'lov
        $result = sendStarsInvoice($chatId, $buyLimit, $telegramUrl);
        
        // Sessionni tozalash
        if(file_exists($userSessionFile)) {
            unlink($userSessionFile);
        }
        
    } else {
        // Bank karti orqali to'lov
        $price = $buyLimit * 800; // 1 limit = 800 so'm

        // To'lovlarni tekshirish va takrorlanmas qiymat yaratish
        $existing = glob($ordersDir . '/order_*.json');
        $used = [];
        foreach($existing as $f) {
            $d = json_decode(file_get_contents($f), true);
            if($d && isset($d['price'])) $used[] = intval($d['price']);
        }
        while(in_array($price, $used)) $price++;

        $res = tezapiCreate($price);
        if(!$res || ($res['status'] ?? '') !== 'success') {
            $err = $res['message'] ?? ($res['error'] ?? 'Noma\'lum xatolik');
            sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("❌ To'lov yaratishda xatolik: $err\n\nIltimos, qayta urinib ko'ring /buy"));
            unlink($userSessionFile);
            unlink($tmpFile);
            exit;
        }

        $order_code = $res['order'];
        $insert_id = $res['insert_id'] ?? $order_code;
        
        $orderData = [
            'chat_id' => $chatId,
            'limit' => $buyLimit,
            'original_price' => $buyLimit * 800,
            'price' => $price,
            'order' => $order_code,
            'insert_id' => $insert_id,
            'status' => 'pending',
            'created_at' => time(),
            'expires_at' => time() + 300 // 5 daqiqa
        ];
        
        ensureFileExists("$ordersDir/order_$order_code.json");
        $orderFile = $ordersDir . "/order_$order_code.json";
        file_put_contents($orderFile, json_encode($orderData), LOCK_EX);
        
        // Sessionni tozalash
        if(file_exists($userSessionFile)) {
            unlink($userSessionFile);
        }

        $card_number = '4073 4200 7141 5131';
        $formatted_price = number_format($price, 0, ',', ' ');
        
        $msg = "💳 *To'lov ma'lumotlari*\n\n";
        $msg .= "📦 *Limit:* $buyLimit ta\n";
        $msg .= "💵 *To'lov miqdori:* " . $formatted_price . " so'm\n";
        $msg .= "🆔 *Buyurtma raqami:* `$insert_id`\n";
        $msg .= "⏰ *Muddat:* 5 daqiqa\n\n";
        $msg .= "💳 *Karta raqam:* `$card_number`\n\n";
        $msg .= "To'lov qilganingizdan so'ng \"♻️ To'lovni tekshirish\" tugmasini bosing.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $card_number, 'copy_text' => ['text' => $card_number]]
                ],
                [
                    ['text' => '♻️ To\'lovni tekshirish', 'callback_data' => 'check_' . $order_code]
                ]
            ]
        ];

        $postData = [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegramUrl . "sendMessage");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_exec($ch);
        curl_close($ch);
    }
    
    unlink($tmpFile);
    exit;
}

// -------------------------
// Buy sessiyasi faol bo'lganda boshqa xabarlarni bloklash
// -------------------------
if($buySessionActive && $text && !in_array($text, ['/start', '/me', '/buy'])) {
    sendRequest($telegramUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("⚠️ Iltimos, limit sotib olish jarayonini yakunlang yoki /buy ni qayta boshlang."));
    unlink($tmpFile);
    exit;
}

// -------------------------
// Foydalanuvchi xabarlarini loglash (faqat buy sessiyasi faol bo'lmaganda)
// -------------------------
if($text && $chatId && !in_array($text, ['/start', '/me', '/buy']) && !$buySessionActive) {
    addLog([
        'chat_id' => $chatId,
        'username' => $users[$chatId]['username'],
        'first_name' => $users[$chatId]['first_name'],
        'text' => $text
    ]);
}

// -------------------------
// Muddati o'tgan buyurtmalarni tozalash
// -------------------------
$files = glob($ordersDir . '/order_*.json');
foreach($files as $f) {
    $od = json_decode(file_get_contents($f), true);
    if(!$od) continue;
    if($od['status'] === 'pending' && time() > ($od['expires_at'] ?? 0)) {
        $od['status'] = 'expired';
        file_put_contents($f, json_encode($od), LOCK_EX);
        // Foydalanuvchiga xabar berish (bir marta)
        $lastNot = "$sessionDir/last_expired_{$od['chat_id']}.json";
        $prev = file_exists($lastNot) ? json_decode(file_get_contents($lastNot), true) : null;
        if(!$prev || ($prev['order'] ?? '') !== ($od['order'] ?? '')) {
            sendRequest($telegramUrl . "sendMessage?chat_id={$od['chat_id']}&text=" . urlencode("❌ To'lov muddati tugadi! Buyurtma: {$od['insert_id']}\nLimit: {$od['limit']} ta"));
            file_put_contents($lastNot, json_encode(['order' => $od['order'], 'notified_at' => time()]), LOCK_EX);
        }
    }
}

// Oxirida - barcha o'zgarishlarni saqlaymiz
saveUsers($users);

// Vaqtinchalik faylni o'chirish
if(file_exists($tmpFile)) {
    unlink($tmpFile);
}

?>