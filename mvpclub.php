<?php
/*
Plugin Name: mvpclub
Description: Kompaktes Plugin mit Shortcodes, Gutenberg-Blöcken und Backend-Styling
Author: Raik Klein
Version: 3.0
*/

defined('ABSPATH') || exit;

// Globale Funktionen laden
require_once plugin_dir_path(__FILE__) . 'backend.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'fixes.php';
require_once plugin_dir_path(__FILE__) . 'blocks/scouting-posts.php';
require_once plugin_dir_path(__FILE__) . 'blocks/ads.php';

// Frontend Styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'mvpclub-scouting-style',
        plugins_url('scouting-style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'scouting-style.css')
    );
});
