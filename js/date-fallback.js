(function($){
    $(function(){
        var $fields = $('input.mvpclub-date-field');
        if(!$fields.length) return;
        var test = document.createElement('input');
        test.setAttribute('type','date');
        if(test.type !== 'date') {
            $fields.each(function(){
                var $input = $(this);
                var textVal = $input.data('date-text') || '';
                if(textVal) {
                    $input.val(textVal);
                }
                $input.attr('type','text').datepicker({
                    dateFormat: 'dd.mm.yy'
                });
            });
        }
    });
})(jQuery);
