<?php
if (!defined('ABSPATH')) exit;

function mvpclub_default_competitions() {
    return array(
        array('country' => 'DE', 'level' => '1', 'name' => 'Bundesliga'),
        array('country' => 'DE', 'level' => '2', 'name' => '2. Bundesliga'),
        array('country' => 'DE', 'level' => '3', 'name' => '3. Liga'),
        array('country' => 'DE', 'level' => '4', 'name' => 'Regionalliga'),
        array('country' => 'ES', 'level' => '1', 'name' => 'La Liga'),
        array('country' => 'ES', 'level' => '2', 'name' => 'La Liga 2'),
        array('country' => 'IT', 'level' => '1', 'name' => 'Serie A'),
        array('country' => 'IT', 'level' => '2', 'name' => 'Serie B'),
        array('country' => 'FR', 'level' => '1', 'name' => 'Ligue 1'),
        array('country' => 'FR', 'level' => '2', 'name' => 'Ligue 2'),
        array('country' => 'GB', 'level' => '1', 'name' => 'Premier League'),
        array('country' => 'GB', 'level' => '2', 'name' => 'EFL Championship'),
        array('country' => 'GB', 'level' => '3', 'name' => 'EFL League One'),
        array('country' => 'GB', 'level' => '4', 'name' => 'EFL League Two'),
        array('country' => 'NL', 'level' => '1', 'name' => 'Eredivisie'),
        array('country' => 'NL', 'level' => '2', 'name' => 'Eerste Divisie'),
        array('country' => 'PT', 'level' => '1', 'name' => 'Primeira Liga'),
        array('country' => 'PT', 'level' => '2', 'name' => 'Liga Portugal 2'),
        array('country' => 'BE', 'level' => '1', 'name' => 'Jupiler Pro League'),
        array('country' => 'BE', 'level' => '2', 'name' => 'Challenger Pro League'),
        array('country' => 'CH', 'level' => '1', 'name' => 'Super League'),
        array('country' => 'CH', 'level' => '2', 'name' => 'Challenge League'),
        array('country' => 'AT', 'level' => '1', 'name' => 'Bundesliga'),
        array('country' => 'AT', 'level' => '2', 'name' => '2. Liga'),
        array('country' => 'DK', 'level' => '1', 'name' => 'Superligaen'),
        array('country' => 'SE', 'level' => '1', 'name' => 'Allsvenskan'),
        array('country' => 'NO', 'level' => '1', 'name' => 'Eliteserien'),
        array('country' => 'FI', 'level' => '1', 'name' => 'Veikkausliiga'),
        array('country' => 'PL', 'level' => '1', 'name' => 'Ekstraklasa'),
        array('country' => 'CZ', 'level' => '1', 'name' => 'Fortuna Liga'),
        array('country' => 'HU', 'level' => '1', 'name' => 'Nemzeti Bajnokság I'),
        array('country' => 'RS', 'level' => '1', 'name' => 'SuperLiga'),
        array('country' => 'RO', 'level' => '1', 'name' => 'Liga 1'),
        array('country' => 'HR', 'level' => '1', 'name' => 'HNL'),
        array('country' => 'BR', 'level' => '1', 'name' => 'Série A'),
        array('country' => 'AR', 'level' => '1', 'name' => 'Liga Profesional'),
        array('country' => 'US', 'level' => '1', 'name' => 'Major League Soccer'),
        array('country' => 'MX', 'level' => '1', 'name' => 'Liga MX'),
        array('country' => 'JP', 'level' => '1', 'name' => 'J1 League'),
        array('country' => 'KR', 'level' => '1', 'name' => 'K League 1'),
        array('country' => 'SA', 'level' => '1', 'name' => 'Saudi Pro League'),
        array('country' => 'QA', 'level' => '1', 'name' => 'Qatar Stars League'),
        array('country' => 'AU', 'level' => '1', 'name' => 'A-League'),
    );
}

function mvpclub_get_competitions() {
    $comps = get_option('mvpclub_competitions');
    if (!is_array($comps) || empty($comps)) {
        $comps = mvpclub_default_competitions();
        update_option('mvpclub_competitions', $comps);
    }
    return $comps;
}

function mvpclub_save_competitions($comps) {
    update_option('mvpclub_competitions', array_values($comps));
}

function mvpclub_sort_competitions(&$comps) {
    usort($comps, function($a, $b){
        $cA = $a['country'] ?? '';
        $cB = $b['country'] ?? '';
        if ($cA === $cB) {
            $order = function($lvl){ return $lvl === 'Jugend' ? 99 : intval($lvl); };
            $lA = $order($a['level'] ?? '');
            $lB = $order($b['level'] ?? '');
            return $lA <=> $lB;
        }
        return strcasecmp($cA, $cB);
    });
    return $comps;
}

function mvpclub_get_country_map() {
    $path = plugin_dir_path(__FILE__) . 'assets/countries.json';
    $data = json_decode(file_get_contents($path), true);
    $map = array();
    if (is_array($data)) {
        foreach ($data as $c) {
            $map[$c['code']] = $c;
        }
    }
    return $map;
}

