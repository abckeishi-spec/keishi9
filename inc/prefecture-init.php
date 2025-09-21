<?php
/**
 * Prefecture Initialization Script
 * 
 * This script initializes prefecture terms with sample data for testing
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize prefectures with sample grant assignments
 */
function gi_init_prefecture_sample_data() {
    // First, ensure all prefecture terms exist
    $prefectures = array(
        // Major cities with more grants
        array('name' => '東京都', 'slug' => 'tokyo', 'weight' => 5),
        array('name' => '大阪府', 'slug' => 'osaka', 'weight' => 4),
        array('name' => '神奈川県', 'slug' => 'kanagawa', 'weight' => 3),
        array('name' => '愛知県', 'slug' => 'aichi', 'weight' => 3),
        array('name' => '福岡県', 'slug' => 'fukuoka', 'weight' => 3),
        array('name' => '北海道', 'slug' => 'hokkaido', 'weight' => 2),
        array('name' => '京都府', 'slug' => 'kyoto', 'weight' => 2),
        array('name' => '兵庫県', 'slug' => 'hyogo', 'weight' => 2),
        array('name' => '埼玉県', 'slug' => 'saitama', 'weight' => 2),
        array('name' => '千葉県', 'slug' => 'chiba', 'weight' => 2),
        // Other prefectures
        array('name' => '青森県', 'slug' => 'aomori', 'weight' => 1),
        array('name' => '岩手県', 'slug' => 'iwate', 'weight' => 1),
        array('name' => '宮城県', 'slug' => 'miyagi', 'weight' => 2),
        array('name' => '秋田県', 'slug' => 'akita', 'weight' => 1),
        array('name' => '山形県', 'slug' => 'yamagata', 'weight' => 1),
        array('name' => '福島県', 'slug' => 'fukushima', 'weight' => 1),
        array('name' => '茨城県', 'slug' => 'ibaraki', 'weight' => 1),
        array('name' => '栃木県', 'slug' => 'tochigi', 'weight' => 1),
        array('name' => '群馬県', 'slug' => 'gunma', 'weight' => 1),
        array('name' => '新潟県', 'slug' => 'niigata', 'weight' => 1),
        array('name' => '富山県', 'slug' => 'toyama', 'weight' => 1),
        array('name' => '石川県', 'slug' => 'ishikawa', 'weight' => 1),
        array('name' => '福井県', 'slug' => 'fukui', 'weight' => 1),
        array('name' => '山梨県', 'slug' => 'yamanashi', 'weight' => 1),
        array('name' => '長野県', 'slug' => 'nagano', 'weight' => 1),
        array('name' => '岐阜県', 'slug' => 'gifu', 'weight' => 1),
        array('name' => '静岡県', 'slug' => 'shizuoka', 'weight' => 2),
        array('name' => '三重県', 'slug' => 'mie', 'weight' => 1),
        array('name' => '滋賀県', 'slug' => 'shiga', 'weight' => 1),
        array('name' => '奈良県', 'slug' => 'nara', 'weight' => 1),
        array('name' => '和歌山県', 'slug' => 'wakayama', 'weight' => 1),
        array('name' => '鳥取県', 'slug' => 'tottori', 'weight' => 1),
        array('name' => '島根県', 'slug' => 'shimane', 'weight' => 1),
        array('name' => '岡山県', 'slug' => 'okayama', 'weight' => 1),
        array('name' => '広島県', 'slug' => 'hiroshima', 'weight' => 2),
        array('name' => '山口県', 'slug' => 'yamaguchi', 'weight' => 1),
        array('name' => '徳島県', 'slug' => 'tokushima', 'weight' => 1),
        array('name' => '香川県', 'slug' => 'kagawa', 'weight' => 1),
        array('name' => '愛媛県', 'slug' => 'ehime', 'weight' => 1),
        array('name' => '高知県', 'slug' => 'kochi', 'weight' => 1),
        array('name' => '佐賀県', 'slug' => 'saga', 'weight' => 1),
        array('name' => '長崎県', 'slug' => 'nagasaki', 'weight' => 1),
        array('name' => '熊本県', 'slug' => 'kumamoto', 'weight' => 1),
        array('name' => '大分県', 'slug' => 'oita', 'weight' => 1),
        array('name' => '宮崎県', 'slug' => 'miyazaki', 'weight' => 1),
        array('name' => '鹿児島県', 'slug' => 'kagoshima', 'weight' => 1),
        array('name' => '沖縄県', 'slug' => 'okinawa', 'weight' => 1)
    );
    
    // Create prefecture terms if they don't exist
    foreach ($prefectures as $prefecture) {
        if (!term_exists($prefecture['slug'], 'grant_prefecture')) {
            wp_insert_term(
                $prefecture['name'],
                'grant_prefecture',
                array('slug' => $prefecture['slug'])
            );
        }
    }
    
    // Get all published grants
    $grants = get_posts(array(
        'post_type' => 'grant',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    if (empty($grants)) {
        return;
    }
    
    // Build weighted prefecture list for random assignment
    $weighted_prefectures = array();
    foreach ($prefectures as $pref) {
        for ($i = 0; $i < $pref['weight']; $i++) {
            $weighted_prefectures[] = $pref['slug'];
        }
    }
    
    // Assign prefectures to grants
    foreach ($grants as $grant) {
        $existing_prefs = wp_get_object_terms($grant->ID, 'grant_prefecture', array('fields' => 'ids'));
        
        // Only assign if no prefectures are already assigned
        if (empty($existing_prefs)) {
            // Randomly assign 1-3 prefectures
            $num_prefs = rand(1, 3);
            $selected_prefs = array();
            
            for ($i = 0; $i < $num_prefs; $i++) {
                $random_index = array_rand($weighted_prefectures);
                $selected_slug = $weighted_prefectures[$random_index];
                
                // Avoid duplicates
                if (!in_array($selected_slug, $selected_prefs)) {
                    $selected_prefs[] = $selected_slug;
                }
            }
            
            // Set the terms
            wp_set_object_terms($grant->ID, $selected_prefs, 'grant_prefecture');
        }
    }
    
    // Clear term count cache to ensure accurate counts
    wp_cache_delete('all_ids', 'grant_prefecture');
    delete_option('grant_prefecture_children');
    
    // Force recount of terms
    $terms = get_terms(array(
        'taxonomy' => 'grant_prefecture',
        'hide_empty' => false
    ));
    
    foreach ($terms as $term) {
        wp_update_term_count_now(array($term->term_id), 'grant_prefecture');
    }
}

// Hook to initialize on admin visit
add_action('admin_init', 'gi_maybe_init_prefecture_data');
function gi_maybe_init_prefecture_data() {
    $initialized = get_option('gi_prefecture_data_initialized_v2', false);
    
    if (!$initialized) {
        gi_init_prefecture_sample_data();
        update_option('gi_prefecture_data_initialized_v2', true);
    }
}

// Add admin notice for manual initialization
add_action('admin_notices', 'gi_prefecture_init_notice');
function gi_prefecture_init_notice() {
    if (isset($_GET['init_prefectures']) && $_GET['init_prefectures'] === '1') {
        gi_init_prefecture_sample_data();
        echo '<div class="notice notice-success is-dismissible"><p>都道府県データが初期化されました。</p></div>';
    }
}