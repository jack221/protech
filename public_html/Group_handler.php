<?php
/**
 * ProtechBot - Group Handler Module
 */

require_once __DIR__ . '/config.php';

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

// ─── كشف موديل سامسونج مباشر (حروف+أرقام مثل J500F, A505F, G973F) ──────────
function detectSamsungModel(string $query): ?string {
    // موديلات سامسونج: حرف أو حرفين + 3-4 أرقام + حرف أو حرفين اختياري
    // مثال: J500F, A505F, G973F, SM-J500F, N910C
    $q = trim($query);
    // شيل SM- من البداية لو موجودة
    $q = preg_replace('/^SM-/i', '', $q);
    if (preg_match('/^([A-Z]{1,2}[0-9]{3,4}[A-Z]{0,3})$/i', $q, $m)) {
        return strtoupper($m[1]);
    }
    // لو موجود بوسط جملة
    if (preg_match('/(SM-)?([A-Z]{1,2}[0-9]{3,4}[A-Z]{0,3})/i', $q, $m)) {
        return strtoupper($m[2]);
    }
    return null;
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
        $url = BLOG_API . '/posts?search=' . urlencode($term) . '&per_page=10&_fields=title,link';
        $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) continue;
        $posts = json_decode($raw, true) ?: [];
        foreach ($posts as $post) {
            $title = mb_strtolower($post['title']['rendered'] ?? '', 'UTF-8');
            foreach ($words as $w) {
                // تحقق إن الكلمة مطابقة بالضبط — مش جزء من كلمة أطول
                // مثال: A505F لا يطابق A505FN
                $pattern = '/(?<![a-z0-9])' . preg_quote($w, '/') . '(?![a-z0-9])/i';
                if (preg_match($pattern, $title)) {
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

// ─── AI موحد — يدعم Gemini / OpenAI / Claude ────────────────────────────────
function callAI(string $prompt, int $maxTokens = 100, float $temp = 0.7): ?string {
    $provider  = AI_PROVIDER;
    $providers = unserialize(AI_PROVIDERS);
    $config    = $providers[$provider] ?? null;
    if (!$config) return null;

    $headers = ['Content-Type: application/json'];
    $url     = $config['url'];

    if ($provider === 'gemini') {
        $url     = str_replace('{KEY}', AI_API_KEY, $url);
        $payload = json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => $temp],
        ]);
    } elseif ($provider === 'openai') {
        $headers[] = 'Authorization: Bearer ' . AI_API_KEY;
        $payload   = json_encode([
            'model'       => $config['model'],
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => $maxTokens,
            'temperature' => $temp,
        ]);
    } elseif ($provider === 'claude') {
        $headers[] = 'x-api-key: ' . AI_API_KEY;
        $headers[] = 'anthropic-version: 2023-06-01';
        $payload   = json_encode([
            'model'      => $config['model'],
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);
    } else {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $res      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$res) return null;
    $decoded = json_decode($res, true);

    if ($provider === 'gemini') return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($provider === 'openai') return $decoded['choices'][0]['message']['content'] ?? null;
    if ($provider === 'claude') return $decoded['content'][0]['text'] ?? null;
    return null;
}

function generateDiagnosticIntro(string $q): string {
    return callAI(
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


// ─── جلب مجلدات الجهاز من قاعدة البيانات ────────────────────────────────────
function getDeviceFoldersFromDB(string $device): array {
    $link = @mysqli_connect("localhost", "protechs_res", "w@HHmmFpqe", "protechs_res");
    if (!$link) return [];
    mysqli_set_charset($link, "utf8mb4");

    $safe      = mysqli_real_escape_string($link, $device);
    $buttons   = [];
    $seenTypes = [];

    $skipTitles = [
        'series a','series b','series c','series d','series e','series f',
        'series g','series j','series m','series n','series s','series t',
        'series x','series z','series',
        'samsung','xiaomi','huawei','apple','nokia','motorola','oppo','vivo',
        'realme','oneplus','honor','redmi','poco','infinix','tecno','sony',
        'lenovo','asus','lg','google','itel','downloads','all files','files',
    ];

    $sql = "SELECT
                dev.folder_id  AS device_id,
                dev.title      AS device_title,
                p1.title       AS parent1_title,
                p2.title       AS parent2_title,
                p3.title       AS parent3_title,
                p4.title       AS parent4_title,
                p5.title       AS parent5_title
            FROM gc_folders dev
            LEFT JOIN gc_folders p1 ON dev.parent_id = p1.folder_id
            LEFT JOIN gc_folders p2 ON p1.parent_id  = p2.folder_id
            LEFT JOIN gc_folders p3 ON p2.parent_id  = p3.folder_id
            LEFT JOIN gc_folders p4 ON p3.parent_id  = p4.folder_id
            LEFT JOIN gc_folders p5 ON p4.parent_id  = p5.folder_id
            WHERE (
                dev.title = '$safe'
                OR dev.title LIKE '$safe {%'
                OR dev.title LIKE '% $safe'
                OR dev.title LIKE '% $safe {%'
            )
            AND dev.is_active = 1
            LIMIT 20";

    $result = mysqli_query($link, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $deviceId = $row['device_id'];
            if (in_array($deviceId, $seenTypes)) continue;
            $seenTypes[] = $deviceId;

            // ابحث عن أول أب فيه معنى — تجاوز البراندات والسيريز
            // الأولوية: أي اسم فيه كلمة تقنية مفيدة
            $usefulWords = ['recovery','fastboot','combination','repair','root','cert',
                            'frp','imei','schematic','hardware','boardview','drk','fix',
                            'eng','stock','efs','flash','lock','remove','unlock','twrp',
                            'boot','modem','sboot','kernel','firmware'];
            $labelTitle = '';
            // أولاً: دور على أي أب فيه كلمة مفيدة
            foreach (['parent1_title','parent2_title','parent3_title','parent4_title','parent5_title'] as $col) {
                $val = strtolower(trim($row[$col] ?? ''));
                if (!$val) continue;
                foreach ($usefulWords as $uw) {
                    if (strpos($val, $uw) !== false) { $labelTitle = $row[$col]; break 2; }
                }
            }
            // لو ما لقى كلمة مفيدة — خذ أول أب مش سيريز أو براند
            if (!$labelTitle) {
                foreach (['parent1_title','parent2_title','parent3_title','parent4_title','parent5_title'] as $col) {
                    $val = strtolower(trim($row[$col] ?? ''));
                    if (!$val) continue;
                    $skip = false;
                    foreach ($skipTitles as $sk) {
                        if (strpos($val, $sk) !== false) { $skip = true; break; }
                    }
                    if (!$skip) { $labelTitle = $row[$col]; break; }
                }
            }
            if (!$labelTitle) $labelTitle = trim($row['parent1_title'] ?? '');
            if (!$labelTitle) continue;

            // شيل اسم البراند من بداية العنوان
            $brandPrefixes = ['SAMSUNG ','Xiaomi ','Huawei ','Apple ','Nokia ','Oppo ',
                              'Vivo ','Realme ','OnePlus ','Honor ','Redmi ','Poco ',
                              'Infinix ','Tecno ','Sony ','Lenovo ','Asus ','LG ','Google '];
            foreach ($brandPrefixes as $bp) {
                if (stripos($labelTitle, $bp) === 0) {
                    $labelTitle = substr($labelTitle, strlen($bp));
                    break;
                }
            }
            $labelTitle = trim($labelTitle);

            $t = strtolower($labelTitle);
            if      (strpos($t, 'fastboot')    !== false) $emoji = '⚡';
            elseif  (strpos($t, 'recovery')    !== false) $emoji = '🔄';
            elseif  (strpos($t, 'combination') !== false) $emoji = '🔧';
            elseif  (strpos($t, 'repair imei') !== false) $emoji = '📡';
            elseif  (strpos($t, 'eng modem')   !== false) $emoji = '📡';
            elseif  (strpos($t, 'eng boot')    !== false) $emoji = '🔩';
            elseif  (strpos($t, 'schematic')   !== false) $emoji = '📐';
            elseif  (strpos($t, 'hardware')    !== false) $emoji = '🔌';
            elseif  (strpos($t, 'boardview')   !== false) $emoji = '📐';
            elseif  (strpos($t, 'drk')         !== false) $emoji = '🛡️';
            elseif  (strpos($t, 'root')         !== false) $emoji = '🔓';
            elseif  (strpos($t, 'cert')         !== false) $emoji = '📜';
            elseif  (strpos($t, 'frp')          !== false) $emoji = '🔑';
            elseif  (strpos($t, 'imei')         !== false) $emoji = '📡';
            elseif  (strpos($t, 'flash')        !== false) $emoji = '💾';
            else $emoji = '📦';

            $url       = "https://support.protech.software/index.php?a=downloads&b=folder&id=" . $deviceId;
            $buttons[] = [["text" => $emoji . ' ' . $labelTitle, "url" => $url]];
        }
        mysqli_free_result($result);
    }

    mysqli_close($link);
    return $buttons;
}


// ═══════════════════════════════════════════════════════════════════════════════
// المنطق الرئيسي
// ═══════════════════════════════════════════════════════════════════════════════
$query            = trim($messageText);
$searchUrl        = 'https://protech.software/?s=' . urlencode($query);
$supportSearchUrl = SUPPORT_SEARCH . urlencode($query);

// ─── الفلتر الرئيسي ──────────────────────────────────────────────────────────

// 1) كشف الجهاز أولاً
$device = detectSamsungModel($query);
if (!$device) $device = extractDevice($query);

// لو الرسالة إنجليزية بالكامل (1-3 كلمات) ومش اجتماعية = كودنيم
if (!$device) {
    $trimmed = trim($query);
    if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9 _+-]*$/', $trimmed)) {
        $wordCount = count(explode(' ', $trimmed));
        if ($wordCount <= 3 && !isSocialMessage($trimmed)) {
            $device = strtolower($trimmed);
        }
    }
}

