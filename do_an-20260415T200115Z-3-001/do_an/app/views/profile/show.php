<?php
declare(strict_types=1);

$avatarPath = trim((string) ($profileUser['avatar'] ?? ''));
$avatarUrl = $avatarPath !== '' ? $avatarPath : 'https://via.placeholder.com/120x120.png?text=Avatar';
$displayName = (string) ($profileUser['name'] ?? 'User');
$username = (string) ($profileUser['username'] ?? '');
$bio = trim((string) ($profileUser['bio'] ?? ''));

function profile_tab_url(string $tab, string $boardFilter = 'all'): string
{
    $query = ['r' => 'profile/show', 'tab' => $tab];
    if ($tab === 'boards') {
        $query['board_filter'] = $boardFilter;
    }
    return 'index.php?' . http_build_query($query);
}
?>

<section class="profile-page">
    <div class="profile-header-card">
        <div class="profile-avatar-wrap">
            <img class="profile-avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($displayName) ?>">
            <?php if ($isOwnProfile): ?>
                <form class="d-inline" method="post" action="index.php?r=profile/updateAvatar" enctype="multipart/form-data">
                    <label class="btn btn-light btn-sm mb-0">
                        Chọn ảnh
                        <input class="d-none" type="file" name="avatar" accept=".jpg,.jpeg,.png,.gif" onchange="this.form.submit()">
                    </label>
                </form>
            <?php endif; ?>
        </div>
        <h1 class="profile-name"><?= htmlspecialchars($displayName) ?></h1>
        <p class="profile-username">@<?= htmlspecialchars($username) ?></p>
        <p class="text-muted mb-2">
            <?= $bio !== '' ? htmlspecialchars($bio) : ($isOwnProfile ? 'Chưa có tiểu sử. Hãy cập nhật ở “Chỉnh sửa hồ sơ”.' : '') ?>
        </p>
        <?php if ($isOwnProfile): ?>
            <div class="mb-3">
                <button class="btn btn-outline-dark btn-sm js-open-modal" data-target="#editProfileModal" type="button">Chỉnh sửa hồ sơ</button>
            </div>
        <?php endif; ?>
        <div class="profile-metrics">
            <div class="profile-metric-item">
                <span class="profile-metric-value"><?= (int) $followers ?></span>
                <span class="profile-metric-label">Người theo dõi</span>
            </div>
            <div class="profile-metric-item">
                <span class="profile-metric-value"><?= (int) $following ?></span>
                <span class="profile-metric-label">Đang theo dõi</span>
            </div>
            <div class="profile-metric-item">
                <span class="profile-metric-value"><?= (int) $pinCount ?></span>
                <span class="profile-metric-label">Pins</span>
            </div>
        </div>
    </div>

    <div class="profile-tabs">
        <a class="profile-tab <?= $activeTab === 'pins' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('pins')) ?>">Pins</a>
        <a class="profile-tab <?= $activeTab === 'boards' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('boards', $boardFilter)) ?>">Boards</a>
        <a class="profile-tab <?= $activeTab === 'saved-pins' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('saved-pins')) ?>">Saved Pins</a>
        <a class="profile-tab <?= $activeTab === 'saved-boards' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('saved-boards')) ?>">Saved Boards</a>
    </div>

    <?php if ($activeTab === 'pins' && $isOwnProfile): ?>
        <div class="text-right mb-3">
            <button class="btn btn-primary btn-sm js-open-modal" data-target="#createPinModal" type="button">Tạo Pin mới</button>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'boards'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="Board filter">
                <a class="btn btn-outline-dark <?= $boardFilter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('boards', 'all')) ?>">Tất cả</a>
                <a class="btn btn-outline-dark <?= $boardFilter === 'public' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('boards', 'public')) ?>">Public</a>
                <a class="btn btn-outline-dark <?= $boardFilter === 'private' ? 'active' : '' ?>" href="<?= htmlspecialchars(profile_tab_url('boards', 'private')) ?>">Private</a>
            </div>
            <?php if ($isOwnProfile): ?>
                <button class="btn btn-primary btn-sm js-open-modal" data-target="#createBoardModal" type="button">Tạo board mới</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'pins'): ?>
        <?php if (empty($createdPins)): ?>
            <div class="alert alert-light border">Chưa có pin nào.</div>
        <?php else: ?>
            <section class="pin-grid">
                <?php foreach ($createdPins as $pin): ?>
                    <article class="pin-card">
                        <a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>">
                            <img src="<?= htmlspecialchars((string) $pin['image_url']) ?>" alt="<?= htmlspecialchars((string) $pin['title']) ?>">
                        </a>
                        <div class="pin-card-body">
                            <h2 class="pin-title"><a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>"><?= htmlspecialchars((string) $pin['title']) ?></a></h2>
                            <p class="pin-desc"><?= htmlspecialchars((string) $pin['description']) ?></p>
                            <?php if ($isOwnProfile): ?>
                                <div class="d-flex mt-2">
                                    <button
                                        class="btn btn-outline-primary btn-sm mr-2 js-open-edit-pin"
                                        type="button"
                                        data-target="#editPinModal"
                                        data-pin-id="<?= (int) $pin['id'] ?>"
                                        data-pin-title="<?= htmlspecialchars((string) $pin['title']) ?>"
                                        data-pin-description="<?= htmlspecialchars((string) $pin['description']) ?>"
                                        data-pin-category-label="<?= htmlspecialchars((string) ($pin['category_label'] ?? '')) ?>"
                                    >
                                        Sửa
                                    </button>
                                    <form method="post" action="index.php?r=profile/deletePin" onsubmit="return confirm('Bạn có chắc muốn xóa pin này không?');">
                                        <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Xóa</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php elseif ($activeTab === 'boards'): ?>
        <?php if (empty($createdBoards)): ?>
            <div class="alert alert-light border">Không có board phù hợp bộ lọc.</div>
        <?php else: ?>
            <div class="row community-boards-row">
                <?php foreach ($createdBoards as $board): ?>
                    <div class="col-12 col-sm-6 col-lg-4 mb-4">
                        <?php
                        $showSaveForm = false;
                        $showDeleteForm = $isOwnProfile;
                        $showBoardPrivacy = true;
                        require __DIR__ . '/../boards/card_collage.php';
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeTab === 'saved-pins'): ?>
        <?php if (empty($savedPins)): ?>
            <div class="alert alert-light border">Bạn chưa lưu pin nào.</div>
        <?php else: ?>
            <section class="pin-grid">
                <?php foreach ($savedPins as $pin): ?>
                    <article class="pin-card">
                        <a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>">
                            <img src="<?= htmlspecialchars((string) $pin['image_url']) ?>" alt="<?= htmlspecialchars((string) $pin['title']) ?>">
                        </a>
                        <div class="pin-card-body">
                            <h2 class="pin-title"><a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>"><?= htmlspecialchars((string) $pin['title']) ?></a></h2>
                            <p class="pin-desc">Tác giả: <?= htmlspecialchars((string) ($pin['author_name'] ?? '')) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($savedBoards)): ?>
            <div class="alert alert-light border">Chưa có Saved Board nào.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($savedBoards as $board): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <h2 class="h6 mb-1">
                                    <a href="index.php?r=board/detail&id=<?= (int) $board['id'] ?>"><?= htmlspecialchars((string) $board['name']) ?></a>
                                </h2>
                                <p class="text-muted small mb-2">by <?= htmlspecialchars((string) ($board['owner_name'] ?? '')) ?></p>
                                <p class="mb-0"><?= htmlspecialchars((string) $board['description']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($isOwnProfile): ?>
<div class="profile-modal" id="createPinModal" aria-hidden="true">
    <div class="profile-modal-card">
        <button class="profile-modal-close js-close-modal" type="button">Đóng</button>
        <h3 class="h5 mb-3">Tạo pin mới</h3>
        <form method="post" action="index.php?r=profile/createPin" enctype="multipart/form-data">
            <div class="form-group">
                <label>Ảnh pin</label>
                <input class="form-control-file" type="file" name="image" accept=".jpg,.jpeg,.png,.gif" required>
            </div>
            <div class="form-group">
                <label>Tiêu đề</label>
                <input class="form-control" type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Nội dung pin</label>
                <textarea class="form-control" name="description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Danh mục phân loại</label>
                <input class="form-control" type="text" name="category_label" placeholder="Ví dụ: Ẩm thực, Du lịch, Thời trang...">
            </div>
            <div class="text-right">
                <button class="btn btn-secondary js-close-modal" type="button">Hủy</button>
                <button class="btn btn-primary" type="submit">Xuất bản</button>
            </div>
        </form>
    </div>
</div>

<div class="profile-modal" id="createBoardModal" aria-hidden="true">
    <div class="profile-modal-card">
        <button class="profile-modal-close js-close-modal" type="button">Đóng</button>
        <h3 class="h5 mb-3">Tạo board mới</h3>
        <form method="post" action="index.php?r=profile/createBoard">
            <div class="form-group">
                <label>Tên board</label>
                <input class="form-control" type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Mô tả</label>
                <textarea class="form-control" name="description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Quyền riêng tư</label>
                <select class="form-control" name="privacy">
                    <option value="PUBLIC">Public</option>
                    <option value="PRIVATE">Private</option>
                </select>
            </div>
            <div class="text-right">
                <button class="btn btn-secondary js-close-modal" type="button">Hủy</button>
                <button class="btn btn-primary" type="submit">Tạo board</button>
            </div>
        </form>
    </div>
</div>

<div class="profile-modal" id="editPinModal" aria-hidden="true">
    <div class="profile-modal-card">
        <button class="profile-modal-close js-close-modal" type="button">Đóng</button>
        <h3 class="h5 mb-3">Sửa pin</h3>
        <form method="post" action="index.php?r=profile/updatePin">
            <input type="hidden" name="pin_id" id="edit_pin_id" value="">
            <div class="form-group">
                <label>Tiêu đề</label>
                <input class="form-control" type="text" name="title" id="edit_pin_title" required>
            </div>
            <div class="form-group">
                <label>Nội dung pin</label>
                <textarea class="form-control" name="description" id="edit_pin_description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Danh mục phân loại</label>
                <input class="form-control" type="text" name="category_label" id="edit_pin_category_label" placeholder="Ví dụ: Ẩm thực, Du lịch, Thời trang...">
            </div>
            <div class="text-right">
                <button class="btn btn-secondary js-close-modal" type="button">Hủy</button>
                <button class="btn btn-primary" type="submit">Cập nhật</button>
            </div>
        </form>
    </div>
</div>

<div class="profile-modal" id="editProfileModal" aria-hidden="true">
    <div class="profile-modal-card">
        <button class="profile-modal-close js-close-modal" type="button">Đóng</button>
        <h3 class="h5 mb-3">Chỉnh sửa hồ sơ</h3>
        <form method="post" action="index.php?r=profile/updateProfile">
            <div class="form-group">
                <label>Tên tài khoản</label>
                <input class="form-control" type="text" name="name" value="<?= htmlspecialchars($displayName) ?>" required>
            </div>
            <div class="form-group">
                <label>Tiểu sử</label>
                <textarea class="form-control" name="bio" rows="4"><?= htmlspecialchars($bio) ?></textarea>
            </div>
            <div class="text-right">
                <button class="btn btn-secondary js-close-modal" type="button">Hủy</button>
                <button class="btn btn-primary" type="submit">Cập nhật</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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

    var editButtons = document.querySelectorAll('.js-open-edit-pin');
    var editPinId = document.getElementById('edit_pin_id');
    var editPinTitle = document.getElementById('edit_pin_title');
    var editPinDescription = document.getElementById('edit_pin_description');
    var editPinCategoryLabel = document.getElementById('edit_pin_category_label');

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (editPinId) {
                editPinId.value = button.getAttribute('data-pin-id') || '';
            }
            if (editPinTitle) {
                editPinTitle.value = button.getAttribute('data-pin-title') || '';
            }
            if (editPinDescription) {
                editPinDescription.value = button.getAttribute('data-pin-description') || '';
            }
            if (editPinCategoryLabel) {
                editPinCategoryLabel.value = button.getAttribute('data-pin-category-label') || '';
            }
            openModal('#editPinModal');
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
