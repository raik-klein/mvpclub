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
        'image'       => 'Bild',
        'radar_chart' => 'Radar Chart',
    );
}

/**
 * Register meta fields so they appear in the REST API
 */
add_action('init', function() {
    foreach (mvpclub_player_fields() as $key => $label) {
        $args = array(
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback'=> function() { return current_user_can('edit_posts'); },
        );
        if ($key === 'radar_chart') {
            $args['type'] = 'string';
            $args['sanitize_callback'] = null;
        } elseif ($key === 'image') {
            $args['type'] = 'integer';
            $args['sanitize_callback'] = 'absint';
        } else {
            $args['type'] = 'string';
            $args['sanitize_callback'] = 'sanitize_text_field';
        }
        register_post_meta('mvpclub-spieler', $key, $args);
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
    if ($screen->post_type !== 'mvpclub-spieler') return;

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

    wp_enqueue_media();
    wp_enqueue_script(
        'mvpclub-player-image',
        plugins_url('assets/player-image.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/player-image.js'),
        true
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
        } elseif ($key === 'image') {
            $img_id  = intval($value);
            $preview = $img_id ? wp_get_attachment_image_src($img_id, 'thumbnail') : false;
            echo '<tr><th><label for="mvpclub-player-image">' . esc_html($label) . '</label></th><td>';
            echo '<div class="mvpclub-player-image-preview">';
            if ($preview) {
                echo '<img src="' . esc_url($preview[0]) . '" style="max-width:150px;height:auto;" />';
            }
            echo '</div>';
            echo '<input type="hidden" name="image" id="mvpclub-player-image" value="' . esc_attr($img_id) . '" />';
            echo '<p><button type="button" class="button mvpclub-player-image-select">Bild auswählen</button> ';
            echo '<button type="button" class="button mvpclub-player-image-remove">Bild entfernen</button></p>';
            echo '</td></tr>';
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
        } elseif ($key === 'image') {
            $img = isset($_POST['image']) ? intval($_POST['image']) : 0;
            if ($img) {
                update_post_meta($post_id, 'image', $img);
            } else {
                delete_post_meta($post_id, 'image');
            }
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
    if (!$player_id) return '';

    $fields = mvpclub_player_fields();
    $data = array();
    foreach ($fields as $key => $label) {
        $data[$key] = get_post_meta($player_id, $key, true);
    }
    $title = get_the_title($player_id);
    $custom_image_id = intval(get_post_meta($player_id, 'image', true));
    if ($custom_image_id) {
        $img = wp_get_attachment_image($custom_image_id, 'medium', false, array('style' => 'max-width:100%;height:auto;'));
    } else {
        $img = get_the_post_thumbnail($player_id, 'medium', array('style' => 'max-width:100%;height:auto;'));
    }
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
    echo '</ul>';

    if (!empty($data['radar_chart'])) {
        $chart = json_decode($data['radar_chart'], true);
        if (!empty($chart['labels']) && !empty($chart['values'])) {
            $chart_id = 'radar-chart-' . $player_id;
            echo '<canvas id="' . esc_attr($chart_id) . '" width="300" height="300"></canvas>';
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js');
            $inline  = 'document.addEventListener("DOMContentLoaded",function(){var c=document.getElementById("' . esc_js($chart_id) . '");if(c){new Chart(c,{type:"radar",data:{labels:' . wp_json_encode($chart['labels']) . ',datasets:[{label:"' . esc_js($title) . '",data:' . wp_json_encode($chart['values']) . ',backgroundColor:"rgba(54,162,235,0.2)",borderColor:"rgba(54,162,235,1)"}]},options:{scales:{r:{min:0,max:100,beginAtZero:true}}});}});';
            wp_add_inline_script('chartjs', $inline);
        }
    }
    echo '</div>';
    return ob_get_clean();
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
        if ($column === 'image') {
            $img_id = intval(get_post_meta($post_id, 'image', true));
            if ($img_id) {
                echo wp_get_attachment_image($img_id, 'thumbnail');
            } else {
                echo get_the_post_thumbnail($post_id, 'thumbnail');
            }
        } else {
            echo esc_html(get_post_meta($post_id, $column, true));
        }
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
