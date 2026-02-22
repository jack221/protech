<?php
/**
 * ProtechBot - Group Handler Module
 */

define('BLOG_URL',       'https://protech.software');
define('SUPPORT_URL',    'https://support.protech.software');
define('BLOG_API',       'https://protech.software/wp-json/wp/v2');
define('SUPPORT_SEARCH', 'https://support.protech.software/index.php?a=search&q=');
define('AI_API_KEY',     'AIzaSyAVhDSGb3dZl7llBZ4wzdIk30f90RrpLRM');
define('MIN_QUESTION_LEN', 3);

$chatType = $message['chat']['type'] ?? 'private';
$isGroup  = in_array($chatType, ['group', 'supergroup']);
if (!$isGroup) return;
if (strlen($messageText) < MIN_QUESTION_LEN || $messageText[0] === '/') return;

// ─── براندات الهواتف ──────────────────────────────────────────────────────────
function getBrands(): array {
    return [
        // شاومي وعائلتها
        'شاومي'      => 'xiaomi',  'شيومي'     => 'xiaomi',  'زياومي'    => 'xiaomi',
        'ريدمي'      => 'redmi',   'ريدمى'     => 'redmi',
        'بوكو'       => 'poco',
        'مي'         => 'xiaomi',
        // سامسونج
        'سامسونج'   => 'samsung', 'سامسونغ'   => 'samsung', 'سامسونق'   => 'samsung',
        'غالاكسي'   => 'samsung', 'جالاكسي'   => 'samsung',
        // هواوي وعائلتها
        'هواوي'      => 'huawei',  'هوواي'     => 'huawei',
        'هونر'       => 'honor',   'اونر'      => 'honor',
        // باقي البراندات
        'ايفون'      => 'iphone',  'آيفون'     => 'iphone',  'ايفن'      => 'iphone',
        'نوكيا'      => 'nokia',
        'موتورولا'   => 'motorola',
        'اوبو'       => 'oppo',    'أوبو'      => 'oppo',
        'فيفو'       => 'vivo',
        'ريلمي'      => 'realme',  'ريلمى'     => 'realme',
        'ونبلس'      => 'oneplus', 'وان بلس'   => 'oneplus',
        'انفينكس'    => 'infinix', 'إنفينيكس'  => 'infinix',
        'تيكنو'      => 'tecno',   'ايتل'      => 'itel',
        'لينوفو'     => 'lenovo',  'اسوس'      => 'asus',
        'سوني'       => 'sony',    'ال جي'     => 'lg',
        'جوجل'       => 'google',  'بيكسل'     => 'pixel',
        // إنجليزي
        'xiaomi'     => 'xiaomi',  'redmi'     => 'redmi',   'poco'      => 'poco',
        'samsung'    => 'samsung', 'galaxy'    => 'samsung',
        'huawei'     => 'huawei',  'honor'     => 'honor',
        'iphone'     => 'iphone',  'apple'     => 'apple',
        'nokia'      => 'nokia',   'motorola'  => 'motorola',
        'oppo'       => 'oppo',    'vivo'      => 'vivo',    'realme'    => 'realme',
        'oneplus'    => 'oneplus', 'infinix'   => 'infinix', 'tecno'     => 'tecno',
        'pixel'      => 'pixel',   'sony'      => 'sony',    'lenovo'    => 'lenovo',
        'asus'       => 'asus',    'motorola'  => 'motorola','lg'        => 'lg',
        'google'     => 'google',  'itel'      => 'itel',
        // حسابات/مشاكل معروفة
        'mi account' => 'mi account', 'مي اكونت'  => 'mi account',
        'google frp' => 'frp',         'frp'        => 'frp',
        'جوجل اكونت' => 'google frp',  'حساب جوجل' => 'google frp',
        'حساب مي'    => 'mi account',
    ];
}

