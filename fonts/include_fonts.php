<?php
// Shared fonts include — prefers local font files if present and falls back to local system names.
?>
<style>
/* Primary: local bundled fonts (place .woff2 files in /fonts/) */
<?php
$hasBPGArial = file_exists(__DIR__ . '/BPGArial.woff2');
$hasBPGArialCaps = file_exists(__DIR__ . '/BPGArialCaps.woff2');
?>
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
  font-family: 'BPG Arial Caps', 'BPG Arial', Arial, sans-serif !important;
}
</style>
