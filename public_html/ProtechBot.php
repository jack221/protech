<?php
/**
 * ProtechBot - Telegram Bot for ProTech Software
 * Version: 1.1.0 | Last Updated: 2026-02-20
 */

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// --- CONFIG ---
$botToken = "8597731016:AAHUmGGfjDSyiJ2M3n_8odvd4Ph6hmDJ1LU";
$website  = "https://api.telegram.org/bot" . $botToken;
$adminId  = 463538817;
$GLOBALS["website"] = $website;

// --- PARSE UPDATE ---
$update      = json_decode(file_get_contents("php://input"), true);
$message     = $update["message"] ?? $update["callback_query"]["message"] ?? null;
$chatId      = $message["chat"]["id"] ?? null;
$messageId   = $message["message_id"] ?? null;
$messageText = isset($message['text']) && is_string($message['text']) ? trim($message['text']) : '';
// إزالة @BotUsername من الأوامر (سلوك تيليغرام بالمجموعات)
$messageText = preg_replace('/@\w+/', '', $messageText);

if (!$chatId || !$message) { http_response_code(200); exit(); }
$GLOBALS["messageid"] = $messageId;

// --- HELPERS ---
function sendPTMessage($chatId, $text, $replyMarkup = null, $parseMode = null) {
    $fields = [
        'chat_id'                     => $chatId,
        'text'                        => $text,
        'reply_to_message_id'         => $GLOBALS["messageid"],
        'allow_sending_without_reply' => true,
    ];
    if ($parseMode)   $fields['parse_mode']  = $parseMode;
    if ($replyMarkup) $fields['reply_markup'] = $replyMarkup;
    $ch = curl_init($GLOBALS["website"] . "/sendMessage");
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch); curl_close($ch);
}

function sendMessage($chatId, $message) {
    $fields = [
        'chat_id'                     => $chatId,
        'text'                        => $message,
        'reply_to_message_id'         => $GLOBALS["messageid"],
        'allow_sending_without_reply' => true,
    ];
    $ch = curl_init($GLOBALS["website"] . "/sendMessage");
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch); curl_close($ch);
}

function deleteMessage($chatId, $messageid) {
    $url = $GLOBALS["website"] . "/deleteMessage?chat_id=" . $chatId . "&message_id=" . $messageid;
    file_get_contents($url, false, stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]));
}

// --- API FUNCTIONS ---
function publishLogsToBlog(array $options = []) {
    $payload = [
        'key'       => 'Jack_2026_LogsSecret',
        'date_from' => $options['date_from'] ?? '2026-02-07',
        'limit'     => $options['limit']     ?? 5000,
        'chunk'     => $options['chunk']     ?? 100,
        'author'    => $options['author']    ?? 1,
    ];
    if (!empty($options['categories'])) $payload['categories[]'] = $options['categories'];
    if (!empty($options['tags']))       $payload['tags[]']        = $options['tags'];

    $ch = curl_init('https://protech.software/protech-logs-publish.php');
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true]);
    $response = curl_exec($ch);
    $result   = ['http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'curl_error' => curl_error($ch), 'raw_response' => $response];
    curl_close($ch);
    return $result;
}

function addFirmwareViaApi(array $data) {
    $payload = [
        'brand'        => 'XIAOMI',
        'device_name'  => $data['device_name'] ?? ($data['device'] ?? ''),
        'codename'     => strtolower($data['pattern'] ?? ''),
        'branch'       => $data['branch']   ?? 'Global',
        'type'         => $data['type']     ?? 'Fastboot',
        'version'      => $data['version']  ?? '',
        'android'      => $data['android']  ?? '',
        'download_url' => $data['download'] ?? '',
        'date'         => $data['date']     ?? '',
    ];
    if (!empty($data['size']) && preg_match('/([\d\.]+)\s*([GMK]B)/i', $data['size'], $m)) {
        $num  = (float)$m[1]; $unit = strtoupper($m[2]);
        if ($unit === 'GB')     $payload['size_bytes'] = (int)($num * 1073741824);
        elseif ($unit === 'MB') $payload['size_bytes'] = (int)($num * 1048576);
    }
    $ch = curl_init('https://support.protech.software/custom-api/add_firmware.php');
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE), CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'], CURLOPT_RETURNTRANSFER => true]);
    $response = curl_exec($ch);
    $result   = ['http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'curl_error' => curl_error($ch), 'raw_response' => $response, 'json' => json_decode($response, true)];
    curl_close($ch);
    return $result;
}

