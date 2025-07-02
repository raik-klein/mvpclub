(function( wp, document ){
    var el = wp.element.createElement;
    var parse = wp.blocks.parse;
    var serialize = wp.blocks.serialize;
    var createBlock = wp.blocks.createBlock;
    var BlockEditorProvider = wp.blockEditor.BlockEditorProvider;
    var BlockList = wp.blockEditor.BlockList;
    var BlockTools = wp.blockEditor.BlockTools;
    var dispatch = wp.data.dispatch;

    document.addEventListener('DOMContentLoaded', function(){
        var container = document.getElementById('mvpclub-block-editor');
        var input = document.getElementById('mvpclub_scout_template');
        if( ! container || ! input ){ return; }
        var initial = mvpclubScoutEditor.template || '';
        var blocks = parse( initial );

        function App(){
            var _useState = wp.element.useState( blocks ), value = _useState[0], setValue = _useState[1];

            function onChange( next ){ setValue( next ); input.value = serialize( next ); }

            return el(BlockEditorProvider, { value: value, onInput: onChange, onChange: onChange, settings: {} },
                el(BlockTools, {},
                    el(BlockList, { blocks: value })
                )
            );
        }
        wp.element.render( el(App), container );

        document.querySelectorAll('.insert-placeholder').forEach(function(btn){
            btn.addEventListener('click', function(){
                var tag = btn.getAttribute('data-placeholder');
                dispatch('core/block-editor').insertBlocks( createBlock('core/paragraph', { content: tag }) );
            });
        });
    });
})( window.wp, document );

