(function(blocks, element, i18n, components, data) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var __ = i18n.__;
    var SelectControl = components.SelectControl;
    var Spinner = components.Spinner;
    var useSelect = data.useSelect;

    registerBlockType('mvpclub/player-info', {
        title: __('Spielerinfo', 'mvpclub'),
        icon: 'id',
        category: 'mvpclub',
        attributes: {
            playerId: { type: 'integer', default: 0 }
        },
        edit: function(props) {
            var players = useSelect(function(select) {
                return select('core').getEntityRecords('postType', 'mvpclub_player', { per_page: -1 });
            }, []);

            if (!players) {
                return el(Spinner, null);
            }

            var options = [{ label: __('\u2013 ausw\u00E4hlen \u2013', 'mvpclub'), value: 0 }];
            players.forEach(function(p) {
                options.push({ label: p.title.rendered, value: p.id });
            });

            return el('div', {},
                el(SelectControl, {
                    label: __('Spieler ausw\u00E4hlen', 'mvpclub'),
                    value: props.attributes.playerId,
                    options: options,
                    onChange: function(val) { props.setAttributes({ playerId: parseInt(val, 10) }); }
                })
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components, window.wp.data);
