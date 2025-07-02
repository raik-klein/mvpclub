<?php
if (!defined('ABSPATH')) exit;

/**
 * Register the "mvpclub_player" post type for the player database
 */
add_action('init', 'mvpclub_register_player_cpt');
function mvpclub_register_player_cpt() {
    register_post_type('mvpclub_player', array(
        'labels' => array(
            'name'          => 'Spieler',
            'singular_name' => 'Spieler',
        ),
        'public'      => true,
        'has_archive' => false,
        'show_ui'     => true,
        'show_in_menu'=> false,
        'supports'    => array('title', 'thumbnail'),
        'show_in_rest'=> true,
    ));
}

/**
 * Field definitions for the player meta box and REST API
 */
function mvpclub_player_fields() {
    return array(
        'birthdate'   => 'Geburtsdatum',
        'birthplace'  => 'Geburtsort',
        'height'      => 'Gr\xC3\xB6\xC3\x9Fe',
        'nationality' => 'Nationalit\xC3\xA4t',
        'position'    => 'Position',
        'foot'        => 'F\xC3\xBC\xC3\x9F',
        'agent'       => 'Berater',
        'club'        => 'Verein',
    );
}

/**
 * Register meta fields so they appear in the REST API
 */
add_action('init', function() {
    foreach (mvpclub_player_fields() as $key => $label) {
        register_post_meta('mvpclub_player', $key, array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
            'auth_callback'     => function() { return current_user_can('edit_posts'); },
        ));
    }
});

/**
 * Add meta box for player details
 */
add_action('add_meta_boxes', function() {
    add_meta_box('mvpclub_player_details', 'Spielerdaten', 'mvpclub_player_meta_box', 'mvpclub_player');
});

/**
 * Enqueue autocomplete script for nationality field
 */
add_action('admin_enqueue_scripts', 'mvpclub_player_admin_scripts');
function mvpclub_player_admin_scripts($hook) {
    $screen = get_current_screen();
    if ($screen->post_type !== 'mvpclub_player') return;

    wp_enqueue_script(
        'mvpclub-nationality-autocomplete',
        plugins_url('assets/nationality-autocomplete.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/nationality-autocomplete.js'),
        true
    );

    wp_localize_script('mvpclub-nationality-autocomplete', 'mvpclubPlayers', array(
        'countriesUrl' => plugins_url('assets/countries.json', __FILE__),
    ));

    wp_enqueue_style(
        'mvpclub-nationality-autocomplete',
        plugins_url('assets/nationality-autocomplete.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/nationality-autocomplete.css')
    );
}

function mvpclub_player_meta_box($post) {
    wp_nonce_field('mvpclub_save_player', 'mvpclub_player_nonce');
    echo '<table class="form-table">';
    foreach (mvpclub_player_fields() as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" /></td></tr>';
    }
    echo '</table>';
}

/**
 * Save meta box values
 */
add_action('save_post_mvpclub_player', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['mvpclub_player_nonce']) || !wp_verify_nonce($_POST['mvpclub_player_nonce'], 'mvpclub_save_player')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach (mvpclub_player_fields() as $key => $label) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
});

/**
 * Add submenu pages for the player database and scouting report settings
 */
add_action('admin_menu', function() {
    add_submenu_page('mvpclub-main', 'Spielerdatenbank', 'Spielerdatenbank', 'edit_posts', 'edit.php?post_type=mvpclub_player');
    add_submenu_page('mvpclub-main', 'Scoutingberichte', 'Scoutingberichte', 'edit_posts', 'mvpclub-scout-settings', 'mvpclub_render_scout_settings_page');
});

/**
 * Render the "Scoutingberichte" settings page
 */