// ترجمة أجزاء اسم الجهاز عربي → إنجليزي
function translateParts(string $text): string {
    $map = [
        'نوت'    => 'note',   'برو'    => 'pro',    'بلس'    => 'plus',
        'ماكس'   => 'max',    'ميني'   => 'mini',   'لايت'   => 'lite',
        'الترا'  => 'ultra',  'فلب'    => 'flip',   'فولد'   => 'fold',
        'زيرو'   => 'zero',   'باور'   => 'power',  'سوبر'   => 'super',
        'نيو'    => 'neo',    'تيربو'  => 'turbo',  'اس اي'  => 'se',
        'اكس'    => 'x',      'واي'    => 'y',       'ايه'    => 'a',
        'اف'     => 'f',      'سي'     => 'c',
    ];
    foreach ($map as $ar => $en) {
        $text = str_replace($ar, ' ' . $en . ' ', $text);
    }
    return $text;
}

// ─── سحب اسم الجهاز من أي جملة ───────────────────────────────────────────────
function extractDevice(string $query): ?string {
    $brands   = getBrands();
    $lower    = mb_strtolower($query, 'UTF-8');
    $lower    = translateParts($lower);

    $foundBrand    = null;
    $foundBrandKey = null;
    $foundPos      = PHP_INT_MAX;

    foreach ($brands as $key => $en) {
        $pos = mb_strpos($lower, $key, 0, 'UTF-8');
        if ($pos !== false && $pos < $foundPos) {
            $foundPos      = $pos;
            $foundBrand    = $en;
            $foundBrandKey = $key;
        }
    }

    if (!$foundBrand) return null;

    // خد النص بعد البراند واستخرج الأرقام والكلمات الإنجليزية
    $after = mb_substr($lower, $foundPos + mb_strlen($foundBrandKey, 'UTF-8'), null, 'UTF-8');
    $after = trim($after);

    $parts = [];
    foreach (explode(' ', $after) as $w) {
        $w = trim($w);
        if ($w === '') continue;
        if (preg_match('/^[a-z0-9]+$/i', $w)) {
            $parts[] = $w;
            if (count($parts) >= 4) break;
        }
    }

    $result = $foundBrand;
    if (!empty($parts)) $result .= ' ' . implode(' ', $parts);

    return $result;
}

