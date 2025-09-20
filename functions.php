<?php
/**
 * Grant Insight Perfect - Functions File Loader
 * @package Grant_Insight_Perfect
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// テーマバージョン定数（重複チェック追加）
if (!defined('GI_THEME_VERSION')) {
    define('GI_THEME_VERSION', '6.2.2');
}
if (!defined('GI_THEME_PREFIX')) {
    define('GI_THEME_PREFIX', 'gi_');
}

// 機能ファイルの読み込み
$inc_dir = get_template_directory() . '/inc/';

// ファイル存在チェックを追加
$required_files = array(
    '1-theme-setup-optimized.php',    // テーマ基本設定、スクリプト（最適化版）
    '2-post-types.php',               // 投稿タイプ、タクソノミー
    '3-ajax-functions.php',           // AJAX関連
    '4-helper-functions.php',         // ヘルパー関数
    '5-template-tags.php',            // テンプレート用関数
    '6-admin-functions.php',          // 管理画面関連
    '7-acf-setup.php',                // ACF関連
    '8-acf-fields-setup.php',         // ACFフィールド定義
    '9-mobile-optimization.php',      // モバイル最適化機能
    '10-performance-helpers.php',     // パフォーマンス最適化ヘルパー
    '11-grant-card-renderer.php',     // 助成金カードレンダラー
    '12-ai_concierge_function.php',   // AIコンシェルジュ機能
    '13-ai-enhanced-functions.php',   // AI強化機能（セマンティック検索、ストリーミング、音声認識）
    '14-vector-database.php',         // ベクトルデータベース（セマンティック検索用）
    '15-openai-integration.php',      // OpenAI API統合（完全実装版）
    '16-grant-semantic-search.php',   // 助成金セマンティック検索（強化版）
    '17-emotion-learning-system.php'  // 感情分析＆学習システム（日本語対応）
);

// 各ファイルを安全に読み込み
foreach ($required_files as $file) {
    $file_path = $inc_dir . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // デバッグモードの場合はエラーログに記録
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Grant Insight Theme: Required file not found - ' . $file_path);
        }
    }
}

// 統一カードレンダラーの読み込み（エラーハンドリング付き）
$card_renderer_path = get_template_directory() . '/inc/11-grant-card-renderer.php';
$card_unified_path = get_template_directory() . '/template-parts/grant-card-unified.php';

if (file_exists($card_renderer_path)) {
    require_once $card_renderer_path;
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Grant Insight Theme: GrantCardRenderer class not found at ' . $card_renderer_path);
    }
}

if (file_exists($card_unified_path)) {
    require_once $card_unified_path;
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Grant Insight Theme: grant-card-unified.php not found at ' . $card_unified_path);
    }
}

// グローバルで使えるヘルパー関数
if (!function_exists('gi_render_card')) {
    function gi_render_card($post_id, $view = 'grid') {
        if (class_exists('GrantCardRenderer')) {
            $renderer = GrantCardRenderer::getInstance();
            return $renderer->render($post_id, $view);
        }
        
        // フォールバック
        return '<div class="grant-card-error">カードレンダラーが利用できません</div>';
    }
}

/**
 * テーマの最終初期化
 */
function gi_final_init() {  // ✅ 修正
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Grant Insight Theme v' . GI_THEME_VERSION . ': Mobile optimization included, initialization completed successfully');
    }
}
add_action('wp_loaded', 'gi_final_init', 999);

// 以下のコードはそのまま...


/**
 * クリーンアップ処理
 */
function gi_theme_cleanup() {
    // オプションの削除
    delete_option('gi_login_attempts');
    
    // モバイル最適化キャッシュのクリア
    delete_option('gi_mobile_cache');
    
    // トランジェントのクリア
    delete_transient('gi_site_stats_v2');
    
    // オブジェクトキャッシュのフラッシュ（存在する場合のみ）
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}
add_action('switch_theme', 'gi_theme_cleanup');

/**
 * AI強化機能のスクリプトとスタイル登録
 */
function gi_enqueue_ai_enhanced_assets() {
    // 強化版AIアシスタントJS
    wp_enqueue_script(
        'gi-ai-assistant-enhanced',
        get_template_directory_uri() . '/js/ai-assistant-enhanced.js',
        array('jquery'),
        GI_THEME_VERSION,
        true
    );
    
    // 強化版AIアシスタントCSS
    wp_enqueue_style(
        'gi-ai-assistant-enhanced',
        get_template_directory_uri() . '/css/ai-assistant-enhanced.css',
        array(),
        GI_THEME_VERSION
    );
    
    // Ajax設定をローカライズ
    wp_localize_script('gi-ai-assistant-enhanced', 'gi_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gi_ai_nonce'),
        'api_key_configured' => !empty(get_option('gi_ai_concierge_settings')['openai_api_key'])
    ));
}
add_action('wp_enqueue_scripts', 'gi_enqueue_ai_enhanced_assets', 100);

