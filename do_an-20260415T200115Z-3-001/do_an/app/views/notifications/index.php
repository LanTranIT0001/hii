<?php
declare(strict_types=1);
?>
<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="h4 mb-1">Thông báo</h1>
                        <div class="text-muted">Thông báo khi có người thích pin, bình luận pin hoặc theo dõi bạn.</div>
                    </div>
                    <a class="btn btn-sm btn-link text-danger" href="index.php?r=notification/markRead">Đánh dấu tất cả đã đọc</a>
                </div>
                <?php if (empty($notifications)): ?>
                    <div class="text-center text-muted py-5">Chưa có thông báo nào.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $type = (string) ($notification['type'] ?? '');
                        $pinId = (int) ($notification['pin_id'] ?? 0);
                        $targetLink = $type === 'follow' || $pinId <= 0
                            ? 'index.php?r=profile/show'
                            : 'index.php?r=pin/detail&id=' . $pinId;
                        $link = 'index.php?' . http_build_query([
                            'r' => 'notification/open',
                            'id' => (int) ($notification['id'] ?? 0),
                            'next' => $targetLink,
                        ]);
                        $actorName = (string) ($notification['actor_name'] ?? 'Người dùng');
                        $pinTitle = (string) ($notification['pin_title'] ?? 'Pin của bạn');
                        $commentText = trim((string) ($notification['comment_text'] ?? ''));
                        $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
                        $message = '';
                        if ($type === 'like') {
                            $message = 'đã thích pin của bạn: ' . $pinTitle;
                        } elseif ($type === 'comment') {
                            $message = 'đã bình luận pin của bạn: ' . ($commentText !== '' ? $commentText : $pinTitle);
                        } else {
                            $message = 'đã theo dõi tài khoản của bạn.';
                        }
                        ?>
                        <a href="<?= htmlspecialchars($link) ?>" class="notification-card d-flex align-items-center p-3 mb-3 rounded-lg shadow-sm text-decoration-none">
                            <div class="notification-avatar mr-3"><?= htmlspecialchars(substr($actorName, 0, 1)) ?></div>
                            <div class="flex-fill">
                                <div class="d-flex align-items-start justify-content-between mb-1">
                                    <div>
                                        <div class="font-weight-bold mb-1 text-dark"><?= htmlspecialchars($actorName) ?></div>
                                        <div class="text-muted small text-truncate">
                                            <?= htmlspecialchars($message) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between small text-muted">
                                    <span><?= date('H:i:s d/m/Y', strtotime($notification['created_at'])) ?></span>
                                    <?php if ($isUnread): ?>
                                        <span class="notification-dot"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
