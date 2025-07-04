<?php
if (!defined('ABSPATH')) exit;

function mvpclub_get_api_football_key() {
    return get_option('mvpclub_api_football_key', '');
}

function mvpclub_api_football_request($endpoint, $params = array()) {
    $key = mvpclub_get_api_football_key();
    if (empty($key)) {
        return new WP_Error('missing_key', __('API key not set', 'mvpclub'));
    }

    $url = 'https://v3.football.api-sports.io/' . ltrim($endpoint, '/');
    if (!empty($params)) {
        $url = add_query_arg($params, $url);
    }

    $response = wp_remote_get($url, array(
        'headers' => array(
            'x-apisports-key' => $key,
        ),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        return new WP_Error('bad_response', __('API request failed', 'mvpclub'), $response);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    return $data;
}

/**
 * Return nationality label in plugin format (emoji + German name)
 * from an English country name.
 */
function mvpclub_get_nationality_label($english_name) {
    static $map = null;
    if ($map === null) {
        $map = array();
        $path_en = plugin_dir_path(__FILE__) . 'assets/countries_en.json';
        if (file_exists($path_en)) {
            $en_map = json_decode(file_get_contents($path_en), true);
            if (is_array($en_map)) {
                $countries = mvpclub_get_country_map();
                foreach ($en_map as $en => $code) {
                    if (isset($countries[$code])) {
                        $map[$en] = trim($countries[$code]['emoji'] . ' ' . $countries[$code]['name']);
                    }
                }
            }
        }
    }
    return isset($map[$english_name]) ? $map[$english_name] : '';
}

function mvpclub_api_football_get_player($player_id, $season = null) {
    if (!$season) {
        $season = date('Y');
    }
    $data = mvpclub_api_football_request('players', array(
        'id' => $player_id,
        'season' => $season,
    ));

    if (is_wp_error($data)) {
        return $data;
    }
    if (empty($data['response'][0])) {
        return new WP_Error('not_found', __('Player not found', 'mvpclub'));
    }
    return $data['response'][0];
}

function mvpclub_api_football_get_player_profiles($player_id) {
    $data = mvpclub_api_football_request('players/profiles', array(
        'player' => $player_id,
    ));

    if (is_wp_error($data)) {
        return $data;
    }

    return isset($data['response']) ? $data['response'] : array();
}

function mvpclub_api_football_search_players($query) {
    $params = array(
        'search' => $query,
    );
    $data = mvpclub_api_football_request('players/profiles', $params);

    if (is_wp_error($data)) {
        return $data;
    }

    return isset($data['response']) ? $data['response'] : array();
}

function mvpclub_create_player_post($player){
    $full_name = trim(($player['firstname'] ?? '') . ' ' . ($player['lastname'] ?? ''));
    if ($full_name === '') {
        $full_name = isset($player['name']) ? $player['name'] : '';
    }
    $post_id = wp_insert_post(array(
        'post_type'   => 'mvpclub-spieler',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field($full_name),
    ));
    if (is_wp_error($post_id)) return $post_id;

    update_post_meta($post_id, 'api_id', intval($player['id']));

    if (!empty($player['birth']['date'])) {
        $d = DateTime::createFromFormat('Y-m-d', $player['birth']['date']);
        if ($d) update_post_meta($post_id, 'birthdate', $d->format('d.m.Y'));
    }
    if (!empty($player['birth']['country']) || !empty($player['birth']['place'])) {
        $place = trim($player['birth']['country'] . ' ' . $player['birth']['place']);
        update_post_meta($post_id, 'birthplace', $place);
    }
    if (!empty($player['nationality'])) {
        $nat_label = mvpclub_get_nationality_label($player['nationality']);
        update_post_meta($post_id, 'nationality', $nat_label ? $nat_label : $player['nationality']);
    }
    if (!empty($player['height']) && preg_match('/(\d+)/', $player['height'], $m)) {
        update_post_meta($post_id, 'height', intval($m[1]));
    }
    if (!empty($player['position'])) {
        $map = array('Goalkeeper'=>'Tor','Defender'=>'Abwehr','Midfielder'=>'Mittelfeld','Attacker'=>'Sturm');
        $pos = isset($map[$player['position']]) ? $map[$player['position']] : $player['position'];
        update_post_meta($post_id, 'position', $pos);
    }
    if (!empty($player['photo'])) {
        update_post_meta($post_id, 'image_external', esc_url_raw($player['photo']));
    }
    return $post_id;
}

function mvpclub_import_player_post($player_id, $season = null) {
    $profiles = mvpclub_api_football_get_player_profiles($player_id);
    if (is_wp_error($profiles)) {
        return $profiles;
    }
    if (empty($profiles[0]['player'])) {
        return new WP_Error('not_found', __('Player not found', 'mvpclub'));
    }

    return mvpclub_create_player_post($profiles[0]['player']);
}

function mvpclub_api_football_get_player_seasons($player_id) {
    $data = mvpclub_api_football_request('players/seasons', array(
        'player' => $player_id,
    ));

    if (is_wp_error($data)) {
        return $data;
    }

    return isset($data['response']) ? $data['response'] : array();
}

add_action('wp_ajax_mvpclub_search_players', 'mvpclub_ajax_search_players');
function mvpclub_ajax_search_players(){
    check_ajax_referer('mvpclub_api_football', 'nonce');
    $query  = sanitize_text_field($_POST['query']);
    $res = mvpclub_api_football_search_players($query);
    if(is_wp_error($res)){
        wp_send_json_error($res->get_error_message());
    }
    wp_send_json_success($res);
}

add_action('wp_ajax_mvpclub_add_player', 'mvpclub_ajax_add_player');
function mvpclub_ajax_add_player(){
    check_ajax_referer('mvpclub_add_player', 'nonce');
    $raw = isset($_POST['player']) ? $_POST['player'] : '';
    if (is_string($raw)) {
        $player = json_decode(wp_unslash($raw), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Ungültige Daten', 'mvpclub'));
        }
    } elseif (is_array($raw)) {
        $player = array_map('wp_unslash', $raw);
    } else {
        $player = array();
    }
    if (empty($player['id'])) {
        wp_send_json_error(__('Missing data', 'mvpclub'));
    }
    $id = mvpclub_create_player_post($player);
    if(is_wp_error($id)){
        wp_send_json_error($id->get_error_message());
    }
    $edit_link = admin_url('post.php?post='.$id.'&action=edit');
    wp_send_json_success(array('post_id'=>$id,'edit_link'=>$edit_link));
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mvpclub import-player', function($args, $assoc_args) {
        list($player_id) = $args;
        $season = isset($assoc_args['season']) ? $assoc_args['season'] : null;
        $id = mvpclub_import_player_post($player_id, $season);
        if (is_wp_error($id)) {
            WP_CLI::error($id->get_error_message());
        } else {
            WP_CLI::success('Imported player with post ID ' . $id);
        }
    });
}