// --- ADMIN COMMANDS ---
if ($chatId == $adminId) {

    if (strpos($messageText, '/update ') === 0) {
        $pattern = trim(str_replace('/update ', '', $messageText));
        if (!$pattern) { sendMessage($chatId, "استخدم الأمر هكذا:\n/update GARNET"); exit; }

        $script = '/home/protechs/home5/protechs/home/protechs/support.protech.software/custom-api/miui_fetch_fastboot.py';
        $data   = json_decode(shell_exec('python3 ' . escapeshellarg($script) . ' ' . escapeshellarg($pattern) . ' 2>&1'), true);

        if (!is_array($data) || !empty($data['error'])) {
            $msg = "❌ فشل الجلب للموديل: $pattern";
            if (($data['error'] ?? '') === 'NO_ROM_FOUND') $msg .= "\nلم يتم العثور على أي روم لهذا الموديل.";
            sendMessage($chatId, $msg); exit;
        }

        $multiModels = ['emerald' => 'Redmi Note 13 Pro 4G / POCO M6 Pro / Redmi Note 14S'];
        $reply = "✅ تم تحديث الفلاشات للموديل: $pattern\n\n";

        foreach ($data as $rom) {
            $rom['pattern'] = $pattern;
            if (isset($multiModels[strtolower($pattern)])) $rom['device'] = $multiModels[strtolower($pattern)];

            $res     = addFirmwareViaApi($rom);
            $jsonRes = $res['json'] ?? null;
            $state   = 'فشل الإضافة';

            if ($jsonRes && ($jsonRes['status'] ?? '') === 'success') {
                $state = 'موجود مسبقاً';
                if (isset($jsonRes['note']) && $jsonRes['note'] !== 'already_exists') {
                    $state = 'ملف جديد';
                } elseif (isset($jsonRes['results'])) {
                    foreach ($jsonRes['results'] as $r) {
                        if (($r['note'] ?? '') === 'created') { $state = 'ملف جديد'; break; }
                    }
                }
            }
            $reply .= "📱 موديل: " . ($rom['device'] ?? 'Unknown') . "\n";
            $reply .= "💿 النوع: " . ($rom['type']   ?? '') . "\n";
            $reply .= "🌍 الفرع: " . ($rom['branch'] ?? '') . "\n";
            $reply .= "📅 تاريخ الفلاشة: " . ($rom['date'] ?? '') . "\n";
            $reply .= "🔖 الحالة: $state\n\n";
        }
        sendMessage($chatId, trim($reply)); exit;
    }

    if ($messageText === '/publish_logs') {
        sendMessage($chatId, "🚀 جاري تنفيذ نشر اللوج...");
        $result = publishLogsToBlog(['limit' => 200]);
        if (!is_array($result) || empty($result['raw_response'])) { sendMessage($chatId, "❌ حدث خطأ أثناء النشر."); exit; }
        sendMessage($chatId, "📤 نتيجة نشر اللوج:\n--------------------------\n" . $result['raw_response']); exit;
    }
}

// --- GENERAL COMMANDS ---
$command = explode('@', $messageText)[0];