/**
 * スクリプトにdefer属性を追加（改善版）
 */
if (!function_exists('gi_add_defer_attribute')) {
    function gi_add_defer_attribute($tag, $handle, $src) {
        // 管理画面では処理しない
        if (is_admin()) {
            return $tag;
        }
        
        // WordPressコアスクリプトは除外
        if (strpos($src, 'wp-includes/js/') !== false) {
            return $tag;
        }
        
        // 既にdefer/asyncがある場合はスキップ
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        
        // 特定のハンドルにのみdeferを追加
        $defer_handles = array(
            'gi-main-js',
            'gi-frontend-js',
            'gi-mobile-enhanced'
        );
        
        if (in_array($handle, $defer_handles)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
}

// フィルターの重複登録を防ぐ
remove_filter('script_loader_tag', 'gi_add_defer_attribute', 10);
add_filter('script_loader_tag', 'gi_add_defer_attribute', 10, 3);

// モバイル専用テンプレート切り替えは削除（統合されました）

/**
 * モバイル用AJAX エンドポイント - さらに読み込み
 */
function gi_ajax_load_more_grants() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $page = intval($_POST['page'] ?? 1);
    $posts_per_page = 10;
    
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'post_status' => 'publish',
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        wp_send_json_error('No more posts found');
    }
    
    ob_start();
    
    while ($query->have_posts()): $query->the_post();
        echo gi_render_mobile_card(get_the_ID());
    endwhile;
    
    wp_reset_postdata();
    
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'page' => $page,
        'max_pages' => $query->max_num_pages,
        'found_posts' => $query->found_posts
    ]);
}
add_action('wp_ajax_gi_load_more_grants', 'gi_ajax_load_more_grants');
add_action('wp_ajax_nopriv_gi_load_more_grants', 'gi_ajax_load_more_grants');

/**
 * テーマのアクティベーションチェック
 */
function gi_theme_activation_check() {
    // PHP バージョンチェック
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Grant Insight テーマはPHP 7.4以上が必要です。現在のバージョン: ' . PHP_VERSION;
            echo '</p></div>';
        });
    }
    
    // WordPress バージョンチェック
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            echo 'Grant Insight テーマはWordPress 5.8以上を推奨します。';
            echo '</p></div>';
        });
    }
    
    // 必須プラグインチェック（ACFなど）
    if (!class_exists('ACF') && is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>';
            echo 'Grant Insight テーマの全機能を利用するには、Advanced Custom Fields (ACF) プラグインのインストールを推奨します。';
            echo '</p></div>';
        });
    }
}
add_action('after_setup_theme', 'gi_theme_activation_check');

/**
 * エラーハンドリング用のグローバル関数
 */
if (!function_exists('gi_log_error')) {
    function gi_log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[Grant Insight Error] ' . $message;
            if (!empty($context)) {
                $log_message .= ' | Context: ' . print_r($context, true);
            }
            error_log($log_message);
        }
    }
}

/**
 * テーマ設定のデフォルト値を取得
 */
if (!function_exists('gi_get_theme_option')) {
    function gi_get_theme_option($option_name, $default = null) {
        $theme_options = get_option('gi_theme_options', array());
        
        if (isset($theme_options[$option_name])) {
            return $theme_options[$option_name];
        }
        
        return $default;
    }
}

/**
 * テーマ設定を保存
 */
if (!function_exists('gi_update_theme_option')) {
    function gi_update_theme_option($option_name, $value) {
        $theme_options = get_option('gi_theme_options', array());
        $theme_options[$option_name] = $value;
        
        return update_option('gi_theme_options', $theme_options);
    }
}



/**
 * テーマのバージョンアップグレード処理
 */
