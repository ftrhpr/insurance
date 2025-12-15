BPG Arial font placeholder

This project prefers the BPG Arial family (BPG Arial Caps / BPG Arial) for Georgian typography.

Bundled font filenames (expected):
- `BPGArial.woff2`         — regular/bold weights (400..700)
- `BPGArialCaps.woff2`     — uppercase/caps display weight (700)

Steps to enable bundled fonts:
1. Place the above `.woff2` files into the `fonts/` directory in the project root.
2. The project already includes `fonts/include_fonts.php` which contains @font-face rules referencing these files.
   - The include is automatically added to page heads in this repo; no further edits required.
3. (Optional) Remove or comment out Google Fonts `<link>` tags in page heads if you want to avoid remote font loading.

Security & licensing:
- Ensure you have the legal right to distribute these font files before committing them to the repo.

If you want, I can add placeholder `.woff2` files (zero-byte stubs) or update the include to point to a CDN instead.
