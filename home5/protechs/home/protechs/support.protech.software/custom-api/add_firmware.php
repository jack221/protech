<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);
header("Content-Type: application/json; charset=utf-8");

define("DB_HOST", "localhost");
define("DB_NAME", "protechs_res");
define("DB_USER", "protechs_res");
define("DB_PASSWORD", "w@HHmmFpqe");
define("XIAOMI_FW_ID", 8970);

// ─── DB ─────────────────────────────────────────────────────────────────────
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($db->connect_error) die(json_encode(["status" => "error", "message" => "DB connection failed"]));
$db->set_charset("utf8mb4");

// ─── Helpers ─────────────────────────────────────────────────────────────────
function respond(string $status, array $data = []): void {
    echo json_encode(array_merge(["status" => $status], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function markPathAsNew(mysqli $db, int $folderId): void {
    for ($i = 0; $i < 10 && $folderId > 0; $i++) {
        $s = $db->prepare("UPDATE gc_folders SET is_new=1 WHERE folder_id=? LIMIT 1");
        if ($s) {
            $s->bind_param("i", $folderId);
            $s->execute();
            $s->close();
        }
        $s = $db->prepare("SELECT parent_id FROM gc_folders WHERE folder_id=? LIMIT 1");
        if (!$s) break;
        $s->bind_param("i", $folderId);
        $s->execute();
        $s->bind_result($pid);
        $folderId = $s->fetch() ? (int)$pid : 0;
        $s->close();
    }
}

function splitModelName(string $name): array {
    $parts = array_map('trim', explode('/', $name));
    $models = []; $brand = ''; $last = '';
    foreach ($parts as $p) {
        if (!$p) continue;
        if (preg_match('/^(Pro\+?|Plus)$/i', $p)) {
            if ($last) $models[] = trim(preg_replace('/\s+Pro$/i', '', $last) . ' Pro+');
            continue;
        }
        if (stripos($p, 'Pro/Pro+') !== false) {
            $base = preg_replace('/\s*Pro\/Pro\+.*/i', '', $p);
            if (preg_match('/^(Redmi|POCO|Xiaomi)\b/i', $base, $m)) $brand = $m[1];
            $models[] = trim($base . ' Pro');
            $last = $models[] = trim($base . ' Pro+');
            continue;
        }
        if (preg_match('/^(Redmi|POCO|Xiaomi)\b/i', $p, $m)) { $brand = $m[1]; $last = $models[] = $p; }
        else { $last = $models[] = $brand ? "$brand $p" : $p; }
    }
    return array_values(array_unique($models));
}

function detectProPlus(string $raw): string {
    $part = trim(explode('(', $raw)[0]);
    $variants = array_map('trim', explode('/', $part));
    $base = $variants[0];
    foreach ($variants as $v) {
        if (stripos($v, 'Pro+') === false) continue;
        if (preg_match('/^(Redmi|POCO|Xiaomi)\b/i', $v)) return $v;
        $bp = explode(' ', $base);
        if (strcasecmp(end($bp), 'Pro') === 0) array_pop($bp);
        return trim(implode(' ', $bp) . ' ' . $v);
    }
    return $base;
}

function getOrCreateFolder(mysqli $db, int $parentId, string $name, string $desc = ''): int {
    if (!($name = trim($name))) return 0;
    $s = $db->prepare("SELECT folder_id FROM gc_folders WHERE parent_id=? AND title=? LIMIT 1");
    $s->bind_param("is", $parentId, $name); $s->execute(); $s->bind_result($id);
    $found = $s->fetch(); $s->close();
    if ($found) return (int)$id;
    $s = $db->prepare("INSERT INTO gc_folders (parent_id,title,description,is_active,is_new) VALUES (?,?,?,1,0)");
    $s->bind_param("iss", $parentId, $name, $desc); $s->execute();
    $id = (int)$s->insert_id; $s->close();
    return $id;
}

function detectRegion(string $v): ?string {
    $r = strtoupper($v);
    if (strpos($r,'CNXM')!==false && strpos($r,'HYBRID')!==false) return 'EU';
    if (strpos($r,'JLB54.0')!==false || strpos($r,'TAURUS')!==false) return 'China';
    foreach (['KHCMIEK'=>'Global','KHJMIDL'=>'Global','LHJMIEK'=>'Global','KHKMIED'=>'Global'] as $k=>$n)
        if (strpos($r,$k)!==false) return $n;
    $codes = ['MIXM'=>'Global','EUXM'=>'EEA','EAXM'=>'EEA','TRXM'=>'Turkey','CNXM'=>'China',
              'INXM'=>'Indian','RUXM'=>'Russian','IDXM'=>'Indonesia','TWXM'=>'Taiwan',
              'JPXM'=>'Japan','MIDC'=>'Global_dc','KRXM'=>'Korea','MIUI'=>'Global',
              'MIFA'=>'Global','MIFD'=>'Global','MIDA'=>'Global','MIFM'=>'Global',
              'INMI'=>'Indian','INFI'=>'Indian','INRF'=>'Indian','RUMI'=>'Russian',
              'RUFI'=>'Russian','RURF'=>'Russian','RUFD'=>'Russian','TRMI'=>'Turkey',
              'TRFI'=>'Turkey','TWMI'=>'Taiwan','EEMI'=>'EEA','EEFI'=>'EEA',
              'IDMI'=>'Indonesia','IDFI'=>'Indonesia','CNFI'=>'China','CNMI'=>'China',
              'CNFD'=>'China','CNEK'=>'China','CNFA'=>'China','CNCK'=>'China'];
    foreach ($codes as $k=>$n) if (strpos($r,$k)!==false) return $n;
    foreach (['CN'=>'China','MI'=>'Global','IN'=>'Indian','RU'=>'Russian','EU'=>'EEA',
              'ID'=>'Indonesia','TR'=>'Turkey','TW'=>'Taiwan','KR'=>'Korea','JP'=>'Japan',
              'LM'=>'Latin','LA'=>'Latin','CL'=>'Latin'] as $k=>$n)
        if (strpos($r,$k)!==false) return $n;
    return null;
}

// ─── Parse Input ─────────────────────────────────────────────────────────────
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) respond("error", ["message" => "Invalid JSON"]);
foreach (["brand","device_name","codename","branch","type","version","download_url"] as $k)
    if (empty($data[$k])) respond("error", ["message" => "Missing: $k"]);

$brand       = strtoupper(trim($data["brand"]));
$deviceName  = trim($data["device_name"]);
$codename    = strtolower(trim($data["codename"]));
$version     = trim($data["version"]);
$type        = trim($data["type"]);
$androidVer  = trim($data["android"] ?? "");
$sizeBytes   = (int)($data["size_bytes"] ?? 0);
$dlUrl       = trim($data["download_url"] ?? "");
$buildDate   = trim($data["date"] ?? "");

// Resolve region
$branch = detectRegion($version) ?? (([
    'indo'=>'Indonesia','indonesian'=>'Indonesia','indian'=>'Indian','chinese'=>'China',
    'russian'=>'Russian','turkish'=>'Turkey','european'=>'EEA','eu'=>'EEA',
    'japan'=>'Japan','japanese'=>'Japan','global_dc'=>'Global_DC','korea'=>'Korea','latin'=>'Latin'
])[strtolower($data["branch"])] ?? ucfirst(strtolower($data["branch"])));

// Fetch size via HEAD if missing
if ($sizeBytes <= 0 && $dlUrl) {
    $ch = curl_init($dlUrl);
    curl_setopt_array($ch, [CURLOPT_NOBODY=>true,CURLOPT_HEADER=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>10]);
    curl_exec($ch);
    $sz = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    if ($sz > 0) $sizeBytes = (int)$sz;
    curl_close($ch);
}

$deviceNames = splitModelName(trim(preg_replace('/\s*\([^)]*\)\s*/', '', $deviceName)));
$results = [];
$rootId  = (int)XIAOMI_FW_ID;

// ─── Process Each Model ───────────────────────────────────────────────────────
foreach ($deviceNames as $dev) {
    if (!($dev = trim($dev))) continue;

    // Strip region suffix from model base
    $base = $dev;
    foreach ([" China"," Global"," Indonesia"," Indian"," Russia"," Russian"," Turkey"," Taiwan"," EEA"," EU"," Japan"] as $s)
        if (strlen($base) > strlen($s) && substr($base, -strlen($s)) === $s) { $base = rtrim(substr($base, 0, -strlen($s))); break; }
    if (stripos(detectProPlus($deviceName), 'Pro+') !== false) $base = detectProPlus($deviceName);

    $folderTitle = $base . " {" . $codename . "}";

    // 1) Model folder
    $modelFid = 0;
    $s = $db->prepare("SELECT folder_id FROM gc_folders WHERE parent_id=? AND (title=? OR title LIKE ?) LIMIT 1");
    if ($s) {
        $like = "%$base%"; $s->bind_param("iss", $rootId, $folderTitle, $like);
        $s->execute(); $s->bind_result($fid); $found = $s->fetch(); $s->close();
        if ($found) $modelFid = (int)$fid;
    }
    if (!$modelFid) $modelFid = getOrCreateFolder($db, $rootId, $folderTitle, $folderTitle);
    if (!$modelFid) { $results[] = ["status"=>"error","device"=>$dev,"message"=>"Cannot create model folder"]; continue; }

    // 2) Type folder
    $typeName = stripos($type, 'fast') !== false ? 'Fastboot' : 'Recovery';
    $typeFid  = getOrCreateFolder($db, $modelFid, $typeName, "$typeName for $base (".strtoupper($codename).")");
    if (!$typeFid) { $results[] = ["status"=>"error","device"=>$dev,"message"=>"Cannot create type folder"]; continue; }

    // 3) Region folder
    $regFid = getOrCreateFolder($db, $typeFid, $branch, "$branch firmware for $base (".strtoupper($codename).")");
    if (!$regFid) { $results[] = ["status"=>"error","device"=>$dev,"message"=>"Cannot create region folder"]; continue; }

    // 4) Build title
    $rw = [' India',' Indian',' Global',' Indonesia',' Russia',' Turkish',' Turkey',' Taiwan',' EEA',' EU',' Japan'];
    $mp = str_replace([' ','/','+'],['_','_','+'], str_ireplace($rw,'',trim($base)));
    $dp = $buildDate ? (date("Ymd", strtotime($buildDate)) ?: "00000000") : "00000000";
    $ap = 'Android' . (int)floor((float)($androidVer ?: "14.0"));
    $fileTitle = "{$mp}_{$codename}_".strtolower($branch)."_images_{$ap}_{$version}_{$dp}_ProTech.Software";
    $fileDesc  = "$base ($codename) $branch $typeName ROM $version" . ($androidVer ? " – Android $androidVer." : "") . " By Protech.Software";
    $tags      = implode(', ', array_filter([$brand,$base,$codename,$branch,$typeName,$version,$androidVer?"Android $androidVer":""]));

    // 5) Check existing
    $s = $db->prepare("SELECT file_id,url FROM gc_files WHERE folder_id=? AND ref_id=? LIMIT 1");
    $s->bind_param("is", $regFid, $version); $s->execute(); $s->bind_result($eId, $eUrl); $exists = $s->fetch(); $s->close();
    if ($exists) {
        $note = "updated_date";
        if ($eUrl !== $dlUrl) {
            $u = $db->prepare("UPDATE gc_files SET url=?,size=?,date_new=NOW(),date_update=NOW() WHERE file_id=? LIMIT 1");
            $u->bind_param("sii",$dlUrl,$sizeBytes,$eId); $u->execute(); $u->close(); $note = "updated_url";
        } else {
            $u = $db->prepare("UPDATE gc_files SET date_new=NOW(),date_update=NOW() WHERE file_id=? LIMIT 1");
            $u->bind_param("i",$eId); $u->execute(); $u->close();
        }
        $results[] = ["status"=>"success","note"=>$note,"device"=>$dev,"file_id"=>(int)$eId,"file_title"=>$fileTitle,"category_id"=>(int)$regFid,"ref_id"=>$version];
        continue;
    }

    // 6) Validate URL
    if ($dlUrl && !filter_var($dlUrl, FILTER_VALIDATE_URL)) {
        $results[] = ["status"=>"error","device"=>$dev,"message"=>"Invalid URL"]; continue;
    }

    // 7) Insert
    $s = $db->prepare("INSERT INTO gc_files (folder_id,folder_title,title,description,size,url,url_type,price,visits,is_active,is_new,is_featured,server_id,rating_count,rating_points,tags,ref_id) VALUES (?,?,?,?,?,?,'direct',0,0,1,1,0,0,0,0,?,?)");
    if (!$s) { $results[] = ["status"=>"error","device"=>$dev,"message"=>"Prepare failed: ".$db->error]; continue; }
    $ft = "$base ($codename)";
    $s->bind_param("isssisss", $regFid,$ft,$fileTitle,$fileDesc,$sizeBytes,$dlUrl,$tags,$version);
    if (!$s->execute()) { $err=$s->error; $s->close(); $results[] = ["status"=>"error","device"=>$dev,"message"=>"Insert failed: $err"]; continue; }
    $newId = (int)$s->insert_id; $s->close();

    markPathAsNew($db, $regFid);
    $results[] = ["status"=>"success","note"=>"created","device"=>$dev,"file_id"=>$newId,"file_title"=>$fileTitle,"category_id"=>(int)$regFid,"ref_id"=>$version];
}

respond("success", ["results" => $results]);