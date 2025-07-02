(function($){
    $(function(){
        var input = $('#nationality');
        if(!input.length){return;}
        var wrapper = input.parent();
        if(wrapper.css('position') === 'static'){
            wrapper.css('position','relative');
        }
        var container = $('<div class="mvpclub-suggestions"></div>').css({position:'absolute', border:'1px solid #ccc', background:'#fff', 'z-index':1000, left:0, right:0}).appendTo(wrapper);
        container.css('top', input.outerHeight());
        container.hide();
        $.getJSON(mvpclubPlayers.countriesUrl, function(countries){
            function show(){
                var val = input.val().toLowerCase();
                container.empty();
                if(!val){container.hide(); return;}
                var matches = countries.filter(function(c){
                    return c.name.toLowerCase().indexOf(val) !== -1;
                }).slice(0,10);
                if(!matches.length){container.hide(); return;}
                matches.forEach(function(c){
                    var item = $('<div class="item"></div>').text(c.emoji+' '+c.name).css({padding:'2px 4px', cursor:'pointer'}).appendTo(container);
                    item.on('mousedown', function(e){
                        e.preventDefault();
                        input.val(c.emoji + ' ' + c.name);
                        container.hide();
                    });
                });
                container.show();
            }
            input.on('input focus', show);
            $(document).on('click', function(e){
                if(!container.is(e.target) && !input.is(e.target)){
                    container.hide();
                }
            });
        });
    });
})(jQuery);
