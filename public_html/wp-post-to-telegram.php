<?php
error_log("WP-Post-Telegram START: " . date('Y-m-d H:i:s'));

// إعدادات أساسية
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// نفس توكن البوت تبعك
$botToken = "8597731016:AAHUmGGfjDSyiJ2M3n_8odvd4Ph6hmDJ1LU";
$apiUrl   = "https://api.telegram.org/bot" . $botToken;

$secret   = "J4ck_ProTech_2026!";

// استلام البيانات من ووردبريس (POST)
$title   = $_POST['title']   ?? '';
$link    = $_POST['link']    ?? '';
$excerpt = $_POST['excerpt'] ?? '';
$sec     = $_POST['secret']  ?? '';

// تحقق من السر


if ($sec !== $secret) {
    http_response_code(403);
    exit("Forbidden");
}


// تحقق من وجود بيانات
if ($title === '' || $link === '') {
    http_response_code(400);
    exit("Missing data");
}

// صياغة نص بأسلوب عام ومناسب لكل أنواع المقالات
$msg  = "📰 *مقال جديد على ProTech Software*\n";
$msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$msg .= "🔥 تم إضافة مقال جديد على موقع برو تك سوفتوير:\n\n";
$msg .= "📌 *" . $title . "*\n\n";
$msg .= "📝 تفاصيل المقال، الروابط والملفات متوفرة داخل صفحة المقال على الموقع.\n\n";
$msg .= "━━━━━━━━━━━━━━━━━━━━\n";
$msg .= "👇 من خلال الأزرار التالية تقدر توصل للمحتوى والخدمات مباشرة:\n";

// IDs الجروبات اللي البوت فيها أدمن
$groups = [
    "-1001357802001",
    "-1001317643403",
    "-1002565292059",
];

// دالة إرسال رسالة مع أزرار
function sendTelegramPost($chatId, $text, $apiUrl, $postLink) {
    $url = $apiUrl . "/sendMessage";

    $keyboard = [
        "inline_keyboard" => [
            [
                [
                    "text" => "📖 قراءة المقال الآن",
                    "url"  => $postLink
                ]
            ],
            [
                [
                    "text" => "🛠 زيارة قسم السبورت",
                    "url"  => "https://support.protech.software"
                ]
            ],
            [
                [
                    "text" => "🌐 زيارة موقع ProTech Software",
                    "url"  => "https://protech.software"
                ]
            ]
        ]
    ];

    $replyMarkup = json_encode($keyboard, JSON_UNESCAPED_UNICODE);

    $postFields = [
        'chat_id'      => $chatId,
        'text'         => $text,
        'parse_mode'   => 'Markdown',
        'reply_markup' => $replyMarkup
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// إرسال الرسالة لكل الجروبات
foreach ($groups as $gid) {
    sendTelegramPost($gid, $msg, $apiUrl, $link);
}

echo "OK";
error_log("WP-Post-Telegram END: " . date('Y-m-d H:i:s'));
