<?php
declare(strict_types=1);
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Đăng nhập Admin</h1>
                <p class="text-muted">Chỉ tài khoản có quyền admin mới truy cập được.</p>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars((string) $error) ?></div>
                <?php endif; ?>
                <form method="post" action="index.php?r=auth/login">
                    <div class="form-group">
                        <label>Tên đăng nhập</label>
                        <input type="text" class="form-control" name="username" value="Admin123" required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <input type="password" class="form-control" name="password" value="12345678910" required>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Vào trang kiểm duyệt</button>
                </form>
            </div>
        </div>
    </div>
</div>
