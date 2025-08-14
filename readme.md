# PHP To-Do List (SQLite)

A tiny PHP to-do app using SQLite + PDO with a Bootstrap UI.

## Quick Start
1. Place `index.php` on any PHP 8+ server (Apache/Nginx or `php -S localhost:8000`).
2. Visit the page — it will auto-create `data.sqlite`.
3. Add tasks, mark done, edit, delete. Filter and search included.

## Notes
- CSRF protection via PHP sessions.
- No external migrations needed; table is created automatically.
- To reset, delete `data.sqlite`.
