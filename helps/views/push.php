<style>
    i.rate {
        display: inline-block;
        width: 50px;
        padding: 1px 3px;
        color: #fff;
        text-align: center;
        float: left;
        margin-left: 1px;
        height: 20px;
    }

    span.agent {
        display: inline-block;
        width: 72px;
        /*float: left;*/
        height: 24px;
        line-height: 24px;
        background: #eee;
        text-align: center;
        overflow: hidden;
    }

    span.lev {
        display: inline-block;
        width: 72px;
        overflow: hidden;
        color: #eee;
        /*float: left;*/
        height: 24px;
        line-height: 24px;
        /*background: #b6e6a5;*/
    }
</style>
<table class="layui-table mt10">
    <tbody>
    <?php

    function showAgent($host, array &$dir, $tr, int $lev)
    {
        foreach ($dir as $n => $rs) {
            $span = str_repeat("<span class='lev'>---------------</span>", $lev);
            if (empty($rs)) {
                $url = urlencode("{$host}/{$n}");
                $bar = "<a href='/debug/files/{$url}/json' width='1600' height='750' title='{$host}/{$n}' class='open layui-btn layui-btn-xs'>{$host}/{$n}</a>";
                echo sprintf($tr, $span, $bar, "");
            } else {
                $url = urlencode("{$host}/{$n}");
                echo sprintf($tr, $span, "<span class='layui-btn layui-btn-xs layui-btn-disabled'>{$n}</span>", "<a href='/debug/del/{$url}' class='red ml5 ajax'>Del</a>");
                showAgent("{$host}/{$n}", $rs, $tr, $lev + 1);
            }
        }
    }

    $host = _RUNTIME . '/push';
    $tr = "<tr><td>%s%s%s</td></tr>";
    $lev = 0;
    //    showAgent($host, $allDir, $tr, $lev);

    ?>

    </tbody>

</table>

