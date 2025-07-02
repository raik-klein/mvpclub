jQuery(function($){
    function addPerformanceRow(){
        var row = $('<tr>')
            .append('<td><input type="text" name="perf_saison[]" /></td>')
            .append('<td><input type="text" name="perf_competition[]" /></td>')
            .append('<td><input type="number" name="perf_games[]" /></td>')
            .append('<td><input type="number" name="perf_goals[]" /></td>')
            .append('<td><input type="number" name="perf_assists[]" /></td>')
            .append('<td><input type="number" name="perf_minutes[]" /></td>')
            .append('<td><button class="button remove-performance-row">X</button></td>');
        $('#performance-data-table tbody').append(row);
    }

    $(document).on('click', '#add-performance-row', function(e){
        e.preventDefault();
        addPerformanceRow();
    });

    $(document).on('click', '.remove-performance-row', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    var radarChart;
    function renderRadar(){
        var canvas = document.getElementById('mvpclub-radar-preview');
        if(!canvas || typeof Chart === 'undefined') return;
        var labels = [], data = [];
        for(var i=0;i<6;i++){
            labels.push($('[name="radar_chart_label'+i+'"]').val());
            data.push(parseInt($('[name="radar_chart_value'+i+'"]').val()) || 0);
        }
        if(radarChart) radarChart.destroy();
        radarChart = new Chart(canvas, {
            type:'radar',
            data:{
                labels:labels,
                datasets:[{
                    label:'Werte',
                    data:data,
                    backgroundColor:'rgba(54,162,235,0.2)',
                    borderColor:'rgba(54,162,235,1)'
                }]
            },
            options:{scales:{r:{min:0,max:100,beginAtZero:true}}}
        });
    }

    $(document).on('input', '[name^="radar_chart_label"], [name^="radar_chart_value"]', renderRadar);
    renderRadar();

    $(document).on('click', '#mvpclub-player-tabs .nav-tab', function(e){
        e.preventDefault();
        var tab = $(this).data('tab');
        $('#mvpclub-player-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('#mvpclub-player-tabs .mvpclub-tab-content').removeClass('active').hide();
        $('#tab-'+tab).addClass('active').show();
    });
});
