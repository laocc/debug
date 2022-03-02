<?php
echo '<h4 style="padding:0.5em;">';
echo "<a href='{$linkPath}/debug/warn/' class='blue'>warn</a>";
echo '</h4>';


foreach ($folder as $i => $f) {
    echo "<li class='m05em float_left'><a href='/debug/warn/{$f}/' class='layui-btn layui-btn-sm'>{$f}</a></li>";
}

foreach ($file as $i => $fil) {
    [$fl, $fn] = explode('/', $fil);
    $tm = substr($fn, 2, 12);
    $class = '';
    $time = strtotime("20{$tm}");
    $name = date('Y-m-d H:i:s', $time);
    if (time() - $time < 86400) $class = 'layui-btn layui-btn-warm';
    if (time() - $time < 3600) $class = 'layui-btn-danger';
    $fil = urlencode($fil);
    echo "<li class='m05em float_left'><a href='{$linkPath}/debug/error_view/{$fil}/1' class='layui-btn layui-btn-sm {$class} open'  width='1600' height='700' title='系统错误'>{$name}</a></li>";
}
