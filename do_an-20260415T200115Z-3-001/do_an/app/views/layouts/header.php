<?php
declare(strict_types=1);

$notificationCount = 0;
if (isset($_SESSION['user']['id']) && isset($dbConfig) && is_array($dbConfig)) {
    try {
        $db = \Core\Database::connection($dbConfig);
        $notificationModel = new \App\Models\Notification($db);
        $notificationCount = $notificationModel->countUnreadByUser((int) $_SESSION['user']['id']);
    } catch (\Throwable $e) {
        $notificationCount = 0;
    }
}
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
<header class="topbar">
    <div class="container-fluid topbar-inner">
        <a class="brand" href="index.php?r=home/index">P</a>
        <nav class="main-nav">
            <a class="nav-pill nav-pill-active" href="index.php?r=home/index">Trang chủ</a>
            <a class="nav-pill" href="index.php?r=board/index">Cộng đồng</a>
            <?php if (isset($_SESSION['user'])): ?>
                <a class="nav-pill" href="index.php?r=profile/show">Profile</a>
                <a class="nav-pill nav-pill-notification" href="index.php?r=notification/index">
                    Thông Báo
                    <?php if ($notificationCount > 0): ?>
                        <span class="notif-dot" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
                <a class="nav-pill" href="index.php?r=message/inbox">Tin nhắn</a>
            <?php endif; ?>
        </nav>
        <form class="search-form" method="get" action="index.php">
            <input type="hidden" name="r" value="home/index">
            <input class="search-input" type="search" name="q" placeholder="Tìm ý tưởng..." value="<?= isset($_GET['q']) ? htmlspecialchars((string) $_GET['q']) : '' ?>">
        </form>
        <nav class="auth-links">
            <?php if (isset($_SESSION['user'])): ?>
                <span class="mr-2">Xin chào, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
                <a href="index.php?r=auth/logout">Đăng xuất</a>
            <?php else: ?>
                <a class="mr-2" href="index.php?r=auth/login">Đăng nhập</a>
                <a href="index.php?r=auth/register">Đăng ký</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container-fluid py-4">
