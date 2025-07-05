(function (blocks, i18n, element) {
    const { registerBlockType } = blocks;
    const { __ } = i18n;
    const { createElement: el } = element;

    registerBlockType('mvpclub/scouting-posts', {
        title: __('Scouting-Beitragsliste', 'mvpclub'),
        icon: 'media-document',
        category: 'mvpclub',

        edit: () => {
            return el('div', {
                    style: {
                        background: '#F2F2F2',
                        padding: '1em',
                        border: '1px dashed #111111',
                        textAlign: 'center',
                        fontWeight: 'bold',
                    },
            }, __('Scouting Posts Block – Vorschau (Frontend wird serverseitig gerendert)', 'mvpclub'));
        },

        save: () => null // weiterhin serverseitig
    });
})(window.wp.blocks, window.wp.i18n, window.wp.element);
