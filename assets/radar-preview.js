(function($){
    $(function(){
        var canvas = document.getElementById('mvpclub_radar_preview');
        if(!canvas){return;}
        var labelInputs = [], valueInputs = [];
        for(var i=0;i<6;i++){
            labelInputs[i] = $('input[name="radar_chart_label'+i+'"]');
            valueInputs[i] = $('input[name="radar_chart_value'+i+'"]');
        }
        function collect(){
            var labels = labelInputs.map(function($el){ return $el.val(); });
            var values = valueInputs.map(function($el){ return parseInt($el.val(),10) || 0; });
            return {labels: labels, values: values};
        }
        var data = collect();
        var chart = new Chart(canvas, {
            type: 'radar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Vorschau',
                    data: data.values,
                    backgroundColor: 'rgba(54,162,235,0.2)',
                    borderColor: 'rgba(54,162,235,1)'
                }]
            },
            options: {
                scales: {
                    r: {min: 0, max: 100, beginAtZero: true}
                }
            }
        });
        function update(){
            var d = collect();
            chart.data.labels = d.labels;
            chart.data.datasets[0].data = d.values;
            chart.update();
        }
        labelInputs.concat(valueInputs).forEach(function($el){
            $el.on('input', update);
        });
    });
})(jQuery);
