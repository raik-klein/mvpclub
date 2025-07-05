<?php
if (!defined('ABSPATH')) exit;

function mvpclub_get_api_football_key() {
    return get_option('mvpclub_api_football_key', '');
}

function mvpclub_api_football_request($endpoint, $params = array()) {
    $key = mvpclub_get_api_football_key();
    if (empty($key)) {
        return new WP_Error('missing_key', 'API key not set');
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
        return new WP_Error('bad_response', 'API request failed', $response);
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
        return new WP_Error('not_found', 'Player not found');
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

function mvpclub_api_football_search_players($query, $season = null, $league_id = null) {
    if (!$season) {
        $season = date('Y');
    }
    $params = array(
        'search' => $query,
        'season' => $season,
    );
    if ($league_id) {
        $params['league'] = $league_id;
    }
    $data = mvpclub_api_football_request('players', $params);

    if (is_wp_error($data)) {
        return $data;
    }

    return isset($data['response']) ? $data['response'] : array();
}

function mvpclub_import_player_post($player_id, $season = null) {
    $profiles = mvpclub_api_football_get_player_profiles($player_id);
    if (is_wp_error($profiles)) {
        return $profiles;
    }

    if (empty($profiles[0]['player'])) {
        return new WP_Error('not_found', 'Player not found');
    }

    $player = $profiles[0]['player'];
    $stats  = array();

    $full_name = trim(($player['firstname'] ?? '') . ' ' . ($player['lastname'] ?? ''));
    if ($full_name === '') {
        $full_name = isset($player['name']) ? $player['name'] : '';
    }
    $post_id = wp_insert_post(array(
        'post_type'   => 'mvpclub-spieler',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field($full_name),
    ));
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $birthdate = '';
    if (!empty($player['birth']['date'])) {
        $d = DateTime::createFromFormat('Y-m-d', $player['birth']['date']);
        if ($d) {
            $birthdate = $d->format('d.m.Y');
        }
    }
    if ($birthdate) {
        update_post_meta($post_id, 'birthdate', $birthdate);
    }

    if (!empty($player['birth']['country']) || !empty($player['birth']['place'])) {
        $place = trim($player['birth']['country'] . ' ' . $player['birth']['place']);
        update_post_meta($post_id, 'birthplace', $place);
    }

    if (!empty($player['nationality'])) {
        $nat_label = mvpclub_get_nationality_label($player['nationality']);
        if ($nat_label) {
            update_post_meta($post_id, 'nationality', $nat_label);
        } else {
            update_post_meta($post_id, 'nationality', $player['nationality']);
        }
    }

    if (!empty($player['height'])) {
        if (preg_match('/(\d+)/', $player['height'], $m)) {
            update_post_meta($post_id, 'height', intval($m[1]));
        }
    }

    $position_value = !empty($stats['games']['position']) ? $stats['games']['position'] : (isset($player['position']) ? $player['position'] : '');
    if ($position_value) {
        $map = array(
            'Goalkeeper' => 'Tor',
            'Defender'   => 'Abwehr',
            'Midfielder' => 'Mittelfeld',
            'Attacker'   => 'Sturm',
        );
        $position = isset($map[$position_value]) ? $map[$position_value] : $position_value;
        update_post_meta($post_id, 'position', $position);
    }

    if (!empty($stats['team']['name'])) {
        update_post_meta($post_id, 'club', $stats['team']['name']);
    }

    if (!empty($player['photo'])) {
        update_post_meta($post_id, 'image_external', esc_url_raw($player['photo']));
    }

    return $post_id;
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

function mvpclub_api_football_find_league_id($label) {
    $name = trim(preg_replace('/^[^a-zA-Z0-9]+/u', '', $label));
    if ($name === '') return '';
    $data = mvpclub_api_football_request('leagues', array('search' => $name));
    if (is_wp_error($data)) {
        return $data;
    }
    if (empty($data['response'][0]['league']['id'])) {
        return '';
    }
    return $data['response'][0]['league']['id'];
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
        'API-FOOTBALL',
        'API-FOOTBALL',
        'manage_options',
        'mvpclub-api-football',
        'mvpclub_render_api_football_settings_page'
    );
});

function mvpclub_render_api_football_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['mvpclub_api_key']) && check_admin_referer('mvpclub_api_football_save', 'mvpclub_api_football_nonce')) {
        update_option('mvpclub_api_football_key', sanitize_text_field($_POST['mvpclub_api_key']));
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }

    $import_result = null;
    if (isset($_GET['add_player']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'mvpclub_add_player')) {
        $pid = intval($_GET['add_player']);
        $import_result = mvpclub_import_player_post($pid);
        if (is_wp_error($import_result)) {
            echo '<div class="error"><p>' . esc_html($import_result->get_error_message()) . '</p></div>';
        } else {
            $edit_link = admin_url('post.php?post=' . $import_result . '&action=edit');
            echo '<div class="updated"><p>Spieler importiert. <a href="' . esc_url($edit_link) . '">Profil bearbeiten</a></p></div>';
        }
    }

    $player_search = isset($_GET['player_search']) ? sanitize_text_field($_GET['player_search']) : '';
    $search_league = isset($_GET['search_league']) ? wp_unslash($_GET['search_league']) : '';
    $results = array();
    if ($player_search !== '') {
        $league_id = '';
        if ($search_league !== '') {
            $league_id = mvpclub_api_football_find_league_id($search_league);
            if (is_wp_error($league_id)) {
                echo '<div class="error"><p>' . esc_html($league_id->get_error_message()) . '</p></div>';
                $league_id = '';
            }
        }
        $result = mvpclub_api_football_search_players($player_search, null, $league_id);
        if (is_wp_error($result)) {
            echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            $results = $result;
        }
    }

    $key = get_option('mvpclub_api_football_key', '');
    ?>
    <div class="wrap">
        <h1>API-FOOTBALL</h1>
        <form method="post" action="">
            <?php wp_nonce_field('mvpclub_api_football_save','mvpclub_api_football_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mvpclub_api_key">API Key</label></th>
                    <td><input name="mvpclub_api_key" type="text" id="mvpclub_api_key" value="<?php echo esc_attr($key); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Speichern'); ?>
        </form>

        <h2>Spieler-ID suchen</h2>
        <form method="get">
            <input type="hidden" name="page" value="mvpclub-api-football" />
            <input name="player_search" type="text" value="<?php echo esc_attr($player_search); ?>" class="regular-text" />
            <?php echo mvpclub_competition_select($search_league, 'search_league'); ?>
            <?php submit_button('Suchen', 'secondary', 'submit', false); ?>
        </form>

        <?php if (!empty($results)) : ?>
            <h2>Ergebnisse</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Team</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row) :
                        $p = $row['player'];
                        $team = isset($row['statistics'][0]['team']['name']) ? $row['statistics'][0]['team']['name'] : '';
                        $link = wp_nonce_url(admin_url('admin.php?page=mvpclub-api-football&add_player=' . $p['id'] . '&player_search=' . urlencode($player_search) . '&search_league=' . urlencode($search_league)), 'mvpclub_add_player');
                    ?>
                        <tr>
                            <td><?php echo esc_html(trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? $p['name']))); ?></td>
                            <td><?php echo esc_html($team); ?></td>
                            <td><a href="<?php echo esc_url($link); ?>" class="button">Spieler hinzuf&uuml;gen</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
