<?php
// Shared fonts include — prefers local font files if present and falls back to local system names.
$hasBPGArial = file_exists(__DIR__ . '/BPGArial.woff2');
$hasBPGArialCaps = file_exists(__DIR__ . '/BPGArialCaps.woff2');
$useCDN = !$hasBPGArial || !$hasBPGArialCaps;
if ($useCDN) {
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@400;700&display=swap">';
}
?>
<style>
/* Primary: local bundled fonts (place .woff2 files in /fonts/) */
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
  font-family: <?php echo $useCDN ? "'Noto Sans Georgian', " : ""; ?>'BPG Arial Caps', 'BPG Arial', Arial, sans-serif !important;
}
</style>
