<?php
declare(strict_types=1);
?>
<div class="community-boards-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0 font-weight-bold">Cộng đồng</h1>
    </div>

    <?php if (empty($boards)): ?>
        <div class="alert alert-light border">Chưa có board công khai nào.</div>
    <?php else: ?>
        <div class="row community-boards-row">
            <?php foreach ($boards as $board): ?>
                <div class="col-12 col-sm-6 col-lg-4 mb-4">
                    <?php
                    $showSaveForm = true;
                    $showDeleteForm = false;
                    $showBoardPrivacy = false;
                    require __DIR__ . '/card_collage.php';
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
