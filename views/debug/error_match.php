<div style="padding:1em;">
    <table class="layui-table">
        <tbody class="layui-form">

        <?php

        foreach ($value as $i => $rs) {
            $e = urlencode($rs['er'] ?? 'null');
            $n = urlencode($i);
            $tm = substr($i, 2, 12);
//            $fl = urlencode($rs['fl'] ?? '');

            $rs['fn'] = "{$rs['fn']}<a class=\"blue ajax\" href=\"{$linkPath}/debug/error_delete/{$e}/{$warn}\">删除相同</a>/";
            $rs['fn'] .= "<a class=\"blue ajax\" href=\"{$linkPath}/debug/error_del/{$n}/{$warn}\">删除本条</a>";
            $rs['time'] = date('Y-m-d H:i:s', strtotime("20{$tm}"));
            $rs['time'] = "<a href='{$linkPath}/debug/error_view/{$n}/{$warn}' class='open blue' width='1600' height='700'>{$rs['time']}</a>";
            \esp\helper\pre($rs);
        }

        ?>
        </tbody>
    </table>

</div>