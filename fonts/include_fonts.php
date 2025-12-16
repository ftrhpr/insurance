<?php
// Shared fonts include — prefers local font files if present and falls back to local system names.
?>
<style>
/* Primary: local bundled fonts (place .woff2 files in /fonts/) */
<?php
$hasBPGArial = file_exists(__DIR__ . '/BPGArial.woff2');
$hasBPGArialCaps = file_exists(__DIR__ . '/BPGArialCaps.woff2');
$useCdnFallback = (!$hasBPGArial || !$hasBPGArialCaps);
?>
<?php if ($useCdnFallback): ?>
/* CDN fallback: Google Fonts Noto Sans (supports Georgian) */
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&display=swap');
<?php endif; ?>
@font-face {
  font-family: 'BPG Arial';
  src: local('BPG Arial'), local('BPGArial')<?php if ($hasBPGArial) echo ", url('/fonts/BPGArial.woff2') format('woff2')"; ?>;
  font-weight: 400 700;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'BPG Arial Caps';
  src: local('BPG Arial Caps'), local('BPGArialCaps')<?php if ($hasBPGArialCaps) echo ", url('/fonts/BPGArialCaps.woff2') format('woff2')"; ?>;
  font-weight: 700;
  font-style: normal;
  font-display: swap;
}

/* Fallbacks and global application */
html, body, * {
  /* If local BPG fonts are missing, 'Noto Sans' (imported above) will be used as the next fallback */
  font-family: 'BPG Arial Caps', 'BPG Arial', 'Noto Sans', Arial, sans-serif !important;
}
</style>
