<?php
declare(strict_types=1);
?>
<div class="card border-0 shadow-sm mx-auto" style="max-width: 640px;">
    <div class="card-body">
        <h1 class="h4 mb-3">Tao board moi</h1>
        <form method="post" action="index.php?r=board/create">
            <div class="form-group">
                <label>Ten board</label>
                <input class="form-control" type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Mo ta</label>
                <textarea class="form-control" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Quyen rieng tu</label>
                <select class="form-control" name="privacy">
                    <option value="PUBLIC">PUBLIC</option>
                    <option value="PRIVATE">PRIVATE</option>
                </select>
            </div>
            <button class="btn btn-danger" type="submit">Tao board</button>
        </form>
    </div>
</div>
