jQuery(function($){
    var results = [], perPage = 10, currentPage = 1;

    function buildRow(p){
        var tr = $('<tr>');
        tr.append('<td>'+p.id+'</td>');
        tr.append('<td>'+(p.firstname||'')+'</td>');
        tr.append('<td>'+(p.lastname||'')+'</td>');
        tr.append('<td>'+(p.age||'')+'</td>');
        tr.append('<td>'+(p.birth && p.birth.date ? p.birth.date : '')+'</td>');
        tr.append('<td>'+(p.birth && p.birth.place ? p.birth.place : '')+'</td>');
        tr.append('<td>'+(p.nationality||'')+'</td>');
        tr.append('<td>'+(p.height||'')+'</td>');
        tr.append('<td>'+(p.position||'')+'</td>');
        var btn = $('<button type="button">')
            .addClass('button mvpclub-add-player')
            .text('Hinzuf\u00fcgen')
            .attr('data-id', p.id)
            .data('player', p);
        tr.append($('<td>').append(btn));
        return tr;
    }

    function render(page){
        if(page) currentPage = page;
        var tbody = $('#mvpclub-search-results tbody');
        tbody.empty();
        var start = (currentPage-1)*perPage;
        var slice = results.slice(start, start+perPage);
        if(slice.length){
            slice.forEach(function(p){ tbody.append(buildRow(p)); });
        }else{
            tbody.append('<tr><td colspan="10">Keine Ergebnisse</td></tr>');
        }
        renderPagination();
    }

    function renderPagination(){
        var total = Math.ceil(results.length/perPage);
        var div = $('#mvpclub-search-pagination');
        div.empty();
        if(total <= 1) return;
        for(var i=1;i<=total;i++){
            var a = $('<a href="#">').text(i).data('page',i);
            if(i===currentPage) a.addClass('current');
            div.append(a);
            if(i<total) div.append(' ');
        }
    }

    $('#mvpclub-player-search-form').on('submit', function(e){
        e.preventDefault();
        var query = $(this).find('input[name="player_search"]').val();
        $.post(mvpclubAPIFootball.ajaxUrl, {
            action: 'mvpclub_search_players',
            nonce: mvpclubAPIFootball.nonce,
            query: query
        }, function(resp){
            results = [];
            if(resp.success && Array.isArray(resp.data)){
                resp.data.forEach(function(row){ results.push(row.player); });
            }
            render(1);
        }, 'json');
    });

    $(document).on('click','#mvpclub-search-pagination a', function(e){
        e.preventDefault();
        render($(this).data('page'));
    });

    $(document).on('click','.mvpclub-add-player', function(e){
        e.preventDefault();
        var btn = $(this);
        if(btn.prop('disabled')) return;
        btn.prop('disabled', true);
        var player = btn.data('player');

        if(!player || typeof player !== 'object'){
            // Fallback: read from table row if no data attached
            var tr = btn.closest('tr');
            player = {
                id: parseInt(tr.find('td').eq(0).text(), 10),
                firstname: tr.find('td').eq(1).text(),
                lastname: tr.find('td').eq(2).text(),
                age: parseInt(tr.find('td').eq(3).text(), 10) || '',
                birth: {
                    date: tr.find('td').eq(4).text(),
                    place: tr.find('td').eq(5).text()
                },
                nationality: tr.find('td').eq(6).text(),
                height: tr.find('td').eq(7).text(),
                position: tr.find('td').eq(8).text()
            };
        }

        $.post(mvpclubAPIFootball.ajaxUrl, {
            action: 'mvpclub_add_player',
            nonce: mvpclubAPIFootball.addNonce,
            player: JSON.stringify(player)
        }, function(resp){
            if(resp.success && resp.data && resp.data.edit_link){
                alert('Spieler importiert');
                window.location.href = resp.data.edit_link;
            }else{
                alert(resp.data || 'Fehler beim Import');
            }
            btn.prop('disabled', false);
        }, 'json').fail(function(){
            alert('Fehler beim Senden der Anfrage');
            btn.prop('disabled', false);
        });
    });
});
