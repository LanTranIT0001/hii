# do_an_admin

Project admin tách riêng để kiểm duyệt pin bị báo cáo, dùng chung database với project user (`db_pinterest`).

## Chạy dự án

```bash
php -S localhost:8001 -t public
```

Mở: `http://localhost:8001/index.php?r=auth/login`

## Luồng chính

- Chỉ tài khoản có `role = ADMIN` (hoặc `admin`) mới đăng nhập được.
- Sau khi đăng nhập, admin chỉ vào một màn hình: `admin/dashboard`.
- Màn hình này hiển thị danh sách pin trong bảng `pin_reports` có `status = PENDING`.
- Khi admin xóa pin, hệ thống xóa pin khỏi bảng `pins` và các dữ liệu liên quan (`saved_pins`, `likes`, `comments`, ...), nên bên người dùng cũng không còn pin đó.

## Ghi chú database chung

- Project này dùng đúng cấu hình DB như dự án user.
- Nếu DB chưa có bảng `pin_reports`, hệ thống tự tạo bảng này khi mở dashboard admin.
