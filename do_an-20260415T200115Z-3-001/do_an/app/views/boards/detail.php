<?php
declare(strict_types=1);
?>
<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between">
        <h1 class="h4 mb-1"><?= htmlspecialchars($board['name']) ?></h1>
        <?php if (!empty($isOwner)): ?>
            <button class="btn btn-dark btn-sm rounded-circle board-plus-btn js-open-modal" data-target="#attachSavedPinModal" type="button" aria-label="Thêm pin đã lưu">+</button>
        <?php endif; ?>
    </div>
    <p class="text-muted mb-0">by <?= htmlspecialchars($board['owner_name']) ?> - <?= htmlspecialchars($board['privacy']) ?></p>
</div>
<?php if (!empty($board['description'])): ?>
    <p><?= htmlspecialchars((string) $board['description']) ?></p>
<?php endif; ?>

<?php if (!empty($_GET['attach']) && $_GET['attach'] === 'ok'): ?>
    <div class="alert alert-success py-2">Đã thêm pin vào board.</div>
<?php elseif (!empty($_GET['attach']) && $_GET['attach'] === 'failed'): ?>
    <div class="alert alert-danger py-2">Không thể thêm pin vào board.</div>
<?php elseif (!empty($_GET['attach']) && $_GET['attach'] === 'denied'): ?>
    <div class="alert alert-warning py-2">Bạn chỉ được thêm pin đã lưu vào board của chính mình.</div>
<?php endif; ?>

<?php if (empty($pins)): ?>
    <div class="alert alert-light border">Board nay chua co pin.</div>
<?php else: ?>
    <section class="pin-grid">
        <?php foreach ($pins as $pin): ?>
            <article class="pin-card">
                <a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>">
                    <img src="<?= htmlspecialchars($pin['image_url']) ?>" alt="<?= htmlspecialchars($pin['title']) ?>">
                </a>
                <div class="pin-card-body">
                    <h2 class="pin-title">
                        <a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>"><?= htmlspecialchars($pin['title']) ?></a>
                    </h2>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (!empty($isOwner)): ?>
    <div class="profile-modal" id="attachSavedPinModal" aria-hidden="true">
        <div class="profile-modal-card">
            <button class="profile-modal-close js-close-modal" type="button">Đóng</button>
            <h3 class="h5 mb-3">Thêm pin đã lưu vào board</h3>

            <?php if (empty($savedPinsForAttach)): ?>
                <div class="alert alert-light border mb-0">Bạn chưa có pin đã lưu nào để thêm hoặc tất cả đã nằm trong board này.</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($savedPinsForAttach as $savedPin): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <img class="card-img-top" src="<?= htmlspecialchars((string) $savedPin['image_url']) ?>" alt="<?= htmlspecialchars((string) $savedPin['title']) ?>" style="object-fit: cover; max-height: 160px;">
                                <div class="card-body">
                                    <h4 class="h6"><?= htmlspecialchars((string) $savedPin['title']) ?></h4>
                                    <form method="post" action="index.php?r=board/attachSavedPin">
                                        <input type="hidden" name="board_id" value="<?= (int) $board['id'] ?>">
                                        <input type="hidden" name="pin_id" value="<?= (int) $savedPin['id'] ?>">
                                        <button class="btn btn-primary btn-sm" type="submit">Thêm vào board</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var openButtons = document.querySelectorAll('.js-open-modal');
        var closeButtons = document.querySelectorAll('.js-close-modal');

        function openModal(selector) {
            var modal = document.querySelector(selector);
            if (!modal) {
                return;
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal(modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button.getAttribute('data-target'));
            });
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var modal = button.closest('.profile-modal');
                if (modal) {
                    closeModal(modal);
                }
            });
        });

        document.querySelectorAll('.profile-modal').forEach(function (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });
    });
    </script>
<?php endif; ?>
