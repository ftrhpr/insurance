# Backup Tool (FX)

Usage:

- Create a backup (prefix default `fx`):
  - `php tools/fx.php backup` -> creates backups/fx-YYYYmmdd-HHMMSS.zip
  - `php tools/fx.php backup myprefix` -> creates backups/myprefix-...zip

- List backups:
  - `php tools/fx.php list` -> lists backups with default prefix `fx`
  - `php tools/fx.php list myprefix` -> list backups for `myprefix`

- Restore a backup:
  - `php tools/fx.php restore latest` -> restores the latest `fx` backup
  - `php tools/fx.php restore fx` -> restores latest `fx` backup
  - `php tools/fx.php restore fx-20251213-120000.zip` -> restores exact file

Convenience wrapper scripts:
- `restore` (Unix) and `restore.bat` (Windows) wrappers call the PHP tool.
  Example: `./restore fx`
  Example (Windows): `restore fx`

Notes:
- Backups include project files with extensions: .php, .sql, .json, .js, .css, .html, .md, .txt
- Backups exclude the `backups/` folder and `.git/` folder.
- Restores overwrite files already present in the repo.
- This tool is intentionally simple and uses ZipArchive; your environment must have PHP CLI and Zip extension enabled.
