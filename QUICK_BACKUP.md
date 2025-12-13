# Quick Backup & Restore with 'fx'

Use the included tools to create and restore backups of your project files quickly.

Examples:

- Create a backup with default prefix 'fx':
  - `php tools/fx.php backup`

- Restore the latest 'fx' backup using wrapper:
  - POSIX: `./restore fx`
  - Windows: `restore.bat fx`

- List backups:
  - `php tools/fx.php list fx`

Notes:
- Backups are stored in `/backups` and `backups/` is added to `.gitignore`.
- If not using PHP CLI, run the `fx.php` script on the server environment where PHP CLI and Zip extension are available.
