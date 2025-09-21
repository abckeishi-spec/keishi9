<?php
/**
 * Prefecture Fix Functions
 * 
 * This file contains functions to fix prefecture taxonomy issues
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize all 47 prefectures if they don't exist
 */
function gi_init_all_prefectures() {
    $prefectures = array(
        // 北海道・東北
        array('name' => '北海道', 'slug' => 'hokkaido'),
        array('name' => '青森県', 'slug' => 'aomori'),
        array('name' => '岩手県', 'slug' => 'iwate'),
        array('name' => '宮城県', 'slug' => 'miyagi'),
        array('name' => '秋田県', 'slug' => 'akita'),
        array('name' => '山形県', 'slug' => 'yamagata'),
        array('name' => '福島県', 'slug' => 'fukushima'),
        // 関東
        array('name' => '茨城県', 'slug' => 'ibaraki'),
        array('name' => '栃木県', 'slug' => 'tochigi'),
        array('name' => '群馬県', 'slug' => 'gunma'),
        array('name' => '埼玉県', 'slug' => 'saitama'),
        array('name' => '千葉県', 'slug' => 'chiba'),
        array('name' => '東京都', 'slug' => 'tokyo'),
        array('name' => '神奈川県', 'slug' => 'kanagawa'),
        // 中部
        array('name' => '新潟県', 'slug' => 'niigata'),
        array('name' => '富山県', 'slug' => 'toyama'),
        array('name' => '石川県', 'slug' => 'ishikawa'),
        array('name' => '福井県', 'slug' => 'fukui'),
        array('name' => '山梨県', 'slug' => 'yamanashi'),
        array('name' => '長野県', 'slug' => 'nagano'),
        array('name' => '岐阜県', 'slug' => 'gifu'),
        array('name' => '静岡県', 'slug' => 'shizuoka'),
        array('name' => '愛知県', 'slug' => 'aichi'),
        // 近畿
        array('name' => '三重県', 'slug' => 'mie'),
        array('name' => '滋賀県', 'slug' => 'shiga'),
        array('name' => '京都府', 'slug' => 'kyoto'),
        array('name' => '大阪府', 'slug' => 'osaka'),
        array('name' => '兵庫県', 'slug' => 'hyogo'),
        array('name' => '奈良県', 'slug' => 'nara'),
        array('name' => '和歌山県', 'slug' => 'wakayama'),
        // 中国
        array('name' => '鳥取県', 'slug' => 'tottori'),
        array('name' => '島根県', 'slug' => 'shimane'),
        array('name' => '岡山県', 'slug' => 'okayama'),
        array('name' => '広島県', 'slug' => 'hiroshima'),
        array('name' => '山口県', 'slug' => 'yamaguchi'),
        // 四国
        array('name' => '徳島県', 'slug' => 'tokushima'),
        array('name' => '香川県', 'slug' => 'kagawa'),
        array('name' => '愛媛県', 'slug' => 'ehime'),
        array('name' => '高知県', 'slug' => 'kochi'),
        // 九州・沖縄
        array('name' => '福岡県', 'slug' => 'fukuoka'),
        array('name' => '佐賀県', 'slug' => 'saga'),
        array('name' => '長崎県', 'slug' => 'nagasaki'),
        array('name' => '熊本県', 'slug' => 'kumamoto'),
        array('name' => '大分県', 'slug' => 'oita'),
        array('name' => '宮崎県', 'slug' => 'miyazaki'),
        array('name' => '鹿児島県', 'slug' => 'kagoshima'),
        array('name' => '沖縄県', 'slug' => 'okinawa')
    );
    
    foreach ($prefectures as $prefecture) {
        if (!term_exists($prefecture['slug'], 'grant_prefecture')) {
            wp_insert_term(
                $prefecture['name'],
                'grant_prefecture',
                array('slug' => $prefecture['slug'])
            );
        }
    }
}