// ─── كشف رسالة اجتماعية ──────────────────────────────────────────────────────
function isSocialMessage(string $query): bool {
    // لو في براند = مش اجتماعية
    $brands = getBrands();
    $lower  = mb_strtolower($query, 'UTF-8');
    foreach ($brands as $key => $en) {
        if (mb_strpos($lower, $key, 0, 'UTF-8') !== false) return false;
    }

    $keywords = [
        'هلا','هلو','هاي','مرحبا','مرحبتين','اهلا','اهلين','يا هلا',
        'السلام','سلام','سلامو','سلامات',
        'صباح','مساء','تصبح','طاب صباح','طاب مساء',
        'كيفك','كيفكم','كيف حالك','كيف الأحوال','كيف الحال',
        'شو اخبارك','شو أخبارك','كيف امورك','عامل إيه','عساك بخير','ولا بأس',
        'يلا سلامة','مع السلامة','الله يسلمك','في امان الله','باي',
        'شكرا','شكراً','مشكور','مشكورين','ممنون','تسلم','يسلمو',
        'يعطيك العافية','جزاك الله','بارك الله','ثانكس','ثنكس',
        'الله يعافيك','ربي يحميك','الله يوفقك','ربنا يكرمك',
        'هههه','هاهاها','هيهيهي','ههههه','خخخخ','خخخ','😂','🤣',
        'وحشتنا','وحشتوني','نورت','نورتنا','وين كنت','وين غبت',
        'يلا يلا','اوكي','اوك','تمام','ماشي','عال العال','زين',
        'يا جماعة','يا شباب','حبيبي','حبيبتي','يا عمي','يا اخوي',
        'بدنا نولع','نولع الجو','خليها تولع',
        'hello','hi','hey','how are','good morning','good evening','good night',
        'thanks','thank you','thx','bye','goodbye','lol','haha','hehe','ok','okay',
        'nice','great','cool','wow',
    ];

    foreach ($keywords as $kw) {
        if (mb_strpos($lower, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) return true;
    }
    return false;
}

// ─── ردود اجتماعية ───────────────────────────────────────────────────────────
function getSocialReply(string $q): string {
    $lower = mb_strtolower(trim($q), 'UTF-8');
    $replies = [
        // سلام
        'السلام عليكم'  => 'وعليكم السلام ورحمة الله وبركاته 👋',
        'سلام عليكم'    => 'وعليكم السلام 👋',
        'سلام'          => 'هلا وسهلا 👋',
        // صباح مساء
        'صباح الخير'    => 'صباح النور والسرور ☀️',
        'صباح النور'    => 'الله ينور عليك ☀️',
        'صباح'          => 'صباح النور ☀️',
        'مساء الخير'    => 'مساء النور والبركة 🌙',
        'مساء النور'    => 'الله ينور عليك 🌙',
        'مساء'          => 'مساء النور 🌙',
        'تصبح'          => 'وأنت بخير وعافية 🌙',
        'طاب صباح'      => 'وطاب مساؤك 😊',
        // تحيات
        'مرحبا'         => 'أهلاً وسهلاً ومرحبتين 😊 كيف أقدر أساعدك؟',
        'مرحبتين'       => 'أهلاً وسهلاً 😊 كيف أقدر أساعدك؟',
        'اهلا'          => 'أهلاً فيك 😊 كيف أقدر أساعدك؟',
        'اهلين'         => 'أهلاً وسهلاً 😊',
        'هلا'           => 'هلا فيك ونورتنا 👋 كيف أقدر أساعدك؟',
        'هلو'           => 'هلو 👋 كيف أقدر أساعدك؟',
        'هاي'           => 'هاي 👋 كيف أقدر أساعدك؟',
        'يا هلا'        => 'يا هلا فيك 👋',
        // كيف الحال
        'كيفك'          => 'تمام الحمد لله 😊 وأنت كيفك؟',
        'كيفكم'         => 'كلنا بخير الحمد لله 😊 وأنتم؟',
        'كيف حالك'      => 'تمام الحمد لله 😊 وأنت كيف حالك؟',
        'كيف الأحوال'   => 'كلشي تمام الحمد لله 😊',
        'كيف الحال'     => 'تمام الحمد لله 😊',
        'شو اخبارك'     => 'كلشي تمام 😊 وأنت شو اخبارك؟',
        'شو أخبارك'     => 'كلشي تمام 😊 وأنت؟',
        'كيف امورك'     => 'تمام الحمد لله 😊 وأمورك؟',
        'عامل إيه'      => 'تمام الحمد لله 😊 وأنت؟',
        'عساك بخير'     => 'وأنت بخير إن شاء الله 😊',
        'ولا بأس'       => 'الحمد لله 😊',
        // شكر
        'شكرا جزيلا'    => 'العفو جزيلاً 😊 دايماً بخدمتك',
        'ألف شكر'       => 'ألف عفو 😊 يسعدنا خدمتك',
        'شكرا'          => 'العفو 😊 دايماً بخدمتك',
        'شكراً'         => 'العفو 😊',
        'مشكور'         => 'العفو، خدمتك دايماً 😊',
        'مشكورين'       => 'العفو جميعاً 😊',
        'ممنون'         => 'العفو 😊',
        'ممنونك'        => 'العفو 😊',
        'تسلم'          => 'يسلمك ويعافيك 🙏',
        'تسلمي'         => 'يسلمك ويعافيك 🙏',
        'تسلموا'        => 'يسلمكم ويعافيكم 🙏',
        'يسلمو'         => 'الله يسلمك ويعافيك 😊',
        'يعطيك العافية' => 'الله يعافيك ويعافي والديك 😊',
        'جزاك الله'     => 'وإياك وبارك الله فيك 😊',
        'جزاكم الله'    => 'وإياكم 😊',
        'بارك الله'     => 'وفيك بارك الله 😊',
        'ثانكس'         => 'العفو 😊',
        'ثنكس'          => 'العفو 😊',
        // دعاء
        'الله يعافيك'   => 'وإياك إن شاء الله 😊',
        'ربي يحميك'     => 'وإياك إن شاء الله 😊',
        'الله يوفقك'    => 'وإياك يوفق إن شاء الله 😊',
        'ربنا يكرمك'    => 'وإياك إن شاء الله 😊',
        'الله يكرمك'    => 'وإياك 😊',
        // وداع
        'يلا سلامة'     => 'الله يسلمك 👋 نورتنا',
        'مع السلامة'    => 'الله يسلمك 👋',
        'الله يسلمك'    => 'وإياك 😊',
        'في امان الله'  => 'الله يحفظك ويسلمك 👋',
        'باي'           => 'Bye! 👋 نورتنا',
        // ترحيب بعودة
        'وحشتنا'        => 'وحشتنا كثير والله 😊 رجعت ونورتنا',
        'وحشتوني'       => 'وحشتنا أكثر 😊',
        'نورت'          => 'نورتنا أنت والله 🌟',
        'نورتنا'        => 'الله ينور عليك 🌟',
        'وين كنت'       => 'هلا بيك رجعت ونورتنا! 🌟',
        'وين غبت'       => 'هلا بيك! وحشتنا 🌟',
        // تعبيرات
        'هههه'          => '😄😄',
        'هاهاها'        => '😄',
        'هيهيهي'        => '😄',
        'خخخ'           => '😄',
        'تمام'          => '👍',
        'اوكي'          => '👍',
        'اوك'           => '👍',
        'ماشي'          => '👍 تفضل',
        'عال العال'     => '😊👍',
        'زين'           => '👍',
        'يا شباب'       => 'هلا بالشباب كلهم 👋',
        'يا جماعة'      => 'هلا بالجماعة 👋',
        'حبيبي'         => 'هلا حبيبي 😊 كيف أقدر أساعدك؟',
        'حبيبتي'        => 'هلا 😊 كيف أقدر أساعدك؟',
        'يا عمي'        => 'هلا عمي 😊 كيف أقدر أساعدك؟',
        'يا اخوي'       => 'هلا أخوي 😊 كيف أقدر أساعدك؟',
        'بدنا نولع'     => 'يلا نولعها 🔥😄',
        'نولع الجو'     => 'يلا 🔥',
        // إنجليزي
        'how are you'   => 'Fine, thank you! How can I help? 😊',
        'how are'       => 'Fine! How can I help you? 😊',
        'hello'         => 'Hello! 👋 How can I help?',
        'hi there'      => 'Hi there! 👋 How can I help?',
        'hi'            => 'Hi! 👋 How can I help?',
        'hey'           => 'Hey! 👋',
        'good morning'  => 'Good morning! ☀️',
        'good evening'  => 'Good evening! 🌙',
        'good night'    => 'Good night! 🌙',
        'thanks'        => 'You are welcome! 😊',
        'thank you'     => 'You are welcome! 😊',
        'thx'           => 'Welcome! 😊',
        'bye'           => 'Goodbye! 👋',
        'goodbye'       => 'Goodbye! Take care 👋',
        'lol'           => '😄',
        'haha'          => '😄',
        'hehe'          => '😄',
        'ok'            => '👍',
        'okay'          => '👍',
        'nice'          => '😊 Thanks!',
        'great'         => '😊',
        'cool'          => '😎',
        'wow'           => '😮',
    ];
    foreach ($replies as $kw => $reply) {
        if (mb_strpos($lower, $kw, 0, 'UTF-8') !== false) return $reply;
    }
    return 'هلا 👋 كيف أقدر أساعدك؟';
}

// ─── بحث المدونة ──────────────────────────────────────────────────────────────
function searchBlog(string $query): ?array {
    $words = [];
    foreach (explode(' ', mb_strtolower($query, 'UTF-8')) as $w) {
        if (mb_strlen($w, 'UTF-8') > 2) $words[] = $w;
    }
    if (empty($words)) return null;
    $terms = array_merge([$query], $words);
    foreach ($terms as $term) {
        $url = BLOG_API . '/posts?search=' . urlencode($term) . '&per_page=5&_fields=title,link';
        $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) continue;
        $posts = json_decode($raw, true) ?: [];
        foreach ($posts as $post) {
            $title = mb_strtolower($post['title']['rendered'] ?? '', 'UTF-8');
            foreach ($words as $w) {
                if (mb_strpos($title, $w, 0, 'UTF-8') !== false) {
                    return [
                        'title' => html_entity_decode(strip_tags($post['title']['rendered']), ENT_QUOTES, 'UTF-8'),
                        'url'   => $post['link'] ?? '',
                    ];
                }
            }
        }
    }
    return null;
}