function mvpclub_competition_select($selected = '', $name = 'competition') {
    $comps = mvpclub_get_competitions();
    mvpclub_sort_competitions($comps);
    $countries = mvpclub_get_country_map();
    $html = '<select name="'.esc_attr($name).'">';
    $html .= '<option value="">-</option>';
    $found = false;
    foreach ($comps as $c) {
        $emoji = isset($countries[$c['country']]['emoji']) ? $countries[$c['country']]['emoji'] : '';
        $label = trim($emoji.' '.$c['name']);
        $sel = '';
        if ($label === $selected) { $sel = ' selected'; $found = true; }
        $html .= '<option value="'.esc_attr($label).'"'.$sel.'>'.$label.'</option>';
    }
    if (!$found && $selected !== '') {
        $html .= '<option value="'.esc_attr($selected).'" selected>'.esc_html($selected).'</option>';
    }
    $html .= '</select>';
    return $html;
}

function mvpclub_competition_labels() {
    $comps = mvpclub_get_competitions();
    mvpclub_sort_competitions($comps);
    $countries = mvpclub_get_country_map();
    $labels = array();
    foreach ($comps as $c) {
        $emoji = isset($countries[$c['country']]['emoji']) ? $countries[$c['country']]['emoji'] : '';
        $labels[] = trim($emoji.' '.$c['name']);
    }
    return $labels;
}

/**
 * Try to match a league name from the API to a competition label.
 * Returns the label with flag emoji if found, otherwise the input name.
 */
function mvpclub_match_competition_label($league_name) {
    $comps = mvpclub_get_competitions();
    $countries = mvpclub_get_country_map();
    foreach ($comps as $c) {
        if (strcasecmp($c['name'], $league_name) === 0) {
            $emoji = isset($countries[$c['country']]['emoji']) ? $countries[$c['country']]['emoji'] : '';
            return trim($emoji.' '.$c['name']);
        }
    }
    return $league_name;
}

add_action('admin_menu', function(){
    add_submenu_page('mvpclub-main', 'Wettbewerbe', 'Wettbewerbe', 'edit_posts', 'mvpclub-wettbewerbe', 'mvpclub_render_competitions_page');
});

function mvpclub_render_competitions_page() {
    if (!current_user_can('edit_posts')) return;
    $countries = mvpclub_get_country_map();
    $comps = mvpclub_get_competitions();

    if (isset($_POST['action']) && check_admin_referer('mvpclub_competitions_action','mvpclub_competitions_nonce')) {
        $action = sanitize_text_field($_POST['action']);
        if ($action === 'add') {
            $comps[] = array(
                'country' => sanitize_text_field($_POST['country']),
                'level'   => sanitize_text_field($_POST['level']),
                'name'    => sanitize_text_field($_POST['name'])
            );
        } elseif (($action === 'update' || $action === 'delete') && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            if (isset($comps[$id])) {
                if ($action === 'delete') {
                    unset($comps[$id]);
                } else {
                    $comps[$id]['country'] = sanitize_text_field($_POST['country']);
                    $comps[$id]['level']   = sanitize_text_field($_POST['level']);
                    $comps[$id]['name']    = sanitize_text_field($_POST['name']);
                }
            }
        }
        mvpclub_sort_competitions($comps);
        mvpclub_save_competitions($comps);
        $comps = mvpclub_get_competitions();
    }

    mvpclub_sort_competitions($comps);
    ?>
    <div class="wrap">
        <h1>Wettbewerbe</h1>
        <table class="widefat fixed">
            <thead>
                <tr><th>Nation</th><th>Level</th><th>Name</th><th>Aktion</th></tr>
            </thead>
            <tbody>
                <?php foreach ($comps as $id => $c): ?>
                <tr>
                    <td><?php echo esc_html($countries[$c['country']]['emoji'] ?? '').' '.esc_html($countries[$c['country']]['name'] ?? $c['country']); ?></td>
                    <td><?php echo esc_html($c['level']); ?></td>
                    <td><?php echo esc_html($c['name']); ?></td>
                    <td>
                        <form method="post" style="display:inline-block;margin-right:5px;">
                            <?php wp_nonce_field('mvpclub_competitions_action','mvpclub_competitions_nonce'); ?>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?php echo $id; ?>" />
                            <?php submit_button('Löschen', 'delete', 'submit', false, array('onclick' => "return confirm('Wirklich löschen?');")); ?>
                        </form>
                        <form method="post" style="display:inline-block;">
                            <?php wp_nonce_field('mvpclub_competitions_action','mvpclub_competitions_nonce'); ?>
                            <input type="hidden" name="action" value="update" />
                            <input type="hidden" name="id" value="<?php echo $id; ?>" />
                            <select name="country">
                                <?php foreach ($countries as $code => $ct): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $c['country']); ?>><?php echo esc_html($ct['emoji'].' '.$ct['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="level">
                                <?php foreach (array('1','2','3','4','Jugend') as $lvl): ?>
                                    <option value="<?php echo esc_attr($lvl); ?>" <?php selected($lvl, $c['level']); ?>><?php echo esc_html($lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="name" value="<?php echo esc_attr($c['name']); ?>" />
                            <?php submit_button('Speichern', 'primary', 'submit', false); ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <form method="post">
                        <?php wp_nonce_field('mvpclub_competitions_action','mvpclub_competitions_nonce'); ?>
                        <input type="hidden" name="action" value="add" />
                        <td>
                            <select name="country">
                                <?php foreach ($countries as $code => $ct): ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($ct['emoji'].' '.$ct['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="level">
                                <?php foreach (array('1','2','3','4','Jugend') as $lvl): ?>
                                    <option value="<?php echo esc_attr($lvl); ?>"><?php echo esc_html($lvl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="name" /></td>
                        <td><?php submit_button('Hinzufügen', 'primary', 'submit', false); ?></td>
                    </form>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
