<?php
// Shared fonts include — prefers local font files if present and falls back to local system names and CDN.
$hasBPGArial = file_exists(__DIR__ . '/BPGArial.woff2');
$hasBPGArialCaps = file_exists(__DIR__ . '/BPGArialCaps.woff2');
?>
<!-- Google Fonts fallback for Georgian text -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;700&display=swap" rel="stylesheet">
<style>
/* Primary: local bundled fonts (place .woff2 files in /fonts/) */
@font-face {
  font-family: 'BPG Arial';
  src: local('BPG Arial'), local('BPGArial')<?php if ($hasBPGArial) echo ", url('/fonts/BPGArial.woff2') format('woff2')"; ?>,
       url('https://fonts.gstatic.com/s/notosansgeorgian/v28/PlZTJG1UNuW9XyNgWwE2bgI6sdaR3bCpzLKQwJ0.woff2') format('woff2');
  font-weight: 400 700;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'BPG Arial Caps';
  src: local('BPG Arial Caps'), local('BPGArialCaps')<?php if ($hasBPGArialCaps) echo ", url('/fonts/BPGArialCaps.woff2') format('woff2')"; ?>,
       url('https://fonts.gstatic.com/s/notosansgeorgian/v28/PlZTJG1UNuW9XyNgWwE2bgI6sdaR3bCpzLKQwJ0.woff2') format('woff2');
  font-weight: 700;
  font-style: normal;
  font-display: swap;
}

/* Fallbacks and global application */
html, body, * {
  font-family: 'BPG Arial Caps', 'BPG Arial', 'Noto Sans Georgian', Arial, sans-serif !important;
}
</style>
