<?php
declare(strict_types=1);
?>
<div class="auth-box card border-0 shadow-sm mx-auto">
    <div class="card-body">
        <h1 class="h4 mb-3">Đăng ký</h1>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php?r=auth/register">
            <div class="form-group">
                <label>Họ tên</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" name="email" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" class="form-control" name="password" minlength="8" required>
            </div>
            <button class="btn btn-danger btn-block" type="submit">Tạo tài khoản</button>
        </form>
    </div>
</div>
