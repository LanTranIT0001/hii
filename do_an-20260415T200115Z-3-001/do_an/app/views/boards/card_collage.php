<?php
declare(strict_types=1);
/**
 * Thẻ board kiểu Pinterest: collage 2/3 + 1/3, board trống = nền xám. Không có nút "Tạo".
 *
 * @var array<string, mixed> $board
 * @var bool $showSaveForm
 * @var bool $showDeleteForm
 * @var bool $showBoardPrivacy
 */
$showSaveForm = $showSaveForm ?? false;
$showDeleteForm = $showDeleteForm ?? false;
$showBoardPrivacy = $showBoardPrivacy ?? false;
$viewerId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
$ownerId = (int) ($board['user_id'] ?? 0);
$isOwnBoard = $viewerId > 0 && $ownerId > 0 && $ownerId === $viewerId;
$previewImages = isset($board['preview_images']) && is_array($board['preview_images'])
    ? $board['preview_images']
    : [];
$pinCount = (int) ($board['pin_count'] ?? 0);
$img0 = isset($previewImages[0]) ? (string) $previewImages[0] : '';
$img1 = isset($previewImages[1]) ? (string) $previewImages[1] : '';
$img2 = isset($previewImages[2]) ? (string) $previewImages[2] : '';
?>
<div class="community-board-card">
    <a class="community-board-card-link" href="index.php?r=board/detail&id=<?= (int) $board['id'] ?>">
        <?php if ($pinCount === 0): ?>
            <div class="community-board-collage community-board-collage--empty" aria-hidden="true"></div>
        <?php else: ?>
            <div class="community-board-collage">
                <div class="community-board-collage-main">
                    <?php if ($img0 !== ''): ?>
                        <img src="<?= htmlspecialchars($img0) ?>" alt="">
                    <?php else: ?>
                        <span class="community-board-collage-placeholder"></span>
                    <?php endif; ?>
                </div>
                <div class="community-board-collage-side">
                    <div class="community-board-collage-cell">
                        <?php if ($img1 !== ''): ?>
                            <img src="<?= htmlspecialchars($img1) ?>" alt="">
                        <?php else: ?>
                            <span class="community-board-collage-placeholder"></span>
                        <?php endif; ?>
                    </div>
                    <div class="community-board-collage-cell">
                        <?php if ($img2 !== ''): ?>
                            <img src="<?= htmlspecialchars($img2) ?>" alt="">
                        <?php else: ?>
                            <span class="community-board-collage-placeholder"></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="community-board-card-meta">
            <div class="community-board-card-title"><?= htmlspecialchars((string) $board['name']) ?></div>
            <div class="community-board-card-count"><?= (int) $pinCount ?> Ghim</div>
            <?php if ($showBoardPrivacy && !empty($board['privacy'])): ?>
                <div class="community-board-card-privacy"><?= htmlspecialchars((string) $board['privacy']) ?></div>
            <?php endif; ?>
        </div>
    </a>
    <?php if ($showSaveForm && isset($_SESSION['user']) && !$isOwnBoard): ?>
        <form class="community-board-save-form mt-2" method="post" action="index.php?r=<?= ((int) ($board['is_saved'] ?? 0) === 1) ? 'board/unsave' : 'board/save' ?>">
            <input type="hidden" name="board_id" value="<?= (int) $board['id'] ?>">
            <button class="btn btn-sm <?= ((int) ($board['is_saved'] ?? 0) === 1) ? 'btn-secondary' : 'btn-outline-dark' ?>" type="submit">
                <?= ((int) ($board['is_saved'] ?? 0) === 1) ? 'Đã Lưu' : 'Lưu' ?>
            </button>
        </form>
    <?php endif; ?>
    <?php if ($showDeleteForm): ?>
        <form class="community-board-save-form mt-2" method="post" action="index.php?r=profile/deleteBoard" onsubmit="return confirm('Bạn có chắc muốn xóa board này không?');">
            <input type="hidden" name="board_id" value="<?= (int) $board['id'] ?>">
            <button class="btn btn-outline-danger btn-sm" type="submit">Xóa</button>
        </form>
    <?php endif; ?>
</div>
