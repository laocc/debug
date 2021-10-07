<div class="headDiv">
    <form class="layui-form" action="/debug/mysql/" method="get">
        <table class="layui-table">
            <tr>
                <td style="width:170px;">
                    <select name="module" lay-verify="required" class="layui-input" lay-search>
                        <?php
                        $s = ($mode === '' ? 'selected' : '');
                        echo "<option {$s} value=''>所有虚拟机</option>";
                        foreach ($module as $k => $rs) {
                            $s = ($mode === $rs ? 'selected' : '');
                            echo "<option value='{$rs}' {$s}>{$rs}</option>";
                        }
                        ?>
                    </select>
                </td>
                <td style="width:150px;">
                    <input type="text" placeholder="日期" readonly value="<?= $day ?>" class="layui-input layDate"
                           name="day">
                </td>
                <td>
                    <button class="layui-btn icon_search">查询</button>
                </td>

            </tr>
        </table>
    </form>
</div>
<style>
    td.warn {
        background-color: #ff001f;
        color: #fff;
    }
</style>
<table class="layui-table mt10 mb150">
    <thead class="layui-form">
    <tr>
        <th width="260">Controller</th>
        <?php
        $label = $val = [];
        for ($h = 0; $h < 24; $h++) {
            echo "<th>{$h}</th>";
            $label[] = $h;
            $val[] = 0;
        }
        ?>

    </tr>
    </thead>
    <tbody class="layui-form">

    <?php

    $label = urlencode(json_encode($label));
    foreach ($data as $i => $rs) {
        $value = $val;
        $title = urlencode("{$rs['debugModule']}-{$rs['debugController']}-{$rs['debugAction']}");
        $td = '';
        for ($h = 0; $h < 24; $h++) {
            $n = intval($rs[$h] ?? 0);
            $c = $n > ($rs['average'] * 2) ? 'red' : '';
            if ($n > ($rs['average'] * 3)) $c = 'warn';
            $td .= "<td class='{$c}'>" . ($rs[$h] ?? '') . "</td>";
            $value[$h] = $n;
        }
        $value = urlencode(json_encode($value));
        echo "<tr><td>";
        echo "<a href='/debug/table/?day={$day}&title={$title}&labels={$label}&data={$value}' class='open' data-title='false' data-width='1000' data-height='350'>";
        echo "{$rs['debugModule']}/{$rs['debugController']}->{$rs['debugAction']}";
        echo "</a></td>{$td}</tr>";
    }
    ?>
    </tbody>
    <tfoot>
    <tr>
        <td colspan="100">
        </td>
    </tr>
    </tfoot>

</table>