/**
 * Get accurate count of grants for a prefecture
 */
function gi_get_prefecture_grant_count($prefecture_slug) {
    $args = array(
        'post_type' => 'grant',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => array(
            array(
                'taxonomy' => 'grant_prefecture',
                'field' => 'slug',
                'terms' => $prefecture_slug,
            ),
        ),
    );
    
    $query = new WP_Query($args);
    return $query->found_posts;
}

/**
 * Get all prefectures with accurate counts
 */
function gi_get_prefectures_with_counts() {
    $all_prefectures = gi_get_all_prefectures();
    
    foreach ($all_prefectures as &$prefecture) {
        $term = get_term_by('slug', $prefecture['slug'], 'grant_prefecture');
        
        if ($term && !is_wp_error($term)) {
            // Get accurate count using WP_Query
            $prefecture['count'] = gi_get_prefecture_grant_count($prefecture['slug']);
            $prefecture['term_id'] = $term->term_id;
        } else {
            $prefecture['count'] = 0;
            $prefecture['term_id'] = null;
        }
    }
    
    return $all_prefectures;
}

/**
 * Fix prefecture URL generation with proper filter parameter
 */
function gi_get_prefecture_filter_url($prefecture_slug) {
    $archive_url = get_post_type_archive_link('grant');
    
    if (!$archive_url) {
        $archive_url = home_url('/grants/');
    }
    
    // Ensure we use the correct parameter name that the archive page expects
    return add_query_arg(array(
        'prefecture' => $prefecture_slug,  // Using 'prefecture' as expected by archive-grant.php
        'filter_applied' => '1'
    ), $archive_url);
}

/**
 * Initialize prefectures on admin_init
 */
add_action('admin_init', 'gi_maybe_init_prefectures');
function gi_maybe_init_prefectures() {
    // Check if we need to initialize prefectures
    $initialized = get_option('gi_prefectures_initialized', false);
    
    if (!$initialized) {
        gi_init_all_prefectures();
        update_option('gi_prefectures_initialized', true);
    }
}

/**
 * Add sample prefecture data to grants (for testing)
 */
