<?php
// Rankmath Tests deaktivieren
add_filter('rank_math/researches/tests', function ($tests, $type) {
	unset($tests['titleHasNumber']);
    unset($tests['contentHasTOC']);
    unset($tests['titleHasPowerWords']);
    unset($tests['hasContentAI']);
	return $tests;
}, 10, 2 );

// Tabellenstil im Frontend und Editor anwenden
function mvpclub_custom_table_style() {
    $css = <<<CSS
        /* Grundstruktur der Tabelle */
        .wp-block-table {
            width: 100%;                          /* Volle Breite */
            border-collapse: collapse;           /* Keine doppelten Rahmen */
            overflow-x: auto;                    /* Seitliches Scrollen auf Mobilgeräten */
            -webkit-overflow-scrolling: touch;   /* Besseres Scrollverhalten auf iOS */
        }

        /* Tabellenzellen (Kopf + Inhalt) */
        .wp-block-table th,
        .wp-block-table td {
            border: 1px solid #f2f2f2;           /* Sehr dezente Rahmenfarbe */
        }

        /* Tabellenkopf */
        .wp-block-table thead th {
            border-bottom: 1px solid #dcdcdc !important; /* Dünne untere Linie */
            box-shadow: none !important;                 /* Entfernt mögliche Schattenlinie */
        }

        /* Hover-Effekt */
        .wp-block-table tbody tr:hover {
            background-color: #f2f2f2;           /* Grauer Hover statt Weiß */
            transition: background-color 0.2s ease;
        }
CSS;

    // Frontend-Stil einfügen
    wp_add_inline_style('wp-block-library', $css);

    // Editor-Stil einfügen
    add_action('admin_head', function () use ($css) {
        echo '<style>' . $css . '</style>';
    });
}
add_action('init', 'mvpclub_custom_table_style');
