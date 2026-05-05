<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT cover_image FROM blog_posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();

if ($post) {
    if ($post['cover_image']) {
        $file = __DIR__ . '/../../' . $post['cover_image'];
        if (file_exists($file)) unlink($file);
    }
    $pdo->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
