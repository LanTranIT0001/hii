<?php
declare(strict_types=1);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Pin Feed</h1>
    <small class="text-muted"><?= (int) $totalItems ?> kết quả</small>
</div>
<?php
$savedPinMap = array_fill_keys(array_map('intval', $savedPinIds ?? []), true);
$smartCategories = is_array($smartCategories ?? null) ? $smartCategories : [];
$activeCategory = trim((string) ($activeCategory ?? ''));
?>

<?php if (!empty($smartCategories)): ?>
    <div class="home-category-bar mb-3">
        <a class="home-category-chip <?= $activeCategory === '' ? 'active' : '' ?>"
           href="index.php?<?= htmlspecialchars(http_build_query(['r' => 'home/index', 'q' => $keyword])) ?>">
            Tất cả
        </a>
        <?php foreach ($smartCategories as $category): ?>
            <a class="home-category-chip <?= mb_strtolower($activeCategory) === mb_strtolower((string) $category) ? 'active' : '' ?>"
               href="index.php?<?= htmlspecialchars(http_build_query(['r' => 'home/index', 'q' => $keyword, 'category' => $category])) ?>">
                <?= htmlspecialchars((string) $category) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($pins)): ?>
    <div class="alert alert-light border">Không có pin phù hợp.</div>
<?php else: ?>
    <section class="pin-grid">
        <?php foreach ($pins as $pin): ?>
            <article class="pin-card">
                <a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>">
                    <img src="<?= htmlspecialchars($pin['image_url']) ?>" alt="<?= htmlspecialchars($pin['title']) ?>">
                </a>
                <div class="pin-overlay">
                    <?php if (isset($_SESSION['user'])): ?>
                        <?php $isSaved = isset($savedPinMap[(int) $pin['id']]); ?>
                        <form method="post" action="index.php?r=<?= $isSaved ? 'pin/unsave' : 'pin/save' ?>" class="js-save-form">
                            <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                            <button class="save-btn <?= $isSaved ? 'saved' : '' ?>" type="submit">
                                <?= $isSaved ? 'Đã Lưu' : 'Lưu' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a class="save-btn-link" href="index.php?r=auth/login">Lưu</a>
                    <?php endif; ?>
                </div>
                <div class="pin-card-body">
                    <h2 class="pin-title">
                        <a href="index.php?r=pin/detail&id=<?= (int) $pin['id'] ?>">
                            <?= htmlspecialchars($pin['title']) ?>
                        </a>
                    </h2>
                    <?php
                    $description = (string) $pin['description'];
                    if (function_exists('mb_strimwidth')) {
                        $description = mb_strimwidth($description, 0, 90, '...');
                    } elseif (strlen($description) > 90) {
                        $description = substr($description, 0, 87) . '...';
                    }
                    ?>
                    <p class="pin-desc"><?= htmlspecialchars($description) ?></p>
                    <?php if (!empty($pin['category_label'])): ?>
                        <p class="pin-desc"><span class="badge badge-light">#<?= htmlspecialchars((string) $pin['category_label']) ?></span></p>
                    <?php endif; ?>
                    <p class="pin-desc">by <?= htmlspecialchars((string) ($pin['author_name'] ?? '')) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="index.php?<?= http_build_query(['r' => 'home/index', 'q' => $keyword, 'category' => $activeCategory, 'page' => $i]) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php if (isset($_SESSION['user'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.js-save-form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('.save-btn');
            if (!button || button.dataset.loading === '1') {
                return;
            }

            button.dataset.loading = '1';
            var originalText = button.textContent;
            var isSaved = button.classList.contains('saved');
            button.textContent = isSaved ? 'Đang bỏ lưu...' : 'Đang lưu...';

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    if (isSaved) {
                        button.classList.remove('saved');
                        button.textContent = 'Lưu';
                        form.action = 'index.php?r=pin/save';
                    } else {
                        button.classList.add('saved');
                        button.textContent = 'Đã Lưu';
                        form.action = 'index.php?r=pin/unsave';
                    }
                    button.dataset.loading = '0';
                    return;
                }
                button.textContent = originalText;
                button.dataset.loading = '0';
            })
            .catch(function () {
                button.textContent = originalText;
                button.dataset.loading = '0';
            });
        });
    });
});
</script>
<?php endif; ?>
