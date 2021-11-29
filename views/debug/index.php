<?php
echo '<h4 style="padding:0.5em;">';
$pathA = explode('/', $path);
$dp = urlencode($debug);
echo "<a href='{$linkPath}/debug/index/{$dp}' class='blue'>{$debug}</a>";

foreach ($pathA as $p) {
    if (empty($p)) continue;
    $debug .= "/{$p}";
    $dp = urlencode($debug);
    echo "<a href='{$linkPath}/debug/index/{$dp}' class='blue'>/{$p}</a>";
}
echo "<a href='#' onclick='$(\"#form\").show();$(this).hide();return false;' style='float:right;color:#aaa;'>定位&筛选</a>\n";
echo "<a href='{$linkPath}/debug/ord/{$dp}' class='open' data-width='700' data-height='500'>查找</a>\n";
echo '</h4>';


echo <<<HTML
<div id="form" style="display: none;">
<form action="{$linkPath}/debug/index" method="get" autocomplete="off" style="margin:1em;width:600px;display:inline-block;float:left;">
    <input type="tel" name="path" class="layui-input" style="width:400px;display:inline-block;float:left;"
           placeholder="输入日志文件完整路径快速查看">
    <button class="layui-btn">定位</button>
</form>
<form action="?" method="get" autocomplete="off" style="margin:1em;width:400px;display:inline-block;float:left;">
    <input type="tel" name="key" class="layui-input" style="width:200px;display:inline-block;float:left;"
           placeholder="关键词搜索">
    <button class="layui-btn">筛选</button>
</form>

</div>
<style>
.flex{
flex-wrap:wrap;
}
.flex li{
margin:2px;
}
</style>
HTML;


$today = date('Y_m_d');
echo '<table class="layui-table mt10">';
foreach ($allDir[0] as $name => $tmp) {
    $p = urlencode($tmp);
    $color = 'blue';
    if ($today === $name) $color = 'red';
    if (preg_match('/(wx[a-f0-9]{16})(\..+)?/', $name, $mch)) {
        $ns = $site[$mch[1]] ?? null;
        if ($ns) {
            $name .= "({$ns['name']})";
        }
    }
    echo "<tr><td><a href='{$linkPath}/debug/index/{$p}' class='{$color}'>{$name}</a></td></tr>";
}
echo '</table>';

echo "\n<ul class='flex'>";
$key = $_GET['key'] ?? null;

$files = [];
foreach ($allDir[1] as $name => $tmp) {
    $v = explode('_', $name);
    if (!isset($v[1])) {
        $files[$name] = [$v, $tmp];
    } else {
        $files[intval("{$v[0]}{$v[1]}{$v[2]}")] = [$v, $tmp];
    }
}
ksort($files);
foreach ($files as $name => $tmp) {
    if ($key) {
        $html = file_get_contents($tmp[1]);
        if (!strpos($html, $key)) continue;
    }
    $p = urlencode($tmp[1]);
    if (!isset($tmp[0][1])) {
        $name = $tmp[0][0];//. "-{$tmp[0][3]}";
    } else {
        $name = implode(':', [$tmp[0][0], $tmp[0][1], $tmp[0][2]]);//. "-{$tmp[0][3]}";
    }
    echo "<li class='fs16'><a href='{$linkPath}/debug/file/{$p}' width='1600' height='750' class='open layui-btn layui-btn-xs'>{$name}</a></li>\n";
}
echo "</ul>\n";

