<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <span class="navbar-brand mb-0 h1">Admin Moderation</span>
    <div>
        <?php if (isset($_SESSION['admin'])): ?>
            <span class="text-light mr-3">Xin chào, <?= htmlspecialchars((string) $_SESSION['admin']['name']) ?></span>
            <a class="btn btn-outline-light btn-sm" href="index.php?r=auth/logout">Đăng xuất</a>
        <?php endif; ?>
    </div>
</nav>
<main class="container py-4">
