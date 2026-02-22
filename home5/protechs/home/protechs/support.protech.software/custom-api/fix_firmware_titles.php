<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

header("Content-Type: text/plain; charset=utf-8");

define("DB_HOST",     "localhost");
define("DB_NAME",     "protechs_res");
define("DB_USER",     "protechs_res");
define("DB_PASSWORD", "w@HHmmFpqe");
define("XIAOMI_FIRMWARE_ID", 8970);

$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($db->connect_error) {
    die("DB connection failed: " . $db->connect_error . "\n");
}
$db->set_charset("utf8mb4");

$DRY_RUN = true; // خليه true أول مرة

// 1) جمع كل folder_id تحت جذر شاومي
$rootId = (int)XIAOMI_FIRMWARE_ID;
$folderIds = [$rootId];

// نجمع مستوى 1
$sql1 = "SELECT folder_id FROM gc_folders WHERE parent_id = $rootId";
$res1 = $db->query($sql1);
if ($res1) {
    while ($r = $res1->fetch_assoc()) {
        $folderIds[] = (int)$r['folder_id'];
    }
    $res1->free();
}

// مستوى 2
$level1 = array_slice($folderIds, 1);
if (!empty($level1)) {
    $in1 = implode(',', $level1);
    $sql2 = "SELECT folder_id FROM gc_folders WHERE parent_id IN ($in1)";
    $res2 = $db->query($sql2);
    if ($res2) {
        while ($r = $res2->fetch_assoc()) {
            $folderIds[] = (int)$r['folder_id'];
        }
        $res2->free();
    }
}

// مستوى 3 (لو موجود)
$level2 = array_diff($folderIds, [$rootId], $level1);
if (!empty($level2)) {
    $in2 = implode(',', $level2);
    $sql3 = "SELECT folder_id FROM gc_folders WHERE parent_id IN ($in2)";
    $res3 = $db->query($sql3);
    if ($res3) {
        while ($r = $res3->fetch_assoc()) {
            $folderIds[] = (int)$r['folder_id'];
        }
        $res3->free();
    }
}

$folderIds = array_values(array_unique($folderIds));
if (count($folderIds) <= 1) {
    die("No Xiaomi sub-folders found under ID " . $rootId . "\n");
}

echo "Xiaomi folder_ids: " . implode(',', $folderIds) . "\n\n";

// 2) تحضير تعبير REPLACE
$extraWords = [
    'India',
    'Indian',
    'Global',
    'Indonesia',
    'Russia',
    'Turkish',
    'Turkey',
    'Taiwan',
    'EEA',
    'EU',
    'Japan',
];

$expr = 'title';
foreach ($extraWords as $w) {
    $expr = "REPLACE(REPLACE($expr, '_{$w}_', '_'), '_{$w}', '')";
}

$folderIn = implode(',', $folderIds);

// 3) معاينة
$sqlPreview = "
    SELECT file_id, title AS old_title, $expr AS new_title
    FROM gc_files
    WHERE folder_id IN ($folderIn)
      AND (
            title LIKE '%_India_%'
         OR title LIKE '%_Indian_%'
         OR title LIKE '%_Global_%'
         OR title LIKE '%_Indonesia_%'
         OR title LIKE '%_Russia_%'
         OR title LIKE '%_Turkish_%'
         OR title LIKE '%_Turkey_%'
         OR title LIKE '%_Taiwan_%'
         OR title LIKE '%_EEA_%'
         OR title LIKE '%_EU_%'
         OR title LIKE '%_Japan_%'
      )
    LIMIT 100
";

echo "=== PREVIEW (أول 100) ===\n\n";
$res = $db->query($sqlPreview);
if (!$res) {
    die("Preview query error: " . $db->error . "\n");
}
$found = 0;
while ($row = $res->fetch_assoc()) {
    $found++;
    echo $row['file_id'] . ":\n  OLD: " . $row['old_title'] . "\n  NEW: " . $row['new_title'] . "\n\n";
}
$res->free();

if ($found === 0) {
    echo "لا يوجد عناوين مطابقة لشروط LIKE داخل مجلدات شاومي.\n";
}

if ($DRY_RUN) {
    echo "DRY_RUN = true → لا يوجد UPDATE فعلي.\n";
    exit;
}

// 4) تحديث فعلي
$sqlUpdate = "
    UPDATE gc_files
    SET title = $expr
    WHERE folder_id IN ($folderIn)
      AND (
            title LIKE '%_India_%'
         OR title LIKE '%_Indian_%'
         OR title LIKE '%_Global_%'
         OR title LIKE '%_Indonesia_%'
         OR title LIKE '%_Russia_%'
         OR title LIKE '%_Turkish_%'
         OR title LIKE '%_Turkey_%'
         OR title LIKE '%_Taiwan_%'
         OR title LIKE '%_EEA_%'
         OR title LIKE '%_EU_%'
         OR title LIKE '%_Japan_%'
      )
";

if (!$db->query($sqlUpdate)) {
    die("Update query error: " . $db->error . "\n");
}

echo "تم التحديث. Rows affected: " . $db->affected_rows . "\n";
