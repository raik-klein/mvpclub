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

    function characteristicSelect(type, name){
        var select = $('<select name="'+name+'[]">').append('<option value=""></option>');
        if(window.mvpclubPlayerAdmin && window.mvpclubPlayerAdmin.characteristics){
            var list = window.mvpclubPlayerAdmin.characteristics[type] || [];
            list.forEach(function(item){
                var optgroup = $('<optgroup>').attr('label', item.main);
                optgroup.append('<option value="'+item.main+'">'+item.main+'</option>');
                if(Array.isArray(item.subs)){
                    item.subs.forEach(function(s){
                        optgroup.append('<option value="'+s+'">'+s+'</option>');
                    });
                }
                select.append(optgroup);
            });
        }
        return select;
    }

    function addCharacteristicRow(list, type, name){
        var row = $('<li>').append(characteristicSelect(type, name))
            .append(' <button class="button remove-characteristic">X</button>');
        $(list).append(row);
    }

    $(document).on('click', '#add-strength', function(e){
        e.preventDefault();
        var type = $('#position').val()==='Tor' ? 'Tor' : 'Feldspieler';
        addCharacteristicRow('#mvpclub-strengths-list', type, 'strengths');
    });

    $(document).on('click', '#add-weakness', function(e){
        e.preventDefault();
        var type = $('#position').val()==='Tor' ? 'Tor' : 'Feldspieler';
        addCharacteristicRow('#mvpclub-weaknesses-list', type, 'weaknesses');
    });

    $(document).on('click', '.remove-characteristic', function(e){
        e.preventDefault();
        $(this).parent().remove();
    });

    if($.fn.sortable){
        $('#mvpclub-strengths-list, #mvpclub-weaknesses-list').sortable({items:'li'});
    }

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

    if (typeof inlineEditPost !== 'undefined') {
        var wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(id) {
            wp_inline_edit.apply(this, arguments);
            var postId = typeof id === 'object' ? this.getId(id) : id;
            var row = $('#post-' + postId);
            var editRow = $('#edit-' + postId);
            ['birthdate','birthplace','height','nationality','position','detail_position','foot','club','market_value'].forEach(function(key){
                var val = row.find('.column-' + key).text().trim();
                if(key==='height'){ val = val.replace(/[^0-9]/g,''); }
                editRow.find('input[name="'+key+'"]').val(val);
            });
        };
    }
});
