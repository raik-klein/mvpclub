jQuery(function($){
    function competitionSelect(){
        var select = $('<select name="perf_competition[]">').append('<option value="">-</option>');
        if(window.mvpclubPlayerAdmin && Array.isArray(window.mvpclubPlayerAdmin.competitions)){
            window.mvpclubPlayerAdmin.competitions.forEach(function(l){
                select.append('<option value="'+l+'">'+l+'</option>');
            });
        }
        return select;
    }

    function addStatistikRow(){
        var row = $('<tr>')
            .append('<td><input type="text" name="perf_saison[]" /></td>')
            .append($('<td>').append(competitionSelect()))
            .append('<td><input type="number" name="perf_games[]" /></td>')
            .append('<td><input type="number" name="perf_goals[]" /></td>')
            .append('<td><input type="number" name="perf_assists[]" /></td>')
            .append('<td><input type="number" name="perf_minutes[]" /></td>')
            .append('<td><button class="button remove-statistik-row">X</button></td>');
        $('#statistik-data-table tbody').append(row);
    }

    $(document).on('click', '#add-statistik-row', function(e){
        e.preventDefault();
        addStatistikRow();
    });

    $(document).on('click', '.remove-statistik-row', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    var radarChart;
    function adjustRadarSize(){
        var canvas = $('#mvpclub-radar-preview');
        if(canvas.length){
            var size = 250;
            canvas.attr('width', size).attr('height', size);
        }
    }

    function renderRadar(){
        var canvas = document.getElementById('mvpclub-radar-preview');
        if(!canvas || typeof Chart === 'undefined') return;
        adjustRadarSize();
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
    adjustRadarSize();
    renderRadar();

    $(document).on('click', '#mvpclub-player-tabs .nav-tab', function(e){
        e.preventDefault();
        var tab = $(this).data('tab');
        $('#mvpclub-player-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('#mvpclub-player-tabs .mvpclub-tab-content').removeClass('active').hide();
        $('#tab-'+tab).addClass('active').show();
        if(tab === 'radar'){ adjustRadarSize(); renderRadar(); }
    });

    function updateBirthplaceSelect(){
        var select = $('#birthplace_country');
        if(!select.length) return;
        select.find('option').each(function(){
            var full = $(this).data('full');
            if(full){ $(this).text(full); }
        });
        var opt = select.find('option:selected');
        if(opt.length){
            opt.text(opt.data('emoji'));
        }
    }

    $(document).on('change', '#birthplace_country', updateBirthplaceSelect);
    updateBirthplaceSelect();

    $(document).on('input', '#height', function(){
        $(this).next('output').text(this.value + ' cm');
    });
    $('#height').trigger('input');
});
