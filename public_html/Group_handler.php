<?php
/**
 * ProtechBot - Group Handler Module
 */

define('BLOG_URL',       'https://protech.software');
define('SUPPORT_URL',    'https://support.protech.software');
define('BLOG_API',       'https://protech.software/wp-json/wp/v2');
define('SUPPORT_SEARCH', 'https://support.protech.software/index.php?a=search&q=');
define('AI_API_KEY',     'YOUR_GEMINI_KEY_HERE');
define('MIN_QUESTION_LEN', 5);

$chatType = $message['chat']['type'] ?? 'private';
$isGroup  = in_array($chatType, ['group', 'supergroup']);
if (!$isGroup) return;
if (strlen($messageText) < MIN_QUESTION_LEN || $messageText[0] === '/') return;

// ─── استخراج الكلمات المفيدة ─────────────────────────────────────────────────
function extractWords(string $query): array {
    $words = [];
    foreach (explode(' ', mb_strtolower(trim($query), 'UTF-8')) as $w) {
        $w = trim($w);
        if (mb_strlen($w, 'UTF-8') > 2) $words[] = $w;
    }
    return $words;
}

// ─── كشف اسم جهاز بنمط صارم ─────────────────────────────────────────────────

// ─── البحث بالمدونة ──────────────────────────────────────────────────────────
function fetchBlogPosts(string $term): array {
    $url = BLOG_API . '/posts?search=' . urlencode($term) . '&per_page=5&_fields=title,link';
    $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [];
    return json_decode($raw, true) ?: [];
}

function matchPost(array $posts, array $words): ?array {
    foreach ($posts as $post) {
        $title = mb_strtolower($post['title']['rendered'] ?? '', 'UTF-8');
        foreach ($words as $word) {
            if (mb_strpos($title, $word, 0, 'UTF-8') !== false) {
                return [
                    'title' => html_entity_decode(strip_tags($post['title']['rendered']), ENT_QUOTES, 'UTF-8'),
                    'url'   => $post['link'] ?? '',
                ];
            }
        }
    }
    return null;
}

function searchBlog(string $query): ?array {
    $words = extractWords($query);
    if (empty($words)) return null;
    $result = matchPost(fetchBlogPosts($query), $words);
    if ($result) return $result;
    foreach ($words as $word) {
        $result = matchPost(fetchBlogPosts($word), [$word]);
        if ($result) return $result;
    }
    return null;
}

// ─── البحث بالسبورت ──────────────────────────────────────────────────────────
function fetchSupportResult(string $term, array $words): ?array {
    $url = SUPPORT_SEARCH . urlencode($term);
    $ctx = stream_context_create(['http' => ['timeout' => 6], 'ssl' => ['verify_peer' => false]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;

    $patterns = [
        '/<a[^>]+href="([^"]*index\.php\?a=file&b=show[^"]*)"[^>]*>\s*([^<]{3,80})/i',
        '/<a[^>]+href="([^"]*index\.php\?a=(?:browse|downloads)[^"]*)"[^>]*>\s*([^<]{3,80})/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $raw, $m)) {
            $title = trim(strip_tags($m[2]));
            $link  = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (!$title) continue;
            $titleLower = mb_strtolower($title, 'UTF-8');
            foreach ($words as $w) {
                if (mb_strpos($titleLower, $w, 0, 'UTF-8') !== false) {
                    if (strpos($link, 'http') !== 0) $link = SUPPORT_URL . '/' . ltrim($link, '/');
                    return ['title' => $title, 'url' => $link];
                }
            }
        }
    }
    return null;
}

function searchSupport(string $query): ?array {
    $words = extractWords($query);
    if (empty($words)) return null;
    $result = fetchSupportResult($query, $words);
    if ($result) return $result;
    foreach ($words as $word) {
        $result = fetchSupportResult($word, [$word]);
        if ($result) return $result;
    }
    return null;
}

// ─── Gemini ───────────────────────────────────────────────────────────────────
function callGemini(string $prompt, int $maxTokens = 100, float $temp = 0.7): ?string {
    if (AI_API_KEY === 'YOUR_GEMINI_KEY_HERE') return null;
    $payload = json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => $temp],
    ]);
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . AI_API_KEY);
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $res      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$res) return null;
    $decoded = json_decode($res, true);
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

