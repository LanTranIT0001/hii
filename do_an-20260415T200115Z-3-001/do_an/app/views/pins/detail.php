<?php
declare(strict_types=1);
?>
<article class="pin-detail-page mx-auto">
    <div class="row no-gutters">
        <div class="col-lg-7">
            <img class="pin-detail-image" src="<?= htmlspecialchars($pin['image_url']) ?>" alt="<?= htmlspecialchars($pin['title']) ?>">
        </div>
        <div class="col-lg-5">
            <div class="pin-detail-sidebar">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button type="button" class="btn btn-light btn-sm" id="copy-pin-link">Sao chép liên kết</button>
                    <?php if (isset($_SESSION['user'])): ?>
                        <?php if (!empty($isSaved)): ?>
                            <form method="post" action="index.php?r=pin/unsave">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-dark btn-sm" type="submit">Đã Lưu</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="index.php?r=pin/save">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Lưu</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <h1 class="h3 mb-2"><?= htmlspecialchars($pin['title']) ?></h1>
                <p class="text-muted mb-3"><?= nl2br(htmlspecialchars((string) $pin['description'])) ?></p>

                <?php if (!empty($_GET['report']) && $_GET['report'] === 'success'): ?>
                    <div class="alert alert-success py-2">Đã gửi báo cáo pin tới admin để kiểm duyệt.</div>
                <?php elseif (!empty($_GET['report']) && $_GET['report'] === 'failed'): ?>
                    <div class="alert alert-danger py-2">Không thể gửi báo cáo. Vui lòng thử lại.</div>
                <?php endif; ?>

                <div class="d-flex align-items-center justify-content-between border-top border-bottom py-2 mb-3">
                    <div class="small text-muted"><?= (int) $likeCount ?> lượt thích</div>
                    <div class="small text-muted"><?= count($comments) ?> bình luận</div>
                </div>

                <?php if (isset($_SESSION['user'])): ?>
                    <div class="d-flex flex-wrap mb-3">
                        <?php if (!empty($isLiked)): ?>
                            <form method="post" action="index.php?r=pin/unlike" class="mr-2 mb-2">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-outline-dark btn-sm" type="submit">Bo thich</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="index.php?r=pin/like" class="mr-2 mb-2">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm" type="submit">Thich</button>
                            </form>
                        <?php endif; ?>
                        <?php if ((int) ($_SESSION['user']['id'] ?? 0) !== (int) ($pin['user_id'] ?? 0)): ?>
                            <form method="post" action="index.php?r=pin/report" class="mr-2 mb-2">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <input type="hidden" name="reason" value="Nội dung vi phạm hoặc không phù hợp">
                                <button class="btn btn-outline-warning btn-sm" type="submit">Báo cáo pin</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="font-weight-bold"><?= htmlspecialchars((string) ($pin['author_name'] ?? 'Unknown')) ?></div>
                        <div class="small text-muted"><?= (int) $authorFollowerCount ?> người theo dõi</div>
                    </div>
                    <?php if (isset($_SESSION['user']) && (int) ($_SESSION['user']['id'] ?? 0) !== (int) ($pin['user_id'] ?? 0)): ?>
                        <?php if (!empty($isFollowingAuthor)): ?>
                            <form method="post" action="index.php?r=pin/unfollow">
                                <input type="hidden" name="author_id" value="<?= (int) ($pin['user_id'] ?? 0) ?>">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-outline-dark btn-sm" type="submit">Dang theo doi</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="index.php?r=pin/follow">
                                <input type="hidden" name="author_id" value="<?= (int) ($pin['user_id'] ?? 0) ?>">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-dark btn-sm" type="submit">Theo doi</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (isset($_SESSION['user']) && !empty($conversations)): ?>
                    <div class="share-chat-box mb-4">
                        <div class="font-weight-bold mb-2">Chia sẻ qua chat</div>
                        <form method="post" action="index.php?r=pin/shareChat" class="form-inline d-flex">
                            <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                            <select class="form-control form-control-sm mr-2 flex-grow-1" name="conversation_id" required>
                                <option value="">Chọn cuộc trò chuyện</option>
                                <?php foreach ($conversations as $conversation): ?>
                                    <option value="<?= (int) $conversation['id'] ?>"><?= htmlspecialchars((string) $conversation['peer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-danger btn-sm" type="submit">Gửi</button>
                        </form>
                        <div class="small text-muted mt-2">Tin nhắn sẽ gửi kèm liên kết pin này.</div>
                    </div>
                <?php endif; ?>

                <div class="comment-section">
                    <h2 class="h5 mb-3">Bình luận</h2>
                    <?php if (isset($_SESSION['user'])): ?>
                        <form method="post" action="index.php?r=pin/comment" class="mb-3">
                            <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                            <div class="input-group">
                                <input class="form-control" type="text" name="content" placeholder="Viết bình luận..." required>
                                <div class="input-group-append">
                                    <button class="btn btn-dark" type="submit">Đăng</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if (empty($comments)): ?>
                        <div class="text-muted small">Chưa có bình luận nào.</div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item mb-3">
                                <div class="font-weight-bold"><?= htmlspecialchars((string) ($comment['user_name'] ?? 'Người dùng')) ?></div>
                                <div><?= htmlspecialchars((string) $comment['content']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars((string) $comment['created_at']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</article>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var copyButton = document.getElementById('copy-pin-link');
    if (!copyButton) {
        return;
    }
    copyButton.addEventListener('click', function () {
        var link = <?= json_encode((string) $shareLink) ?>;
        navigator.clipboard.writeText(link).then(function () {
            copyButton.textContent = 'Da sao chep';
            setTimeout(function () {
                copyButton.textContent = 'Sao chép liên kết';
            }, 1200);
        });
    });
});
</script>
