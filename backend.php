<?php
if (!defined('ABSPATH')) exit;

// Admin-Menü registrieren
add_action('admin_menu', function () {
    add_menu_page(
        'MVP Zentrale',
        'MVP',
        'edit_posts',
        'mvpclub-main',
        'mvpclub_redirect_to_players',
        'dashicons-star-filled',
        3
    );

    // Kombinierter Menüpunkt für Blöcke und Shortcodes
    add_submenu_page(
        'mvpclub-main',
        'Elemente',
        'Elemente',
        'edit_posts',
        'mvpclub-elements',
        'mvpclub_render_elements_page'
    );
});

// Seiteninhalte
// Redirects the top-level "MVP" menu to the player database
function mvpclub_redirect_to_players() {
    wp_safe_redirect(admin_url('edit.php?post_type=mvpclub-spieler'));
    exit;
}

function mvpclub_render_block_page() {
    echo '<div class="wrap"><h1>Scouting-Block</h1><p>Der Gutenberg-Block <code>Scouting Posts</code> ist aktiv.</p></div>';
}

function mvpclub_render_shortcodes_page() {
    echo '<div class="wrap">
        <h1>Shortcodes</h1>
        <p>Folgende Shortcodes stehen zur Verfügung:</p>
        <table class="widefat fixed striped" style="width:100%; table-layout:auto;">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Beschreibung</th>
                    <th>Beispiel</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[lesedauer]</code></td>
                    <td>Zeigt die geschätzte Lesedauer des Beitrags an.</td>
                    <td><code>5 Minuten</code></td>
                </tr>
                <tr>
                    <td><code>[alter datum="01.01.2000"]</code></td>
                    <td>Berechnet das aktuelle Alter basierend auf dem angegebenen Datum.</td>
                    <td><code>24 Jahre</code></td>
                </tr>
                <tr>
                    <td><code>[ad]</code></td>
                    <td>Zeigt eine Google AdSense-Anzeige an.</td>
                    <td><code>Anzeige wird hier angezeigt.</code></td>
                </tr>
            </tbody>
        </table>
    </div>';
}

// Neue kombinierte Seite für Blöcke und Shortcodes
function mvpclub_render_elements_page() {
    mvpclub_render_block_page();
    mvpclub_render_shortcodes_page();
}

/**
 * Fügt im Admin-Menü unter „mvpclub“ einen Unterpunkt „Werbung“ hinzu.
 */
add_action('admin_menu', function() {
    // „mvpclub-main“ müsste der Slug deines Haupt-Menüs sein
    add_submenu_page(
        'mvpclub-main',
        'Werbung-Einstellungen',
        'Werbung',
        'edit_posts', // <-- Hier geändert
        'mvpclub-ads-settings',
        'mvpclub_render_ads_settings_page'
    );
});

/**
 * Callback: Rendert die Settings-Seite für den Ads-Block.
 */
function mvpclub_render_ads_settings_page() {
    // Sicherheitstoken
    if(!current_user_can('manage_options')) {
        return;
    }
    // Speichern, wenn Formular abgeschickt
    if (isset($_POST['mvpclub_ads_client']) && check_admin_referer('mvpclub_ads_save', 'mvpclub_ads_nonce')) {
        update_option('mvpclub_ads_client', sanitize_text_field($_POST['mvpclub_ads_client']));
        update_option('mvpclub_ads_slot',   sanitize_text_field($_POST['mvpclub_ads_slot']));
        echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
    }

    // Aktuelle Werte laden
    $client = get_option('mvpclub_ads_client', 'ca-pub-3126572075544456');
    $slot   = get_option('mvpclub_ads_slot', '8708811170');

    ?>
    <div class="wrap">
      <h1>Werbung-Block konfigurieren</h1>
      <form method="post" action="">
        <?php wp_nonce_field('mvpclub_ads_save','mvpclub_ads_nonce'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="mvpclub_ads_client">AdSense Client-ID</label></th>
            <td><input name="mvpclub_ads_client" type="text" id="mvpclub_ads_client" value="<?php echo esc_attr($client); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="mvpclub_ads_slot">AdSense Slot-ID</label></th>
            <td><input name="mvpclub_ads_slot" type="text" id="mvpclub_ads_slot" value="<?php echo esc_attr($slot); ?>" class="regular-text" /></td>
          </tr>
        </table>
        <?php submit_button('Speichern'); ?>
      </form>
    </div>
    <?php
}

/**
 * Passt das Shortcode-Rendering an, um Client- und Slot-ID zu nutzen.
 */
add_filter('shortcode_atts_ad', function($out) {
    $out['client'] = get_option('mvpclub_ads_client', 'ca-pub-3126572075544456');
    $out['slot']   = get_option('mvpclub_ads_slot',   '8708811170');
    return $out;
}, 10, 1);