function getSocialReply(string $q): string {
    return callGemini(
        "أنت عضو ودي في مجموعة تيليغرام عربية.\nرد طبيعي قصير (سطر أو سطرين) باللهجة العامية. لا روابط.\nالرسالة: \"{$q}\"",
        60, 0.8
    ) ?: "هلا 👋";
}

function generateDiagnosticIntro(string $q): string {
    return callGemini(
        "مساعد تقني ودي - ProTech Software.\nالمستخدم يسأل: \"{$q}\"\n3 أسطر max:\n1. هلا ودي\n2. شو جربت حتى الآن؟\n3. كيف استلمت الجوال؟\nلا روابط. لا حلول. لهجة عامية.",
        100, 0.6
    ) ?: "هلا 👋\nشو جربت حتى الآن؟ وكيف استلمت الجوال؟ 🤔";
}

function classifyMessage(string $q): string {
    $r = callGemini(
        "رسالة تيليغرام: \"{$q}\"\nكلمة واحدة فقط: social أو technical",
        10, 0.3
    );
    return ($r && stripos($r, 'social') !== false) ? 'social' : 'technical';
}

// ─── الإرسال ──────────────────────────────────────────────────────────────────
function sendGroupReply(int $chatId, int $replyToId, string $text, array $rows = []): void {
    $fields = [
        'chat_id'                     => $chatId,
        'text'                        => $text,
        'parse_mode'                  => 'Markdown',
        'reply_to_message_id'         => $replyToId,
        'allow_sending_without_reply' => true,
    ];
    if (!empty($rows)) $fields['reply_markup'] = json_encode(['inline_keyboard' => $rows]);
    $ch = curl_init($GLOBALS['website'] . '/sendMessage');
    curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    curl_close($ch);
}

// ─── المنطق الرئيسي ───────────────────────────────────────────────────────────
$query            = trim($messageText);
$searchUrl        = 'https://protech.software/?s=' . urlencode($query);
$supportSearchUrl = SUPPORT_SEARCH . urlencode($query);