switch ($command) {
    case '/start':
        sendMessage($chatId,
            "أهلاً بك في بوت ProTech 👋\n\n" .
            "🤖 هذا البوت يساعدك في العثور على ملفات الروم والـ Firmware، وخدمات السيرفر وزيارة موقعنا.\n\n" .
            "🔹 /howtouse - طريقة استخدام البوت\n" .
            "🔹 /files - أقسام تحميل الملفات\n" .
            "🔹 /support - للتواصل مع الدعم الفني\n" .
            "🔹 /blog - زيارة موقعنا الرسمي\n" .
            "🔹 /server - خدمات السيرفر\n" .
            "🔹 /version - عرض إصدار البوت\n\n" .
            "📝 ملاحظة: يمكنك إرسال *رقم الموديل* مباشرة (مثال: /A505F) للبحث."
        ); exit();

    case '/version': case '/v':
        sendPTMessage($chatId,
            "🤖 *معلومات إصدار البوت*\n--------------------------\n" .
            "📌 *الإصدار الحالي:* v1.1.0\n" .
            "📅 *تاريخ التحديث:* 05-02-2026\n\n" .
            "🆕 *الجديد في هذا التحديث:*\n" .
            "• ✅ تحسين سرعة الرد.\n• ✅ إضافة قائمة الأوامر الثابتة.\n" .
            "• ✅ تحديث واجهة المساعدة /howtouse.\n• 🔧 إصلاحات عامة وتحسين الأداء.\n\n" .
            "💻 *تطوير:* فريق ProTech Software",
        null, 'Markdown'); exit();

    case '/howtouse':
        sendPTMessage($chatId,
            "📖 *دليل استخدام بوت ProTech:*\n\n" .
            "للحصول على ملفات أي جهاز، ببساطة أرسل:\n*( / ) + رقم الطراز*\n\n" .
            "✅ *مثال عملي:*\naكتب: `/J500F`\n\n" .
            "🚀 وسيقوم البوت فوراً بجلب كافة الروابط والملفات المتوفرة لهذا الموديل.\n\n" .
            "🌹 *نتمنى لكم تجربة موفقة!*",
        null, 'Markdown'); exit();

    case '/files':
        sendPTMessage($chatId, "🗂 اختر قسم الملفات من الأزرار التالية:", json_encode(["inline_keyboard" => [
            [["text" => "📥 ملفات SAMSUNG",                  "url" => "https://support.protech.software/index.php?a=downloads&b=folder&id=3148"]],
            [["text" => "📥 ملفات XIAOMI",                   "url" => "https://support.protech.software/index.php?a=downloads&b=folder&id=16741"]],
            [["text" => "📥 ملفات HUAWEI & HONOR",           "url" => "https://support.protech.software/index.php?a=downloads&b=folder&id=24447"]],
            [["text" => "📥 ملفات Firmware لكل الموبايلات", "url" => "https://support.protech.software/index.php?a=downloads&b=folder&id=1"]],
            [["text" => "🛠 ملفات REPAIR IMEI",              "url" => "https://support.protech.software/index.php?a=downloads&b=folder&id=4429"]],
        ]])); exit();

    case '/support':
        sendMessage($chatId, "🛠 *السبورت - Support ProTech* 🛠\n\nيمكنك زيارة قسم الدعم والملفات عبر الرابط التالي:\n🔗 https://support.protech.software"); exit();

    case '/blog':
        sendMessage($chatId, "🌐 *موقع برو تك سوفتوير الرسمي*\n\nتصفح أحدث المقالات، الملفات، والشروحات الحصرية:\n🔗 https://protech.software"); exit();

    case '/server':
        sendPTMessage($chatId,
            "🖥 *خدمات السيرفر - ProTech Server* 🖥\n\n" .
            "نقدّم لك خدمات السوفت وير أونلاين، منها:\n" .
            "• تفعيل الأدوات والبوكسات\n• تخطي حساب Mi وإصلاح مشاكله\n" .
            "• خدمات iCloud لأجهزة أبل\n• إزالة FRP لأغلب الموديلات\n\n" .
            "📞 للتواصل عبر الواتساب:\nhttps://wa.me/9053789456789\n\n" .
            "📧 للتواصل عبر البريد الإلكتروني:\nsupport@protech.software",
        null, 'Markdown'); exit();
}

// --- KEYWORD REPLIES ---
$keywordReplies = [
    "السلام عليكم" => "وعليكم السلام ورحمة الله وبركاته 👋",
    "سلام عليكم"  => "وعليكم السلام ورحمة الله وبركاته 👋",
    "مرحبا"        => "أهلاً وسهلاً 😊",
    "هلا"          => "هلا فيك نورت 🌟",
    "مساء الخير"  => "مساء النور 🌹",
    "صباح الخير"  => "صباح النور ☺",
    "كيفك"         => "تمام الحمد لله، إنت كيفك؟ 🙂",
    "شكرا"         => "العفو !! 😊",
    "يسلمو"        => "اللــه يسلمك أخوي 😎",
    "صلي ع النبي" => "اللهم صلِّ وسلّم وبارك على سيدنا محمد ﷺ",
    "يعطيك العافيه" => "الله يعافيك 😍",
    "مشكلة FRP"   => "اضغط هنا وسوف تشاهد جميع الخطوات لحل مشكلة FRP http://protech.software/?cat=58",
    "تعاريف"       => "لتحميل التعريف الذي تريد اضغط على الرابط التالي http://support.protech.software/index.php?a=browse&b=category&id=480",
    "مخططات"       => "لتحميل اي مخطط اضغط هنا https://support.protech.software/index.php?a=downloads&b=folder&id=11601",
    "كراك"         => "لتحميل الكراك الذي تريد اضغط هنا http://support.protech.software/index.php?a=browse&b=category&id=2130",
];
foreach ($keywordReplies as $kw => $rep) {
    if (stristr($messageText, $kw) !== false) { sendMessage($chatId, $rep); exit(); }
}

