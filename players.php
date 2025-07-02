<?php
if (!defined('ABSPATH')) exit;

/**
 * Register the "mvpclub-spieler" post type for the player database
 */
add_action('init', 'mvpclub_register_player_cpt');
function mvpclub_register_player_cpt() {
    register_post_type('mvpclub-spieler', array(
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
 * Migrate old "mvpclub_player" posts to the new slug.
 */
add_action('init', 'mvpclub_migrate_player_cpt', 20);
function mvpclub_migrate_player_cpt() {
    if (get_option('mvpclub_spieler_migrated')) {
        return;
    }

    global $wpdb;
    // migrate from the original "mvpclub_player" slug
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
        'mvpclub_player'
    ));
    if ($count > 0) {
        $wpdb->update(
            $wpdb->posts,
            array('post_type' => 'mvpclub-spieler'),
            array('post_type' => 'mvpclub_player')
        );
    }

    // migrate from the interim "mvpclub-player" slug
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
        'mvpclub-player'
    ));
    if ($count > 0) {
        $wpdb->update(
            $wpdb->posts,
            array('post_type' => 'mvpclub-spieler'),
            array('post_type' => 'mvpclub-player')
        );
    }

    update_option('mvpclub_spieler_migrated', 1);
}

/**
 * Field definitions for the player meta box and REST API
 */
function mvpclub_player_fields() {
    return array(
        'birthdate'   => 'Geburtsdatum',
        'birthplace'  => 'Geburtsort',
        'height'      => 'Größe',
        'nationality' => 'Nationalität',
        'position'    => 'Position',
        'foot'        => 'Fuß',
        'agent'       => 'Berater',
        'club'        => 'Verein',
        'radar_chart' => 'Radar Chart',
    );
}

/**
 * Register meta fields so they appear in the REST API
 */
add_action('init', function() {
    foreach (mvpclub_player_fields() as $key => $label) {
        register_post_meta('mvpclub-spieler', $key, array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => $key === 'radar_chart' ? null : 'sanitize_text_field',
            'show_in_rest'      => true,
            'auth_callback'     => function() { return current_user_can('edit_posts'); },
        ));
    }
});

/**
 * Add meta box for player details
 */
add_action('add_meta_boxes', function() {
    add_meta_box('mvpclub_player_details', 'Spielerdaten', 'mvpclub_player_meta_box', 'mvpclub-spieler');
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
        if ($key === 'radar_chart') {
            $chart = json_decode($value, true);
            $labels = isset($chart['labels']) ? (array) $chart['labels'] : array_fill(0, 6, '');
            $values = isset($chart['values']) ? (array) $chart['values'] : array_fill(0, 6, 0);
            echo '<tr><th colspan="2">' . esc_html($label) . '</th></tr>';
            for ($i = 0; $i < 6; $i++) {
                $l = isset($labels[$i]) ? $labels[$i] : '';
                $v = isset($values[$i]) ? $values[$i] : 0;
                echo '<tr><td><input type="text" name="radar_chart_label' . $i . '" value="' . esc_attr($l) . '" placeholder="Label" /></td>';
                echo '<td><input type="range" name="radar_chart_value' . $i . '" min="0" max="100" value="' . esc_attr($v) . '" oninput="this.nextElementSibling.value=this.value" />';
                echo '<output>' . esc_html($v) . '</output></td></tr>';
            }
        } else {
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
            echo '<td><input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" /></td></tr>';
        }
    }
    echo '</table>';
}

/**
 * Save meta box values
 */
add_action('save_post_mvpclub-spieler', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['mvpclub_player_nonce']) || !wp_verify_nonce($_POST['mvpclub_player_nonce'], 'mvpclub_save_player')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach (mvpclub_player_fields() as $key => $label) {
        if ($key === 'radar_chart') {
            $labels = array();
            $values = array();
            for ($i = 0; $i < 6; $i++) {
                $labels[] = isset($_POST['radar_chart_label' . $i]) ? sanitize_text_field($_POST['radar_chart_label' . $i]) : '';
                $values[] = isset($_POST['radar_chart_value' . $i]) ? intval($_POST['radar_chart_value' . $i]) : 0;
            }
            $chart = array('labels' => $labels, 'values' => $values);
            update_post_meta($post_id, 'radar_chart', wp_json_encode($chart));
        } elseif (isset($_POST[$key])) {
            update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
        }
    }
});

/**
 * Add submenu pages for the player database and scouting report settings
 */
add_action('admin_menu', function() {
    add_submenu_page('mvpclub-main', 'Spielerdatenbank', 'Spielerdatenbank', 'edit_posts', 'edit.php?post_type=mvpclub-spieler');
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
        'mvpclub-spieler-info-editor-script',
        plugins_url('blocks/player-info.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-data'),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/player-info.js')
    );

    register_block_type('mvpclub/player-info', array(
        'editor_script'   => 'mvpclub-spieler-info-editor-script',
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
    if (!$player_id) {
        return '';
    }

    $template = get_option('mvpclub_scout_template', '<p>[spieler-name] - [verein]</p>');

    $placeholders = array(
        '[spieler-name]'  => get_the_title($player_id),
        '[geburtsdatum]'  => get_post_meta($player_id, 'birthdate', true),
        '[geburtsort]'    => get_post_meta($player_id, 'birthplace', true),
        '[groesse]'       => get_post_meta($player_id, 'height', true),
        '[nationalitaet]' => get_post_meta($player_id, 'nationality', true),
        '[position]'      => get_post_meta($player_id, 'position', true),
        '[fuss]'          => get_post_meta($player_id, 'foot', true),
        '[berater]'       => get_post_meta($player_id, 'agent', true),
        '[verein]'        => get_post_meta($player_id, 'club', true),
    );

    if (strpos($template, '[bild]') !== false) {
        $image = get_the_post_thumbnail($player_id, 'medium', array('style' => 'max-width:100%;height:auto;'));
        $placeholders['[bild]'] = $image ? $image : '';
    }

    foreach ($placeholders as $tag => $value) {
        if ($tag !== '[bild]') {
            $placeholders[$tag] = esc_html($value);
        }
    }

    $content = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    $content = wpautop($content);

    return '<div class="mvpclub-scout-preview" style="border:1px solid #ccc;padding:1em;">' . $content . '</div>';
}

/**
 * Add admin columns for player meta fields
 */
add_filter('manage_mvpclub_player_posts_columns', 'mvpclub_player_admin_columns');
function mvpclub_player_admin_columns($columns) {
    $fields = mvpclub_player_fields();
    foreach ($fields as $key => $label) {
        $columns[$key] = $label;
    }
    return $columns;
}

add_action('manage_mvpclub_player_posts_custom_column', 'mvpclub_player_custom_column', 10, 2);
function mvpclub_player_custom_column($column, $post_id) {
    $fields = mvpclub_player_fields();
    if (isset($fields[$column])) {
        echo esc_html(get_post_meta($post_id, $column, true));
    }
}

add_filter('manage_edit-mvpclub_player_sortable_columns', 'mvpclub_player_sortable_columns');
function mvpclub_player_sortable_columns($columns) {
    $fields = mvpclub_player_fields();
    foreach ($fields as $key => $label) {
        $columns[$key] = $key;
    }
    return $columns;
}
