<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <title><?= $_title ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="renderer" content="webkit"/>

    <link rel="stylesheet" href="/public/vui/css/auto.css?__RAND__" media="all">
    <link rel="stylesheet" href="/public/vui/icon/icon.css?__RAND__" media="all">

    <link rel="stylesheet" href="/public/vui/node_modules/element-ui/lib/theme-chalk/index.css" media="all">
    <link rel="stylesheet" href="/public/vui/node_modules/layui-src/dist/css/layui.css" media="all">
    <link rel="stylesheet" href="/public/vui/css/element.patch.css" media="all">

    <link rel="stylesheet" href="/public/vui/css/patch.css?__RAND__" media="all">

    <link rel="stylesheet" href="/public/resource/css/admin.css?__RAND__" media="all">

    <script>const scriptHost = '/public/vui';</script>
    <script src="/public/vui/node_modules/jquery/dist/jquery.min.js"></script>
    <script src="/public/vui/node_modules/vue/dist/vue.js"></script>
    <script src="/public/vui/node_modules/http-vue-loader/src/httpVueLoader.js"></script>
    <script src="/public/vui/node_modules/layui-src/dist/layui.all.js"></script>
    <script src="/public/vui/node_modules/element-ui/lib/index.js"></script>

    <script src="/public/vui/js/extend/utf8.js?__RAND__"></script>
    <script src="/public/vui/js/extend/md5.js?__RAND__"></script>
    <script src="/public/vui/js/prototype.js?__RAND__"></script>

    <script src="/public/vui/extend/layui.extend.js?__RAND__"></script>
    <script src="/public/vui/extend/layui.patch.js?__RAND__"></script>
    <script src="/public/vui/extend/vue.patch.js?__RAND__"></script>
    <script src="/public/vui/js/progress.js?__RAND__"></script>

    <?= $_css ?>
</head>
<body>
<script>
    let login = <?=$login ?? '{}'?>;
    let formData = <?=$data ?? '{}'?>;
</script>
<script src="/public/resource/js/mixin.js?__RAND__"></script>
<?php
echo $_view_html;
?>
<script>
    // Progress.star();
</script>
</body>
</html>