// --- UNLOCK / CERT LOOKUP ---
$unlockPatterns = [
    "N910T" => "O2730", "N910U" => "O2730", "N915F" => "O2745", "N920C" => "O2760",
    "J120F" => "K360",  "J320F" => "K960",  "J530F" => "K1590", "J701F" => "K2103",
    "G950F" => "I2850", "G960F" => "I2880", "G965F" => "I2895",
    "ANE-LX1 8.0.0(C185)" => "67781walytech005",
    "STK-LX1_9.1.0(C185)" => "19931115walytech19991024",
];
$certCodes = [
    "J500H" => "33 😍", "I9301I" => "48 😍", "A800F" => "Y 😍", "E500H" => "BB 😍",
    "G900H" => "DD 😍", "N910C" => "675",     "J500F" => "3604", "G530H" => "166",
];

function handleKeywordRequest($text, $prefix, $map, $chatId) {
    if (strpos($text, $prefix) !== 0) return false;
    $model = trim(str_replace($prefix, '', $text));
    foreach ($map as $key => $code) {
        if (strcasecmp($model, $key) === 0) { sendMessage($chatId, $code); return true; }
    }
    return false;
}

if (handleKeywordRequest($messageText, 'فك نمط ',    $unlockPatterns, $chatId)) exit();
if (handleKeywordRequest($messageText, 'unlock screen ', $unlockPatterns, $chatId)) exit();
if (handleKeywordRequest($messageText, 'سيرت ',      $certCodes, $chatId)) exit();
if (handleKeywordRequest($messageText, 'cert ',      $certCodes, $chatId)) exit();

// --- DETAILED LINKS ---
$detailedLinks = [
    // Root
    'روت a300h' => 'http://protech.software/?p=1386', 'روت a310f' => 'http://protech.software/?p=1403',
    'روت a500h' => 'http://protech.software/?p=1406', 'روت a510f' => 'http://protech.software/?p=1409',
    'روت g532g' => 'http://protech.software/?p=1443', 'روت g610f' => 'http://protech.software/?p=1446',
    'روت g930f' => 'http://protech.software/?p=1489', 'روت g935f' => 'http://protech.software/?p=1502',
    'روت i9500' => 'http://protech.software/?p=1518', 'روت j120h' => 'http://protech.software/?p=1540',
    'روت j200h' => 'http://protech.software/?p=1547', 'روت j500h' => 'http://protech.software/?p=1559',
    'روت j700h' => 'http://protech.software/?p=1574', 'روت n910c' => 'http://protech.software/?p=4757',
    'روت G950F' => 'http://protech.software/?p=3794', 'روت G955F' => 'http://protech.software/?p=3790',
    // Cert
    'سيرت a5000'  => 'http://protech.software/?page_id=1924', 'سيرت a500f'  => 'http://protech.software/?page_id=1986',
    'سيرت a500h'  => 'http://protech.software/?page_id=1990', 'سيرت a700f'  => 'http://protech.software/?page_id=1999',
    'سيرت g530fz' => 'http://protech.software/?page_id=2027', 'سيرت g900h'  => 'لتحميل السيرت اضغط هنا http://protech.software/?page_id=2688',
    'سيرت n910c'  => 'http://protech.software/?page_id=2692 ســــــــــــــيرت',
    'سيرت n9005'  => 'ســـــــيرت http://protech.software/?page_id=2695',
    // Arabic ROMs
    'تعريب g532g' => 'الروم العربي + روت الروم مستقره ومجربه للتحميل اضغط هنا https://protech.software/?p=322',
    'تعريب g928p' => 'http://protech.software/?p=308',  'تعريب g920p' => 'http://protech.software/?p=1020',
    'تعريب n900p' => 'http://protech.software/?p=2314', 'تعريب C7000' => 'https://protech.software/?p=2989',
    // 4-File ROMs
    'اربع ملفات a300f' => 'http://protech.software/?p=4526', 'اربع ملفات a500h' => 'http://protech.software/?p=4560',
    'اربع ملفات e500f' => 'http://protech.software/?p=4587', 'اربع ملفات J500H' => 'http://protech.software/?p=4635',
    'اربع ملفات N7505' => 'http://protech.software/?p=4619',
    // General
    'روم اربع ملفات' => 'لتحميل اي روم تحتوي على اربع ملفات اضغط على الرابط التاليhttp://support.protech.software/index.php?a=browse&b=category&id=3',
    'روم ملف واحد'  => 'لتحميل الروم الذي تريدها اضغط على الرابط التالي http://support.protech.software/index.php?a=browse&b=category&id=4',
    'روم كومبنيشن' => 'http://support.protech.software/index.php?a=browse&b=category&id=5 تفضل اخي حمل الكومبنيشن من هنا',
    'روت سامسونج'  => 'لتحميل الروت الذي تريد اضغط على الرابط التالي http://support.protech.software/index.php?a=browse&b=category&id=1532',
    'كراك اكتوبلس'  => 'http://protech.software/?p=1913',
    'كراك الميراكل' => 'http://protech.software/?p=1033',
    'walytech'      => 'لزيارة السبورت اضغط هنا http://support.protech.software/index.php لزيارة الموقع اضغط هنا https://protech.software/',
];
foreach ($detailedLinks as $kw => $rep) {
    if (stristr($messageText, $kw) !== false) { sendMessage($chatId, $rep); exit(); }
}

