<style>
    body {
        background-color: #555555;
    }

    div.canvas {
        clear: both;
        padding: 0;
        box-shadow: 2px 2px 2px #ccc;
        height: 330px;
        width: 98%;
        margin: 10px auto;
        border: 1px solid #cccccc;
        background-color: #ffffff;
    }

</style>
<div class="canvas">
    <canvas id="table1" style="height:100%;width: 100%;"></canvas>
</div>
<script src="/public/resource/res/js/Chart.min.js"></script>
<script src="/public/resource/res/js/Chart.ext.js"></script>

<script>
    var config = {
        type: 'line',
        data: {
            labels: <?=$labels?>,
            datasets: [{
                label: '<?=$day?>',
                backgroundColor: window.chartColors.red,
                borderColor: window.chartColors.red,
                data: <?=$data?>,
                fill: false,
            }, {
                label: '<?=$day2?>',
                backgroundColor: window.chartColors.blue,
                borderColor: window.chartColors.blue,
                data: <?=$data2?>,
                fill: false,
            }, {
                label: '<?=$day3?>',
                backgroundColor: window.chartColors.green,
                borderColor: window.chartColors.green,
                data: <?=$data3?>,
                fill: false,
            }]
        },
        options: {
            responsive: true,
            title: {
                display: true,
                text: '<?=$title?>'
            },
            scales: {
                yAxes: [{ticks: {suggestedMin: 1, suggestedMax: 100}}]
            }
        }
    };

    window.onload = function () {
        var ctx = document.getElementById('table1').getContext('2d');
        window.myLine = new Chart(ctx, config);
    };

</script>

