<?php
/**
 * 将 /o/ 请求重定向到 public/ 入口。
 * 因为项目的真实 web 入口在 public/index.php，而 /o/ 目录下没有 index.php，
 * 直接访问 /o/ 会显示目录列表或 404。因此这里兜底跳转到 /o/public。
 */
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: /public' . $query);
exit;
