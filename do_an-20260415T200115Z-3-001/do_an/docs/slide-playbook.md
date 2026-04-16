# Slide Playbook for PHP Pinterest Project

## Core stack
- Language: PHP (OOP), MVC architecture.
- Database: MySQL with CRUD + filtering + pagination.
- Frontend: Bootstrap-compatible responsive layout, customized to match Pinterest.

## Request methods
- Use `GET`: list, detail, search, filter, pagination.
- Use `POST`: register, login, create, update, delete, upload.

## Input handling pattern
- Always guard with `isset(...)`.
- Validate format/length/range before processing.
- Sanitize DB input (prefer prepared statements; otherwise `mysqli_real_escape_string`).

## MVC responsibilities
- Model: data access, table logic, validation rules close to domain.
- View: HTML/CSS rendering only.
- Controller: request routing, calling model/service, choosing view/response.

## SQL logic patterns
- Select list/detail with `WHERE`.
- Search with `LIKE`.
- Sort with `ORDER BY`.
- Aggregate with `COUNT`, `SUM`, optional `GROUP BY`.
- Pagination with `LIMIT offset, pageSize`.

## Pagination algorithm
1. Query total rows (`COUNT(*)`).
2. `totalPages = ceil(totalItems / pageSize)`.
3. `offset = (currentPage - 1) * pageSize`.
4. Query page data with `LIMIT offset, pageSize`.

## Auth/session/cookie
- Register: validate -> save user.
- Login: verify credentials -> set session.
- Logout: unset/destroy session.
- Session: primary state store for auth/cart.
- Cookie: lightweight client-side state only.

## Upload flow
1. Form uses `method="post"` and `enctype="multipart/form-data"`.
2. Read from `$_FILES`.
3. Validate MIME/type/size.
4. Move file with unique filename.
5. Return status message for UI display.

## Cart flow (session-based)
- Initialize cart in session if missing.
- Add item: insert or update quantity.
- Update item quantity by key.
- Remove item by key.
- Compute cart total from line totals.

## Captcha flow
1. Generate random captcha text from source alphabet.
2. Save captcha in session.
3. Render PNG with proper no-cache headers.
4. Compare submitted text with stored value.

## Pinterest UI parity checklist
- Masonry-like multi-column card feed.
- Image-first cards with rounded corners and subtle shadow.
- Dense but readable spacing like Pinterest.
- Clear save/favorite call-to-action on cards.
- Responsive behavior across mobile/tablet/desktop breakpoints.