// 1) فلتر اجتماعي محلي
$socialKeywords = [
    'هلا','هلو','هاي','مرحبا','مرحبتين','أهلاً','اهلا','اهلين','السلام','سلام',
    'كيفك','كيف حالك','كيفكم','شو اخبارك','شو أخبارك','كيف الأحوال',
    'صباح الخير','صباح النور','مساء الخير','مساء النور','تصبح على خير',
    'يلا بشوفك','يلا سلامة','مع السلامة','الله يسلمك','يسلمو','يعطيك العافية',
    'شكرا','شكراً','ثانكس','تسلم','مشكور','ممنون','جزاك الله',
    'وحش','وحشتنا','وحشتوني','نورت','نورتنا',
    'بدنا نولع','يلا نولع','نولع الجو','خليها تولع',
    'عساك بخير','الله يعافيك','ربي يحميك',
    'هههه','هاهاها',
];
$lowerQuery = mb_strtolower($query, 'UTF-8');
foreach ($socialKeywords as $kw) {
    if (mb_strpos($lowerQuery, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
        sendGroupReply($chatId, $messageId, getSocialReply($query));
        exit();
    }
}

// 2) تصنيف Gemini للرسائل الغامضة
if (classifyMessage($query) === 'social') {
    sendGroupReply($chatId, $messageId, getSocialReply($query));
    exit();
}

// 3) كشف اسم جهاز — فقط لو كل الكلمات إنجليزية/أرقام ولازم يحتوي رقم أو براند معروف
$isAllAsciiAlnum = (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\s\-]*$/', $query);
$hasNumber       = (bool) preg_match('/[0-9]/', $query);
$knownBrands     = ['samsung','xiaomi','redmi','poco','huawei','honor','oppo','vivo',
                    'realme','iphone','apple','nokia','motorola','sony','oneplus',
                    'google','pixel','galaxy','infinix','tecno','lenovo','asus'];
$startsWithBrand = false;
$lq = strtolower($query);
foreach ($knownBrands as $b) {
    if (strpos($lq, $b) === 0 && strlen($query) > strlen($b) + 1) {
        $startsWithBrand = true;
        break;
    }
}

if ($isAllAsciiAlnum && ($hasNumber || $startsWithBrand)) {
    $text = "🔍 *نتائج البحث للجهاز:* " . $query . "\n\nاختر ما تحتاجه:";
    $rows = [
        [
            ['text' => '📰 مقالات بالمدونة', 'url' => $searchUrl],
            ['text' => '📦 ملفات بالسبورت',  'url' => $supportSearchUrl],
        ],
        [['text' => '🌐 زيارة السبورت', 'url' => SUPPORT_URL]],
    ];
    sendGroupReply($chatId, $messageId, $text, $rows);
    exit();
}

// 4) كلمة قصيرة — روابط بحث مباشرة
$words = extractWords($query);
if (count($words) <= 2 && mb_strlen($query, 'UTF-8') < 20) {
    $intro = generateDiagnosticIntro($query);
    $text  = $intro . "\n\n━━━━━━━━━━━━━━━━━━━\n";
    $text .= "هاي كل الحلول المتوفرة لـ *" . $query . "*:\n";
    $rows  = [
        [
            ['text' => '📰 كل المقالات بالمدونة', 'url' => $searchUrl],
            ['text' => '📦 كل الملفات بالسبورت',  'url' => $supportSearchUrl],
        ],
        [
            ['text' => '🌐 زيارة السبورت',   'url' => SUPPORT_URL],
            ['text' => '📞 تواصل مع الدعم', 'url' => 'https://t.me/PROTECHSOFT'],
        ],
    ];
    $text .= "\n_جرّب ورِدلنا خبر_ 💡";
    sendGroupReply($chatId, $messageId, $text, $rows);
    exit();
}

// 5) بحث فعلي بالمدونة والسبورت
$blogResult    = searchBlog($query);
$supportResult = searchSupport($query);

if ($blogResult || $supportResult) {
    $intro = generateDiagnosticIntro($query);
    $text  = $intro . "\n\n━━━━━━━━━━━━━━━━━━━\n";
    $text .= "بنفس الوقت، جرّب هاي المواضيع:\n";
    $rows  = [];

    if ($blogResult && $blogResult['url']) {
        $text  .= "\n📰 *من المدونة:*\n📌 " . $blogResult['title'] . "\n";
        $rows[] = [
            ['text' => '📖 قراءة المقال', 'url' => $blogResult['url']],
            ['text' => '🔐 الاشتراك',     'url' => 'https://protech.software/membership'],
        ];
        $rows[] = [['text' => '🔍 بحث بنفس الموضوع', 'url' => $searchUrl]];
    }

    if ($supportResult && $supportResult['url']) {
        $text  .= "\n📦 *من السبورت:*\n📌 " . $supportResult['title'] . "\n";
        $rows[] = [
            ['text' => '📦 فتح الملف / الصفحة', 'url' => $supportResult['url']],
            ['text' => '🔍 بحث بالسبورت',        'url' => $supportSearchUrl],
        ];
    }

    $rows[] = [
        ['text' => '🌐 زيارة السبورت',   'url' => SUPPORT_URL],
        ['text' => '📞 تواصل مع الدعم', 'url' => 'https://t.me/PROTECHSOFT'],
    ];
    $text .= "\n_هاي اقتراحات — جرّب ورِدلنا خبر_ 💡";
    sendGroupReply($chatId, $messageId, $text, $rows);
    exit();
}

// 6) ما لقى شي
$text = "هلا 👋\nبحثنا بالمدونة والسبورت ما لقينا نتيجة مباشرة.\n_استنى رد الفريق_ ⏳";
$rows = [
    [
        ['text' => '🔍 بحث بالمدونة', 'url' => $searchUrl],
        ['text' => '📦 بحث بالسبورت', 'url' => $supportSearchUrl],
    ],
    [['text' => '📞 تواصل مع الدعم', 'url' => 'https://t.me/PROTECHSOFT']],
];
sendGroupReply($chatId, $messageId, $text, $rows);