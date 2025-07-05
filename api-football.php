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

function mvpclub_api_football_search_players($query, $season = null) {
    if (!$season) {
        $season = date('Y');
    }
    $data = mvpclub_api_football_request('players', array(
        'search' => $query,
        'season' => $season,
    ));

    if (is_wp_error($data)) {
        return $data;
    }

    return isset($data['response']) ? $data['response'] : array();
}

function mvpclub_import_player_post($player_id, $season = null) {
    $result = mvpclub_api_football_get_player($player_id, $season);
    if (is_wp_error($result)) {
        return $result;
    }

    $player = $result['player'];
    $stats  = isset($result['statistics'][0]) ? $result['statistics'][0] : array();

    $post_id = wp_insert_post(array(
        'post_type'   => 'mvpclub-spieler',
        'post_status' => 'publish',
        'post_title'  => sanitize_text_field($player['name']),
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
        update_post_meta($post_id, 'nationality', $player['nationality']);
    }

    if (!empty($player['height'])) {
        if (preg_match('/(\d+)/', $player['height'], $m)) {
            update_post_meta($post_id, 'height', intval($m[1]));
        }
    }

    if (!empty($stats['games']['position'])) {
        $map = array(
            'Goalkeeper' => 'Tor',
            'Defender'   => 'Abwehr',
            'Midfielder' => 'Mittelfeld',
            'Attacker'   => 'Sturm',
        );
        $position = isset($map[$stats['games']['position']]) ? $map[$stats['games']['position']] : $stats['games']['position'];
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

    $player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : '';
    $results = array();
    if ($player_id !== '') {
        $result = mvpclub_api_football_get_player_profiles($player_id);
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
            <input name="player_id" type="text" value="<?php echo esc_attr($player_id); ?>" class="regular-text" />
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
                        $link = wp_nonce_url(admin_url('admin.php?page=mvpclub-api-football&add_player=' . $p['id'] . '&player_id=' . urlencode($player_id)), 'mvpclub_add_player');
                    ?>
                        <tr>
                            <td><?php echo esc_html($p['name']); ?></td>
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
