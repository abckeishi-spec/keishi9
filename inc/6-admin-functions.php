<?php
/**
 * Grant Insight Perfect - 6. Admin Functions File
 *
 * 管理画面のカスタマイズ（スクリプト読込、投稿一覧へのカラム追加、
 * メタボックス追加、カスタムメニュー追加など）を担当します。
 *
 * @package Grant_Insight_Perfect
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}



/**
 * 管理画面カスタマイズ（強化版）
 */
function gi_admin_init() {
    // 管理画面スタイル
    add_action('admin_head', function() {
        echo '<style>
        .gi-admin-notice {
            border-left: 4px solid #10b981;
            background: #ecfdf5;
            padding: 12px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .gi-admin-notice h3 {
            color: #047857;
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .gi-admin-notice p {
            color: #065f46;
            margin: 0;
        }
        </style>';
    });
    
    // 投稿一覧カラム追加
    add_filter('manage_grant_posts_columns', 'gi_add_grant_columns');
    add_action('manage_grant_posts_custom_column', 'gi_grant_column_content', 10, 2);
}
add_action('admin_init', 'gi_admin_init');

/**
 * 助成金一覧にカスタムカラムを追加
 */
function gi_add_grant_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['gi_prefecture'] = '都道府県';
            $new_columns['gi_amount'] = '金額';
            $new_columns['gi_organization'] = '実施組織';
            $new_columns['gi_status'] = 'ステータス';
        }
    }
    return $new_columns;
}

/**
 * カスタムカラムに内容を表示
 */
function gi_grant_column_content($column, $post_id) {
    switch ($column) {
        case 'gi_prefecture':
            $prefecture_terms = get_the_terms($post_id, 'grant_prefecture');
            if ($prefecture_terms && !is_wp_error($prefecture_terms)) {
                echo gi_safe_escape($prefecture_terms[0]->name);
            } else {
                echo '－';
            }
            break;
        case 'gi_amount':
            $amount = gi_safe_get_meta($post_id, 'max_amount');
            echo $amount ? gi_safe_escape($amount) . '万円' : '－';
            break;
        case 'gi_organization':
            echo gi_safe_escape(gi_safe_get_meta($post_id, 'organization', '－'));
            break;
        case 'gi_status':
            $status = gi_map_application_status_ui(gi_safe_get_meta($post_id, 'application_status', 'open'));
            $status_labels = array(
                'active' => '<span style="color: #059669;">募集中</span>',
                'upcoming' => '<span style="color: #d97706;">募集予定</span>',
                'closed' => '<span style="color: #dc2626;">募集終了</span>'
            );
            echo $status_labels[$status] ?? $status;
            break;
    }
}

/**
 * 管理画面にサンプルデータ作成ボタンを追加
 */
function gi_add_sample_data_page() {
    add_submenu_page(
        'edit.php?post_type=grant',
        'サンプルデータ作成',
        'サンプルデータ',
        'manage_options',
        'gi-sample-data',
        'gi_sample_data_page_content'
    );
}
add_action('admin_menu', 'gi_add_sample_data_page');

/**
 * サンプルデータページの内容
 */
