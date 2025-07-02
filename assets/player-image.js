jQuery(function($){
    var frame;
    $(document).on('click', '.mvpclub-player-image-select', function(e){
        e.preventDefault();
        if(frame){
            frame.open();
            return;
        }
        frame = wp.media({
            title: 'Bild auswählen',
            button: { text: 'Auswählen' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            $('#mvpclub-player-image').val(attachment.id);
            $('.mvpclub-player-image-preview').html('<img src="'+attachment.sizes.thumbnail.url+'" style="max-width:150px;height:auto;" />');
        });
        frame.open();
    });

    $(document).on('click', '.mvpclub-player-image-remove', function(e){
        e.preventDefault();
        $('#mvpclub-player-image').val('');
        $('.mvpclub-player-image-preview').empty();
    });
});
