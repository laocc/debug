<div style="padding:1em;">
    <form action="/debug/ord/<?= $path ?>" class="layui-form layui-form-pane" method="get" autocomplete="off">

        <div class="layui-form-item">
            <label class="layui-form-label">关键词</label>
            <div class="layui-input-block">
                <input type="tel" name="key" class="layui-input"
                       placeholder="关键词" required value="<?= $key ?>">
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn icon_save">提交保存</button>
            </div>
        </div>

        <div class="layui-form-item">
            <textarea cols="30" rows="8" style="line-height: 1.2em;font-size:16px;"
                      class="layui-textarea"><?= $order ?></textarea>
        </div>


    </form>
</div>