function gi_sample_data_page_content() {
    if (isset($_POST['create_sample_data']) && check_admin_referer('gi_create_sample_data')) {
        gi_create_sample_grants();
        echo '<div class="notice notice-success"><p>サンプルデータを作成しました。</p></div>';
    }
    
    // 現在の投稿数を確認
    $grant_count = wp_count_posts('grant')->publish;
    ?>
    <div class="wrap">
        <h1>サンプルデータ作成</h1>
        
        <div class="gi-admin-notice">
            <h3>現在の状況</h3>
            <p>現在の助成金投稿数: <strong><?php echo $grant_count; ?>件</strong></p>
        </div>
        
        <?php if ($grant_count == 0): ?>
        <form method="post" action="">
            <?php wp_nonce_field('gi_create_sample_data'); ?>
            <p>サンプルデータを作成すると、テスト用の助成金情報が登録されます。</p>
            <p>
                <input type="submit" name="create_sample_data" class="button button-primary" value="サンプルデータを作成">
            </p>
        </form>
        <?php else: ?>
        <p>すでに投稿データが存在するため、サンプルデータの作成はスキップされました。</p>
        <?php endif; ?>
        
        <h2>都道府県別統計</h2>
        <?php
        $prefectures = get_terms(array(
            'taxonomy' => 'grant_prefecture',
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        if (!empty($prefectures) && !is_wp_error($prefectures)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>都道府県</th>
                    <th>投稿数</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($prefectures as $pref): ?>
                <tr>
                    <td><?php echo esc_html($pref->name); ?></td>
                    <td><?php echo $pref->count; ?>件</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>都道府県データがありません。</p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 管理メニューの追加
 */
function gi_add_admin_menu() {
    // 都道府県データ初期化
    add_management_page(
        '都道府県データ初期化',
        '都道府県データ初期化',
        'manage_options',
        'gi-prefecture-init',
        'gi_add_prefecture_init_button'
    );
    
    // AI設定メニュー追加
    add_menu_page(
        'AI検索設定',
        'AI検索設定',
        'manage_options',
        'gi-ai-settings',
        'gi_ai_settings_page',
        'dashicons-search',
        30
    );
    
    // AI検索統計サブメニュー
    add_submenu_page(
        'gi-ai-settings',
        'AI検索統計',
        '統計・レポート',
        'manage_options',
        'gi-ai-statistics',
        'gi_ai_statistics_page'
    );
}
add_action('admin_menu', 'gi_add_admin_menu');

/**
 * 都道府県データ初期化ページの表示内容
 */
function gi_add_prefecture_init_button() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['init_prefecture_data']) && isset($_POST['prefecture_nonce']) && wp_verify_nonce($_POST['prefecture_nonce'], 'init_prefecture')) {
        // `gi_setup_prefecture_taxonomy_data` は initial-setup.php にある想定
        if (function_exists('gi_setup_prefecture_taxonomy_data')) {
            gi_setup_prefecture_taxonomy_data();
            echo '<div class="notice notice-success"><p>都道府県データを初期化しました。</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>エラー: 初期化関数が見つかりませんでした。</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h2>都道府県データ初期化</h2>
        <form method="post">
            <?php wp_nonce_field('init_prefecture', 'prefecture_nonce'); ?>
            <p>助成金の都道府県データとサンプルデータを初期化します。</p>
            <p class="description">この操作は既存の都道府県タクソノミーに不足しているデータを追加するもので、既存のデータを削除するものではありません。</p>
            <input type="submit" name="init_prefecture_data" class="button button-primary" value="都道府県データを初期化" />
        </form>
    </div>
    <?php
}

/**
 * AI設定ページ（簡易版）
 */
function gi_ai_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // 設定の保存処理
    if (isset($_POST['save_ai_settings']) && wp_verify_nonce($_POST['ai_settings_nonce'], 'gi_ai_settings')) {
        $settings = [
            'enable_ai_search' => isset($_POST['enable_ai_search']) ? 1 : 0,
            'enable_voice_input' => isset($_POST['enable_voice_input']) ? 1 : 0,
            'enable_ai_chat' => isset($_POST['enable_ai_chat']) ? 1 : 0
        ];
        
        update_option('gi_ai_settings', $settings);
        echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
    }
    
    // 現在の設定を取得
    $settings = get_option('gi_ai_settings', [
        'enable_ai_search' => 1,
        'enable_voice_input' => 1,
        'enable_ai_chat' => 1
    ]);
    ?>
    <div class="wrap">
        <h1>AI検索設定</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('gi_ai_settings', 'ai_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">AI検索を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_ai_search" value="1" 
                                <?php checked($settings['enable_ai_search'], 1); ?>>
                            AIによる高度な検索機能を有効にする
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">音声入力を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_voice_input" value="1" 
                                <?php checked($settings['enable_voice_input'], 1); ?>>
                            音声による検索入力を有効にする
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">AIチャットを有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_ai_chat" value="1" 
                                <?php checked($settings['enable_ai_chat'], 1); ?>>
                            AIアシスタントとのチャット機能を有効にする
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_ai_settings" class="button-primary" value="設定を保存">
            </p>
        </form>
    </div>
    <?php
}

/**
 * AI統計ページ（簡易版）
 */
function gi_ai_statistics_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    
    // テーブルが存在するかチェック
    $search_table = $wpdb->prefix . 'gi_search_history';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$search_table'") === $search_table;
    
    if (!$table_exists) {
        ?>
        <div class="wrap">
            <h1>AI検索統計</h1>
            <div class="notice notice-info">
                <p>統計データテーブルがまだ作成されていません。初回の検索実行時に自動的に作成されます。</p>
            </div>
        </div>
        <?php
        return;
    }
    
    // 統計データの取得
    $total_searches = $wpdb->get_var("SELECT COUNT(*) FROM $search_table") ?: 0;
    
    // チャット履歴テーブル
    $chat_table = $wpdb->prefix . 'gi_chat_history';
    $chat_exists = $wpdb->get_var("SHOW TABLES LIKE '$chat_table'") === $chat_table;
    $total_chats = $chat_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $chat_table WHERE message_type = 'user'") : 0;
    
    // 人気の検索キーワード（直近30日）
    $popular_searches = $wpdb->get_results("
        SELECT search_query, COUNT(*) as count 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY search_query 
        ORDER BY count DESC 
        LIMIT 10
    ");
    
    // 時間帯別利用状況（直近7日）
    $hourly_stats = $wpdb->get_results("
        SELECT HOUR(created_at) as hour, COUNT(*) as count 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(created_at) 
        ORDER BY hour
    ");
    
    // 日別利用状況（直近30日）
    $daily_stats = $wpdb->get_results("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date DESC
    ");
    
    // 平均検索結果数
    $avg_results = $wpdb->get_var("
        SELECT AVG(results_count) 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ") ?: 0;
    
    ?>
    <div class="wrap">
        <h1>AI検索統計</h1>
        
        <!-- 統計サマリー -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">総検索数</h3>
                <p style="font-size: 32px; font-weight: bold; color: #10b981; margin: 10px 0;">
                    <?php echo number_format($total_searches); ?>
                </p>
                <p style="color: #666; font-size: 12px;">全期間</p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">チャット数</h3>
                <p style="font-size: 32px; font-weight: bold; color: #3b82f6; margin: 10px 0;">
                    <?php echo number_format($total_chats); ?>
                </p>
                <p style="color: #666; font-size: 12px;">AIとの対話数</p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">平均検索結果</h3>
                <p style="font-size: 32px; font-weight: bold; color: #f59e0b; margin: 10px 0;">
                    <?php echo number_format($avg_results, 1); ?>
                </p>
                <p style="color: #666; font-size: 12px;">件/検索</p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">本日の検索</h3>
                <p style="font-size: 32px; font-weight: bold; color: #8b5cf6; margin: 10px 0;">
                    <?php 
                    $today_searches = $wpdb->get_var("
                        SELECT COUNT(*) FROM $search_table 
                        WHERE DATE(created_at) = CURDATE()
                    ") ?: 0;
                    echo number_format($today_searches);
                    ?>
                </p>
                <p style="color: #666; font-size: 12px;"><?php echo date('Y年m月d日'); ?></p>
            </div>
        </div>
        
        <!-- 人気検索キーワード -->
        <?php if (!empty($popular_searches)): ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="font-size: 18px; margin-top: 0;">人気の検索キーワード（過去30日）</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 50px;">順位</th>
                        <th>検索キーワード</th>
                        <th style="width: 100px;">検索回数</th>
                        <th style="width: 120px;">割合</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_month = array_sum(array_column($popular_searches, 'count'));
                    foreach ($popular_searches as $index => $search): 
                        $percentage = ($search->count / $total_month) * 100;
                    ?>
                    <tr>
                        <td><strong><?php echo $index + 1; ?></strong></td>
                        <td>
                            <?php echo esc_html($search->search_query); ?>
                            <?php if ($index < 3): ?>
                                <span style="color: #f59e0b;">🔥</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($search->count); ?>回</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div style="background: #e5e5e5; height: 20px; flex: 1; border-radius: 3px; overflow: hidden;">
                                    <div style="background: #10b981; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                                </div>
                                <span style="font-size: 12px;"><?php echo number_format($percentage, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- 時間帯別利用状況 -->
        <?php if (!empty($hourly_stats)): ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="font-size: 18px; margin-top: 0;">時間帯別利用状況（過去7日間）</h2>
            <div style="display: flex; align-items: flex-end; height: 200px; gap: 2px; margin-top: 20px;">
                <?php 
                $max_hour = max(array_column($hourly_stats, 'count'));
                for ($h = 0; $h < 24; $h++):
                    $count = 0;
                    foreach ($hourly_stats as $stat) {
                        if ($stat->hour == $h) {
                            $count = $stat->count;
                            break;
                        }
                    }
                    $height = $max_hour > 0 ? ($count / $max_hour) * 100 : 0;
                ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <div style="background: <?php echo $height > 0 ? '#3b82f6' : '#e5e5e5'; ?>; 
                                width: 100%; 
                                height: <?php echo max($height, 2); ?>%; 
                                border-radius: 2px 2px 0 0;"
                         title="<?php echo $h; ?>時: <?php echo $count; ?>件"></div>
                    <?php if ($h % 3 == 0): ?>
                    <span style="font-size: 10px; margin-top: 5px;"><?php echo $h; ?>時</span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- アクション -->
        <div style="margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=gi-ai-settings'); ?>" class="button button-primary">
                AI設定を確認
            </a>
            <button type="button" class="button" onclick="if(confirm('統計データをリセットしますか？')) location.href='?page=gi-ai-statistics&action=reset&nonce=<?php echo wp_create_nonce('reset_stats'); ?>'">
                統計をリセット
            </button>
        </div>
    </div>
    <?php
    
    // リセット処理
    if (isset($_GET['action']) && $_GET['action'] === 'reset' && wp_verify_nonce($_GET['nonce'], 'reset_stats')) {
        $wpdb->query("TRUNCATE TABLE $search_table");
        if ($chat_exists) {
            $wpdb->query("TRUNCATE TABLE $chat_table");
        }
        echo '<div class="notice notice-success"><p>統計データをリセットしました。</p></div>';
        echo '<script>setTimeout(function(){ location.href="?page=gi-ai-statistics"; }, 2000);</script>';
    }
}