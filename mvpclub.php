<?php
/*
Plugin Name: mvpclub
Description: Das mvpclub.de Plugin mit Shortcodes, Gutenberg-Blöcken und Admin-Menü.
Author: Raik Klein
Version: 3.1.1
*/

defined('ABSPATH') || exit;

// Globale Funktionen laden
require_once plugin_dir_path(__FILE__) . 'backend.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'fixes.php';
require_once plugin_dir_path(__FILE__) . 'blocks/scouting-posts.php';
require_once plugin_dir_path(__FILE__) . 'blocks/ads.php';
