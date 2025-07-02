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
        'radar_chart' => 'Radar Chart',
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
    add_meta_box('mvpclub_player_details', 'Spielerdaten', 'mvpclub_player_meta_box', 'mvpclub_player');
});

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
add_action('save_post_mvpclub_player', function($post_id) {
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
    add_submenu_page('mvpclub-main', 'Spielerdatenbank', 'Spielerdatenbank', 'edit_posts', 'edit.php?post_type=mvpclub_player');
    add_submenu_page('mvpclub-main', 'Scoutingberichte', 'Scoutingberichte', 'edit_posts', 'mvpclub-scout-settings', 'mvpclub_render_scout_settings_page');
});

/**
 * Render the "Scoutingberichte" settings page
 */
function mvpclub_render_scout_settings_page() {
    if (isset($_POST['mvpclub_player_bg_color']) && check_admin_referer('mvpclub_scout_settings', 'mvpclub_scout_nonce')) {
        update_option('mvpclub_player_bg_color', sanitize_hex_color($_POST['mvpclub_player_bg_color']));
        update_option('mvpclub_player_text_color', sanitize_hex_color($_POST['mvpclub_player_text_color']));
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }
    $bg   = get_option('mvpclub_player_bg_color', '#f9f9f9');
    $text = get_option('mvpclub_player_text_color', '#000000');
    ?>
    <div class="wrap">
        <h1>Scoutingberichte</h1>
        <form method="post">
            <?php wp_nonce_field('mvpclub_scout_settings', 'mvpclub_scout_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mvpclub_player_bg_color">Hintergrundfarbe</label></th>
                    <td><input name="mvpclub_player_bg_color" type="text" id="mvpclub_player_bg_color" value="<?php echo esc_attr($bg); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mvpclub_player_text_color">Textfarbe</label></th>
                    <td><input name="mvpclub_player_text_color" type="text" id="mvpclub_player_text_color" value="<?php echo esc_attr($text); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Speichern'); ?>
        </form>
    </div>
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
