<?php
if (!defined('ABSPATH')) exit;

add_shortcode('alter', function ($atts, $content = null) {
    $atts = shortcode_atts([
        'datum' => '',
        'd' => ''
    ], $atts);

    $input = !empty($atts['datum']) ? $atts['datum'] : $atts['d'];

    if (empty($input)) return '';

    $geburt = DateTime::createFromFormat('d.m.Y', $input);
    if (!$geburt) return '';

    $heute = new DateTime();
    return esc_html($heute->diff($geburt)->y . ' Jahre');
});

add_shortcode('lesedauer', function () {
    global $post;
    if (!$post || empty($post->post_content)) return '';
    $text = strip_tags($post->post_content);
    $minuten = ceil(str_word_count($text) / 200);
    return esc_html($minuten . ' Minute' . ($minuten > 1 ? 'n' : '') . ' Lesedauer');
});

add_shortcode('ad', function() {
    ob_start();
    ?>
    <div class="mvpclub-ad">
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3126572075544456"
            crossorigin="anonymous"></script>
        <ins class="adsbygoogle"
            style="display:block"
            data-ad-client="ca-pub-3126572075544456"
            data-ad-slot="8708811170"
            data-ad-format="auto"
            data-full-width-responsive="true"></ins>
        <script>
            (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('bewertung', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $rating = null;
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if ($post_id) {
        $rating = get_post_meta($post_id, 'rating', true);
    }
    return $rating !== '' ? esc_html($rating) : '';
});