add_action('admin_menu', function() {
    add_submenu_page(
        'mvpclub-main',
        __('API', 'mvpclub'),
        __('API', 'mvpclub'),
        'manage_options',
        'mvpclub-api-football',
        'mvpclub_render_api_football_settings_page'
    );
});

add_action('admin_enqueue_scripts', 'mvpclub_api_football_admin_scripts');
function mvpclub_api_football_admin_scripts($hook){
    if (strpos($hook, 'mvpclub-api-football') === false) return;
    wp_enqueue_script(
        'mvpclub-api-football-admin',
        plugins_url('assets/api-football-admin.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__).'assets/api-football-admin.js'),
        true
    );
    wp_localize_script('mvpclub-api-football-admin','mvpclubAPIFootball',array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('mvpclub_api_football'),
        'addNonce'=> wp_create_nonce('mvpclub_add_player')
    ));
}

function mvpclub_render_api_football_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['mvpclub_api_key']) && check_admin_referer('mvpclub_api_football_save', 'mvpclub_api_football_nonce')) {
        update_option('mvpclub_api_football_key', sanitize_text_field($_POST['mvpclub_api_key']));
        echo '<div class="updated"><p>' . esc_html__('Einstellungen gespeichert.', 'mvpclub') . '</p></div>';
    }

    $import_result = null;
    if (isset($_GET['add_player']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'mvpclub_add_player')) {
        $pid = intval($_GET['add_player']);
        $import_result = mvpclub_import_player_post($pid);
        if (is_wp_error($import_result)) {
            echo '<div class="error"><p>' . esc_html($import_result->get_error_message()) . '</p></div>';
        } else {
            $edit_link = admin_url('post.php?post=' . $import_result . '&action=edit');
            echo '<div class="updated"><p>' . sprintf(esc_html__('Spieler importiert. %sProfil bearbeiten%s', 'mvpclub'), '<a href="' . esc_url($edit_link) . '">', '</a>') . '</p></div>';
        }
    }

    $player_search = isset($_GET['player_search']) ? sanitize_text_field($_GET['player_search']) : '';
    $results = array();
    if ($player_search !== '') {
        $result = mvpclub_api_football_search_players($player_search);
        if (is_wp_error($result)) {
            echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            $results = $result;
        }
    }

    $key = get_option('mvpclub_api_football_key', '');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('API', 'mvpclub'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('mvpclub_api_football_save','mvpclub_api_football_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mvpclub_api_key"><?php echo esc_html__('API Key', 'mvpclub'); ?></label></th>
                    <td><input name="mvpclub_api_key" type="text" id="mvpclub_api_key" value="<?php echo esc_attr($key); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(__('Speichern', 'mvpclub')); ?>
        </form>

        <h2><?php echo esc_html__('Spieler suchen', 'mvpclub'); ?></h2>
        <form id="mvpclub-player-search-form" method="get">
            <input type="hidden" name="page" value="mvpclub-api-football" />
            <input name="player_search" type="text" value="<?php echo esc_attr($player_search); ?>" class="regular-text" />
            <?php submit_button(__('Suchen', 'mvpclub'), 'secondary', 'submit', false); ?>
        </form>

        <h2><?php echo esc_html__('Ergebnisse', 'mvpclub'); ?></h2>
        <style>
            #mvpclub-search-results .mvpclub-add-player{display:block;width:100%;box-sizing:border-box;}
            #mvpclub-search-pagination{margin-top:10px;}
            #mvpclub-search-pagination a{margin-right:5px;text-decoration:none;}
            #mvpclub-search-pagination a.current{font-weight:bold;}
        </style>
        <table id="mvpclub-search-results" class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Vorname', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Nachname', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Alter', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Geburtsdatum', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Geburtsort', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Nationalität', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Größe', 'mvpclub'); ?></th>
                    <th><?php echo esc_html__('Position', 'mvpclub'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($results)) : foreach ($results as $row) :
                    $p = $row['player'];
                    $link = '#';
                ?>
                    <tr>
                        <td><?php echo esc_html($p['id']); ?></td>
                        <td><?php echo esc_html($p['firstname']); ?></td>
                        <td><?php echo esc_html($p['lastname']); ?></td>
                        <td><?php echo esc_html($p['age']); ?></td>
                        <td><?php echo esc_html($p['birth']['date']); ?></td>
                        <td><?php echo esc_html($p['birth']['place']); ?></td>
                        <td><?php echo esc_html($p['nationality']); ?></td>
                        <td><?php echo esc_html($p['height']); ?></td>
                        <td><?php echo esc_html($p['position']); ?></td>
                        <td><button type="button" class="button mvpclub-add-player" data-id="<?php echo esc_attr($p['id']); ?>" data-player='<?php echo esc_attr(wp_json_encode($p)); ?>'><?php echo esc_html__('Hinzufügen', 'mvpclub'); ?></button></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="10"><?php echo esc_html__('Keine Ergebnisse', 'mvpclub'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div id="mvpclub-search-pagination"></div>
    </div>
    <?php
}
