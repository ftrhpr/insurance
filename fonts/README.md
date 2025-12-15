BPG Arial font placeholder

This project prefers the BPG Arial family (BPG Arial Caps / BPG Arial) for Georgian typography.

How to bundle real fonts:
1. Obtain the BPG Arial font files (e.g., `BPGArial.woff2`, `BPGArialCaps.woff2`) and place them in this `fonts/` directory.
2. Add @font-face rules in `header.php` or a shared CSS file pointing to the local files, for example:

```css
@font-face {
  font-family: 'BPG Arial';
  src: url('/fonts/BPGArial.woff2') format('woff2');
  font-weight: 400 700;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'BPG Arial Caps';
  src: url('/fonts/BPGArialCaps.woff2') format('woff2');
  font-weight: 700;
  font-style: normal;
  font-display: swap;
}
```

3. Remove/disable the Google Fonts link if you want to rely solely on local fonts.

License note: Ensure you have rights to distribute the font files before committing them.
