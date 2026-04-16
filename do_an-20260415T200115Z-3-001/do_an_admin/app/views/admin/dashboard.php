<?php
declare(strict_types=1);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Kiểm duyệt pin bị báo cáo</h1>
    <span class="badge badge-secondary">Tổng: <?= count($reportedPins ?? []) ?></span>
</div>

<?php if (!empty($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success">Đã xóa pin thành công khỏi hệ thống người dùng.</div>
<?php elseif (!empty($_GET['msg']) && $_GET['msg'] === 'error'): ?>
    <div class="alert alert-danger">Không thể xóa pin. Vui lòng thử lại.</div>
<?php endif; ?>

<?php if (empty($reportedPins)): ?>
    <div class="alert alert-info mb-0">Hiện chưa có pin nào đang ở trạng thái bị báo cáo.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white">
            <thead class="thead-light">
                <tr>
                    <th>ID Pin</th>
                    <th>Hình ảnh</th>
                    <th>Tiêu đề</th>
                    <th>Tác giả</th>
                    <th>Số lượt báo cáo</th>
                    <th>Báo cáo gần nhất</th>
                    <th class="text-center">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportedPins as $pin): ?>
                    <tr>
                        <td>#<?= (int) $pin['id'] ?></td>
                        <td style="width: 130px;">
                            <?php if (!empty($pin['image_url'])): ?>
                                <img src="<?= htmlspecialchars((string) $pin['image_url']) ?>" alt="" class="img-fluid rounded">
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($pin['title'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($pin['author_name'] ?? '')) ?></td>
                        <td><?= (int) ($pin['report_count'] ?? 0) ?></td>
                        <td><?= htmlspecialchars((string) ($pin['latest_report_at'] ?? '')) ?></td>
                        <td class="text-center">
                            <form method="post" action="index.php?r=admin/deletePin" onsubmit="return confirm('Xóa pin này khỏi toàn hệ thống?');">
                                <input type="hidden" name="pin_id" value="<?= (int) $pin['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Xóa pin</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
