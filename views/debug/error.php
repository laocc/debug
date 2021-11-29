<div class="headDiv">
    <table class="layui-table">
        <tr>
            <td style="width:100px;">
                <a href="<?=$linkPath?>/debug/error_match" class="parent layui-btn" title="整理Error">整理显示</a>
            </td>
            <td></td>
        </tr>
    </table>
</div>
<style>
    .flex {
        flex-wrap: wrap;
    }

    .flex li {
        margin: 2px;
    }
</style>

<?php
echo "\n<ul class='flex'>";

foreach ($file as $i => $fil) {
    $tm = substr($fil, 2, 12);
    $class = '';
    $time = strtotime("20{$tm}");
    $name = date('Y-m-d H:i:s', $time);
    if (time() - $time < 86400) $class = 'layui-btn layui-btn-warm';
    if (time() - $time < 3600) $class = 'layui-btn-danger';
    echo "<li class='m05em'><a href='{$linkPath}/debug/error_view/{$fil}' class='layui-btn layui-btn-sm {$class} open'  width='1600' height='700' title='系统错误'>{$name}</a></li>";
}
echo "\n</ul>";
