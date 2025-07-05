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

    function addStatistikRow(season){
        var row = $('<tr>')
            .append('<td><input type="text" name="perf_saison[]" value="'+(season||'')+'" /></td>')
            .append($('<td>').append(competitionSelect()))
            .append('<td><input type="number" name="perf_games[]" /></td>')
            .append('<td><input type="number" name="perf_goals[]" /></td>')
            .append('<td><input type="number" name="perf_assists[]" /></td>')
            .append('<td><input type="number" name="perf_minutes[]" /></td>')
            .append('<td><button class="button remove-statistik-row">X</button></td>');
        $('#statistik-data-table tbody').append(row);
    }

    function updateStatHeaders(){
        if(!window.mvpclubPlayerAdmin) return;
        var headers = $('#position').val()==='Tor' ? window.mvpclubPlayerAdmin.headersTor : window.mvpclubPlayerAdmin.headers;
        var ths = $('#statistik-data-table thead th');
        if(ths.length>=6){
            ths.eq(0).text(headers.saison);
            ths.eq(1).text(headers.wettbewerb);
            ths.eq(2).text(headers.spiele);
            ths.eq(3).text(headers.tore);
            ths.eq(4).text(headers.assists);
            ths.eq(5).text(headers.minuten);
        }
    }

    $(document).on('click', '#add-statistik-row', function(e){
        e.preventDefault();
        addStatistikRow();
    });

    $(document).on('click', '#mvpclub-load-seasons', function(e){
        e.preventDefault();
        var pid = $('#mvpclub-api-player-id').val();
        if(!pid) return;
        $.post(ajaxurl, {
            action: 'mvpclub_load_seasons',
            nonce: mvpclubPlayerAdmin.nonce,
            player_id: pid
        }, function(resp){
            if(resp.success && Array.isArray(resp.data)){
                var tbody = $('#statistik-data-table tbody');
                tbody.empty();
                resp.data.forEach(function(year){
                    addStatistikRow(year);
                });
            }else if(resp.data){
                alert(resp.data);
            }
        }, 'json');
    });

    $(document).on('click', '.remove-statistik-row', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $(document).on('change', '#position', updateStatHeaders);
    updateStatHeaders();

    function characteristicSelect(type, name){
        var select = $('<select name="'+name+'[]">').append('<option value=""></option>');
        if(window.mvpclubPlayerAdmin && window.mvpclubPlayerAdmin.characteristics){
            var list = window.mvpclubPlayerAdmin.characteristics[type] || [];
            list.forEach(function(item){
                var optgroup = $('<optgroup>').attr('label', item.main);
                optgroup.append('<option value="'+item.id+'">'+item.main+'</option>');
                if(Array.isArray(item.subs)){
                    item.subs.forEach(function(s){
                        optgroup.append('<option value="'+s.id+'">'+s.name+'</option>');
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

    // Attribute manager
    function addAttrRow(group){
        var table = $('#attr-table-'+group+' tbody');
        var template = $($('#attr-row-template').html());
        var next = parseInt(table.data('next')) || 1;
        template.find('.attr-id').text(next);
        template.find('.attr-name').attr('name','attr_name['+group+'][0]');
        var parentSel = template.find('.attr-parent').attr('name','attr_parent['+group+'][0]');
        // clone parent options
        var opts = $('#attr-table-'+group+' tbody select:first').find('option').clone();
        parentSel.empty().append(opts);
        template.find('.attr-delete').attr('name','attr_delete['+group+'][0]');
        table.append(template);
        table.data('next', next + 1);
    }

    $(document).on('click','.mvpclub-add-attr',function(e){
        e.preventDefault();
        addAttrRow($(this).data('group'));
    });

    $(document).on('click','.mvpclub-delete-attr',function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });
});
