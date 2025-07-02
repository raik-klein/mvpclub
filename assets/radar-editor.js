jQuery(function($){
    var ctx = document.getElementById('mvpclub-radar-preview');
    if(!ctx || typeof Chart === 'undefined'){return;}

    function getLabels(){
        return $('input[name^="radar_chart_label"]').map(function(){
            return $(this).val();
        }).get();
    }
    function getValues(){
        return $('input[name^="radar_chart_value"]').map(function(){
            var v = parseInt($(this).val(),10);
            return isNaN(v) ? 0 : v;
        }).get();
    }

    var chart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: getLabels(),
            datasets: [{
                label: 'Vorschau',
                data: getValues(),
                backgroundColor: 'rgba(54,162,235,0.2)',
                borderColor: 'rgba(54,162,235,1)'
            }]
        },
        options: {
            scales: { r: { min: 0, max: 100, beginAtZero: true } }
        }
    });

    $('input[name^="radar_chart_label"], input[name^="radar_chart_value"]').on('input change', function(){
        chart.data.labels = getLabels();
        chart.data.datasets[0].data = getValues();
        chart.update();
    });
});
