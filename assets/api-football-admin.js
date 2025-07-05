jQuery(function($){
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
        var btn = $('<button>').addClass('button mvpclub-add-player').text('Spieler hinzuf\u00fcgen').data('player', p);
        tr.append($('<td>').append(btn));
        return tr;
    }

    $('#mvpclub-player-search-form').on('submit', function(e){
        e.preventDefault();
        var query = $(this).find('input[name="player_search"]').val();
        $.post(mvpclubAPIFootball.ajaxUrl, {
            action: 'mvpclub_search_players',
            nonce: mvpclubAPIFootball.nonce,
            query: query
        }, function(resp){
            var tbody = $('#mvpclub-search-results tbody');
            tbody.empty();
            if(resp.success && Array.isArray(resp.data)){
                resp.data.forEach(function(row){
                    tbody.append(buildRow(row.player));
                });
            }else{
                tbody.append('<tr><td colspan="10">'+(resp.data||'Keine Ergebnisse')+'</td></tr>');
            }
        }, 'json');
    });

    $(document).on('click','.mvpclub-add-player', function(e){
        e.preventDefault();
        var player = $(this).data('player');
        $.post(mvpclubAPIFootball.ajaxUrl, {
            action: 'mvpclub_add_player',
            nonce: mvpclubAPIFootball.addNonce,
            player: player
        }, function(resp){
            if(resp.success && resp.data && resp.data.edit_link){
                window.location.href = resp.data.edit_link;
            }else{
                alert(resp.data || 'Fehler');
            }
        }, 'json');
    });
});
