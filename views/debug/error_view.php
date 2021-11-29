<form action="<?=$linkPath?>/debug/file" method="get" autocomplete="off" style="width:90%;margin:0 auto;margin-top:1em;">
    <input type="tel" name="file" class="layui-input" style="width:1000px;display: inline-block"
           value="<?= $debug ?>" placeholder="输入日志文件完整路径快速查看">
    <button class="layui-btn">查看</button>
    <a class="layui-btn ajax" title="确认删除该文件？"
       href="<?=$linkPath?>/debug/error_del/<?= urlencode($file) . '/' . $warn ?>">删除</a>
</form>

<article class="markdown" style="width:90%;margin:0 auto;">
    <?= $html ?>
</article>
