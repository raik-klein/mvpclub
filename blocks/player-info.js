(function(blocks, element, i18n, components, data) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var __ = i18n.__;
    var SelectControl = components.SelectControl;
    var Spinner = components.Spinner;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var ServerSideRender = wp.serverSideRender;
    var useSelect = data.useSelect;

    registerBlockType('mvpclub/player-info', {
        title: __('Scoutingbericht', 'mvpclub'),
        icon: 'search',
        category: 'mvpclub',
        attributes: {
            playerId: { type: 'integer', default: 0 }
        },
        edit: function(props) {
            var players = useSelect(function(select) {
                return select('core').getEntityRecords('postType', 'mvpclub-spieler', { per_page: -1 });
            }, []);

            if (!players) {
                return el(Spinner, null);
            }

            var options = [{ label: __('\u2013 ausw\u00E4hlen \u2013', 'mvpclub'), value: 0 }];
            players.forEach(function(p) {
                options.push({ label: p.title.rendered, value: p.id });
            });
            return el(element.Fragment, {},
                el(InspectorControls, {},
                    el(SelectControl, {
                        label: __('Spieler ausw\u00E4hlen', 'mvpclub'),
                        value: props.attributes.playerId,
                        options: options,
                        onChange: function(val) { props.setAttributes({ playerId: parseInt(val, 10) }); }
                    })
                ),
                el(ServerSideRender, { block: 'mvpclub/player-info', attributes: props.attributes })
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components, window.wp.data);
