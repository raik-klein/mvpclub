<?php
// Registriert den Gutenberg-Block und das zugehörige Editor-Skript
add_action('init', function () {
    wp_register_script(
        'mvpclub-scouting-posts-editor-script',
        plugins_url('scouting-posts.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'scouting-posts.js')
    );

    register_block_type('mvpclub/scouting-posts', array(
        'editor_script'   => 'mvpclub-scouting-posts-editor-script', // Editor-JS
        'render_callback' => 'mvpclub_render_scouting_posts',        // PHP-Renderfunktion für Front- und Backend
        'attributes'      => array(
            'preview' => array(
                'type'    => 'boolean',
                'default' => true,
            ),
        ),
    ));
});

// Fügt eine eigene Block-Kategorie "mvpclub" hinzu
add_filter('block_categories_all', function ($categories) {
    return array_merge(
        $categories,
        array(
            array(
                'slug'  => 'mvpclub',
                'title' => __('mvpclub', 'mvpclub'),
            ),
        )
    );
}, 10, 2);

/**
 * Renderfunktion für den Scouting-Posts-Block.
 * Gibt die 5 neuesten Beiträge der Kategorie "scouting" als Liste mit Flagge, Titel, Bewertung und Fortschrittsbalken aus.
 */
function mvpclub_render_scouting_posts($attributes = [], $content = '') {
    ob_start();

    // Query-Argumente: 5 neueste Beiträge aus Kategorie "scouting"
    $query_args = array(
        'category_name'  => 'scouting',
        'posts_per_page' => 5,
        'post_status'    => 'publish'
    );

    $query = new WP_Query($query_args);

    if ($query->have_posts()) {
        echo '<ul style="list-style: none; padding: 0;">';
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Bewertung aus dem Beitrag extrahieren
            $rating = mvpclub_extract_rating_from_post($post_id);

            // Nationalitäts-Flagge aus Tags extrahieren
            $flag = mvpclub_extract_nationality_from_tags($post_id);

            // Fortschrittsbalken-Breite (max. 100%)
            $progress_width = is_numeric($rating) ? ($rating * 20) : 0;

            // Listeneintrag: Flagge, Titel (verlinkt), Bewertung
            echo '<li style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0;">';
            echo '<span style="margin-right: 8px;">' . esc_html($flag) . '</span>';
            echo '<a href="' . esc_url(get_permalink()) . '" style="text-align: left; flex-grow: 1; text-decoration: none; color: inherit;">' . esc_html(get_the_title()) . '</a>';
            echo '<span style="text-align: right; font-weight: bold; white-space: nowrap;">' . esc_html($rating) . '</span>';
            echo '</li>';

            // Fortschrittsbalken unter jedem Eintrag
            echo '<div style="background: #F2F2F2; width: 100%; height: 10px; margin-top: 5px;">
                    <div style="background: #00E65A; width: ' . esc_attr($progress_width) . '%; height: 100%;"></div>
                  </div>';
        }
        echo '</ul>';
    } else {
        // Keine Beiträge gefunden
        echo '<p>Keine Beiträge gefunden.</p>';
    }

    wp_reset_postdata();

    return ob_get_clean();
}

/**
 * Extrahiert die Bewertung (erste gefundene Zahl nach "Bewertung") aus dem Beitragstext.
 * Gibt die Bewertung als Zahl (z.B. 4.5) oder "KA" (keine Angabe) zurück.
 */
function mvpclub_extract_rating_from_post($post_id) {
    $content = get_post_field('post_content', $post_id);

    // Versuche zuerst, den Spieler aus dem Scoutingbericht-Block zu ermitteln
    if (preg_match('/<!--\s*wp:mvpclub\/player-info\s+({.*?})\s*(?:\/)?-->/', $content, $m)) {
        $attrs = json_decode($m[1], true);
        if (isset($attrs['playerId'])) {
            $rating = get_post_meta(intval($attrs['playerId']), 'rating', true);
            if ($rating !== '' && is_numeric($rating)) {
                return number_format((float)$rating, 1, '.', '');
            }
        }
    }

    // Fallback: alte Methode
    preg_match('/Bewertung.*?(\d+(?:\.\d+)?)/', $content, $matches);
    return !empty($matches[1]) ? number_format(floatval($matches[1]), 1, '.', '') : 'KA';
}

/**
 * Gibt die Flagge zum zweistelligen ISO-Ländercode (z.B. "DE", "FR") anhand der Tag-Slug (Titelform) zurück.
 * Gibt eine Weltkugel zurück, wenn kein passender Tag gefunden wird.
 */
function mvpclub_extract_nationality_from_tags($post_id) {
    // Hole alle Tags als Objekte (damit wir auf ->slug zugreifen können)
    $tags = wp_get_post_tags($post_id);

    foreach ($tags as $tag) {
        // Die Titelform (slug) als ISO-Code verwenden
        if (preg_match('/^[a-z]{2}$/i', $tag->slug)) {
            $iso = strtoupper($tag->slug);
            // Flaggen-Emoji aus ISO-Code generieren
            $flag = '';
            foreach (str_split($iso) as $char) {
                $flag .= mb_convert_encoding('&#' . (127397 + ord($char)) . ';', 'UTF-8', 'HTML-ENTITIES');
            }
            return $flag;
        }
    }
    // Fallback: Weltkugel
    return '🌍';
}
