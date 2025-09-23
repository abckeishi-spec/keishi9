<?php
/**
 * Grant Insight - AI機能統合
 * AI関連の機能を統合したファイル
 * 
 * @version 8.0.1 - AI Function Consolidation
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 検索履歴の保存（統合版）
 * 両方の実装を統合してより堅牢な保存機能を提供
 */
function gi_save_search_history($query, $filters = [], $results_count = 0, $session_id = null) {
    // セッションIDが提供された場合はデータベース保存も実行
    if ($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_search_history';
        // テーブルが存在する場合のみ保存
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
            $wpdb->insert(
                $table,
                [
                    'session_id' => $session_id,
                    'user_id' => get_current_user_id() ?: null,
                    'search_query' => $query,
                    'search_filter' => is_array($filters) ? json_encode($filters) : $filters,
                    'results_count' => $results_count,
                    'search_time' => current_time('mysql')
                ],
                ['%s', '%d', '%s', '%s', '%d', '%s']
            );
        }
    }
    
    // ユーザーメタデータにも保存（ログインユーザーの場合）
    $user_id = get_current_user_id();
    if ($user_id) {
        $history = get_user_meta($user_id, 'gi_search_history', true) ?: [];
        
        // 新しい検索を追加
        array_unshift($history, [
            'query' => sanitize_text_field($query),
            'filters' => $filters,
            'results_count' => intval($results_count),
            'timestamp' => current_time('timestamp')
        ]);
        
        // 最新の20件のみ保持
        $history = array_slice($history, 0, 20);
        
        update_user_meta($user_id, 'gi_search_history', $history);
    }
    
    return true;
}

/**
 * 検索履歴の取得
 */
function gi_get_search_history($limit = 10) {
    $user_id = get_current_user_id();
    if (!$user_id) return [];
    
    $history = get_user_meta($user_id, 'gi_search_history', true) ?: [];
    
    return array_slice($history, 0, $limit);
}

/**
 * 検索履歴のクリア
 */
function gi_clear_search_history() {
    $user_id = get_current_user_id();
    if (!$user_id) return false;
    
    delete_user_meta($user_id, 'gi_search_history');
    return true;
}

/**
 * 人気検索キーワードの取得
 */
function gi_get_popular_search_terms($limit = 10) {
    // 全ユーザーの検索履歴から人気キーワードを集計
    global $wpdb;
    
    $cache_key = 'gi_popular_search_terms_' . $limit;
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $table = $wpdb->prefix . 'gi_search_history';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return [];
    }
    
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT search_query, COUNT(*) as count 
        FROM {$table} 
        WHERE search_query != '' 
        AND search_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY search_query 
        ORDER BY count DESC 
        LIMIT %d
    ", $limit), ARRAY_A);
    
    $popular_terms = [];
    foreach ($results as $result) {
        $popular_terms[] = [
            'term' => $result['search_query'],
            'count' => intval($result['count'])
        ];
    }
    
    // 30分間キャッシュ
    wp_cache_set($cache_key, $popular_terms, '', 1800);
    
    return $popular_terms;
}

/**
 * AI関連設定の取得
 */
function gi_get_ai_settings() {
    return [
        'search_enhancement' => get_option('gi_ai_search_enhancement', false),
        'auto_suggestions' => get_option('gi_ai_auto_suggestions', true),
        'semantic_search' => get_option('gi_ai_semantic_search', false),
        'history_tracking' => get_option('gi_ai_history_tracking', true)
    ];
}

/**
 * AI設定の更新
 */
function gi_update_ai_settings($settings) {
    $valid_settings = ['search_enhancement', 'auto_suggestions', 'semantic_search', 'history_tracking'];
    
    foreach ($settings as $key => $value) {
        if (in_array($key, $valid_settings)) {
            update_option('gi_ai_' . $key, (bool) $value);
        }
    }
    
    return true;
}

/**
 * 検索統計の取得
 */
function gi_get_search_statistics() {
    global $wpdb;
    
    $cache_key = 'gi_search_statistics';
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $table = $wpdb->prefix . 'gi_search_history';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
        return [
            'total_searches' => 0,
            'unique_queries' => 0,
            'average_results' => 0,
            'recent_searches' => []
        ];
    }
    
    $total_searches = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $unique_queries = $wpdb->get_var("SELECT COUNT(DISTINCT search_query) FROM {$table}");
    $average_results = $wpdb->get_var("SELECT AVG(results_count) FROM {$table} WHERE results_count > 0");
    
    $recent_searches = $wpdb->get_results("
        SELECT search_query, results_count, search_time 
        FROM {$table} 
        ORDER BY search_time DESC 
        LIMIT 10
    ", ARRAY_A);
    
    $stats = [
        'total_searches' => intval($total_searches),
        'unique_queries' => intval($unique_queries),
        'average_results' => round(floatval($average_results), 1),
        'recent_searches' => $recent_searches
    ];
    
    // 1時間キャッシュ
    wp_cache_set($cache_key, $stats, '', 3600);
    
    return $stats;
}

/**
 * 検索履歴テーブルの作成
 */
function gi_create_search_history_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'gi_search_history';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        session_id varchar(100) DEFAULT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        search_query text NOT NULL,
        search_filter text DEFAULT NULL,
        results_count int(11) DEFAULT 0,
        search_time datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY search_time (search_time)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * AI機能の初期化
 */
function gi_init_ai_functions() {
    global $wpdb;
    
    // 検索履歴テーブルの作成（存在しない場合）
    $table_name = $wpdb->prefix . 'gi_search_history';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        gi_create_search_history_table();
    }
    
    // デフォルト設定の作成
    $default_settings = [
        'gi_ai_search_enhancement' => false,
        'gi_ai_auto_suggestions' => true,
        'gi_ai_semantic_search' => false,
        'gi_ai_history_tracking' => true
    ];
    
    foreach ($default_settings as $option => $default_value) {
        if (get_option($option) === false) {
            add_option($option, $default_value);
        }
    }
}

// フック登録
add_action('init', 'gi_init_ai_functions');

/**
 * 検索履歴のクリーンアップ（古いデータの削除）
 */
function gi_cleanup_old_search_history() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_search_history';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
        // 90日より古いレコードを削除
        $wpdb->query("DELETE FROM {$table} WHERE search_time < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }
}

// 日次クリーンアップの設定
if (!wp_next_scheduled('gi_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'gi_daily_cleanup');
}
add_action('gi_daily_cleanup', 'gi_cleanup_old_search_history');