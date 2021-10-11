<div id="body" class="fixedForm"
     xmlns:v-slot="http://www.w3.org/1999/XSL/Transform"
     xmlns:v-html="http://www.w3.org/1999/XSL/Transform">

    <el-form :inline="true" class="searchForm" onsubmit="return !1;">
        <el-form-item>
            <db-button class="btn ajax" url="/cache/flush/0" title="确认 清空Config？" @success="EmptyConfig">EmptyConfig</db-button>
            <db-button class="btn ajax" url="/cache/flush/1" title="确认 清空数据缓存？" @success="EmptyConfig">EmptyCache</db-button>
            <db-button class="btn ajax" url="/cache/resource/1" title="确认 重置ResourceRand？" @success="EmptyConfig">ResourceRand
            </db-button>
            <db-button class="btn parent" url="/cache/opcache/1" @success="">OpCache</db-button>
        </el-form-item>
    </el-form>
    <div style="padding:30px;margin:70px auto;">
        <?php
        \esp\helper\pre($data);
        ?>
    </div>
</div>
<script>
    let vm = new Vue({
        el: '#body',
        data() {
            return {}
        },
        methods: {
            EmptyConfig(res) {
                this.$notify({title: '操作完成', message: res.message, type: 'success', duration: 2000});
            }

        }
    });
</script>