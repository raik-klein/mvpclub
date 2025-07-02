(function (blocks, i18n) {
    const { registerBlockType } = blocks;
    const { __ } = i18n;

    registerBlockType('mvpclub/ads', {
        title: __('Werbung', 'mvpclub'),
        icon: 'megaphone',
        category: 'mvpclub',

        // Editor-Placeholder
        edit: () => {
            return (
                wp.element.createElement(
                    'div',
                    {
                        style: {
                            background: '#F2F2F2',
                            padding: '1em',
                            border: '1px dashed #111111',
                            textAlign: 'center',
                            fontWeight: 'bold',
                        },
                        className: 'mvpclub-ad-placeholder'
                    },
                    __('Werbung', 'mvpclub')
                )
            );
        },

        // Server-Rendering via PHP-Callback
        save: () => null
    });
})(window.wp.blocks, window.wp.i18n);
