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
        'club'             => 'Verein',
        'market_value'     => 'Marktwert',
        'rating'           => 'Bewertung',
        'performance_data' => 'Statistik',
        'image'            => 'Bild',
        'radar_chart'      => 'Radar Chart',
    );
}

/**
 * Format detail positions like "OM (ZM / DM)".
 */
function mvpclub_format_detail_position($value) {
    $parts = array_filter(array_map('trim', explode(',', $value)));
    if (empty($parts)) return '';
    $main = array_shift($parts);
    return $parts ? $main . ' (' . implode(' / ', $parts) . ')' : $main;
}

/**
 * Create HTML table from performance data JSON
 */
function mvpclub_generate_statistik_table($json) {
    $rows = json_decode($json, true);
    if (!is_array($rows) || empty($rows)) return '';
    $html = '<table class="mvpclub-statistik"><thead><tr>'
          . '<th>Saison</th><th>Wettbewerb</th><th>Spiele</th>'
          . '<th>Tore</th><th>Assists</th><th>Minuten</th>'
          . '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>'
              . '<td>' . esc_html($r['Saison'] ?? '') . '</td>'
              . '<td>' . esc_html($r['Wettbewerb'] ?? '') . '</td>'
              . '<td>' . esc_html($r['Spiele'] ?? '') . '</td>'
              . '<td>' . esc_html($r['Tore'] ?? '') . '</td>'
              . '<td>' . esc_html($r['Assists'] ?? '') . '</td>'
              . '<td>' . esc_html($r['Minuten'] ?? '') . '</td>'
              . '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/**
 * Build placeholder array for a player
 */
function mvpclub_player_placeholders($player_id) {
    $fields = mvpclub_player_fields();
    $data   = array();
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

    $chart_html = '';
    if (!empty($data['radar_chart'])) {
        $chart = json_decode($data['radar_chart'], true);
        if (!empty($chart['labels']) && !empty($chart['values'])) {
            $chart_id = 'radar-chart-' . $player_id;
            $chart_src = plugins_url('assets/chart.js', __FILE__);
            $chart_html = '<canvas id="' . esc_attr($chart_id) . '" width="300" height="300"></canvas>';
            $chart_html .= '<script>(function(){function r(){var c=document.getElementById("' . esc_js($chart_id) . '");if(!c||typeof Chart==="undefined")return;new Chart(c,{type:"radar",data:{labels:' . wp_json_encode($chart['labels']) . ',datasets:[{label:"' . esc_js($title) . '",data:' . wp_json_encode($chart['values']) . ',backgroundColor:"rgba(54,162,235,0.2)",borderColor:"rgba(54,162,235,1)"}]},options:{scales:{r:{min:0,max:100,beginAtZero:true}}});}if(typeof Chart==="undefined"){var s=document.createElement("script");s.src="' . esc_url($chart_src) . '";s.onload=r;document.body.appendChild(s);}else{r();}})();</script>';
        }
    }

    $statistik_html = mvpclub_generate_statistik_table($data['performance_data']);

    $age_text = '';
    if (!empty($data['birthdate'])) {
        $d = DateTime::createFromFormat('d.m.Y', $data['birthdate']);
        if ($d) {
            $age_text = (new DateTime())->diff($d)->y . ' Jahre';
        }
    }

    $img_url = '';
    if ($custom_image_id) {
        $src = wp_get_attachment_image_src($custom_image_id, 'medium');
        if ($src) $img_url = $src[0];
    } else {
        $src = wp_get_attachment_image_src(get_post_thumbnail_id($player_id), 'medium');
        if ($src) $img_url = $src[0];
    }

    $placeholders = array(
        '[spielername]'     => $title,
        '[geburtsdatum]'    => isset($data['birthdate']) ? $data['birthdate'] : '',
        '[geburtsort]'      => isset($data['birthplace']) ? $data['birthplace'] : '',
        '[alter]'           => $age_text,
        '[groesse]'         => isset($data['height']) ? $data['height'] : '',
        '[nationalitaet]'   => isset($data['nationality']) ? $data['nationality'] : '',
        '[position]'        => isset($data['position']) ? $data['position'] : '',
        '[detailposition]'  => mvpclub_format_detail_position(isset($data['detail_position']) ? $data['detail_position'] : ''),
        '[fuss]'            => isset($data['foot']) ? $data['foot'] : '',
        '[verein]'          => isset($data['club']) ? $data['club'] : '',
        '[marktwert]'       => isset($data['market_value']) ? $data['market_value'] : '',
        '[bewertung]'       => isset($data['rating']) ? $data['rating'] : '',
        '[bild]'            => $img,
        '[bild-url]'        => $img_url,
        '[statistik]'       => $statistik_html,
        '[radar]'           => $chart_html,
    );

    foreach ($placeholders as $tag => $val) {
        if ($tag !== '[bild]' && $tag !== '[bild-url]' && $tag !== '[radar]' && $tag !== '[statistik]') {
            $placeholders[$tag] = esc_html($val);
        }
    }

    return $placeholders;
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

    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');


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

    wp_enqueue_script(
        'mvpclub-player-admin',
        plugins_url('assets/player-admin.js', __FILE__),
        array('jquery', 'chartjs'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/player-admin.js'),
        true
    );
    wp_localize_script('mvpclub-player-admin', 'mvpclubPlayerAdmin', array(
        'competitions' => mvpclub_competition_labels(),
    ));

    wp_enqueue_style(
        'mvpclub-player-admin',
        plugins_url('assets/admin-player.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/admin-player.css')
    );
}

function mvpclub_player_meta_box($post) {
    wp_nonce_field('mvpclub_save_player', 'mvpclub_player_nonce');

    $fields = mvpclub_player_fields();
    $values = array();
    foreach ($fields as $k => $l) {
        $values[$k] = get_post_meta($post->ID, $k, true);
    }

    echo '<div id="mvpclub-player-tabs">';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="#" class="nav-tab nav-tab-active" data-tab="info">Information</a>';
    echo '<a href="#" class="nav-tab" data-tab="rating">Bewertung</a>';
    echo '<a href="#" class="nav-tab" data-tab="statistik">Statistik</a>';
    echo '<a href="#" class="nav-tab" data-tab="radar">Radar</a>';
    echo '</h2>';

    // Information Tab
    echo '<div id="tab-info" class="mvpclub-tab-content active"><table class="form-table">';
    $info_keys = array('image','birthdate','nationality','birthplace','position','detail_position','height','foot','club','market_value');

    $age_text = '';
    if (!empty($values['birthdate'])) {
        $d = DateTime::createFromFormat('d.m.Y', $values['birthdate']);
        if ($d) {
            $age_text = (new DateTime())->diff($d)->y . ' Jahre';
        }
    }
    foreach ($info_keys as $key) {
        $label = $fields[$key];
        $value = isset($values[$key]) ? $values[$key] : '';
        if ($key === 'detail_position') {
            $selected = $value ? explode(',', $value) : array();
            $options  = array('TW','LV','IV','RV','DM','ZM','OM','LA','RA','ST');
            $main  = isset($selected[0]) ? $selected[0] : '';
            $side1 = isset($selected[1]) ? $selected[1] : '';
            $side2 = isset($selected[2]) ? $selected[2] : '';
            echo '<tr><th><label>' . esc_html($label) . '</label></th><td>';
            echo '<select name="detail_position[]" class="mvpclub-main-position">';
            echo '<option value="">-</option>';
            foreach ($options as $op) {
                $sel = $op === $main ? ' selected' : '';
                echo '<option value="' . esc_attr($op) . '"' . $sel . '>' . esc_html($op) . '</option>';
            }
            echo '</select> ';
            echo '<select name="detail_position[]">';
            echo '<option value="">-</option>';
            foreach ($options as $op) {
                $sel = $op === $side1 ? ' selected' : '';
                echo '<option value="' . esc_attr($op) . '"' . $sel . '>' . esc_html($op) . '</option>';
            }
            echo '</select> ';
            echo '<select name="detail_position[]">';
            echo '<option value="">-</option>';
            foreach ($options as $op) {
                $sel = $op === $side2 ? ' selected' : '';
                echo '<option value="' . esc_attr($op) . '"' . $sel . '>' . esc_html($op) . '</option>';
            }
            echo '</select></td></tr>';
        } elseif ($key === 'birthdate') {
            echo '<tr><th><label for="birthdate">' . esc_html($label) . '</label></th><td>';
            echo '<div class="mvpclub-birthdate-wrap">';
            echo '<input type="text" name="birthdate" id="birthdate" value="' . esc_attr($value) . '" class="regular-text" style="width:8em;margin-right:0.5em;" />';
            echo '<span class="mvpclub-age">' . esc_html($age_text) . '</span>';
            echo '</div></td></tr>';
        } elseif ($key === 'nationality') {
            $countries = mvpclub_get_country_map();
            echo '<tr><th><label for="nationality">' . esc_html($label) . '</label></th><td>';
            echo '<select name="nationality" id="nationality" class="regular-text">';
            echo '<option value=""></option>';
            foreach ($countries as $c) {
                $val = $c['emoji'] . ' ' . $c['name'];
                $sel = $value === $val ? ' selected' : '';
                echo '<option value="' . esc_attr($val) . '"' . $sel . '>' . esc_html($val) . '</option>';
            }
            echo '</select></td></tr>';
        } elseif ($key === 'birthplace') {
            $country = '';
            $city = $value;
            if (preg_match('/^(\X+)\s+(.*)$/u', $value, $m)) {
                $country = $m[1];
                $city = $m[2];
            }
            $countries = mvpclub_get_country_map();
            echo '<tr><th><label for="birthplace_city">' . esc_html($label) . '</label></th><td>';
            echo '<select name="birthplace_country" id="birthplace_country" class="mvpclub-emoji-select">';
            echo '<option value=""></option>';
            foreach ($countries as $c) {
                $sel = $country === $c['emoji'] ? ' selected' : '';
                $full = $c['emoji'] . ' ' . $c['name'];
                echo '<option value="' . esc_attr($c['emoji']) . '"' . $sel . ' data-emoji="' . esc_attr($c['emoji']) . '" data-full="' . esc_attr($full) . '">' . esc_html($full) . '</option>';
            }
            echo '</select> ';
            echo '<input type="text" name="birthplace_city" id="birthplace_city" value="' . esc_attr($city) . '" class="regular-text" />';
            echo '</td></tr>';
        } elseif ($key === 'position') {
            $options = array('Tor','Abwehr','Mittelfeld','Sturm');
            echo '<tr><th><label for="position">' . esc_html($label) . '</label></th><td>';
            echo '<select name="position" id="position">';
            echo '<option value="">-</option>';
            foreach ($options as $op) {
                $sel = $op === $value ? ' selected' : '';
                echo '<option value="' . esc_attr($op) . '"' . $sel . '>' . esc_html($op) . '</option>';
            }
            echo '</select></td></tr>';
        } elseif ($key === 'foot') {
            $options = array('Links','Rechts','Beidfüßig');
            echo '<tr><th><label for="foot">' . esc_html($label) . '</label></th><td>';
            echo '<select name="foot" id="foot">';
            echo '<option value="">-</option>';
            foreach ($options as $op) {
                $sel = $op === $value ? ' selected' : '';
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
    echo '</table></div>';

    // Rating Tab
    echo '<div id="tab-rating" class="mvpclub-tab-content"><table class="form-table">';
    $rating = isset($values['rating']) && $values['rating'] !== '' ? $values['rating'] : '3.0';
    echo '<tr><th><label for="rating">' . esc_html($fields['rating']) . '</label></th><td><input type="range" name="rating" id="rating" min="1" max="5" step="0.5" value="' . esc_attr($rating) . '" oninput="this.nextElementSibling.value=this.value" /> <output>' . esc_html($rating) . '</output><br />';
    echo '<pre class="mvpclub-rating-info" style="margin-top:4px;white-space:pre-wrap;">5.0 (S): Weltklassespieler
4.5 (A+): CL-Schlüsselspieler
4.0 (A): CL-Kaderspieler
3.5 (B+): Top-5-Schlüsselspieler
3.0 (B): Top-5-Kaderspieler
2.5 (C+): Top-9-Schlüsselspieler
2.0 (C): Top-9-Kaderspieler
1.5 (D+): RDW-Schlüsselspieler
1.0 (D): RDW-Kaderspieler</pre></td></tr>';
    echo '</table></div>';

    // Performance Tab
    echo '<div id="tab-statistik" class="mvpclub-tab-content"><table id="statistik-data-table" class="widefat"><thead><tr><th>Saison</th><th>Wettbewerb</th><th>Spiele</th><th>Tore</th><th>Assists</th><th>Minuten</th><th></th></tr></thead><tbody>';
    $perf = json_decode($values['performance_data'], true);
    if (!is_array($perf)) { $perf = array(); }
    foreach ($perf as $row) {
        echo '<tr>';
        echo '<td><input type="text" name="perf_saison[]" value="' . esc_attr($row['Saison'] ?? '') . '" /></td>';
        echo '<td>' . mvpclub_competition_select($row['Wettbewerb'] ?? '', 'perf_competition[]') . '</td>';
        echo '<td><input type="number" name="perf_games[]" value="' . esc_attr($row['Spiele'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_goals[]" value="' . esc_attr($row['Tore'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_assists[]" value="' . esc_attr($row['Assists'] ?? '') . '" /></td>';
        echo '<td><input type="number" name="perf_minutes[]" value="' . esc_attr($row['Minuten'] ?? '') . '" /></td>';
        echo '<td><button class="button remove-statistik-row">X</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><button type="button" class="button" id="add-statistik-row">Zeile hinzufügen</button></p></div>';

    // Radar Tab
    echo '<div id="tab-radar" class="mvpclub-tab-content"><div class="mvpclub-radar-flex"><canvas id="mvpclub-radar-preview" width="400" height="400"></canvas><table class="form-table mvpclub-radar-settings">';
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
    echo '</table></div></div>';

    echo '</div>'; // end tabs
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
        } elseif ($key === 'birthplace') {
            $country = isset($_POST['birthplace_country']) ? wp_unslash($_POST['birthplace_country']) : '';
            $city = isset($_POST['birthplace_city']) ? sanitize_text_field($_POST['birthplace_city']) : '';
            $val = trim($country . ' ' . $city);
            if ($val !== '') {
                update_post_meta($post_id, 'birthplace', $val);
            } else {
                delete_post_meta($post_id, 'birthplace');
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
                        'Wettbewerb' => sanitize_text_field(wp_unslash($_POST['perf_competition'][$i])),
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
    if (isset($_POST['mvpclub_scout_code']) && check_admin_referer('mvpclub_scout_settings', 'mvpclub_scout_nonce')) {
        $code = wp_unslash($_POST['mvpclub_scout_code']);
        if (!current_user_can('unfiltered_html')) {
            $code = wp_kses_post($code);
        }
        update_option('mvpclub_scout_code', $code);
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }

    $code = get_option('mvpclub_scout_code', '<p>[spielername] - [verein]</p>');

    $players = get_posts(array(
        'post_type'   => 'mvpclub-spieler',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
    ));
    $selected_player = 0;
    foreach ($players as $p) {
        if ($p->post_title === 'Lennart Karl') {
            $selected_player = $p->ID;
            break;
        }
    }
    if (!$selected_player && !empty($players)) {
        $selected_player = $players[0]->ID;
    }

    $placeholders = mvpclub_player_placeholders($selected_player);
    $preview_html = str_replace(array_keys($placeholders), array_values($placeholders), $code);
    $preview      = $preview_html;
    ?>
    <div class="wrap">
        <h1>Scoutingberichte</h1>
        <form method="post" id="mvpclub-scout-form">
            <?php wp_nonce_field('mvpclub_scout_settings', 'mvpclub_scout_nonce'); ?>
            <h2>Code</h2>
            <textarea id="mvpclub_scout_code" name="mvpclub_scout_code" rows="15" class="large-text code"><?php echo esc_textarea($code); ?></textarea>
            <p><?php esc_html_e('Platzhalter einfügen:', 'mvpclub'); ?>
                <?php foreach ($placeholders as $tag => $sample) : ?>
                    <button type="button" class="insert-placeholder button" data-placeholder="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
                <?php endforeach; ?>
            </p>
            <?php submit_button('Speichern'); ?>
        </form>

        <h2>Vorschau <select id="mvpclub_preview_player" style="float:right;">
            <?php foreach ($players as $p) : ?>
                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($p->ID, $selected_player); ?>><?php echo esc_html($p->post_title); ?></option>
            <?php endforeach; ?>
        </select></h2>
        <div class="mvpclub-scout-preview" style="border:1px solid #ccc;padding:1em;">
            <?php echo $preview; ?>
        </div>
    </div>
    <script>
    var mvpclubPreview = { nonce: '<?php echo wp_create_nonce('mvpclub_scout_preview'); ?>' };
    jQuery(function($){
        function updatePreview(){
            $.post(ajaxurl, {
                action: 'mvpclub_preview_code',
                nonce: mvpclubPreview.nonce,
                playerId: $('#mvpclub_preview_player').val(),
                code: $('#mvpclub_scout_code').val()
            }, function(resp){
                $('.mvpclub-scout-preview').html(resp);
            });
        }

        $('.insert-placeholder').on('click', function(){
            var tag = $(this).data('placeholder');
            var textarea = $('#mvpclub_scout_code');
            textarea.val(textarea.val() + tag);
            textarea.focus();
            updatePreview();
        });

        $('#mvpclub_scout_code').on('input', updatePreview);
        $('#mvpclub_preview_player').on('change', updatePreview);
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

    $bg   = get_option('mvpclub_player_bg_color', '#f9f9f9');
    $text = get_option('mvpclub_player_text_color', '#000000');
    $code = get_option('mvpclub_scout_code', '<p>[spielername] - [verein]</p>');

    $placeholders = mvpclub_player_placeholders($player_id);

    $content = str_replace(array_keys($placeholders), array_values($placeholders), $code);
    ob_start();
    echo '<div class="mvpclub-player-info" style="background:' . esc_attr($bg) . ';color:' . esc_attr($text) . ';padding:1em;">';
    echo $content;
    echo '</div>';
    return ob_get_clean();
}

/**
 * AJAX handler for live preview of player info code
 */
add_action('wp_ajax_mvpclub_preview_code', 'mvpclub_ajax_preview_code');
function mvpclub_ajax_preview_code() {
    check_ajax_referer('mvpclub_scout_preview', 'nonce');

    $player_id = absint($_POST['playerId']);
    $code      = wp_unslash($_POST['code']);
    if (!current_user_can('unfiltered_html')) {
        $code = wp_kses_post($code);
    }
    if (!$player_id) {
        wp_die('');
    }

    $placeholders = mvpclub_player_placeholders($player_id);
    $content      = str_replace(array_keys($placeholders), array_values($placeholders), $code);
    echo $content;
    wp_die();
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
