<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

$baseDir      = __DIR__;
$pythonList   = $baseDir . '/miui_list_codenames.py';
$pythonFetch  = $baseDir . '/miui_fetch_fastboot.py';
$stateFile    = $baseDir . '/cron_state.json';
$batchSize    = 20; // عدد الكودنيمات بكل تشغيل

// إعدادات تلغرام (لو حابب تستعملها لاحقاً)
$botToken = '8597731016:AAHUmGGfjDSyiJ2M3n_8odvd4Ph6hmDJ1LU';
$adminId  = '463538817';

// عداد الملفات المضافة
$addedFilesCount = 0;

// 1) جلب كل الكودنيمات من المصدر
$cmdList = 'python3 ' . escapeshellarg($pythonList) . ' 2>&1';
$output  = shell_exec($cmdList);
$codes   = json_decode($output, true);

if (!is_array($codes)) {
    // فقط أخطاء جلب القائمة
    file_put_contents($baseDir.'/cron_auto_update.log',
        date('Y-m-d H:i:s') . " ERROR listing codenames: $output\n",
        FILE_APPEND
    );
    exit;
}

// 2) قراءة حالة آخر index من ملف state
$startFrom = 0;
if (file_exists($stateFile)) {
    $stJson = json_decode(file_get_contents($stateFile), true);
    if (is_array($stJson) && isset($stJson['index'])) {
        $startFrom = (int)$stJson['index'];
    }
}
$totalCodes = count($codes);

// لو تجاوزنا النهاية نرجع للبداية
if ($startFrom >= $totalCodes) {
    $startFrom = 0;
}

// نحدد شريحة الكودنيمات لهاي الدورة
$codesSlice = array_slice($codes, $startFrom, $batchSize);
$newIndex   = $startFrom + $batchSize;

// 3) دالة استدعاء add_firmware.php
function callAddFirmware(array $rom, &$addedFilesCount) {
    $deviceName = $rom['device_name'] ?? ($rom['device'] ?? '');

    $payload = [
        'brand'        => 'XIAOMI',
        'device_name'  => $deviceName,
        'codename'     => strtolower($rom['pattern'] ?? ''),
        'branch'       => $rom['branch'] ?? 'Global',
        'type'         => $rom['type'] ?? 'Fastboot',
        'version'      => $rom['version'] ?? '',
        'android'      => $rom['android'] ?? '',
        'download_url' => $rom['download'] ?? '',
        'date'         => $rom['date'] ?? '',
    ];

    if (!empty($rom['size']) && preg_match('/([\d\.]+)\s*([GMK]B)/i', $rom['size'], $m)) {
        $num  = (float)$m[1];
        $unit = strtoupper($m[2]);
        if ($unit === 'GB')      $payload['size_bytes'] = (int)($num * 1024 * 1024 * 1024);
        elseif ($unit === 'MB') $payload['size_bytes'] = (int)($num * 1024 * 1024);
        elseif ($unit === 'KB') $payload['size_bytes'] = (int)($num * 1024);
    }

    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://support.protech.software/custom-api/add_firmware.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($jsonBody),
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    // لا نكتب كل نتيجة في اللوج؛ نزيد العداد فقط عند النجاح
    if ($err === '' && $httpCode === 200) {
        $jsonRes = json_decode($response, true);
        if (is_array($jsonRes) && ($jsonRes['status'] ?? '') === 'success') {
            if (!empty($jsonRes['results'])) {
                foreach ($jsonRes['results'] as $r) {
                    if (($r['note'] ?? '') === 'created') {
                        $addedFilesCount++;
                    }
                }
            } elseif (($jsonRes['note'] ?? '') === 'created') {
                $addedFilesCount++;
            }
        }
    }
}

// 4) لف على شريحة الكودنيمات فقط
foreach ($codesSlice as $pattern) {
    $pattern = strtoupper(trim($pattern));
    if ($pattern === '') continue;

    $cmd    = 'python3 ' . escapeshellarg($pythonFetch) . ' ' . escapeshellarg($pattern) . ' 2>&1';
    $output = shell_exec($cmd);
    $data   = json_decode($output, true);

    if (!is_array($data) || (isset($data['error']) && $data['error'])) {
        // فقط أخطاء جلب الروم لهذا الكودنيم
        file_put_contents($baseDir.'/cron_auto_update.log',
            date('Y-m-d H:i:s') . " $pattern ERROR: $output\n",
            FILE_APPEND
        );
        continue;
    }

    foreach ($data as $rom) {
        $rom['pattern'] = $pattern;
        callAddFirmware($rom, $addedFilesCount);
    }
}

// 5) حفظ index الجديد للدورة القادمة
file_put_contents($stateFile, json_encode(['index' => $newIndex]));

// 6) (اختياري) إرسال رسالة تلغرام بعدد الملفات المضافة
if ($addedFilesCount > 0) {
    $msg  = "🤖 Cron Auto Update\n";
    $msg .= "📅 " . date("d/m/Y H:i") . "\n";
    $msg .= "📁 عدد الملفات الجديدة المضافة للسبورت: {$addedFilesCount}";
    notifyTelegram($botToken, $adminId, $msg);
}

echo "OK\n";

// دالة إرسال تلغرام
function notifyTelegram($token, $chatId, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chatId}&text=" . urlencode($text);
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