function gi_assign_sample_prefectures() {
    $grants = get_posts(array(
        'post_type' => 'grant',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    $prefecture_slugs = array('tokyo', 'osaka', 'kanagawa', 'aichi', 'fukuoka', 'hokkaido', 'kyoto', 'hyogo', 'saitama', 'chiba');
    
    foreach ($grants as $index => $grant) {
        $existing_prefs = wp_get_object_terms($grant->ID, 'grant_prefecture', array('fields' => 'ids'));
        
        if (empty($existing_prefs)) {
            // Assign 1-3 random prefectures to each grant
            $num_prefs = rand(1, 3);
            $selected_prefs = array_rand(array_flip($prefecture_slugs), $num_prefs);
            
            if (!is_array($selected_prefs)) {
                $selected_prefs = array($selected_prefs);
            }
            
            wp_set_object_terms($grant->ID, $selected_prefs, 'grant_prefecture');
        }
    }
}

// Make sure the function is available globally
if (!function_exists('gi_get_all_prefectures')) {
    function gi_get_all_prefectures() {
        return array(
            // 北海道・東北
            array('name' => '北海道', 'slug' => 'hokkaido', 'region' => 'hokkaido'),
            array('name' => '青森県', 'slug' => 'aomori', 'region' => 'tohoku'),
            array('name' => '岩手県', 'slug' => 'iwate', 'region' => 'tohoku'),
            array('name' => '宮城県', 'slug' => 'miyagi', 'region' => 'tohoku'),
            array('name' => '秋田県', 'slug' => 'akita', 'region' => 'tohoku'),
            array('name' => '山形県', 'slug' => 'yamagata', 'region' => 'tohoku'),
            array('name' => '福島県', 'slug' => 'fukushima', 'region' => 'tohoku'),
            // 関東
            array('name' => '茨城県', 'slug' => 'ibaraki', 'region' => 'kanto'),
            array('name' => '栃木県', 'slug' => 'tochigi', 'region' => 'kanto'),
            array('name' => '群馬県', 'slug' => 'gunma', 'region' => 'kanto'),
            array('name' => '埼玉県', 'slug' => 'saitama', 'region' => 'kanto'),
            array('name' => '千葉県', 'slug' => 'chiba', 'region' => 'kanto'),
            array('name' => '東京都', 'slug' => 'tokyo', 'region' => 'kanto'),
            array('name' => '神奈川県', 'slug' => 'kanagawa', 'region' => 'kanto'),
            // 中部
            array('name' => '新潟県', 'slug' => 'niigata', 'region' => 'chubu'),
            array('name' => '富山県', 'slug' => 'toyama', 'region' => 'chubu'),
            array('name' => '石川県', 'slug' => 'ishikawa', 'region' => 'chubu'),
            array('name' => '福井県', 'slug' => 'fukui', 'region' => 'chubu'),
            array('name' => '山梨県', 'slug' => 'yamanashi', 'region' => 'chubu'),
            array('name' => '長野県', 'slug' => 'nagano', 'region' => 'chubu'),
            array('name' => '岐阜県', 'slug' => 'gifu', 'region' => 'chubu'),
            array('name' => '静岡県', 'slug' => 'shizuoka', 'region' => 'chubu'),
            array('name' => '愛知県', 'slug' => 'aichi', 'region' => 'chubu'),
            // 近畿
            array('name' => '三重県', 'slug' => 'mie', 'region' => 'kinki'),
            array('name' => '滋賀県', 'slug' => 'shiga', 'region' => 'kinki'),
            array('name' => '京都府', 'slug' => 'kyoto', 'region' => 'kinki'),
            array('name' => '大阪府', 'slug' => 'osaka', 'region' => 'kinki'),
            array('name' => '兵庫県', 'slug' => 'hyogo', 'region' => 'kinki'),
            array('name' => '奈良県', 'slug' => 'nara', 'region' => 'kinki'),
            array('name' => '和歌山県', 'slug' => 'wakayama', 'region' => 'kinki'),
            // 中国
            array('name' => '鳥取県', 'slug' => 'tottori', 'region' => 'chugoku'),
            array('name' => '島根県', 'slug' => 'shimane', 'region' => 'chugoku'),
            array('name' => '岡山県', 'slug' => 'okayama', 'region' => 'chugoku'),
            array('name' => '広島県', 'slug' => 'hiroshima', 'region' => 'chugoku'),
            array('name' => '山口県', 'slug' => 'yamaguchi', 'region' => 'chugoku'),
            // 四国
            array('name' => '徳島県', 'slug' => 'tokushima', 'region' => 'shikoku'),
            array('name' => '香川県', 'slug' => 'kagawa', 'region' => 'shikoku'),
            array('name' => '愛媛県', 'slug' => 'ehime', 'region' => 'shikoku'),
            array('name' => '高知県', 'slug' => 'kochi', 'region' => 'shikoku'),
            // 九州・沖縄
            array('name' => '福岡県', 'slug' => 'fukuoka', 'region' => 'kyushu'),
            array('name' => '佐賀県', 'slug' => 'saga', 'region' => 'kyushu'),
            array('name' => '長崎県', 'slug' => 'nagasaki', 'region' => 'kyushu'),
            array('name' => '熊本県', 'slug' => 'kumamoto', 'region' => 'kyushu'),
            array('name' => '大分県', 'slug' => 'oita', 'region' => 'kyushu'),
            array('name' => '宮崎県', 'slug' => 'miyazaki', 'region' => 'kyushu'),
            array('name' => '鹿児島県', 'slug' => 'kagoshima', 'region' => 'kyushu'),
            array('name' => '沖縄県', 'slug' => 'okinawa', 'region' => 'kyushu')
        );
    }
}