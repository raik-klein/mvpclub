jQuery(function($){
    $('#mvpclub-player-search-form').on('submit', function(e){
        e.preventDefault();
        var query = $(this).find('input[name="player_search"]').val();
        var league = $(this).find('select[name="search_league"]').val();
        var season = $(this).find('select[name="player_season"]').val();
        $.post(mvpclubAPIFootball.ajaxUrl, {
            action: 'mvpclub_search_players',
            nonce: mvpclubAPIFootball.nonce,
            query: query,
            league: league,
            season: season
        }, function(resp){
            var tbody = $('#mvpclub-search-results tbody');
            tbody.empty();
            if(resp.success && Array.isArray(resp.data)){
                resp.data.forEach(function(row){
                    var p = row.player;
                    var team = row.statistics && row.statistics[0] && row.statistics[0].team ? row.statistics[0].team.name : '';
                    var link = mvpclubAPIFootball.baseUrl + '&add_player=' + p.id + '&player_season=' + season + '&_wpnonce=' + mvpclubAPIFootball.addNonce;
                    var tr = $('<tr>');
                    tr.append($('<td>').text($.trim((p.firstname||'')+' '+(p.lastname||p.name))));
                    tr.append($('<td>').text(team));
                    tr.append($('<td>').append($('<a>').addClass('button').attr('href', link).text('Spieler hinzuf\u00fcgen')));
                    tbody.append(tr);
                });
            }else if(resp.data){
                tbody.append('<tr><td colspan="3">'+resp.data+'</td></tr>');
            }else{
                tbody.append('<tr><td colspan="3">Keine Ergebnisse</td></tr>');
            }
        }, 'json');
    });
});