// 2) لو في جهاز — ابحث وارد
if ($device) {
    // يكمل للكود تحت
}
// 3) لو ما في جهاز — شيك اجتماعي
// الكلمة الاجتماعية تشتغل حتى لو بجملة طويلة عربية
elseif (isSocialMessage($query)) {
    sendGroupReply($chatId, $messageId, getSocialReply($query));
    exit();
}
// 4) لا جهاز ولا اجتماعي — صمت
else {
    exit();
}

if ($device) {
    $dbButtons = getDeviceFoldersFromDB($device);
    if (!empty($dbButtons)) {
        $text = "🔍 *نتائج البحث للجهاز:* `{$device}`\n\nاختر نوع الملف:";
        sendGroupReply($chatId, $messageId, $text, $dbButtons);
    } else {
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
    }
    exit();
}

// 2) بحث فعلي بالمدونة والسبورت — لما يذكر جهاز بس ما لقى بالDB
$blogResult    = searchBlog($query);
$supportResult = searchSupport($query);

if ($blogResult || $supportResult) {
    $intro = generateDiagnosticIntro($query);
    $text  = $intro . "\n\n━━━━━━━━━━━━━━━━━━━";
    $rows  = [];

    if ($blogResult) {
        $text .= "\n\n📰 *أقرب مقال من المدونة:*\n";
        $text .= "📌 " . $blogResult['title'];
        $rows[] = [
            ['text' => '📖 قراءة المقال', 'url' => $blogResult['url']],
        ];
    }

    if ($supportResult) {
        $text .= "\n\n📦 *أقرب ملف من السبورت:*\n";
        $text .= "📌 " . $supportResult['title'];
        $rows[] = [
            ['text' => '📂 فتح الملف', 'url' => $supportResult['url']],
        ];
    }

    // صف بحث واحد بالأسفل
    $text .= "\n\n_لو ما وجدت ما تحتاجه جرب البحث المباشر_ 👇";
    $rows[] = [
        ['text' => '🔍 بحث بالمدونة',  'url' => $searchUrl],
        ['text' => '📦 بحث بالسبورت', 'url' => $supportSearchUrl],
    ];

    sendGroupReply($chatId, $messageId, $text, $rows);
    exit();
}