// ─── بحث السبورت ─────────────────────────────────────────────────────────────
function searchSupport(string $query): ?array {
    $words = [];
    foreach (explode(' ', mb_strtolower($query, 'UTF-8')) as $w) {
        if (mb_strlen($w, 'UTF-8') > 2) $words[] = $w;
    }
    $terms = array_merge([$query], $words);
    foreach ($terms as $term) {
        $url = SUPPORT_SEARCH . urlencode($term);
        $ctx = stream_context_create(['http' => ['timeout' => 6], 'ssl' => ['verify_peer' => false]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) continue;
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
    }
    return null;
}

// ─── Gemini ───────────────────────────────────────────────────────────────────
function callGemini(string $prompt, int $maxTokens = 100, float $temp = 0.7): ?string {
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

function generateDiagnosticIntro(string $q): string {
    return callGemini(
        "مساعد تقني ودي - ProTech Software.\nالمستخدم يسأل: \"{$q}\"\n3 أسطر max باللهجة العامية. لا روابط. لا حلول.",
        80, 0.6
    ) ?: "هلا 👋\nشو جربت حتى الآن؟ 🤔";
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

// ═══════════════════════════════════════════════════════════════════════════════
// المنطق الرئيسي
// ═══════════════════════════════════════════════════════════════════════════════
$query            = trim($messageText);
$searchUrl        = 'https://protech.software/?s=' . urlencode($query);
$supportSearchUrl = SUPPORT_SEARCH . urlencode($query);

// 1) سحب اسم الجهاز — الأولوية القصوى
$device = extractDevice($query);
if ($device) {
    $devSearchUrl  = 'https://protech.software/?s=' . urlencode($device);
    $devSupportUrl = SUPPORT_SEARCH . urlencode($device);
    $text = "🔍 *نتائج البحث للجهاز:* `{$device}`\n\nاختر ما تحتاجه:";
    $rows = [
        [
            ['text' => '📰 مقالات بالمدونة', 'url' => $devSearchUrl],
            ['text' => '📦 ملفات بالسبورت',  'url' => $devSupportUrl],
        ],
        [['text' => '🌐 زيارة السبورت', 'url' => SUPPORT_URL]],
    ];
    sendGroupReply($chatId, $messageId, $text, $rows);
    exit();
}

// 2) رسالة اجتماعية
if (isSocialMessage($query)) {
    sendGroupReply($chatId, $messageId, getSocialReply($query));
    exit();
}

// 3) بحث فعلي بالمدونة والسبورت
$blogResult    = searchBlog($query);
$supportResult = searchSupport($query);

if ($blogResult || $supportResult) {
    $intro = generateDiagnosticIntro($query);
    $text  = $intro . "\n\n━━━━━━━━━━━━━━━━━━━\n";
    $rows  = [];
    if ($blogResult) {
        $text  .= "\n📰 *من المدونة:*\n📌 " . $blogResult['title'] . "\n";
        $rows[] = [
            ['text' => '📖 قراءة المقال', 'url' => $blogResult['url']],
            ['text' => '🔍 بحث بالمدونة', 'url' => $searchUrl],
        ];
    }
    if ($supportResult) {
        $text  .= "\n📦 *من السبورت:*\n📌 " . $supportResult['title'] . "\n";
        $rows[] = [
            ['text' => '📦 فتح الصفحة',   'url' => $supportResult['url']],
            ['text' => '🔍 بحث بالسبورت', 'url' => $supportSearchUrl],
        ];
    }
    $rows[] = [['text' => '📞 تواصل مع الدعم', 'url' => 'https://t.me/PROTECHSOFT']];
    sendGroupReply($chatId, $messageId, $text, $rows);
    exit();
}

// 4) ما لقى شي
$text = "هلا 👋\nبحثنا ما لقينا نتيجة مباشرة.\n_استنى رد الفريق_ ⏳";
$rows = [
    [
        ['text' => '🔍 بحث بالمدونة', 'url' => $searchUrl],
        ['text' => '📦 بحث بالسبورت', 'url' => $supportSearchUrl],
    ],
    [['text' => '📞 تواصل مع الدعم', 'url' => 'https://t.me/PROTECHSOFT']],
];
sendGroupReply($chatId, $messageId, $text, $rows);