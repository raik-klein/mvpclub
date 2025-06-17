<?php
// Registriert den Gutenberg-Block und das zugehörige Editor-Skript
add_action('init', function () {
    wp_register_script(
        'mvpclub-ads-editor-script',
        plugins_url('ads.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'ads.js')
    );

    // Dynamischer Block ohne Attribute: Editor-Ansicht defined in JS, Frontend via render_callback
    register_block_type('mvpclub/ads', array(
        'editor_script'   => 'mvpclub-ads-editor-script',
        'render_callback' => 'mvpclub_render_ads'
    ));
});

/**
 * Render-Callback für den ads-Block.
 * Gibt im Frontend das [ad]-Shortcode-Output zurück.
 */
function mvpclub_render_ads() {
    return do_shortcode('[ad]');
}