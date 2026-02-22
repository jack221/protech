<?php
// ملف: public_html/protech-publish.php

// يفضّل تعطيل إظهار الأخطاء في الإنتاج
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// مفتاح الأمان
$secret = 'Jack_2026_PublishSecret';

// قراءة المفتاح من POST فقط
$key = $_POST['key'] ?? null;
if ($key !== $secret) {
    http_response_code(403);
    exit('Forbidden');
}

// تحميل ووردبريس
require_once __DIR__ . '/wp-load.php';

// قراءة البيانات من الطلب
$title      = isset($_POST['title'])      ? trim($_POST['title'])           : '';
$content    = isset($_POST['content'])    ? trim($_POST['content'])         : '';
$slug       = isset($_POST['slug'])       ? sanitize_title($_POST['slug'])  : '';
$categories = isset($_POST['categories']) ? (array) $_POST['categories']    : [];
$tags       = isset($_POST['tags'])       ? (array) $_POST['tags']          : [];
$author_id  = isset($_POST['author_id'])  ? (int) $_POST['author_id']       : 1;
$excerpt    = isset($_POST['excerpt'])    ? trim($_POST['excerpt'])         : '';

// لو بدك تضمن أن كل المحتوى في المنتصف، تقدر تلفّه بـ div هنا:
if ($content !== '') {
    $content = '<div style="text-align:center;">' . $content . '</div>';
}

// إنشاء البوست
$postarr = [
    'post_title'   => $title,
    'post_content' => $content,
    'post_excerpt' => $excerpt,
    'post_status'  => 'publish',
    'post_type'    => 'post',
    'post_author'  => $author_id,
];

if ($slug !== '') {
    $postarr['post_name'] = $slug;
}

if (!empty($categories)) {
    $postarr['post_category'] = array_map('intval', $categories);
}

$post_id = wp_insert_post($postarr, true);

if (is_wp_error($post_id)) {
    exit('Error: ' . $post_id->get_error_message());
}

if (!empty($tags)) {
    wp_set_post_tags($post_id, $tags, false);
}

echo 'OK_POST_CREATED_ID_' . $post_id;
