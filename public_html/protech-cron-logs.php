<?php
// ملف: public_html/protech-cron-logs.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// حماية: يشتغل من CLI أو بـ secret key فقط
$secret = $_REQUEST['key'] ?? null;
if (PHP_SAPI !== 'cli' && $secret !== 'Jack_2026_CronSecret') {
    http_response_code(403);
    exit('Forbidden');
}

// ──────────────────────────────────────
// إعدادات
// ──────────────────────────────────────
define("DB_NAME",     "protechs_res");
define("DB_USER",     "protechs_res");
define("DB_PASSWORD", "w@HHmmFpqe");
define("DB_HOST",     "localhost");

$botToken  = '8597731016:AAHUmGGfjDSyiJ2M3n_8odvd4Ph6hmDJ1LU';
$adminId   = '463538817'; // حسابك الشخصي
$dateFrom  = '2026-02-07';
$chunkSize = 100;

// ──────────────────────────────────────
// اتصال قاعدة البيانات
// ──────────────────────────────────────
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($db->connect_error) {
    notifyTelegram($botToken, $adminId, "❌ Cron فشل: DB FAILED\n" . $db->connect_error);
    exit('DB FAILED: ' . $db->connect_error);
}
$db->set_charset("utf8mb4");

// ──────────────────────────────────────
// فحص عدد الملفات غير المنشورة
// ──────────────────────────────────────
$res   = $db->query("
    SELECT COUNT(*) as cnt
    FROM gc_files
    WHERE date_create >= '{$dateFrom}'
      AND published_to_blog = 0
");
$row   = $res->fetch_assoc();
$count = (int)$row['cnt'];

// لو أقل من 100 لا ينشر
if ($count < $chunkSize) {
    $msg = "⏳ Cron – لا يوجد ما يكفي للنشر\n";
    $msg .= "📅 " . date("d/m/Y H:i") . "\n";
    $msg .= "📁 الملفات الجديدة حالياً: {$count} / {$chunkSize}";
    notifyTelegram($botToken, $adminId, $msg);
    exit("SKIP: Only {$count} unpublished files. Need {$chunkSize}.");
}

// ──────────────────────────────────────
// استدعاء سكربت النشر
// ──────────────────────────────────────
$ch = curl_init('https://protech.software/protech-logs-publish.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'key'       => 'Jack_2026_LogsSecret',
    'date_from' => $dateFrom,
    'limit'     => $chunkSize,   // هنا المهم: يرسل فقط 100 ملف كحد أقصى
    'chunk'     => $chunkSize,   // chunk = 100 ⇒ مقال واحد
    'author'    => 1,
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

// ──────────────────────────────────────
// لو في خطأ cURL
// ──────────────────────────────────────
if ($curlErr) {
    $msg = "❌ Cron – خطأ في الاتصال\n";
    $msg .= "📅 " . date("d/m/Y H:i") . "\n";
    $msg .= "cURL Error: {$curlErr}";
    notifyTelegram($botToken, $adminId, $msg);
    exit("CURL ERROR: {$curlErr}");
}

// ──────────────────────────────────────
// تحليل الرد وبناء رسالة التلغرام
// ──────────────────────────────────────
$lines      = explode("\n", $response);
$details    = '';
$totalParts = 0;
$totalFiles = 0;

foreach ($lines as $line) {
    $line = trim($line);

    if (strpos($line, 'Total Files:') === 0) {
        $totalFiles = trim(str_replace('Total Files:', '', $line));
    }

    if (strpos($line, 'CHUNK_') === 0) {
        // مثال: CHUNK_1_100 (Part 1 | 100 files): OK_POST_CREATED_ID_123
        preg_match('/CHUNK_\d+_\d+ \(Part (\d+) \| (\d+) files\)/', $line, $m);
        if (!empty($m)) {
            $details    .= "✅ Part {$m[1]}: {$m[2]} ملف\n";
            $totalParts++;
        } elseif (strpos($line, 'ERROR') !== false) {
            $details .= "❌ " . $line . "\n";
        }
    }
}

// رسالة النجاح
$msg  = "🤖 Cron – نشر تلقائي ✅\n";
$msg .= "📅 " . date("d/m/Y H:i") . "\n";
$msg .= "📁 إجمالي الملفات الجديدة: {$totalFiles}\n";
$msg .= "📦 عدد المقالات المنشورة: {$totalParts}\n";
$msg .= "─────────────────\n";
$msg .= $details;

notifyTelegram($botToken, $adminId, $msg);

echo "DONE\n";
echo $response;

// ──────────────────────────────────────
// دالة إرسال تلغرام
// ──────────────────────────────────────
function notifyTelegram($token, $chatId, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chatId}&text=" . urlencode($text);
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
