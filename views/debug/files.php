<div style="padding:1em;">


<?php
$nFile = [];
foreach ($file as $i => $fil) {
    $tm = explode('_', $fil, 4);
    if (!isset($tm[2])) {
        $nFile[$fil] = $fil;
        continue;
    }
    $nFile["{$tm[0]}:{$tm[1]}:{$tm[2]}"] = $fil;
}
ksort($nFile);
foreach ($nFile as $i => $fil) {
    $full = urlencode("{$path}/{$fil}");
    echo "<a href='{$linkPath}/debug/file/{$full}' class='layui-btn layui-btn-sm open'  
        style='margin-right:1em;margin-bottom:1em;' width='1200' height='600' title='{$path}/{$fil}'>{$i}</a>";
}
echo '</div>';