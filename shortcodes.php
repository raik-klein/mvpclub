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

add_shortcode('ad', function($atts = array()) {
    $atts = shortcode_atts(array(
        'client' => 'ca-pub-3126572075544456',
        'slot'   => '8708811170',
    ), $atts, 'ad');
    ob_start();
    ?>
    <div class="mvpclub-ad">
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo esc_attr($atts['client']); ?>"
            crossorigin="anonymous"></script>
        <ins class="adsbygoogle"
            style="display:block"
            data-ad-client="<?php echo esc_attr($atts['client']); ?>"
            data-ad-slot="<?php echo esc_attr($atts['slot']); ?>"
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
    if ($rating !== '') {
        $rating = number_format((float)$rating, 1);
        return esc_html($rating);
    }
    return '';
});

add_shortcode('statistik', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if (!$post_id) return '';
    $json = get_post_meta($post_id, 'performance_data', true);
    return mvpclub_generate_statistik_table($json);
});

add_shortcode('spielstil', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if (!$post_id) return '';
    $val = get_post_meta($post_id, 'spielstil', true);
    return $val !== '' ? esc_html($val) : '';
});

add_shortcode('rolle', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if (!$post_id) return '';
    $val = get_post_meta($post_id, 'rolle', true);
    return $val !== '' ? esc_html($val) : '';
});

add_shortcode('staerken', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if (!$post_id) return '';
    $json = get_post_meta($post_id, 'strengths', true);
    $items = json_decode($json, true);
    if (!is_array($items) || empty($items)) return '';
    $out = '<ul class="procon">';
    foreach ($items as $it) {
        $out .= '<li>' . esc_html($it) . '</li>';
    }
    $out .= '</ul>';
    return $out;
});

add_shortcode('schwaechen', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if (!$post_id) return '';
    $json = get_post_meta($post_id, 'weaknesses', true);
    $items = json_decode($json, true);
    if (!is_array($items) || empty($items)) return '';
    $out = '<ul class="procon">';
    foreach ($items as $it) {
        $out .= '<li>' . esc_html($it) . '</li>';
    }
    $out .= '</ul>';
    return $out;
});

add_shortcode('radar', function($atts = []) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post_id = $atts['id'] ? intval($atts['id']) : get_the_ID();
    if (!$post_id) return '';
    $json = get_post_meta($post_id, 'radar_chart', true);
    $chart = json_decode($json, true);
    if (empty($chart['labels']) || empty($chart['values'])) return '';
    $chart_id = 'radar-chart-' . $post_id . '-' . wp_rand(1, 9999);
    $chart_src = plugins_url('assets/chart.js', __FILE__);
    ob_start();
    ?>
    <canvas id="<?php echo esc_attr($chart_id); ?>" width="300" height="300"></canvas>
    <script>(function(){function r(){var c=document.getElementById("<?php echo esc_js($chart_id); ?>");if(!c||typeof Chart==="undefined")return;new Chart(c,{type:"radar",data:{labels:<?php echo wp_json_encode($chart['labels']); ?>,datasets:[{label:"<?php echo esc_js(get_the_title($post_id)); ?>",data:<?php echo wp_json_encode($chart['values']); ?>,backgroundColor:"rgba(54,162,235,0.2)",borderColor:"rgba(54,162,235,1)"}]},options:{scales:{r:{min:0,max:100,beginAtZero:true}}});}if(typeof Chart==="undefined"){var s=document.createElement("script");s.src="<?php echo esc_url($chart_src); ?>";s.onload=r;document.body.appendChild(s);}else{r();}})();</script>
    <?php
    return ob_get_clean();
});