function mvpclub_render_scout_settings_page() {
    if (isset($_POST['mvpclub_scout_template']) && check_admin_referer('mvpclub_scout_settings', 'mvpclub_scout_nonce')) {
        update_option('mvpclub_scout_template', wp_kses_post($_POST['mvpclub_scout_template']));
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }

    $template = get_option('mvpclub_scout_template', '<p>[spieler-name] - [verein]</p>');

    $placeholders = array(
        '[spieler-name]'  => 'Max Mustermann',
        '[geburtsdatum]'  => '01.01.2000',
        '[geburtsort]'    => 'Beispielstadt',
        '[groesse]'       => '180 cm',
        '[nationalitaet]' => 'Deutsch',
        '[position]'      => 'Stürmer',
        '[fuss]'          => 'rechts',
        '[berater]'       => 'Musterberater',
        '[verein]'        => 'FC Beispiel',
    );

    $preview = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    ?>
    <div class="wrap">
        <h1>Scoutingberichte</h1>
        <form method="post">
            <?php wp_nonce_field('mvpclub_scout_settings', 'mvpclub_scout_nonce'); ?>
            <?php
            wp_editor($template, 'mvpclub_scout_template_editor', array(
                'textarea_name' => 'mvpclub_scout_template',
                'textarea_rows' => 10,
            ));
            ?>
            <p><?php esc_html_e('Platzhalter einfügen:', 'mvpclub'); ?>
                <?php foreach ($placeholders as $tag => $sample) : ?>
                    <button type="button" class="insert-placeholder button" data-placeholder="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
                <?php endforeach; ?>
            </p>
            <?php submit_button('Speichern'); ?>
        </form>

        <h2>Vorschau</h2>
        <div class="mvpclub-scout-preview" style="border:1px solid #ccc;padding:1em;">
            <?php echo wpautop($preview); ?>
        </div>
    </div>
    <script>
    jQuery(function($){
        $('.insert-placeholder').on('click', function(){
            var tag = $(this).data('placeholder');
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                tinymce.activeEditor.execCommand('mceInsertContent', false, tag);
            } else {
                var textarea = $('#mvpclub_scout_template_editor');
                textarea.val(textarea.val() + tag);
            }
        });
    });
    </script>
    <?php
}

/**
 * Register dynamic Gutenberg block for player info
 */
add_action('init', 'mvpclub_register_player_info_block');
function mvpclub_register_player_info_block() {
    wp_register_script(
        'mvpclub-player-info-editor-script',
        plugins_url('blocks/player-info.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/player-info.js')
    );

    register_block_type('mvpclub/player-info', array(
        'editor_script'   => 'mvpclub-player-info-editor-script',
        'render_callback' => 'mvpclub_render_player_info',
        'attributes'      => array(
            'playerId' => array(
                'type'    => 'integer',
                'default' => 0,
            ),
        ),
    ));
}

/**
 * Render callback for the player info block
 */
function mvpclub_render_player_info($attributes) {
    $player_id = !empty($attributes['playerId']) ? absint($attributes['playerId']) : 0;
    if (!$player_id) return '';

    $fields = mvpclub_player_fields();
    $data = array();
    foreach ($fields as $key => $label) {
        $data[$key] = get_post_meta($player_id, $key, true);
    }
    $title = get_the_title($player_id);
    $img   = get_the_post_thumbnail($player_id, 'medium', array('style' => 'max-width:100%;height:auto;'));
    $bg    = get_option('mvpclub_player_bg_color', '#f9f9f9');
    $text  = get_option('mvpclub_player_text_color', '#000000');

    ob_start();
    echo '<div class="mvpclub-player-info" style="background:' . esc_attr($bg) . ';color:' . esc_attr($text) . ';padding:1em;">';
    if ($img) echo '<div class="mvpclub-player-image">' . $img . '</div>';
    echo '<h2 class="mvpclub-player-name">' . esc_html($title) . '</h2>';
    echo '<ul class="mvpclub-player-data">';
    foreach ($fields as $key => $label) {
        if (!empty($data[$key])) {
            echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($data[$key]) . '</li>';
        }
    }
    echo '</ul></div>';
    return ob_get_clean();
}
