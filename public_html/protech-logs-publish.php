<?php
// ملف: public_html/protech-logs-publish.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$localSecret = 'Jack_2026_LogsSecret';
$key = $_REQUEST['key'] ?? null;
if ($key !== $localSecret) {
    http_response_code(403);
    exit('Forbidden');
}

define("DB_NAME", "protechs_res");
define("DB_USER", "protechs_res");
define("DB_PASSWORD", "w@HHmmFpqe");
define("DB_HOST", "localhost");

$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($db->connect_error) {
    http_response_code(500);
    exit('DB FAILED: ' . $db->connect_error);
}
$db->set_charset("utf8mb4");

$dateFrom = $_REQUEST['date_from'] ?? '2026-02-07';
$limit    = isset($_REQUEST['limit'])  ? (int)$_REQUEST['limit']  : 10000;
$chunk    = isset($_REQUEST['chunk'])  ? (int)$_REQUEST['chunk']  : 100;
$authorId = isset($_REQUEST['author']) ? (int)$_REQUEST['author'] : 1;

if ($chunk <= 0) $chunk = 100;
if ($limit <= 0) $limit = 10000;

function humanFileSize($bytes, $decimals = 2) {
    $units  = ['B','KB','MB','GB','TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . " " . $units[$factor];
}

// جلب الملفات غير المنشورة فقط
$stmt = $db->prepare("
    SELECT file_id, folder_id, title, folder_title, size, date_create
    FROM gc_files
    WHERE date_create >= ?
      AND published_to_blog = 0
    ORDER BY date_create ASC
    LIMIT ?
");
$stmt->bind_param('si', $dateFrom, $limit);
$stmt->execute();
$res   = $stmt->get_result();
$files = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($files)) {
    header('Content-Type: text/plain; charset=utf-8');
    exit('NO_FILES: لا يوجد ملفات جديدة للنشر');
}

$chunks = array_chunk($files, $chunk);
$total  = count($files);

$publishUrl    = 'https://protech.software/protech-publish.php';
$publishSecret = 'Jack_2026_PublishSecret';

$categories = isset($_REQUEST['categories']) ? (array)$_REQUEST['categories'] : [];
$tags       = isset($_REQUEST['tags'])       ? (array)$_REQUEST['tags']       : ['file log', 'support'];

// ──────────────────────────────────────
// تاريخ اليوم بصيغتين
// ──────────────────────────────────────
$displayDateEn   = date("d/m/Y");
$todaySlugPart   = date("d-m-Y"); // للـ slug والبحث

$monthsAr = [
    1=>'يناير',  2=>'فبراير', 3=>'مارس',    4=>'أبريل',
    5=>'مايو',   6=>'يونيو',  7=>'يوليو',   8=>'أغسطس',
    9=>'سبتمبر', 10=>'أكتوبر',11=>'نوفمبر', 12=>'ديسمبر'
];
$displayDateArFull = date("d") . " " . $monthsAr[(int)date("m")] . " " . date("Y");

// ──────────────────────────────────────
// جيب آخر Part منشور اليوم فقط
// لو يوم جديد يبدأ من Part 1 تلقائياً
// ──────────────────────────────────────
$lastPartNum = 0;
$searchSlug  = "latest-update-protech-support-" . $todaySlugPart;

$chLatest = curl_init();
curl_setopt($chLatest, CURLOPT_URL,
    'https://protech.software/wp-json/wp/v2/posts?per_page=1&orderby=date&order=desc&search=' . urlencode($searchSlug)
);
curl_setopt($chLatest, CURLOPT_RETURNTRANSFER, true);
$wpResponse = curl_exec($chLatest);
curl_close($chLatest);

$wpPosts = json_decode($wpResponse, true);
if (!empty($wpPosts) && isset($wpPosts[0]['slug'])) {
    $lastSlug = $wpPosts[0]['slug'];
    // تأكد إن الـ slug يخص اليوم فعلاً
    if (
        strpos($lastSlug, $todaySlugPart) !== false &&
        preg_match('/part-(\d+)$/', $lastSlug, $m)
    ) {
        $lastPartNum = (int)$m[1];
    }
    // لو من يوم ثاني → $lastPartNum يبقى 0 → يبدأ Part 1
}

// ──────────────────────────────────────
$createdPosts = [];

foreach ($chunks as $index => $group) {

    $startNum  = $index * $chunk + 1;
    $endNum    = $index * $chunk + count($group);
    $partNum   = $lastPartNum + $index + 1;
    $fileCount = count($group);

    // عنوان إنجليزي
    $title   = "Latest Update Protech Support ({$displayDateEn}) Part {$partNum}";

    // عنوان ثانوي عربي (excerpt)
    $excerpt = "آخر تحديثات سبورت برو تك ({$displayDateArFull}) الجزء {$partNum}";

    // Slug فريد باليوم + رقم الجزء
    $slug = "latest-update-protech-support-{$todaySlugPart}-part-{$partNum}";

    // ──────────────────────────────────────
    // بناء محتوى المقال
    // ──────────────────────────────────────
    // ──────────────────────────────────────
    // بناء محتوى المقال
    // ──────────────────────────────────────
    ob_start();
    ?>
    <div style="text-align:center; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; padding:20px 0;">

        <!-- عنوان إنجليزي -->
        <h2 style="
            font-size:26px;
            font-weight:800;
            margin-bottom:6px;
            letter-spacing:0.5px;
        ">
            🚀 Latest Update Protech Support
        </h2>

        <!-- عنوان عربي -->
        <h2 style="
            font-size:24px;
            font-weight:800;
            margin-bottom:10px;
            direction:rtl;
        ">
            آخر تحديثات سبورت برو تك
        </h2>

        <!-- تاريخ + رقم الجزء إنجليزي -->
        <p style="font-size:16px; margin-bottom:4px;">
            📅 <strong><?= $displayDateEn ?></strong>
            &nbsp;|&nbsp;
            Part <strong><?= $partNum ?></strong>
        </p>

        <!-- تاريخ + رقم الجزء عربي -->
        <p style="font-size:15px; direction:rtl; margin-bottom:20px;">
            📅 <strong><?= $displayDateArFull ?></strong>
            &nbsp;|&nbsp;
            الجزء <strong><?= $partNum ?></strong>
        </p>

        <!-- وصف قصير -->
        <div style="
            font-size:15px;
            border-radius:8px;
            padding:14px 24px;
            display:inline-block;
            max-width:700px;
            margin:0 auto 30px auto;
            line-height:1.8;
            border:1px solid rgba(0,0,0,0.08);
        ">
            This article shows the latest
            <strong><?= $fileCount ?> files</strong>
            uploaded to Protech Support – Part <?= $partNum ?><br>
            <span style="direction:rtl; display:block; margin-top:6px;">
                هذا المقال يعرض آخر
                <strong><?= $fileCount ?> ملف</strong>
                تم إضافتهم للسبورت – الجزء <?= $partNum ?>
            </span>
        </div>

    </div>

    <!-- قائمة الملفات -->
    <?php foreach ($group as $file):
        $name      = $file['title']        ?? '';
        $fileId    = $file['file_id']      ?? 0;
        $folderId  = $file['folder_id']    ?? 0;
        $fileUrl   = "https://support.protech.software/index.php?a=downloads&b=file&id=" . $fileId;
        $folderUrl = "https://support.protech.software/index.php?a=downloads&b=folder&id=" . $folderId;
        $folder    = $file['folder_title'] ?? '';
        $size      = humanFileSize($file['size'] ?? 0);
        $date      = date("Y-m-d H:i", strtotime($file['date_create'] ?? 'now'));
    ?>
    <div style="
        border:1px solid rgba(0,0,0,0.08);
        border-radius:10px;
        padding:14px 18px;
        margin:12px auto;
        max-width:780px;
        text-align:center;
        font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
    ">
        <!-- اسم الملف -->
        <p style="
            margin:0 0 10px 0;
            font-size:16px;
            font-weight:700;
            word-break:break-word;
        ">
            📁 <?= htmlspecialchars($name ?? '') ?>
        </p>

        <!-- اسم الجهاز / المجلد -->
        <p style="
            margin:0 0 10px 0;
            font-size:14px;
        ">
            🗂️ <?= htmlspecialchars($folder ?? '') ?>
        </p>

        <!-- الأزرار -->
        <div style="margin:10px 0 8px 0;">
            <a href="<?= $fileUrl ?>"
               target="_blank"
               style="
                   display:inline-block;
                   margin:4px 6px 4px 0;
                   padding:8px 18px;
                   background:#1a73e8;
                   color:#fff;
                   text-decoration:none;
                   border-radius:6px;
                   font-size:13px;
                   font-weight:600;
               ">
                ⬇️ تحميل الملف
            </a>

            <a href="<?= $folderUrl ?>"
               target="_blank"
               style="
                   display:inline-block;
                   margin:4px 0;
                   padding:8px 18px;
                   background:#f1f3f4;
                   color:#202124;
                   text-decoration:none;
                   border-radius:6px;
                   font-size:13px;
                   font-weight:600;
               ">
                📁 فتح مجلد الجهاز
            </a>
        </div>

        <!-- الحجم والتاريخ -->
        <p style="
            margin:4px 0 0 0;
            font-size:12px;
            opacity:0.8;
        ">
            📦 حجم الملف: <strong><?= $size ?></strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            🕒 التاريخ: <strong><?= $date ?></strong>
        </p>
    </div>
    <?php endforeach; ?>


    <!-- فوتر -->
    <div style="text-align:center; margin-top:30px; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
        <hr style="border:none; border-top:1px solid #e0e0e0; max-width:600px; margin:20px auto;">
        <p style="font-size:15px; color:#444; margin-bottom:6px;">
            📢 To follow the latest updates, subscribe to our Telegram channel:
        </p>
        <p style="font-size:15px; color:#444; direction:rtl; margin-bottom:12px;">
            لمتابعة آخر التحديثات، اشترك في قناتنا على تلغرام:
        </p>
        <a href="https://t.me/protechchannel"
           target="_blank"
           style="
               display:inline-block;
               padding:10px 28px;
               background:#0088cc;
               color:#fff;
               border-radius:6px;
               text-decoration:none;
               font-size:15px;
               font-weight:600;
           ">
            👉 انضم لقناة تلغرام | Join Telegram Channel
        </a>
    </div>
    <?php
    $content = ob_get_clean();

    // ──────────────────────────────────────
    // إرسال إلى protech-publish.php
    // ──────────────────────────────────────
    $postData = [
        'key'        => $publishSecret,
        'title'      => $title,
        'excerpt'    => $excerpt,
        'content'    => $content,
        'slug'       => $slug,
        'author_id'  => $authorId,
        'categories' => $categories,
        'tags'       => $tags,
    ];

    $ch = curl_init($publishUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    // علّم الملفات كمنشورة بعد نجاح النشر
    if (!$err && strpos($response, 'POST_CREATED_ID_') !== false) {
        $ids    = array_column($group, 'file_id');
        $ids_in = implode(',', array_map('intval', $ids));
        if ($ids_in !== '') {
            $db->query("UPDATE gc_files SET published_to_blog = 1 WHERE file_id IN ({$ids_in})");
        }
    }

    if ($err) {
        $createdPosts[] = "ERROR_{$startNum}_{$endNum}: {$err}";
    } else {
        $createdPosts[] = "CHUNK_{$startNum}_{$endNum} (Part {$partNum} | {$fileCount} files): {$response}";
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "DONE\n";
echo "Total Files: {$total}\n";
echo "Total Batches: " . count($chunks) . "\n";
echo "─────────────────\n";
echo implode("\n", $createdPosts);
