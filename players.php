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
        'birthdate'        => 'Geburtsdatum',
        'birthplace'       => 'Geburtsort',
        'height'           => 'Größe',
        'nationality'      => 'Nationalität',
        'position'         => 'Position',
        'detail_position'  => 'Detailposition',
        'foot'             => 'Fuß',
        'agent'            => 'Berater',
        'club'             => 'Verein',
        'market_value'     => 'Marktwert',
        'rating'           => 'Bewertung',
        'performance_data' => 'Leistungsdaten',
        'image'            => 'Bild',
        'radar_chart'      => 'Radar Chart',
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
        if ($key === 'radar_chart' || $key === 'performance_data') {
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

    wp_enqueue_script(
        'chartjs',
        plugins_url('assets/chart.js', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/chart.js'),
        true
    );
}

function mvpclub_player_meta_box($post) {
    wp_nonce_field('mvpclub_save_player', 'mvpclub_player_nonce');

    $fields = mvpclub_player_fields();
    $values = array();
    foreach ($fields as $k => $l) {
        $values[$k] = get_post_meta($post->ID, $k, true);
    }

    echo '<h2>Information</h2><table class="form-table">';
    $info_keys = array('birthdate','birthplace','height','nationality','position','detail_position','foot','agent','club','market_value','image');
    foreach ($info_keys as $key) {
        $label = $fields[$key];
        $value = isset($values[$key]) ? $values[$key] : '';
        if ($key === 'detail_position') {
            $selected = $value ? explode(',', $value) : array();
            $options = array('TW','LV','IV','RV','DM','ZM','OM','LA','RA','ST');
            echo '<tr><th><label for="detail_position">' . esc_html($label) . '</label></th><td><select name="detail_position[]" id="detail_position" multiple size="5">';
            foreach ($options as $op) {
                $sel = in_array($op, $selected) ? ' selected' : '';
                echo '<option value="' . esc_attr($op) . '"' . $sel . '>' . esc_html($op) . '</option>';
            }
            echo '</select></td></tr>';
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

    echo '<h2>Bewertung</h2><table class="form-table">';
    $rating = isset($values['rating']) ? $values['rating'] : '';
    echo '<tr><th><label for="rating">' . esc_html($fields['rating']) . '</label></th><td><input type="range" name="rating" id="rating" min="1" max="5" step="0.5" value="' . esc_attr($rating) . '" oninput="this.nextElementSibling.value=this.value" /> <output>' . esc_html($rating) . '</output></td></tr>';
    echo '</table>';

    echo '<h2>Leistungsdaten</h2>';
    $perf = json_decode($values['performance_data'], true);
    if (!is_array($perf)) { $perf = array(); }
    echo '<table id="performance-data-table" class="widefat"><thead><tr><th>Saison</th><th>Wettbewerb</th><th>Spiele</th><th>Tore</th><th>Assists</th><th>Minuten</th><th></th></tr></thead><tbody>';
    foreach ($perf as $row) {
        echo '<tr>';
        echo '<td><input type="text" name="perf_saison[]" value="' . esc_attr($row['Saison'] ?? '') . '" /></td>';
        echo '<td><input type="text" name="perf_competition[]" value="' . esc_attr($row['Wettbewerb'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_games[]" value="' . esc_attr($row['Spiele'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_goals[]" value="' . esc_attr($row['Tore'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_assists[]" value="' . esc_attr($row['Assists'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_minutes[]" value="' . esc_attr($row['Minuten'] ?? '') . '" /></td>';
        echo '<td><button class="button remove-performance-row">X</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><button type="button" class="button" id="add-performance-row">Zeile hinzufügen</button></p>';

    echo '<h2>Radar</h2><table class="form-table">';
    $chart = json_decode($values['radar_chart'], true);
    $labels = isset($chart['labels']) ? (array) $chart['labels'] : array_fill(0, 6, '');
    $values_radar = isset($chart['values']) ? (array) $chart['values'] : array_fill(0, 6, 0);
    for ($i = 0; $i < 6; $i++) {
        $l = isset($labels[$i]) ? $labels[$i] : '';
        $v = isset($values_radar[$i]) ? $values_radar[$i] : 0;
        echo '<tr><td><input type="text" name="radar_chart_label' . $i . '" value="' . esc_attr($l) . '" placeholder="Label" /></td>';
        echo '<td><input type="range" name="radar_chart_value' . $i . '" min="0" max="100" value="' . esc_attr($v) . '" oninput="this.nextElementSibling.value=this.value" />';
        echo '<output>' . esc_html($v) . '</output></td></tr>';
    }
    echo '<tr><td colspan="2"><canvas id="mvpclub-radar-preview" width="300" height="300"></canvas></td></tr>';
    echo '</table>';

    ?>
    <script>
    jQuery(function($){
        $('#add-performance-row').on('click', function(e){
            e.preventDefault();
            var row = $('<tr>\
                <td><input type="text" name="perf_saison[]" /></td>\
                <td><input type="text" name="perf_competition[]" /></td>\
                <td><input type="number" name="perf_games[]" /></td>\
                <td><input type="number" name="perf_goals[]" /></td>\
                <td><input type="number" name="perf_assists[]" /></td>\
                <td><input type="number" name="perf_minutes[]" /></td>\
                <td><button class="button remove-performance-row">X</button></td>\
            </tr>');
            $('#performance-data-table tbody').append(row);
        });
        $(document).on('click', '.remove-performance-row', function(e){
            e.preventDefault();
            $(this).closest('tr').remove();
        });

        var ctx = document.getElementById('mvpclub-radar-preview');
        var radarChart;
        function renderRadar(){
            if(!ctx){return;}
            var labels = [], data = [];
            for(var i=0;i<6;i++){
                labels.push($('[name="radar_chart_label'+i+'"]').val());
                data.push(parseInt($('[name="radar_chart_value'+i+'"]').val()) || 0);
            }
            if(radarChart){radarChart.destroy();}
            radarChart = new Chart(ctx,{type:'radar',data:{labels:labels,datasets:[{label:'Werte',data:data,backgroundColor:'rgba(54,162,235,0.2)',borderColor:'rgba(54,162,235,1)'}]},options:{scales:{r:{min:0,max:100,beginAtZero:true}}});
        }
        $('[name^="radar_chart_label"], [name^="radar_chart_value"]').on('input', renderRadar);
        renderRadar();
    });
    </script>
    <?php
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
        } elseif ($key === 'detail_position') {
            if (isset($_POST['detail_position']) && is_array($_POST['detail_position'])) {
                $val = implode(',', array_map('sanitize_text_field', $_POST['detail_position']));
                update_post_meta($post_id, 'detail_position', $val);
            } else {
                delete_post_meta($post_id, 'detail_position');
            }
        } elseif ($key === 'rating') {
            $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : '';
            if ($rating !== '') {
                update_post_meta($post_id, 'rating', $rating);
            } else {
                delete_post_meta($post_id, 'rating');
            }
        } elseif ($key === 'performance_data') {
            $perf = array();
            if (isset($_POST['perf_saison'])) {
                $count = count($_POST['perf_saison']);
                for ($i = 0; $i < $count; $i++) {
                    $row = array(
                        'Saison'     => sanitize_text_field($_POST['perf_saison'][$i]),
                        'Wettbewerb' => sanitize_text_field($_POST['perf_competition'][$i]),
                        'Spiele'     => intval($_POST['perf_games'][$i]),
                        'Tore'       => intval($_POST['perf_goals'][$i]),
                        'Assists'    => intval($_POST['perf_assists'][$i]),
                        'Minuten'    => intval($_POST['perf_minutes'][$i]),
                    );
                    if (array_filter($row)) {
                        $perf[] = $row;
                    }
                }
            }
            update_post_meta($post_id, 'performance_data', wp_json_encode($perf));
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
        update_option('mvpclub_scout_template', wp_kses_post(wp_unslash($_POST['mvpclub_scout_template'])));
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }

    $default  = '<!-- wp:paragraph --><p>[spieler-name] - [verein]</p><!-- /wp:paragraph -->';
    $template = get_option('mvpclub_scout_template', $default);

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

    $preview_html = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    $preview      = do_blocks($preview_html);

    // Block editor assets.
    wp_enqueue_script('wp-blocks');
    wp_enqueue_script('wp-element');
    wp_enqueue_script('wp-data');
    wp_enqueue_script('wp-components');
    wp_enqueue_script('wp-block-editor');
    wp_enqueue_style('wp-edit-blocks');

    wp_register_script(
        'mvpclub-scout-editor',
        plugins_url('assets/scout-editor.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-data', 'wp-block-editor'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/scout-editor.js'),
        true
    );
    wp_localize_script('mvpclub-scout-editor', 'mvpclubScoutEditor', array('template' => $template));
    wp_enqueue_script('mvpclub-scout-editor');
    ?>
    <div class="wrap">
        <h1>Scoutingberichte</h1>
        <form method="post" id="mvpclub-scout-form">
            <?php wp_nonce_field('mvpclub_scout_settings', 'mvpclub_scout_nonce'); ?>
            <textarea id="mvpclub_scout_template" name="mvpclub_scout_template" style="display:none;">
                <?php echo esc_textarea($template); ?>
            </textarea>
            <div id="mvpclub-block-editor" class="mvpclub-block-editor" style="min-height:200px;border:1px solid #ccd0d4;"></div>
            <p><?php esc_html_e('Platzhalter einfügen:', 'mvpclub'); ?>
                <?php foreach ($placeholders as $tag => $sample) : ?>
                    <button type="button" class="insert-placeholder button" data-placeholder="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
                <?php endforeach; ?>
            </p>
            <?php submit_button('Speichern'); ?>
        </form>

        <h2>Vorschau</h2>
        <div class="mvpclub-scout-preview" style="border:1px solid #ccc;padding:1em;">
            <?php echo $preview; ?>
        </div>
    </div>
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

    $template = get_option('mvpclub_scout_template', '<p>[spieler-name] - [verein]</p>');

    $chart_html = '';
    if (!empty($data['radar_chart'])) {
        $chart = json_decode($data['radar_chart'], true);
        if (!empty($chart['labels']) && !empty($chart['values'])) {
            $chart_id = 'radar-chart-' . $player_id;
            $chart_html = '<canvas id="' . esc_attr($chart_id) . '" width="300" height="300"></canvas>';
            wp_enqueue_script(
                'chartjs',
                plugins_url('assets/chart.js', __FILE__),
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'assets/chart.js'),
                true
            );
            $inline  = 'document.addEventListener("DOMContentLoaded",function(){var c=document.getElementById("' . esc_js($chart_id) . '");if(c){new Chart(c,{type:"radar",data:{labels:' . wp_json_encode($chart['labels']) . ',datasets:[{label:"' . esc_js($title) . '",data:' . wp_json_encode($chart['values']) . ',backgroundColor:"rgba(54,162,235,0.2)",borderColor:"rgba(54,162,235,1)"}]},options:{scales:{r:{min:0,max:100,beginAtZero:true}}});}});';
            wp_add_inline_script('chartjs', $inline);
        }
    }

    $placeholders = array(
        '[spieler-name]'  => $title,
        '[geburtsdatum]'  => isset($data['birthdate']) ? $data['birthdate'] : '',
        '[geburtsort]'    => isset($data['birthplace']) ? $data['birthplace'] : '',
        '[groesse]'       => isset($data['height']) ? $data['height'] : '',
        '[nationalitaet]' => isset($data['nationality']) ? $data['nationality'] : '',
        '[position]'      => isset($data['position']) ? $data['position'] : '',
        '[fuss]'          => isset($data['foot']) ? $data['foot'] : '',
        '[berater]'       => isset($data['agent']) ? $data['agent'] : '',
        '[verein]'        => isset($data['club']) ? $data['club'] : '',
        '[bild]'          => $img,
        '[radar_chart]'   => $chart_html,
    );

    foreach ($placeholders as $tag => $val) {
        if ($tag !== '[bild]' && $tag !== '[radar_chart]') {
            $placeholders[$tag] = esc_html($val);
        }
    }

    $content = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    $content = wpautop($content);

    ob_start();
    echo '<div class="mvpclub-player-info" style="background:' . esc_attr($bg) . ';color:' . esc_attr($text) . ';padding:1em;">';
    echo $content;
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