function gi_theme_version_upgrade() {
    $current_version = get_option('gi_installed_version', '0.0.0');
    
    if (version_compare($current_version, GI_THEME_VERSION, '<')) {
        // バージョンアップグレード処理
        
        // 6.2.0 -> 6.2.1 のアップグレード
        if (version_compare($current_version, '6.2.1', '<')) {
            // キャッシュのクリア
            gi_theme_cleanup();
        }
        
        // 6.2.1 -> 6.2.2 のアップグレード
        if (version_compare($current_version, '6.2.2', '<')) {
            // 新しいメタフィールドの追加など
            flush_rewrite_rules();
        }
        
        // バージョン更新
        update_option('gi_installed_version', GI_THEME_VERSION);
        
        // アップグレード完了通知
        if (is_admin()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo 'Grant Insight テーマが v' . GI_THEME_VERSION . ' にアップグレードされました。';
                echo '</p></div>';
            });
        }
    }
}
add_action('init', 'gi_theme_version_upgrade');

/**
 * データベーステーブル作成
 */
function gi_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // AI検索履歴テーブル
    $search_history_table = $wpdb->prefix . 'gi_search_history';
    $sql1 = "CREATE TABLE IF NOT EXISTS $search_history_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        search_query text NOT NULL,
        search_filter varchar(50) DEFAULT NULL,
        results_count int(11) DEFAULT 0,
        clicked_results text DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // AIチャット履歴テーブル
    $chat_history_table = $wpdb->prefix . 'gi_chat_history';
    $sql2 = "CREATE TABLE IF NOT EXISTS $chat_history_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        message_type enum('user','assistant') NOT NULL DEFAULT 'user',
        message text NOT NULL,
        intent varchar(100) DEFAULT NULL,
        confidence decimal(3,2) DEFAULT NULL,
        related_grants text DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // 音声入力履歴テーブル
    $voice_history_table = $wpdb->prefix . 'gi_voice_history';
    $sql3 = "CREATE TABLE IF NOT EXISTS $voice_history_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        transcribed_text text NOT NULL,
        confidence decimal(3,2) DEFAULT NULL,
        duration int(11) DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // ユーザー設定テーブル
    $user_preferences_table = $wpdb->prefix . 'gi_user_preferences';
    $sql4 = "CREATE TABLE IF NOT EXISTS $user_preferences_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        preference_key varchar(100) NOT NULL,
        preference_value text DEFAULT NULL,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_preference (user_id, preference_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    
    // バージョン管理
    update_option('gi_db_version', '1.0.0');
}

// テーマ有効化時にテーブル作成
add_action('after_switch_theme', 'gi_create_database_tables');

// 既存のインストールでもテーブル作成を確認
add_action('init', function() {
    $db_version = get_option('gi_db_version', '0');
    if (version_compare($db_version, '1.0.0', '<')) {
        gi_create_database_tables();
    }
});

/**
 * 検索履歴の保存
 */
function gi_save_search_history($session_id, $query, $filter, $results_count) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_search_history';
    $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'search_query' => $query,
            'search_filter' => $filter,
            'results_count' => $results_count
        ],
        ['%s', '%d', '%s', '%s', '%d']
    );
    
    return $wpdb->insert_id;
}

/**
 * チャット履歴の保存
 */
function gi_save_chat_history($session_id, $message_type, $message, $intent = null, $confidence = null, $related_grants = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_chat_history';
    $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'message_type' => $message_type,
            'message' => $message,
            'intent' => $intent,
            'confidence' => $confidence,
            'related_grants' => is_array($related_grants) ? json_encode($related_grants) : $related_grants
        ],
        ['%s', '%d', '%s', '%s', '%s', '%f', '%s']
    );
    
    return $wpdb->insert_id;
}

/**
 * 音声入力履歴の保存
 */
function gi_save_voice_history($session_id, $text, $confidence = null, $duration = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_voice_history';
    $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'transcribed_text' => $text,
            'confidence' => $confidence,
            'duration' => $duration
        ],
        ['%s', '%d', '%s', '%f', '%d']
    );
    
    return $wpdb->insert_id;
}

/**
 * AI検索AJAXハンドラーの早期登録（優先度高）
 */
function gi_register_ai_ajax_handlers() {
    // テスト用のシンプルなハンドラー
    add_action('wp_ajax_gi_test_connection', function() {
        wp_send_json_success(['message' => 'Connection successful', 'time' => current_time('Y-m-d H:i:s')]);
    });
    add_action('wp_ajax_nopriv_gi_test_connection', function() {
        wp_send_json_success(['message' => 'Connection successful (nopriv)', 'time' => current_time('Y-m-d H:i:s')]);
    });
    
    // AI検索とチャットのハンドラーが存在することを確認（inc/3-ajax-functions.phpで定義済み）
}
add_action('init', 'gi_register_ai_ajax_handlers', 5);

/**
 * AJAXハンドラーの登録確認
 */