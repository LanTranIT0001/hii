<?php
declare(strict_types=1);

$activePeerName = '';
foreach ($conversations as $conversation) {
    if ($activeConversationId === (int) $conversation['id']) {
        $activePeerName = $conversation['peer_name'];
        break;
    }
}

$linkifyMessage = static function (string $text): string {
    $pattern = '~(https?://[^\s<]+|index\.php\?r=pin/detail&id=\d+)~i';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return htmlspecialchars($text);
    }

    $html = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match($pattern, $part) === 1) {
            $href = $part;
            if (stripos($href, 'http://') !== 0 && stripos($href, 'https://') !== 0) {
                $href = $part;
            }
            $html .= '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener noreferrer">'
                . htmlspecialchars($part) . '</a>';
            continue;
        }
        $html .= nl2br(htmlspecialchars($part));
    }

    return $html;
};
?>
<div class="row chat-row">
    <div class="col-lg-4 mb-3">
        <div class="chat-sidebar shadow-sm rounded">
            <div class="chat-sidebar-header p-3 border-bottom">
                <h2 class="h6 mb-2">Tin nhắn</h2>
                <form method="post" action="index.php?r=message/start" class="chat-search-form">
                    <div class="input-group">
                        <input type="text" class="form-control search-input" name="peer_username" placeholder="Nhập tên hoặc username" autocomplete="off" required>
                        <div class="input-group-append">
                            <button class="btn btn-dark" type="submit">Tìm</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Nhập tên hiển thị hoặc username để bắt đầu chat. Với 1 ký tự chỉ tìm đúng tên/username.</small>
                </form>
            </div>

            <div class="chat-sidebar-body p-0">
                <div class="conversation-list">
                    <?php if (empty($conversations)): ?>
                        <div class="conversation-empty p-4 text-center text-muted">
                            Chưa có cuộc trò chuyện nào.
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <?php $isActive = $activeConversationId === (int) $conversation['id']; ?>
                            <a class="conversation-item d-flex align-items-center px-3 py-3 <?= $isActive ? 'active' : '' ?>"
                               href="index.php?r=message/inbox&conversation_id=<?= (int) $conversation['id'] ?>">
                                <div class="conversation-avatar mr-3">
                                    <?= htmlspecialchars(substr($conversation['peer_name'] ?: 'Người dùng', 0, 1)) ?>
                                </div>
                                <div class="conversation-content flex-fill">
                                    <div class="conversation-name font-weight-bold mb-1"><?= htmlspecialchars($conversation['peer_name'] ?: 'Người dùng') ?></div>
                                    <div class="conversation-snippet text-muted small text-truncate"><?= htmlspecialchars($conversation['last_message'] ?: 'Bắt đầu trò chuyện...') ?></div>
                                </div>
                                <?php if (($conversation['unread_count'] ?? 0) > 0): ?>
                                    <div class="unread-dot ml-2"></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="chat-panel shadow-sm rounded">
            <?php if ($activeConversationId > 0): ?>
                <div class="chat-panel-header border-bottom p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="h5 mb-1"><?= htmlspecialchars($activePeerName ?: 'Cuộc trò chuyện') ?></div>
                        <div class="text-success small">Đang hoạt động</div>
                    </div>
                    <form method="post" action="index.php?r=message/delete" onsubmit="return confirm('Bạn có chắc muốn xóa cuộc trò chuyện này không?');">
                        <input type="hidden" name="conversation_id" value="<?= (int) $activeConversationId ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">Xóa cuộc trò chuyện</button>
                    </form>
                </div>
                <div class="chat-panel-body p-3">
                    <div class="message-list">
                        <?php if (empty($messages)): ?>
                            <div class="chat-empty-state text-center text-muted py-5">Chưa có tin nhắn. Gửi tin nhắn đầu tiên nhé!</div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php $isSent = $msg['sender_id'] === $currentUserId; ?>
                                <div class="message-item <?= $isSent ? 'sent' : 'received' ?> mb-3">
                                    <?php if (!$isSent): ?>
                                        <div class="message-author font-weight-bold mb-1"><?= htmlspecialchars($msg['sender_name']) ?></div>
                                    <?php endif; ?>
                                    <div class="message-bubble p-3 shadow-sm">
                                        <?php $messageContent = trim((string) ($msg['content'] ?? '')); ?>
                                        <?php $sharedLink = !empty($msg['shared_pin_id']) ? 'index.php?r=pin/detail&id=' . (int) $msg['shared_pin_id'] : ''; ?>
                                        <?php $shouldHideContent = $sharedLink !== ''; ?>
                                        <?php if ($messageContent !== '' && !$shouldHideContent): ?>
                                            <?= $linkifyMessage($messageContent) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($msg['shared_pin_id'])): ?>
                                            <div class="shared-pin mt-2 small text-primary">
                                                <a href="<?= htmlspecialchars($sharedLink) ?>"><?= htmlspecialchars($sharedLink) ?></a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chat-panel-footer border-top p-3">
                    <form method="post" action="index.php?r=message/send" class="d-flex">
                        <input type="hidden" name="conversation_id" value="<?= (int) $activeConversationId ?>">
                        <input class="form-control mr-2 chat-input" type="text" name="content" placeholder="Nhập tin nhắn...">
                        <button class="btn btn-danger" type="submit">Gửi</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="chat-empty-panel d-flex flex-column justify-content-center align-items-center text-center p-5">
                    <div class="mb-3">
                        <strong>Chưa có cuộc trò chuyện nào</strong>
                    </div>
                    <div class="text-muted">Nhập tên người dùng bên trái để tạo cuộc trò chuyện mới hoặc chọn cuộc trò chuyện hiện có.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