// 4) ما لقى شي — بدون زر الدعم مباشرة
$text  = "🔍 بحثنا عن *" . $query . "* ما لقينا نتيجة مباشرة.\n";
$text .= "جرب البحث بنفسك أو غير طريقة الكتابة 👇";
$rows = [
    [
        ['text' => '🌐 بحث بالمدونة',  'url' => $searchUrl],
        ['text' => '📦 بحث بالسبورت', 'url' => $supportSearchUrl],
    ],
];

// زر الدعم بس لما يكتب جملة تدل على إنه محتاج مساعدة
$helpWords = ['مو شغال','ما اشتغل','مشكلة','خربان','ما مشى','ما مشي','ما لقيت','ما لقيت',
              'مو موجود','error','failed','لا يعمل','تالف','بريك','ايمي'];
$needsHelp = false;
$lq = mb_strtolower($query, 'UTF-8');
foreach ($helpWords as $hw) {
    if (mb_strpos($lq, mb_strtolower($hw, 'UTF-8'), 0, 'UTF-8') !== false) {
        $needsHelp = true;
        break;
    }
}
if ($needsHelp) {
    $rows[] = [['text' => '📞 تواصل مع الدعم', 'url' => 'https://t.me/PROTECHSOFT']];
}

sendGroupReply($chatId, $messageId, $text, $rows);
