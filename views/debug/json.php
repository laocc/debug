<style>
    li {
        width: 30%;
        float: left;
        display: inline-block;
        margin-left: 5px;
    }
</style>
<?php
$nFile = [];
$tr = "<tr><td>%s</td><td><a href='/debug/file/%s' class='blue open' width='1200' height='600' >%s</a></td><td>%s</td></tr>";
foreach ($file as $i => $fil) {
    preg_match('/(\d{2})_(\d{2})_(\d{2})_(-{0,1}\d+)\((\d+)\)\.json/', $fil, $mch);
    $full = urlencode("{$path}/{$fil}");
    $nFile[intval($mch[4])] = sprintf($tr, $mch[4], $full, "{$mch[1]}:{$mch[2]}:{$mch[3]}", $mch[5]);
}
ksort($nFile);
echo '<ul><li><table class="layui-table mt0"><tr><td width="100">ID</td><td>时间</td><td width="100">推送</td></tr>';
$lin = 0;
foreach ($nFile as $i => $fil) {
    $lin++;
    echo $fil;
    if ($lin % 360 === 0) {
        echo "</table></li><li><table class=\"layui-table mt0\"><tr><td width=\"100\">ID</td><td>时间</td><td width=\"100\">推送</td></tr>";
    }
}
echo '</table></li></ul>';
