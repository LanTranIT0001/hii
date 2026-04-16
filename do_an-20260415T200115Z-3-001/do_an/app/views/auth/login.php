<?php
declare(strict_types=1);
?>
<div class="auth-box card border-0 shadow-sm mx-auto">
    <div class="card-body">
        <h1 class="h4 mb-3">Đăng nhập</h1>
        <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'registered'): ?>
            <div class="alert alert-success">Đăng ký thành công, mời bạn đăng nhập.</div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php?r=auth/login">
            <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" name="email" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" class="form-control" name="password" required>
            </div>
            <button class="btn btn-danger btn-block" type="submit">Đăng nhập</button>
        </form>
    </div>
</div>
