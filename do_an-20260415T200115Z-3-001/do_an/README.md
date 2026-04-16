# Pinterest PHP MVC Skeleton

## Features
- PHP OOP + MVC structure.
- Front controller + router (`r=controller/action`).
- Base controller/model classes.
- Auth module: register, login, logout (session).
- Pin module: feed, detail, search, pagination.
- Save pin (`saved_pins`) and listing saved pins.
- Community sharing through Boards (`boards`, `board_pins`).
- Messaging with conversations (`conversations`, `conversation_members`, `messages`).
- Pinterest-like feed UI with responsive masonry layout.

## Quick start
1. Create MySQL database `db_pinterest`.
2. Import `database.sql`.
3. Update DB config in `config/app.php` if needed.
4. Run:
   - `php -S localhost:8000 -t .`
5. Open:
   - `http://localhost:8000/index.php?r=home/index`

## Main routes
- Feed/search: `index.php?r=home/index`
- Pin detail: `index.php?r=pin/detail&id=1`
- Saved pins: `index.php?r=pin/saved`
- Boards community: `index.php?r=board/index`
- Messages inbox: `index.php?r=message/inbox`

## Pagination logic
- `totalPages = ceil(totalItems / perPage)`
- `offset = (currentPage - 1) * perPage`

## Important notes
- `GET` for list/search/detail and pagination.
- `POST` for login/register and other write actions.
- Input validated with `isset` and sanitized through prepared statements.