// --- DB DEVICE LOOKUP ---
$deviceName = '';
$bits = explode(" ", $messageText);
if (count($bits) === 1 && strlen($messageText) > 2 && $messageText[0] === '/') {
    $deviceName = preg_replace("/[^a-zA-Z0-9]+/", "", substr($messageText, 1));
}

if (!empty($deviceName)) {
    $link = mysqli_connect("localhost", "walytech_support3", "rf5YkQ7ZDBNFQRd", "walytech_support3");
    if ($link === false) {
        sendMessage($chatId, "حدث خطأ أثناء الاتصال بقاعدة البيانات.");
        exit();
    }
    mysqli_set_charset($link, "utf8");

    $fileTypes = [
        "📦 روم اربعة ملفات" => [31,32,33,34,35,36,37,38,39,40,42,43,1400,1404,2857,2866,8618],
        "📦 روم ملف واحد"    => [7,8,9,10,11,12,13,14,15,16,17,18,412,416,441,2672,2893],
        "📦 روم كومبنيشن"    => [19,20,21,22,23,24,25,26,27,28,29,30,8264,8289,8291],
        "📦 التعريب"          => [4859],
        "📦 CF ROOT"          => [1544,1557,1632,1635,1643,1665,1739,1747,1754,1781,1815,1965,1991],
        "📦 M ROOT"           => [8310,8313,8315,8327],
        "📦 سيرت"             => [3803,3811,3822,3820,3956,3773,3934,3770,3923],
        "📦 روم فك النمط"    => [9469,9493,9502,9552,9606,9621,9917,9956,10378],
    ];

    $buttons = [];
    $found   = false;

    foreach ($fileTypes as $type => $ids) {
        $safeDevice = mysqli_real_escape_string($link, $deviceName);
        $sql = "SELECT category_id FROM `gc_categories` WHERE category_parent_id IN (" . implode(",", $ids) . ") AND category_title='$safeDevice' LIMIT 1";
        if ($result = mysqli_query($link, $sql)) {
            if (mysqli_num_rows($result) > 0) {
                $row      = mysqli_fetch_array($result);
                $folderId = $row["category_id"];
                $url      = "https://support.protech.software/index.php?a=downloads&b=folder&id=" . $folderId;
                $buttons[] = [["text" => $type, "url" => $url]];
                $found = true;
            }
            mysqli_free_result($result);
        }
    }
    mysqli_close($link);

    if ($found) {
        $replyMarkup = json_encode(["inline_keyboard" => $buttons]);
        sendPTMessage($chatId, "🔍 *نتائج البحث للموديل:* `$deviceName`\n\nاختر نوع الملف:", $replyMarkup, 'Markdown');
    } else {
        sendMessage($chatId, "لم يتم العثور على أي ملفات للموديل: $deviceName");
    }
    exit();
}

// --- GROUP HANDLER ---
require_once __DIR__ . '/group_handler.php';