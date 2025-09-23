<?php
/**
 * Grant Insight Perfect - 3. AJAX Functions File (Complete Unified Edition)
 *
 * サイトの動的な機能（検索、フィルタリング、お気に入りなど）を
 * 担当する全てのAJAX処理をここにまとめます。
 * template-parts/grant-card-unified.phpとの統一連携版
 *
 * @package Grant_Insight_Perfect
 * @version 3.0.0 - Complete Unified Edition
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * =============================================================================
 * 1. 統一カードレンダリングシステム
 * =============================================================================
 */

/**
 * 統一カードレンダリング関数（最優先：template-parts/grant-card-unified.php使用）
 */
if (!function_exists('gi_render_card_unified')) {
function gi_render_card_unified($post_id, $view = 'grid', $additional_data = []) {
    if (!$post_id || !get_post($post_id)) {
        return gi_render_error_card('投稿が見つかりません');
    }

    // 1. まずテンプレートファイルをチェック（最優先）
    $template_path = get_template_directory() . '/template-parts/grant-card-unified.php';
    if (file_exists($template_path)) {
        return gi_render_via_template($post_id, $view, $template_path, $additional_data);
    }

    // 2. 統一テンプレート関数をチェック
    if (function_exists('render_grant_card_unified')) {
        $user_favorites = gi_get_user_favorites_safe();
        return render_grant_card_unified($post_id, $view, $user_favorites);
    }

    // 3. GrantCardRendererクラスをチェック
    if (class_exists('GrantCardRenderer')) {
        $renderer = GrantCardRenderer::getInstance();
        return $renderer->render($post_id, $view);
    }

    // 4. フォールバック用の内蔵カード
    return gi_render_builtin_card($post_id, $view);
}
} // function_exists の終了

/**
 * テンプレートファイル経由でのレンダリング
 */
function gi_render_via_template($post_id, $view, $template_path, $additional_data = []) {
    // テンプレートで使用する変数を準備
    $grant_data = gi_get_complete_grant_data($post_id);
    $user_favorites = gi_get_user_favorites_safe();
    $current_view = $view;
    
    // 追加データをマージ
    if (!empty($additional_data)) {
        $grant_data = array_merge($grant_data, $additional_data);
    }

    // グローバル$postを一時保存・設定
    global $post;
    $original_post = $post;
    $post = get_post($post_id);
    
    if ($post) {
        setup_postdata($post);
    }

    // テンプレートを読み込み
    ob_start();
    
    // エラーハンドリング付きでinclude
    try {
        include $template_path;
    } catch (Exception $e) {
        ob_end_clean();
        error_log('Grant Insight Template Error: ' . $e->getMessage());
        return gi_render_error_card('テンプレート読み込みエラー');
    } catch (Error $e) {
        ob_end_clean();
        error_log('Grant Insight Template Fatal Error: ' . $e->getMessage());
        return gi_render_error_card('テンプレート実行エラー');
    }
    
    $html = ob_get_clean();

    // $postを復元
    $post = $original_post;
    if ($post) {
        setup_postdata($post);
    } else {
        wp_reset_postdata();
    }

    return !empty($html) ? $html : gi_render_builtin_card($post_id, $view);
}

/**
 * 完全なデータ取得関数（ACF連携強化版）
 */
function gi_get_complete_grant_data($post_id) {
    static $cache = [];
    
    // キャッシュチェック
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    
    $post = get_post($post_id);
    if (!$post) {
        return [];
    }
    
    // 基本データ
    $data = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
        'excerpt' => get_the_excerpt($post_id),
        'content' => get_post_field('post_content', $post_id),
        'date' => get_the_date('Y-m-d', $post_id),
        'modified' => get_the_modified_date('Y-m-d H:i:s', $post_id),
        'status' => get_post_status($post_id),
        'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
    ];

    // ACFフィールドデータ
    $acf_fields = [
        // 基本情報
        'ai_summary' => '',
        'organization' => '',
        'organization_type' => '',
        
        // 金額情報
        'max_amount' => '',
        'max_amount_numeric' => 0,
        'min_amount' => 0,
        'subsidy_rate' => '',
        'amount_note' => '',
        
        // 締切・ステータス
        'deadline' => '',
        'deadline_date' => '',
        'deadline_timestamp' => '',
        'application_status' => 'active',
        'application_period' => '',
        'deadline_note' => '',
        
        // 対象・条件
        'grant_target' => '',
        'eligible_expenses' => '',
        'grant_difficulty' => 'normal',
        'grant_success_rate' => 0,
        'required_documents' => '',
        
        // 申請・連絡先
        'application_method' => 'online',
        'contact_info' => '',
        'official_url' => '',
        'external_link' => '',
        
        // 管理設定
        'is_featured' => false,
        'priority_order' => 100,
        'views_count' => 0,
        'last_updated' => '',
        'admin_notes' => '',
    ];

    foreach ($acf_fields as $field => $default) {
        $value = gi_get_field_safe($field, $post_id, $default);
        $data[$field] = $value;
    }

    // タクソノミーデータ
    $taxonomies = ['grant_category', 'grant_prefecture', 'grant_tag'];
    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        $data[$taxonomy] = [];
        $data[$taxonomy . '_names'] = [];
        
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $data[$taxonomy][] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'link' => get_term_link($term),
                ];
                $data[$taxonomy . '_names'][] = $term->name;
            }
        }
    }

    // 表示用の値を設定
    $data['main_category'] = !empty($data['grant_category_names']) ? $data['grant_category_names'][0] : '';
    $data['prefecture'] = !empty($data['grant_prefecture_names']) ? $data['grant_prefecture_names'][0] : '';
    
    // 金額の表示形式
    $data['amount_formatted'] = gi_format_amount_display($data['max_amount_numeric'], $data['max_amount']);
    
    // 締切日の処理
    if (!empty($data['deadline_date'])) {
        $data['deadline_formatted'] = gi_format_deadline_display($data['deadline_date']);
        $data['deadline_timestamp'] = strtotime($data['deadline_date']);
        $data['days_remaining'] = gi_calculate_days_remaining($data['deadline_date']);
    }
    
    // ステータス表示
    $data['status_display'] = gi_map_status_for_display($data['application_status']);
    
    // 難易度情報
    $data['difficulty_info'] = gi_get_difficulty_info($data['grant_difficulty']);
    
    // キャッシュに保存
    $cache[$post_id] = $data;
    
    return $data;
}

/**
 * 安全なACFフィールド取得
 */
function gi_get_field_safe($field_name, $post_id, $default = '') {
    // ACF関数が利用可能な場合
    if (function_exists('get_field')) {
        $value = get_field($field_name, $post_id);
        if ($value !== false && $value !== null) {
            return $value;
        }
    }
    
    // 通常のメタフィールドから取得
    $value = get_post_meta($post_id, $field_name, true);
    if ($value !== false && $value !== '') {
        return $value;
    }
    
    // ヘルパー関数が利用可能な場合
    if (function_exists('gi_safe_get_meta')) {
        return gi_safe_get_meta($post_id, $field_name, $default);
    }
    
    return $default;
}

/**
 * 安全なお気に入りリスト取得
 */
function gi_get_user_favorites_safe() {
    if (function_exists('gi_get_user_favorites')) {
        return gi_get_user_favorites();
    }
    
    $user_id = get_current_user_id();
    if ($user_id) {
        return get_user_meta($user_id, 'gi_favorites', true) ?: [];
    }
    
    // Cookieから取得
    $cookie_name = 'gi_favorites';
    if (isset($_COOKIE[$cookie_name])) {
        return array_filter(array_map('intval', explode(',', $_COOKIE[$cookie_name])));
    }
    
    return [];
}

/**
 * エラーカード表示
 */
function gi_render_error_card($message) {
    return "
    <div class='grant-card-error' style='
        padding: 20px; 
        background: #fee; 
        border: 1px solid #fcc; 
        border-radius: 4px;
        color: #c33;
    '>
        <p><strong>エラー:</strong> " . esc_html($message) . "</p>
    </div>";
}

/**
 * 内蔵フォールバックカード
 */
function gi_render_builtin_card($post_id, $view = 'grid') {
    $data = gi_get_complete_grant_data($post_id);
    $user_favorites = gi_get_user_favorites_safe();
    $is_favorite = in_array($post_id, $user_favorites);
    
    $title = esc_html($data['title']);
    $permalink = esc_url($data['permalink']);
    $excerpt = esc_html($data['excerpt']);
    $amount = esc_html($data['amount_formatted']);
    $organization = esc_html($data['organization']);
    $status = esc_html($data['status_display']);
    $prefecture = esc_html($data['prefecture']);
    
    if ($view === 'grid') {
        return "
        <div class='grant-card-builtin grant-card-grid' data-post-id='{$post_id}' style='
            background: #fff; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        '>
            <div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;'>
                <span style='background: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;'>
                    {$status}
                </span>
                <button class='favorite-btn' data-post-id='{$post_id}' style='
                    background: none; border: none; color: " . ($is_favorite ? '#e53e3e' : '#ccc') . "; 
                    font-size: 18px; cursor: pointer;
                '>♥</button>
            </div>
            
            <h3 style='margin: 0 0 10px 0; font-size: 16px; line-height: 1.4;'>
                <a href='{$permalink}' style='text-decoration: none; color: #333;'>{$title}</a>
            </h3>
            
            <div style='font-size: 18px; font-weight: bold; color: #10b981; margin-bottom: 10px;'>
                最大 {$amount}
            </div>
            
            <div style='font-size: 12px; color: #666; margin-bottom: 15px;'>
                実施: {$organization} | 地域: {$prefecture}
            </div>
            
            <p style='font-size: 14px; color: #555; line-height: 1.5; margin-bottom: 15px;'>
                {$excerpt}
            </p>
            
            <a href='{$permalink}' style='
                display: block; 
                background: #10b981; 
                color: white; 
                text-align: center; 
                padding: 10px; 
                text-decoration: none; 
                border-radius: 4px;
                font-weight: bold;
            '>詳細を見る</a>
        </div>";
    } else {
        return "
        <div class='grant-card-builtin grant-card-list' data-post-id='{$post_id}' style='
            background: #fff; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        '>
            <div style='flex: 1;'>
                <h3 style='margin: 0 0 5px 0; font-size: 16px;'>
                    <a href='{$permalink}' style='text-decoration: none; color: #333;'>{$title}</a>
                </h3>
                <div style='font-size: 12px; color: #666;'>{$organization}</div>
            </div>
            
            <div style='text-align: center; min-width: 100px;'>
                <div style='font-size: 14px; font-weight: bold; color: #10b981;'>{$amount}</div>
                <div style='font-size: 10px; color: #999;'>{$status}</div>
            </div>
            
            <button class='favorite-btn' data-post-id='{$post_id}' style='
                background: none; border: none; color: " . ($is_favorite ? '#e53e3e' : '#ccc') . "; 
                font-size: 16px; cursor: pointer; padding: 5px;
            '>♥</button>
        </div>";
    }
}

/**
 * =============================================================================
 * 2. メイン検索・フィルタリング AJAX 処理
 * =============================================================================
 */

/**
 * 助成金読み込み処理（完全版・統一カード対応）
 */
function gi_ajax_load_grants() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }

    // ===== パラメータ取得と検証 =====
    $search = sanitize_text_field($_POST['search'] ?? '');
    $categories = json_decode(stripslashes($_POST['categories'] ?? '[]'), true) ?: [];
    $prefectures = json_decode(stripslashes($_POST['prefectures'] ?? '[]'), true) ?: [];
    $tags = json_decode(stripslashes($_POST['tags'] ?? '[]'), true) ?: [];
    $status = json_decode(stripslashes($_POST['status'] ?? '[]'), true) ?: [];
    $difficulty = json_decode(stripslashes($_POST['difficulty'] ?? '[]'), true) ?: [];
    $success_rate = json_decode(stripslashes($_POST['success_rate'] ?? '[]'), true) ?: [];
    
    // 金額・数値フィルター
    $amount = sanitize_text_field($_POST['amount'] ?? '');
    $amount_min = intval($_POST['amount_min'] ?? 0);
    $amount_max = intval($_POST['amount_max'] ?? 0);
    
    // 新しいフィルター項目
    $subsidy_rate = sanitize_text_field($_POST['subsidy_rate'] ?? '');
    $organization = sanitize_text_field($_POST['organization'] ?? '');
    $organization_type = sanitize_text_field($_POST['organization_type'] ?? '');
    $target_business = sanitize_text_field($_POST['target_business'] ?? '');
    $application_method = sanitize_text_field($_POST['application_method'] ?? '');
    $only_featured = sanitize_text_field($_POST['only_featured'] ?? '');
    $deadline_range = sanitize_text_field($_POST['deadline_range'] ?? '');
    
    // 表示・ソート設定
    $sort = sanitize_text_field($_POST['sort'] ?? 'date_desc');
    $view = sanitize_text_field($_POST['view'] ?? 'grid');
    $page = max(1, intval($_POST['page'] ?? 1));
    $posts_per_page = max(6, min(30, intval($_POST['posts_per_page'] ?? 12)));

    // ===== WP_Queryの引数構築 =====
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
        'post_status' => 'publish'
    ];

    // ===== 検索クエリ（拡張版：ACFフィールドも検索対象） =====
    if (!empty($search)) {
        $args['s'] = $search;
        
        // メタフィールドも検索対象に追加
        add_filter('posts_search', function($search_sql, $wp_query) use ($search) {
            global $wpdb;
            
            if (!$wp_query->is_main_query() || empty($search)) {
                return $search_sql;
            }
            
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            
            $meta_search = $wpdb->prepare("
                OR EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm 
                    WHERE pm.post_id = {$wpdb->posts}.ID 
                    AND pm.meta_key IN ('ai_summary', 'organization', 'grant_target', 'eligible_expenses', 'required_documents')
                    AND pm.meta_value LIKE %s
                )
            ", $search_term);
            
            // 既存の検索SQLに追加
            $search_sql = str_replace('))) AND', '))) ' . $meta_search . ' AND', $search_sql);
            return $search_sql;
        }, 10, 2);
    }

    // ===== タクソノミークエリ =====
    $tax_query = ['relation' => 'AND'];
    
    if (!empty($categories)) {
        $tax_query[] = [
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $categories,
            'operator' => 'IN'
        ];
    }
    
    if (!empty($prefectures)) {
        $tax_query[] = [
            'taxonomy' => 'grant_prefecture',
            'field' => 'slug', 
            'terms' => $prefectures,
            'operator' => 'IN'
        ];
    }
    
    if (!empty($tags)) {
        $tax_query[] = [
            'taxonomy' => 'grant_tag',
            'field' => 'slug',
            'terms' => $tags,
            'operator' => 'IN'
        ];
    }
    
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    // ===== メタクエリ（カスタムフィールド） =====
    $meta_query = ['relation' => 'AND'];
    
    // ステータスフィルター
    if (!empty($status)) {
        // UIステータスをDBの値にマッピング
        $db_status = array_map(function($s) {
            return $s === 'active' ? 'open' : ($s === 'upcoming' ? 'upcoming' : $s);
        }, $status);
        
        $meta_query[] = [
            'key' => 'application_status',
            'value' => $db_status,
            'compare' => 'IN'
        ];
    }
    
    // 難易度フィルター
    if (!empty($difficulty)) {
        $meta_query[] = [
            'key' => 'grant_difficulty',
            'value' => $difficulty,
            'compare' => 'IN'
        ];
    }
    
    // 採択率フィルター
    if (!empty($success_rate)) {
        $rate_query = ['relation' => 'OR'];
        
        if (in_array('high', $success_rate, true)) {
            $rate_query[] = [
                'key' => 'grant_success_rate',
                'value' => 70,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
        }
        
        if (in_array('medium', $success_rate, true)) {
            $rate_query[] = [
                'key' => 'grant_success_rate',
                'value' => [50, 69],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
        }
        
        if (in_array('low', $success_rate, true)) {
            $rate_query[] = [
                'key' => 'grant_success_rate',
                'value' => 50,
                'compare' => '<',
                'type' => 'NUMERIC'
            ];
        }
        
        if (count($rate_query) > 1) {
            $meta_query[] = $rate_query;
        }
    }
    
    // 金額フィルター（範囲指定）
    if (!empty($amount) || $amount_min > 0 || $amount_max > 0) {
        if (!empty($amount)) {
            // プリセット金額範囲
            switch ($amount) {
                case '0-100':
                    $meta_query[] = [
                        'key' => 'max_amount_numeric',
                        'value' => 1000000,
                        'compare' => '<=',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '100-500':
                    $meta_query[] = [
                        'key' => 'max_amount_numeric',
                        'value' => [1000000, 5000000],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '500-1000':
                    $meta_query[] = [
                        'key' => 'max_amount_numeric',
                        'value' => [5000000, 10000000],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '1000-3000':
                    $meta_query[] = [
                        'key' => 'max_amount_numeric',
                        'value' => [10000000, 30000000],
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case '3000+':
                    $meta_query[] = [
                        'key' => 'max_amount_numeric',
                        'value' => 30000000,
                        'compare' => '>=',
                        'type' => 'NUMERIC'
                    ];
                    break;
            }
        } else {
            // カスタム金額範囲
            if ($amount_min > 0 && $amount_max > 0) {
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => [$amount_min, $amount_max],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
            } elseif ($amount_min > 0) {
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => $amount_min,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ];
            } elseif ($amount_max > 0) {
                $meta_query[] = [
                    'key' => 'max_amount_numeric',
                    'value' => $amount_max,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                ];
            }
        }
    }
    
    // 補助率フィルター
    if (!empty($subsidy_rate)) {
        $meta_query[] = [
            'key' => 'subsidy_rate',
            'value' => $subsidy_rate,
            'compare' => 'LIKE'
        ];
    }
    
    // 実施組織フィルター
    if (!empty($organization)) {
        $meta_query[] = [
            'key' => 'organization',
            'value' => $organization,
            'compare' => 'LIKE'
        ];
    }
    
    // 組織タイプフィルター
    if (!empty($organization_type)) {
        $meta_query[] = [
            'key' => 'organization_type',
            'value' => $organization_type,
            'compare' => '='
        ];
    }
    
    // 対象事業者フィルター
    if (!empty($target_business)) {
        $meta_query[] = [
            'key' => 'grant_target',
            'value' => $target_business,
            'compare' => 'LIKE'
        ];
    }
    
    // 申請方法フィルター
    if (!empty($application_method)) {
        $meta_query[] = [
            'key' => 'application_method',
            'value' => $application_method,
            'compare' => '='
        ];
    }
    
    // 注目の助成金フィルター
    if ($only_featured === 'true') {
        $meta_query[] = [
            'key' => 'is_featured',
            'value' => '1',
            'compare' => '='
        ];
    }
    
    // 締切日範囲フィルター
    if (!empty($deadline_range)) {
        $current_time = current_time('timestamp');
        
        switch ($deadline_range) {
            case '1week':
                $end_time = strtotime('+1 week', $current_time);
                break;
            case '1month':
                $end_time = strtotime('+1 month', $current_time);
                break;
            case '3months':
                $end_time = strtotime('+3 months', $current_time);
                break;
            case '6months':
                $end_time = strtotime('+6 months', $current_time);
                break;
            default:
                $end_time = null;
        }
        
        if ($end_time) {
            $meta_query[] = [
                'key' => 'deadline_timestamp',
                'value' => [$current_time, $end_time],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
        }
    }
    
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // ===== ソート順 =====
    switch ($sort) {
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
        case 'date_desc':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'amount_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'DESC';
            break;
        case 'amount_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'ASC';
            break;
        case 'deadline_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'deadline_timestamp';
            $args['order'] = 'ASC';
            break;
        case 'deadline_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'deadline_timestamp';
            $args['order'] = 'DESC';
            break;
        case 'success_rate_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'grant_success_rate';
            $args['order'] = 'DESC';
            break;
        case 'success_rate_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'grant_success_rate';
            $args['order'] = 'ASC';
            break;
        case 'priority':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'priority_order';
            $args['order'] = 'ASC';
            break;
        case 'title_asc':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
        case 'featured':
            $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
            $args['meta_key'] = 'is_featured';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
    }

    // ===== クエリ実行 =====
    $query = new WP_Query($args);
    $grants = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // 統一カードレンダリングを使用
            $html = gi_render_card_unified($post_id, $view);

            $grants[] = [
                'id' => $post_id,
                'html' => $html,
                'title' => get_the_title($post_id),
                'permalink' => get_permalink($post_id)
            ];
        }
        wp_reset_postdata();
    }

    // ===== ページネーション生成 =====
    $pagination_html = '';
    if ($query->max_num_pages > 1) {
        ob_start();
        gi_render_ajax_pagination($page, $query->max_num_pages);
        $pagination_html = ob_get_clean();
    }

    // ===== 統計情報 =====
    $stats = [
        'total_found' => $query->found_posts,
        'current_page' => $page,
        'total_pages' => $query->max_num_pages,
        'posts_per_page' => $posts_per_page,
        'showing_from' => (($page - 1) * $posts_per_page) + 1,
        'showing_to' => min($page * $posts_per_page, $query->found_posts),
    ];

    // ===== レスポンス送信 =====
    wp_send_json_success([
        'grants' => $grants,
        'pagination' => [
            'html' => $pagination_html,
            'current_page' => $page,
            'total_pages' => $query->max_num_pages,
            'total_posts' => $query->found_posts,
            'posts_per_page' => $posts_per_page,
        ],
        'stats' => $stats,
        'view' => $view,
        'query_info' => [
            'search' => $search,
            'filters_applied' => !empty($categories) || !empty($prefectures) || !empty($tags) || !empty($status) || !empty($difficulty) || !empty($success_rate) || !empty($amount) || $amount_min > 0 || $amount_max > 0,
            'sort' => $sort,
        ],
        'debug' => defined('WP_DEBUG') && WP_DEBUG ? $args : null,
    ]);
}
add_action('wp_ajax_gi_load_grants', 'gi_ajax_load_grants');
add_action('wp_ajax_nopriv_gi_load_grants', 'gi_ajax_load_grants');

/**
 * AJAX用ページネーション生成
 */
function gi_render_ajax_pagination($current_page, $total_pages) {
    if ($total_pages <= 1) return;
    
    echo '<div class="gi-pagination flex items-center justify-center space-x-2 mt-8">';
    
    // 前のページ
    if ($current_page > 1) {
        echo '<button class="pagination-btn px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-gray-700" data-page="' . ($current_page - 1) . '">';
        echo '<i class="fas fa-chevron-left mr-1"></i>前へ';
        echo '</button>';
    }
    
    // ページ番号
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    // 最初のページ
    if ($start > 1) {
        echo '<button class="pagination-btn px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 transition-colors text-gray-700" data-page="1">1</button>';
        if ($start > 2) {
            echo '<span class="px-2 text-gray-500">...</span>';
        }
    }
    
    // 中間のページ
    for ($i = $start; $i <= $end; $i++) {
        $active_class = ($i === $current_page) ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50';
        echo '<button class="pagination-btn px-4 py-2 border border-gray-300 rounded-lg transition-colors ' . $active_class . '" data-page="' . $i . '">';
        echo $i;
        echo '</button>';
    }
    
    // 最後のページ
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            echo '<span class="px-2 text-gray-500">...</span>';
        }
        echo '<button class="pagination-btn px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 transition-colors text-gray-700" data-page="' . $total_pages . '">' . $total_pages . '</button>';
    }
    
    // 次のページ
    if ($current_page < $total_pages) {
        echo '<button class="pagination-btn px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-gray-700" data-page="' . ($current_page + 1) . '">';
        echo '次へ<i class="fas fa-chevron-right ml-1"></i>';
        echo '</button>';
    }
    
    echo '</div>';
}

/**
 * =============================================================================
 * 3. お気に入り機能
 * =============================================================================
 */

/**
 * お気に入りの追加・削除処理
 */
function gi_ajax_toggle_favorite() {
    $nonce_check1 = wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce');
    $nonce_check2 = wp_verify_nonce($_POST['nonce'] ?? '', 'grant_insight_search_nonce');
    
    if (!$nonce_check1 && !$nonce_check2) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id || !get_post($post_id)) {
        wp_send_json_error('無効な投稿IDです');
    }
    
    $user_id = get_current_user_id();
    
    if ($user_id) {
        // ログインユーザーの場合
        $favorites = get_user_meta($user_id, 'gi_favorites', true) ?: [];
        
        if (in_array($post_id, $favorites)) {
            $favorites = array_diff($favorites, [$post_id]);
            $action = 'removed';
            $is_favorite = false;
        } else {
            $favorites[] = $post_id;
            $action = 'added';
            $is_favorite = true;
        }
        
        update_user_meta($user_id, 'gi_favorites', array_values($favorites));
    } else {
        // 非ログインユーザーの場合（Cookie使用）
        $cookie_name = 'gi_favorites';
        $favorites = isset($_COOKIE[$cookie_name]) ? 
            array_filter(array_map('intval', explode(',', $_COOKIE[$cookie_name]))) : [];
        
        if (in_array($post_id, $favorites)) {
            $favorites = array_diff($favorites, [$post_id]);
            $action = 'removed';
            $is_favorite = false;
        } else {
            $favorites[] = $post_id;
            $action = 'added';
            $is_favorite = true;
        }
        
        // Cookie設定（30日間）
        setcookie($cookie_name, implode(',', $favorites), time() + (86400 * 30), '/');
    }
    
    wp_send_json_success([
        'action' => $action,
        'post_id' => $post_id,
        'post_title' => get_the_title($post_id),
        'is_favorite' => $is_favorite,
        'count' => count($favorites),
        'message' => $action === 'added' ? 'お気に入りに追加しました' : 'お気に入りから削除しました',
        'favorites_list' => $favorites,
    ]);
}
add_action('wp_ajax_gi_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_gi_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_toggle_favorite', 'gi_ajax_toggle_favorite');

/**
 * お気に入り一覧取得
 */
function gi_ajax_get_favorites() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    $user_id = get_current_user_id();
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = 12;
    
    if ($user_id) {
        $favorite_ids = get_user_meta($user_id, 'gi_favorites', true) ?: [];
    } else {
        $cookie_name = 'gi_favorites';
        $favorite_ids = isset($_COOKIE[$cookie_name]) ? 
            array_filter(array_map('intval', explode(',', $_COOKIE[$cookie_name]))) : [];
    }
    
    $favorites = [];
    $total_count = count($favorite_ids);
    
    if (!empty($favorite_ids)) {
        // ページネーション処理
        $offset = ($page - 1) * $per_page;
        $paged_ids = array_slice($favorite_ids, $offset, $per_page);
        
        $args = [
            'post_type' => 'grant',
            'post__in' => $paged_ids,
            'orderby' => 'post__in',
            'posts_per_page' => $per_page,
            'post_status' => 'publish'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $favorites[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
                    'excerpt' => get_the_excerpt(),
                    'html' => gi_render_card_unified($post_id, 'grid'),
                    'date_added' => get_the_date('Y-m-d')
                ];
            }
            wp_reset_postdata();
        }
    }
    
    $total_pages = ceil($total_count / $per_page);
    
    wp_send_json_success([
        'favorites' => $favorites,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_count' => $total_count,
            'per_page' => $per_page,
        ],
        'user_type' => $user_id ? 'logged_in' : 'guest'
    ]);
}
add_action('wp_ajax_gi_get_favorites', 'gi_ajax_get_favorites');
add_action('wp_ajax_nopriv_gi_get_favorites', 'gi_ajax_get_favorites');

/**
 * =============================================================================
 * 4. 検索候補・オートコンプリート
 * =============================================================================
 */

/**
 * 検索候補取得
 */
function gi_ajax_get_search_suggestions() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    $limit = min(10, intval($_POST['limit'] ?? 5));
    
    if (strlen($query) < 2) {
        wp_send_json_success([]);
    }
    
    $suggestions = [];
    
    // 1. 投稿タイトルから検索
    $posts = get_posts([
        's' => $query,
        'post_type' => 'grant',
        'posts_per_page' => $limit,
        'post_status' => 'publish'
    ]);
    
    foreach ($posts as $post) {
        $suggestions[] = [
            'type' => 'grant',
            'label' => $post->post_title,
            'value' => $post->post_title,
            'url' => get_permalink($post->ID),
            'id' => $post->ID
        ];
    }
    
    // 2. 組織名から検索
    if (count($suggestions) < $limit) {
        global $wpdb;
        $organizations = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT meta_value as org_name 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'organization' 
            AND meta_value LIKE %s 
            ORDER BY meta_value 
            LIMIT %d
        ", '%' . $wpdb->esc_like($query) . '%', $limit - count($suggestions)));
        
        foreach ($organizations as $org) {
            $suggestions[] = [
                'type' => 'organization',
                'label' => $org->org_name . ' (実施組織)',
                'value' => $org->org_name,
                'filter' => 'organization'
            ];
        }
    }
    
    // 3. タクソノミーから検索
    if (count($suggestions) < $limit) {
        $taxonomies = ['grant_category', 'grant_prefecture', 'grant_tag'];
        
        foreach ($taxonomies as $taxonomy) {
            if (count($suggestions) >= $limit) break;
            
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'name__like' => $query,
                'number' => $limit - count($suggestions),
                'hide_empty' => true
            ]);
            
            foreach ($terms as $term) {
                $type_label = $taxonomy === 'grant_category' ? 'カテゴリー' : 
                             ($taxonomy === 'grant_prefecture' ? '都道府県' : 'タグ');
                
                $suggestions[] = [
                    'type' => $taxonomy,
                    'label' => $term->name . " ({$type_label})",
                    'value' => $term->name,
                    'slug' => $term->slug,
                    'filter' => $taxonomy
                ];
            }
        }
    }
    
    wp_send_json_success($suggestions);
}
add_action('wp_ajax_gi_get_search_suggestions', 'gi_ajax_get_search_suggestions');
add_action('wp_ajax_nopriv_gi_get_search_suggestions', 'gi_ajax_get_search_suggestions');

/**
 * =============================================================================
 * 5. 関連助成金取得
 * =============================================================================
 */

/**
 * 関連助成金取得
 */
function gi_ajax_get_related_grants() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    $category = sanitize_text_field($_POST['category'] ?? '');
    $prefecture = sanitize_text_field($_POST['prefecture'] ?? '');
    $limit = min(6, intval($_POST['limit'] ?? 3));
    
    if (!$post_id) {
        wp_send_json_error('無効な投稿IDです');
    }
    
    // 関連助成金の検索条件を構築
    $args = [
        'post_type' => 'grant',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'post__not_in' => [$post_id],
        'meta_query' => [
            [
                'key' => 'application_status',
                'value' => 'open',
                'compare' => '='
            ]
        ]
    ];
    
    $tax_query = ['relation' => 'OR'];
    
    // カテゴリーで関連付け
    if (!empty($category)) {
        $category_term = get_term_by('name', $category, 'grant_category');
        if ($category_term) {
            $tax_query[] = [
                'taxonomy' => 'grant_category',
                'field' => 'term_id',
                'terms' => [$category_term->term_id]
            ];
        }
    }
    
    // 都道府県で関連付け
    if (!empty($prefecture)) {
        $prefecture_term = get_term_by('name', $prefecture, 'grant_prefecture');
        if ($prefecture_term) {
            $tax_query[] = [
                'taxonomy' => 'grant_prefecture',
                'field' => 'term_id',
                'terms' => [$prefecture_term->term_id]
            ];
        }
    }
    
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }
    
    $query = new WP_Query($args);
    $related_grants = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $related_post_id = get_the_ID();
            
            $related_grants[] = [
                'id' => $related_post_id,
                'html' => gi_render_card_unified($related_post_id, 'grid'),
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'data' => gi_get_complete_grant_data($related_post_id)
            ];
        }
        wp_reset_postdata();
    }
    
    wp_send_json_success([
        'related_grants' => $related_grants,
        'count' => count($related_grants)
    ]);
}
add_action('wp_ajax_gi_get_related_grants', 'gi_ajax_get_related_grants');
add_action('wp_ajax_nopriv_gi_get_related_grants', 'gi_ajax_get_related_grants');

/**
 * =============================================================================
 * 6. 統計・分析機能
 * =============================================================================
 */

/**
 * 検索統計取得
 */
function gi_ajax_get_search_stats() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    // キャッシュチェック
    $stats = get_transient('gi_search_stats');
    if (false === $stats) {
        
        // 全体統計
        $total_grants = wp_count_posts('grant')->publish;
        
        // ステータス別統計
        $active_grants = get_posts([
            'post_type' => 'grant',
            'meta_query' => [
                [
                    'key' => 'application_status',
                    'value' => 'open',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ]);
        
        // 平均採択率
        global $wpdb;
        $avg_success_rate = $wpdb->get_var("
            SELECT AVG(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'grant_success_rate' 
            AND meta_value > 0 AND meta_value <= 100
        ");
        
        // 平均助成額
        $avg_amount = $wpdb->get_var("
            SELECT AVG(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'max_amount_numeric' 
            AND meta_value > 0
        ");
        
        // カテゴリー別統計
        $categories = get_terms([
            'taxonomy' => 'grant_category',
            'hide_empty' => true,
            'number' => 10
        ]);
        
        $category_stats = [];
        foreach ($categories as $category) {
            $category_stats[] = [
                'name' => $category->name,
                'count' => $category->count,
                'slug' => $category->slug
            ];
        }
        
        // 都道府県別統計
        $prefectures = get_terms([
            'taxonomy' => 'grant_prefecture',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 10
        ]);
        
        $prefecture_stats = [];
        foreach ($prefectures as $prefecture) {
            $prefecture_stats[] = [
                'name' => $prefecture->name,
                'count' => $prefecture->count,
                'slug' => $prefecture->slug
            ];
        }
        
        $stats = [
            'total_grants' => intval($total_grants),
            'active_grants' => count($active_grants),
            'avg_success_rate' => round(floatval($avg_success_rate), 1),
            'avg_amount' => intval($avg_amount),
            'avg_amount_formatted' => gi_format_amount_display(intval($avg_amount)),
            'categories' => $category_stats,
            'prefectures' => $prefecture_stats,
            'last_updated' => current_time('Y-m-d H:i:s')
        ];
        
        // 1時間キャッシュ
        set_transient('gi_search_stats', $stats, 3600);
    }
    
    wp_send_json_success($stats);
}
add_action('wp_ajax_gi_get_search_stats', 'gi_ajax_get_search_stats');
add_action('wp_ajax_nopriv_gi_get_search_stats', 'gi_ajax_get_search_stats');

/**
 * =============================================================================
 * 7. エクスポート・共有機能
 * =============================================================================
 */

/**
 * 検索結果のCSVエクスポート
 */
function gi_ajax_export_search_results() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    // 検索パラメータを取得（gi_ajax_load_grantsと同様）
    $search = sanitize_text_field($_POST['search'] ?? '');
    $categories = json_decode(stripslashes($_POST['categories'] ?? '[]'), true) ?: [];
    $prefectures = json_decode(stripslashes($_POST['prefectures'] ?? '[]'), true) ?: [];
    
    // 検索条件でクエリを実行
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 200, // エクスポート上限
        'post_status' => 'publish'
    ];
    
    if (!empty($search)) {
        $args['s'] = $search;
    }
    
    // タクソノミークエリ
    $tax_query = ['relation' => 'AND'];
    if (!empty($categories)) {
        $tax_query[] = [
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $categories,
            'operator' => 'IN'
        ];
    }
    if (!empty($prefectures)) {
        $tax_query[] = [
            'taxonomy' => 'grant_prefecture',
            'field' => 'slug',
            'terms' => $prefectures,
            'operator' => 'IN'
        ];
    }
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }
    
    $query = new WP_Query($args);
    
    // CSVファイルを生成
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="grant_search_results_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM付きでUTF-8エンコード
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // ヘッダー行
    fputcsv($output, [
        'ID',
        'タイトル',
        'URL',
        '実施組織',
        '最大助成額',
        '締切日',
        'ステータス',
        '都道府県',
        'カテゴリー',
        '採択率',
        '難易度',
        '更新日'
    ]);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $data = gi_get_complete_grant_data($post_id);
            
            fputcsv($output, [
                $post_id,
                get_the_title(),
                get_permalink(),
                $data['organization'],
                $data['amount_formatted'],
                $data['deadline_formatted'],
                $data['status_display'],
                $data['prefecture'],
                $data['main_category'],
                $data['grant_success_rate'] . '%',
                $data['difficulty_info']['label'] ?? $data['grant_difficulty'],
                get_the_modified_date('Y-m-d')
            ]);
        }
        wp_reset_postdata();
    }
    
    fclose($output);
    exit;
}
add_action('wp_ajax_gi_export_search_results', 'gi_ajax_export_search_results');
add_action('wp_ajax_nopriv_gi_export_search_results', 'gi_ajax_export_search_results');

/**
 * =============================================================================
 * 8. ユーティリティ関数群
 * =============================================================================
 */

/**
 * 金額の表示用フォーマット
 */
function gi_format_amount_display($amount_numeric, $amount_text = '') {
    if (function_exists('gi_format_amount_unified')) {
        return gi_format_amount_unified($amount_numeric, $amount_text);
    }
    
    $amount = intval($amount_numeric);
    
    if ($amount >= 100000000) {
        $oku = $amount / 100000000;
        return $oku == floor($oku) ? number_format($oku) . '億円' : number_format($oku, 1) . '億円';
    } elseif ($amount >= 10000) {
        $man = $amount / 10000;
        return $man == floor($man) ? number_format($man) . '万円' : number_format($man, 1) . '万円';
    } elseif ($amount > 0) {
        return number_format($amount) . '円';
    }
    
    return !empty($amount_text) ? $amount_text : '未定';
}

/**
 * 締切日の表示用フォーマット
 */
function gi_format_deadline_display($deadline_date) {
    if (empty($deadline_date)) return '未定';
    
    if (function_exists('gi_format_deadline_for_display')) {
        return gi_format_deadline_for_display($deadline_date);
    }
    
    $timestamp = is_numeric($deadline_date) ? intval($deadline_date) : strtotime($deadline_date);
    if ($timestamp) {
        return date('Y年n月j日', $timestamp);
    }
    
    return $deadline_date;
}


/**
 * handle_ai_search - 復元された関数
 */
function handle_ai_search() {
    // デバッグ: リクエストの確認
    if (!isset($_POST['action'])) {
        wp_send_json_error(['message' => 'アクションが指定されていません', 'debug' => $_POST]);
        return;
    }
    
    // nonceチェックをエラーハンドリング付きで実行（一時的に緩和）
    if (isset($_POST['nonce'])) {
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'gi_ai_search_nonce');
        if (!$nonce_valid) {
            // 一時的に警告のみ
            error_log('AI Search: Nonce verification failed but continuing for debug');
        }
    }
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    $filter = sanitize_text_field($_POST['filter'] ?? 'all');
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    
    // クエリが空の場合も処理を続行（デバッグのため）
    if (empty($query)) {
        wp_send_json_success([
            'grants' => [],
            'count' => 0,
            'ai_response' => '検索キーワードを入力してください。',
            'keywords' => [],
            'session_id' => $session_id ?: wp_generate_uuid4(),
            'debug' => 'Empty query received'
        ]);
        return;
    }
    
    $start_time = microtime(true);
    
    // セッションIDの生成（空の場合）
    if (empty($session_id)) {
        $session_id = wp_generate_uuid4();
    }
    
    // セマンティック検索用のキーワード抽出
    $keywords = gi_extract_keywords($query);
    
    // 高度な検索アルゴリズム実装（セマンティック検索統合）
    $search_params = gi_parse_search_query($query);
    
    // フィルター条件を構築
    $filters = [];
    if ($filter !== 'all') {
        $filters['category'] = $filter;
    }
    
    // セマンティック検索を実行（統合関数使用）
    $search_result = gi_enhanced_semantic_search($query, $filters);
    
    $grants = [];
    $total_count = 0;
    
    if ($search_result['success']) {
        $grants = $search_result['results'];
        $total_count = $search_result['count'];
        
        // セマンティック検索結果を標準化
        $grants = array_map(function($grant) {
            // 既に適切な形式の場合はそのまま返す
            if (isset($grant['similarity']) && isset($grant['relevance'])) {
                return $grant;
            }
            
            // フォールバック検結果の場合はスコアを計算
            return [
                'id' => $grant['id'],
                'title' => $grant['title'],
                'excerpt' => $grant['excerpt'] ?? '',
                'url' => $grant['url'],
                'image_url' => get_the_post_thumbnail_url($grant['id'], 'medium'),
                'deadline' => $grant['deadline'] ?? '',
                'amount' => $grant['amount'] ?? '',
                'categories' => $grant['categories'] ?? [],
                'relevance_score' => $grant['score'] ?? 0.8,
                'similarity_score' => $grant['similarity'] ?? 0.7,
                'is_featured' => get_field('is_featured', $grant['id']) ? true : false,
                'application_status' => get_field('application_status', $grant['id']),
                'organization' => get_field('organization', $grant['id']),
            ];
        }, $grants);
    } else {
        // フォールバック: 従来のWP_Queryを使用
        $args = [
            'post_type' => 'grant',
            'posts_per_page' => 30,
            'post_status' => 'publish'
        ];
        
        // 複合検索クエリの構築
        if (!empty($query)) {
            $args['s'] = $query;
            
            // メタクエリの構築
            $meta_queries = ['relation' => 'OR'];
            
            $search_fields = [
                'grant_description', 'target_business', 'eligibility_requirements',
                'application_process', 'grant_purpose', 'grant_benefits',
                'organization', 'managing_organization', 'support_details'
            ];
            
            foreach ($search_fields as $field) {
                $meta_queries[] = [
                    'key' => $field,
                    'value' => $query,
                    'compare' => 'LIKE'
                ];
            }
            
            // 金額での検索
            if (preg_match('/(\d+)/', $query, $matches)) {
                $amount = intval($matches[1]);
                $meta_queries[] = [
                    'key' => 'max_amount',
                    'value' => $amount,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ];
            }
            
            $args['meta_query'] = $meta_queries;
        }
        
        // フィルター適用
        if ($filter !== 'all') {
            $filter_mapping = [
                'it' => 'it-support',
                'manufacturing' => 'monozukuri',
                'startup' => 'startup-support',
                'sustainability' => 'sustainability',
                'innovation' => 'innovation',
                'employment' => 'employment'
            ];
            
            if (isset($filter_mapping[$filter])) {
                $args['tax_query'] = [[
                    'taxonomy' => 'grant_category',
                    'field' => 'slug',
                    'terms' => $filter_mapping[$filter]
                ]];
            }
        }
        
        $query_obj = new WP_Query($args);
        $grants = [];
        $total_count = $query_obj->found_posts;
        
        if ($query_obj->have_posts()) {
            while ($query_obj->have_posts()) {
                $query_obj->the_post();
                $post_id = get_the_ID();
                
                $relevance_score = gi_calculate_relevance_score($post_id, $keywords, $query);
                
                // 金額の適切な取得と表示形式
                $amount_raw = get_post_meta($post_id, 'max_amount', true) ?: get_post_meta($post_id, 'grant_amount', true);
                $amount_formatted = gi_format_amount_display($amount_raw);
                
                // 締切の適切な取得と表示形式
                $deadline_raw = get_post_meta($post_id, 'deadline', true) ?: get_post_meta($post_id, 'application_deadline', true);
                $deadline_formatted = gi_format_deadline_display($deadline_raw);
                
                $grants[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(), // フロントエンドが期待するフィールド名
                    'url' => get_permalink(), // 互換性のため両方提供
                    'excerpt' => wp_trim_words(get_the_excerpt(), 20),
                    'image_url' => get_the_post_thumbnail_url($post_id, 'medium'),
                    'amount' => $amount_formatted, // フォーマット済み金額
                    'amount_raw' => $amount_raw, // 生の金額データ
                    'deadline' => $deadline_formatted, // フォーマット済み締切
                    'deadline_raw' => $deadline_raw, // 生の締切データ
                    'organization' => get_post_meta($post_id, 'organization', true) ?: get_post_meta($post_id, 'managing_organization', true),
                    'success_rate' => get_post_meta($post_id, 'grant_success_rate', true) ?: get_post_meta($post_id, 'success_rate', true),
                    'featured' => get_post_meta($post_id, 'is_featured', true), // フロントエンド期待値
                    'is_featured' => get_post_meta($post_id, 'is_featured', true), // 互換性
                    'relevance_score' => $relevance_score,
                    'similarity_score' => 0.7,
                    'application_status' => get_post_meta($post_id, 'application_status', true),
                    'categories' => wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names'])
                ];
            }
            wp_reset_postdata();
            
            // 関連性でソート
            usort($grants, function($a, $b) {
                return $b['relevance_score'] - $a['relevance_score'];
            });
        }
    }
    
    $end_time = microtime(true);
    $processing_time = round(($end_time - $start_time) * 1000); // ミリ秒
    
    // AI応答生成
    $ai_response = gi_generate_ai_search_response($query, $grants);
    
    // セッションへの保存とデータベース登録
    gi_save_search_session($session_id, $query, $grants, ['filter' => $filter, 'keywords' => $keywords]);
    gi_log_search_to_database($session_id, $query, $filter, $grants, $keywords);
    
    // 結果をリミット（表示用）
    $display_grants = array_slice($grants, 0, 20);
    
    wp_send_json_success([
        'grants' => $display_grants,
        'count' => $total_count, // フロントエンドが期待するフィールド名
        'total_count' => $total_count, // 互換性
        'display_count' => count($display_grants),
        'ai_response' => $ai_response,
        'keywords' => $keywords,
        'session_id' => $session_id,
        'search_method' => $search_result['method'] ?? 'fallback',
        'processing_time_ms' => $processing_time,
        'ai_suggestions' => gi_generate_search_suggestions($query, $display_grants),
        'debug' => WP_DEBUG ? [
            'query_params' => $search_params,
            'filter' => $filter,
            'semantic_available' => class_exists('GI_Grant_Semantic_Search')
        ] : null
    ]);
}


/**
 * handle_ai_chat_request - 復元された関数
 */
function handle_ai_chat_request() {
    // デバッグ: リクエストの確認
    if (!isset($_POST['action'])) {
        wp_send_json_error(['message' => 'アクションが指定されていません', 'debug' => $_POST]);
        return;
    }
    
    // nonceチェックをエラーハンドリング付きで実行（一時的に緩和）
    if (isset($_POST['nonce'])) {
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'gi_ai_search_nonce');
        if (!$nonce_valid) {
            // 一時的に警告のみ
            error_log('AI Chat: Nonce verification failed but continuing for debug');
        }
    }
    
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    $context = json_decode(stripslashes($_POST['context'] ?? '[]'), true) ?: [];
    
    if (empty($message)) {
        wp_send_json_error(['message' => 'メッセージが空です']);
    }
    
    // セッションIDの生成（空の場合）
    if (empty($session_id)) {
        $session_id = wp_generate_uuid4();
    }
    
    $start_time = microtime(true);
    
    // 意図分析
    $intent = gi_analyze_user_intent($message);
    
    // 会話コンテキスト取得
    $conversation_context = gi_get_conversation_context($session_id, 5); // 直近5件まで
    
    // ユーザーメッセージをデータベースに保存
    gi_save_chat_message($session_id, 'user', $message, $intent['type']);
    
    // 関連助成金の検索（検索関連の意図の場合）
    $related_grants = [];
    if (in_array($intent['type'], ['grant_search', 'deadline_inquiry', 'amount_inquiry', 'eligibility_inquiry'])) {
        $related_grants = gi_find_related_grants($message, $intent);
    }
    
    // AI応答生成（関連助成金を考慮）
    $ai_response = gi_generate_contextual_chat_response($message, $intent, $conversation_context, $related_grants);
    
    $end_time = microtime(true);
    $processing_time = round(($end_time - $start_time) * 1000);
    
    // AI応答をデータベースに保存
    gi_save_chat_message($session_id, 'assistant', $ai_response, $intent['type'], $related_grants, $processing_time);
    
    // フォローアップ質問生成
    $suggestions = gi_generate_contextual_suggestions($intent, $message, $related_grants);
    
    wp_send_json_success([
        'response' => $ai_response,
        'intent' => $intent,
        'related_grants' => $related_grants,
        'suggestions' => $suggestions,
        'session_id' => $session_id,
        'processing_time_ms' => $processing_time,
        'debug' => WP_DEBUG ? [
            'conversation_history_count' => count($conversation_context),
            'intent_confidence' => $intent['confidence'] ?? 0
        ] : null
    ]);
}


/**
 * gi_ajax_search_suggestions - 復元された関数
 */


/**
 * gi_ajax_process_voice_input - 復元された関数
 */
function gi_ajax_process_voice_input() {
    // nonceチェックをエラーハンドリング付きで実行
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gi_ai_search_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $audio_data = $_POST['audio_data'] ?? '';
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    
    if (empty($audio_data)) {
        wp_send_json_error(['message' => '音声データが空です']);
        return;
    }
    
    $start_time = microtime(true);
    
    // 音声認識処理
    $transcribed_text = gi_transcribe_audio($audio_data);
    
    $end_time = microtime(true);
    $processing_time = round(($end_time - $start_time) * 1000);
    
    if (empty($transcribed_text) || $transcribed_text === '申し訳ございません。音声認識に失敗しました。テキストで入力してください。') {
        wp_send_json_error([
            'message' => '音声認識に失敗しました',
            'suggestion' => 'テキストで入力してください',
            'processing_time_ms' => $processing_time
        ]);
        return;
    }
    
    // セッションIDの生成
    if (empty($session_id)) {
        $session_id = wp_generate_uuid4();
    }
    
    // 音声入力をチャット履歴に記録
    gi_save_chat_message($session_id, 'user', $transcribed_text, 'voice_input', null, $processing_time);
    
    wp_send_json_success([
        'transcribed_text' => $transcribed_text,
        'session_id' => $session_id,
        'processing_time_ms' => $processing_time,
        'character_count' => mb_strlen($transcribed_text),
        'debug' => WP_DEBUG ? [
            'audio_data_length' => strlen($audio_data),
            'transcription_method' => class_exists('GI_OpenAI_Integration') ? 'whisper_api' : 'fallback'
        ] : null
    ]);
}


/**
 * gi_generate_ai_search_response - 復元された関数
 */
function gi_generate_ai_search_response($query, $grants) {
    if (empty($grants)) {
        return "申し訳ございませんが、「{$query}」に該当する助成金が見つかりませんでした。検索条件を変更してお試しください。";
    }
    
    $count = count($grants);
    $categories = [];
    $amounts = [];
    $organizations = [];
    $featured_count = 0;
    
    // 結果分析
    foreach ($grants as $grant) {
        if (!empty($grant['categories'])) {
            $categories = array_merge($categories, $grant['categories']);
        }
        if (!empty($grant['amount'])) {
            $amounts[] = $grant['amount'];
        }
        if (!empty($grant['organization'])) {
            $organizations[] = $grant['organization'];
        }
        if (!empty($grant['featured'])) {
            $featured_count++;
        }
    }
    
    $unique_categories = array_unique($categories);
    $unique_orgs = array_unique($organizations);
    
    // 応答テンプレート生成
    $responses = [
        "「{$query}」で{$count}件の助成金が見つかりました。",
        "検索結果として{$count}件の助成金をご提案いたします。"
    ];
    
    $response = $responses[array_rand($responses)];
    
    // 追加情報
    if ($featured_count > 0) {
        $response .= "このうち{$featured_count}件は特におすすめの助成金です。";
    }
    
    if (!empty($unique_categories)) {
        $cats = array_slice($unique_categories, 0, 3);
        $response .= "主なカテゴリーは" . implode('、', $cats) . "などです。";
    }
    
    if (!empty($unique_orgs)) {
        $orgs = array_slice($unique_orgs, 0, 2);
        $response .= "実施組織は" . implode('、', $orgs) . "などがあります。";
    }
    
    $response .= "詳細については各助成金のページをご確認ください。";
    
    return $response;
}


/**
 * gi_analyze_user_intent - 復元された関数
 */
function gi_analyze_user_intent($message) {
    $intent_patterns = [
        'grant_search' => [
            'keywords' => ['助成金', '補助金', '支援金', '給付金', '探している', '検索', '見つけたい'],
            'priority' => 10
        ],
        'deadline_inquiry' => [
            'keywords' => ['締切', '期限', 'いつまで', 'デッドライン', '申請期限'],
            'priority' => 9
        ],
        'amount_inquiry' => [
            'keywords' => ['金額', 'いくら', '最大', '上限', '費用'],
            'priority' => 8
        ],
        'process_inquiry' => [
            'keywords' => ['申請', '手続き', 'やり方', '方法', '流れ', '必要書類'],
            'priority' => 7
        ],
        'eligibility_inquiry' => [
            'keywords' => ['対象', '条件', '資格', '要件', '対象者'],
            'priority' => 7
        ],
        'success_rate_inquiry' => [
            'keywords' => ['採択率', '成功率', '確率', '通る'],
            'priority' => 6
        ],
        'general_question' => [
            'keywords' => ['教えて', '知りたい', '聞きたい', 'について'],
            'priority' => 3
        ]
    ];
    
    $detected_intents = [];
    
    foreach ($intent_patterns as $intent_type => $pattern) {
        $matches = 0;
        foreach ($pattern['keywords'] as $keyword) {
            if (mb_stripos($message, $keyword) !== false) {
                $matches++;
            }
        }
        
        if ($matches > 0) {
            $detected_intents[] = [
                'type' => $intent_type,
                'confidence' => $matches * $pattern['priority'],
                'matches' => $matches
            ];
        }
    }
    
    // 信頼度でソート
    usort($detected_intents, function($a, $b) {
        return $b['confidence'] - $a['confidence'];
    });
    
    return !empty($detected_intents) ? $detected_intents[0] : [
        'type' => 'unknown',
        'confidence' => 0,
        'matches' => 0
    ];
}


/**
 * gi_find_related_grants - 復元された関数
 */
function gi_find_related_grants($message, $intent) {
    // キーワード抽出
    $keywords = gi_extract_keywords($message);
    
    // 意図に基づく検索条件の調整
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 5,
        'post_status' => 'publish'
    ];
    
    // メタクエリ構築
    $meta_query = ['relation' => 'AND'];
    
    // 募集中のもの優先
    $meta_query[] = [
        'key' => 'application_status',
        'value' => 'open',
        'compare' => '='
    ];
    
    // 意図別の条件追加
    switch ($intent['type']) {
        case 'deadline_inquiry':
            // 締切が近いものを優先
            $args['orderby'] = 'meta_value';
            $args['meta_key'] = 'deadline_timestamp';
            $args['order'] = 'ASC';
            break;
            
        case 'amount_inquiry':
            // 金額が高いものを優先
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'DESC';
            break;
            
        case 'success_rate_inquiry':
            // 採択率が高いものを優先
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'grant_success_rate';
            $args['order'] = 'DESC';
            break;
            
        default:
            // デフォルトは注目度順
            $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
            $args['meta_key'] = 'is_featured';
    }
    
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    
    // キーワードがある場合は検索に含める
    if (!empty($keywords)) {
        $args['s'] = implode(' ', $keywords);
    }
    
    $query = new WP_Query($args);
    $related_grants = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            $related_grants[] = [
                'id' => $post_id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'amount' => gi_safe_get_meta($post_id, 'max_amount', '未定'),
                'deadline' => gi_safe_get_meta($post_id, 'deadline', '随時'),
                'organization' => gi_safe_get_meta($post_id, 'organization', ''),
                'success_rate' => gi_safe_get_meta($post_id, 'grant_success_rate', 0),
                'categories' => wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names'])
            ];
        }
        wp_reset_postdata();
    }
    
    return $related_grants;
}


/**
 * gi_transcribe_audio - 復元された関数
 */
function gi_transcribe_audio($audio_data) {
    // OpenAI Whisper API を使用した音声認識
    $openai = GI_OpenAI_Integration::getInstance();
    
    if (!$openai || !method_exists($openai, 'transcribe_audio')) {
        // フォールバック：基本的な処理
        return gi_fallback_audio_transcription($audio_data);
    }
    
    try {
        return $openai->transcribe_audio($audio_data);
    } catch (Exception $e) {
        error_log('Audio transcription error: ' . $e->getMessage());
        return false;
    }
}


/**
 * gi_get_popular_searches - 復元された関数
 */
function gi_get_popular_searches($limit = 10) {
    global $wpdb;
    
    $cache_key = 'gi_popular_searches_' . $limit;
    $popular = wp_cache_get($cache_key);
    
    if (false === $popular) {
        $table = $wpdb->prefix . 'gi_search_history';
        
        // テーブル存在確認
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            // フォールバック：人気キーワードのハードコード
            return [
                'ものづくり補助金',
                'IT導入補助金',
                '小規模事業者持続化補助金',
                '事業再構築補助金',
                '創業支援',
                'DX推進',
                '設備投資',
                '人材育成'
            ];
        }
        
        $popular = $wpdb->get_col($wpdb->prepare("
            SELECT search_query 
            FROM {$table} 
            WHERE search_query != '' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_query 
            ORDER BY COUNT(*) DESC 
            LIMIT %d
        ", $limit));
        
        // キャッシュ（1時間）
        wp_cache_set($cache_key, $popular, '', 3600);
    }
    
    return $popular ?: [];
}








/**
 * gi_save_search_session - 復元された関数
 */
function gi_save_search_session($session_id, $query, $grants = []) {
    $session_data = [
        'query' => $query,
        'results' => array_slice($grants, 0, 10), // 最初の10件のみ保存
        'timestamp' => current_time('timestamp'),
        'user_id' => get_current_user_id() ?: null
    ];
    
    // セッションデータをキャッシュに保存（1時間）
    wp_cache_set('gi_session_' . $session_id, $session_data, 'gi_search_sessions', 3600);
    
    // データベースにも保存
    return gi_log_search_to_database($session_id, $query, 'ai_search', $grants, []);
}


/**
 * gi_get_conversation_context - 復元された関数
 */
function gi_get_conversation_context($session_id, $limit = 10) {
    $cache_key = 'gi_conversation_' . $session_id;
    $context = wp_cache_get($cache_key);
    
    if (false === $context) {
        // データベースから履歴取得
        global $wpdb;
        $table = $wpdb->prefix . 'gi_chat_history';
        
        // テーブル存在確認
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT message_type, message_content, created_at 
                FROM {$table} 
                WHERE session_id = %s 
                ORDER BY created_at DESC 
                LIMIT %d
            ", $session_id, $limit));
            
            $context = [];
            foreach (array_reverse($results) as $row) {
                $context[] = [
                    'type' => $row->message_type,
                    'message' => $row->message_content,
                    'timestamp' => strtotime($row->created_at)
                ];
            }
        } else {
            $context = [];
        }
    }
    
    return $context ?: [];
}


/**
 * gi_save_chat_message - 復元された関数
 */
function gi_save_chat_message($session_id, $message_type, $message, $intent = null, $related_grants = null, $response_time = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_chat_history';
    
    // テーブルが存在するか確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    if (!$table_exists) {
        gi_create_chat_tables();
    }
    
    $data = [
        'session_id' => $session_id,
        'user_id' => get_current_user_id() ?: null,
        'message_type' => $message_type, // 'user' or 'assistant'
        'message' => $message,
        'intent' => $intent,
        'related_grants' => is_array($related_grants) ? json_encode($related_grants) : $related_grants,
        'response_time_ms' => $response_time,
        'created_at' => current_time('mysql')
    ];
    
    return $wpdb->insert($table, $data);
}


/**
 * gi_create_chat_tables - 復元された関数
 */
function gi_create_chat_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gi_chat_history (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        message_type varchar(20) NOT NULL,
        message_content text NOT NULL,
        intent_data text DEFAULT NULL,
        related_grants text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at),
        KEY message_type (message_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


/**
 * gi_create_search_tables - 復元された関数
 */
function gi_create_search_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gi_search_history (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        search_query text NOT NULL,
        search_filter varchar(50) DEFAULT NULL,
        results_count int(11) DEFAULT 0,
        clicked_results text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


/**
 * gi_log_search_to_database - 復元された関数
 */
function gi_log_search_to_database($session_id, $query, $filter, $grants, $keywords) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_search_history';
    
    // テーブルが存在するか確認、なければ作成
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    if (!$table_exists) {
        gi_create_search_tables();
    }
    
    // 検索履歴を保存
    $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'search_query' => $query,
            'search_filter' => $filter,
            'results_count' => count($grants),
            'clicked_results' => null, // 後でクリック時に更新
            'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%s', '%s', '%d', '%s', '%s']
    );
    
    return $wpdb->insert_id;
}


/**
 * gi_extract_keywords - 復元された関数
 */
function gi_extract_keywords($query) {
    // 重要キーワードのマッピング
    $keyword_map = [
        'IT' => ['IT', 'デジタル', 'システム', 'ソフトウェア', 'DX'],
        'ものづくり' => ['製造', '工場', '設備', '機械', '生産'],
        '創業' => ['創業', '起業', 'スタートアップ', '開業', '新規事業'],
        '持続化' => ['持続化', '継続', '事業継続', '小規模'],
        '再構築' => ['再構築', '転換', '新分野', '業態転換']
    ];
    
    $keywords = [];
    foreach ($keyword_map as $category => $terms) {
        foreach ($terms as $term) {
            if (mb_stripos($query, $term) !== false) {
                $keywords[] = $category;
                break;
            }
        }
    }
    
    return array_unique($keywords);
}


/**
 * =============================================================================
 * 9. 補助金AI アシスタント機能強化
 * =============================================================================
 */

/**
 * 補助金固有のAIアシスタント機能
 */
function gi_ajax_grant_assistant() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    $grant_id = intval($_POST['grant_id'] ?? 0);
    $question_type = sanitize_text_field($_POST['question_type'] ?? '');
    $custom_question = sanitize_textarea_field($_POST['custom_question'] ?? '');
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    
    if (!$grant_id || !get_post($grant_id)) {
        wp_send_json_error('無効な補助金IDです');
    }
    
    // セッションID生成
    if (empty($session_id)) {
        $session_id = wp_generate_uuid4();
    }
    
    $start_time = microtime(true);
    
    // 補助金データ取得
    $grant_data = gi_get_complete_grant_data($grant_id);
    
    // 質問タイプに応じた応答生成
    $response = '';
    $suggestions = [];
    
    switch ($question_type) {
        case 'overview':
            $response = gi_generate_grant_overview($grant_data);
            $suggestions = [
                '申請手順を教えて',
                '必要書類は何ですか？',
                '締切はいつですか？',
                '採択のコツを教えて'
            ];
            break;
            
        case 'requirements':
            $response = gi_generate_requirements_info($grant_data);
            $suggestions = [
                '申請資格を詳しく教えて',
                'どんな費用が対象？',
                '申請書の書き方',
                '審査基準について'
            ];
            break;
            
        case 'process':
            $response = gi_generate_process_info($grant_data);
            $suggestions = [
                '申請スケジュール',
                '必要な準備期間',
                '審査期間はどのくらい？',
                '採択後の手続き'
            ];
            break;
            
        case 'tips':
            $response = gi_generate_success_tips($grant_data);
            $suggestions = [
                '採択率を上げる方法',
                '申請書のポイント',
                '審査員の視点',
                '失敗例を教えて'
            ];
            break;
            
        case 'custom':
            $response = gi_generate_custom_response($grant_data, $custom_question);
            $suggestions = gi_generate_contextual_suggestions_for_grant($grant_data, $custom_question);
            break;
            
        default:
            $response = gi_generate_grant_overview($grant_data);
            $suggestions = [
                '申請について詳しく',
                '対象要件を教えて',
                '申請手順を知りたい',
                '成功のコツは？'
            ];
    }
    
    $end_time = microtime(true);
    $processing_time = round(($end_time - $start_time) * 1000);
    
    // チャット履歴保存
    $user_question = $question_type === 'custom' ? $custom_question : gi_get_question_text($question_type);
    gi_save_chat_message($session_id, 'user', $user_question, $question_type, [$grant_id]);
    gi_save_chat_message($session_id, 'assistant', $response, $question_type, [$grant_id], $processing_time);
    
    wp_send_json_success([
        'response' => $response,
        'suggestions' => $suggestions,
        'grant_info' => [
            'id' => $grant_id,
            'title' => $grant_data['title'],
            'amount' => $grant_data['amount_formatted'],
            'deadline' => $grant_data['deadline_formatted'],
            'organization' => $grant_data['organization']
        ],
        'session_id' => $session_id,
        'processing_time_ms' => $processing_time,
        'question_type' => $question_type
    ]);
}
add_action('wp_ajax_gi_grant_assistant', 'gi_ajax_grant_assistant');
add_action('wp_ajax_nopriv_gi_grant_assistant', 'gi_ajax_grant_assistant');


/**
 * 強化された検索提案生成
 */
function gi_generate_search_suggestions($query, $grants = []) {
    $suggestions = [];
    
    // 検索結果に基づく提案
    if (!empty($grants)) {
        $categories = [];
        $organizations = [];
        $amounts = [];
        
        foreach ($grants as $grant) {
            if (!empty($grant['categories'])) {
                $categories = array_merge($categories, $grant['categories']);
            }
            if (!empty($grant['organization'])) {
                $organizations[] = $grant['organization'];
            }
            if (!empty($grant['amount'])) {
                $amounts[] = $grant['amount'];
            }
        }
        
        $unique_categories = array_unique($categories);
        $unique_orgs = array_unique($organizations);
        
        // カテゴリー別の深掘り提案
        if (!empty($unique_categories)) {
            $category = $unique_categories[0];
            $suggestions[] = $category . '以外の補助金も見つけて';
            $suggestions[] = $category . 'の申請方法を教えて';
        }
        
        // 組織別の提案
        if (!empty($unique_orgs)) {
            $org = $unique_orgs[0];
            $suggestions[] = $org . 'の他の補助金は？';
        }
        
        // 金額に関する提案
        if (count($grants) > 1) {
            $suggestions[] = 'より高額な補助金を探して';
            $suggestions[] = '申請しやすい補助金はどれ？';
        }
        
        // 締切に関する提案
        $suggestions[] = '締切が近い補助金を優先して';
        $suggestions[] = '採択率が高い補助金を教えて';
    }
    
    // クエリに基づく一般的な提案
    if (mb_stripos($query, 'IT') !== false || mb_stripos($query, 'デジタル') !== false) {
        $suggestions[] = 'DX推進に使える補助金は？';
        $suggestions[] = 'システム導入の補助金を教えて';
        $suggestions[] = 'IT導入補助金の申請条件は？';
    }
    
    if (mb_stripos($query, 'ものづくり') !== false || mb_stripos($query, '製造') !== false) {
        $suggestions[] = '設備投資の補助金を探して';
        $suggestions[] = 'ものづくり補助金の採択のコツは？';
        $suggestions[] = '製造業向けの支援制度は？';
    }
    
    if (mb_stripos($query, '創業') !== false || mb_stripos($query, '起業') !== false) {
        $suggestions[] = 'スタートアップ向けの補助金は？';
        $suggestions[] = '創業時に使える支援制度';
        $suggestions[] = '起業資金の調達方法を教えて';
    }
    
    // デフォルト提案
    if (empty($suggestions)) {
        $suggestions = [
            '申請しやすい補助金を教えて',
            '締切が近い補助金は？',
            '高額な補助金を探して',
            '採択率の高い補助金は？',
            '必要書類が少ない補助金は？'
        ];
    }
    
    // 重複除去と上限設定
    $suggestions = array_unique($suggestions);
    return array_slice($suggestions, 0, 5);
}


/**
 * 補助金概要生成
 */
function gi_generate_grant_overview($grant_data) {
    $title = $grant_data['title'] ?? '補助金';
    $amount = $grant_data['amount_formatted'] ?? '未定';
    $organization = $grant_data['organization'] ?? '';
    $deadline = $grant_data['deadline_formatted'] ?? '随時';
    $target = $grant_data['grant_target'] ?? '';
    $summary = $grant_data['ai_summary'] ?? '';
    
    $overview = "【{$title}】の概要をご説明します。\n\n";
    
    // 基本情報
    $overview .= "💰 **助成額**: 最大{$amount}\n";
    $overview .= "🏢 **実施組織**: {$organization}\n";
    $overview .= "📅 **申請締切**: {$deadline}\n\n";
    
    // 対象者・対象事業
    if (!empty($target)) {
        $overview .= "🎯 **対象者**: {$target}\n\n";
    }
    
    // AI要約がある場合
    if (!empty($summary)) {
        $overview .= "📋 **概要**: {$summary}\n\n";
    }
    
    // 次のステップ提案
    $overview .= "詳細な申請要件や手順について、お気軽にお聞きください。";
    
    return $overview;
}


/**
 * 申請要件情報生成
 */
function gi_generate_requirements_info($grant_data) {
    $title = $grant_data['title'] ?? '補助金';
    $target = $grant_data['grant_target'] ?? '';
    $eligible_expenses = $grant_data['eligible_expenses'] ?? '';
    $amount_note = $grant_data['amount_note'] ?? '';
    $difficulty = $grant_data['difficulty_info']['label'] ?? $grant_data['grant_difficulty'];
    
    $info = "【{$title}】の申請要件をご案内します。\n\n";
    
    // 対象者
    if (!empty($target)) {
        $info .= "👥 **申請対象者**\n";
        $info .= $target . "\n\n";
    }
    
    // 対象経費
    if (!empty($eligible_expenses)) {
        $info .= "💸 **対象となる経費**\n";
        $info .= $eligible_expenses . "\n\n";
    }
    
    // 助成額の詳細
    if (!empty($amount_note)) {
        $info .= "💰 **助成額の詳細**\n";
        $info .= $amount_note . "\n\n";
    }
    
    // 難易度
    if (!empty($difficulty)) {
        $info .= "⭐ **申請難易度**: {$difficulty}\n\n";
    }
    
    $info .= "具体的な申請書類や手続きについて詳しく知りたい場合は、お聞かせください。";
    
    return $info;
}


/**
 * 申請プロセス情報生成
 */
function gi_generate_process_info($grant_data) {
    $title = $grant_data['title'] ?? '補助金';
    $application_method = $grant_data['application_method'] ?? '';
    $required_documents = $grant_data['required_documents'] ?? '';
    $deadline = $grant_data['deadline_formatted'] ?? '随時';
    $contact_info = $grant_data['contact_info'] ?? '';
    
    $info = "【{$title}】の申請プロセスをご案内します。\n\n";
    
    // 申請方法
    if (!empty($application_method)) {
        $method_text = $application_method === 'online' ? 'オンライン申請' : 
                      ($application_method === 'paper' ? '書面申請' : $application_method);
        $info .= "📝 **申請方法**: {$method_text}\n\n";
    }
    
    // 必要書類
    if (!empty($required_documents)) {
        $info .= "📄 **必要書類**\n";
        $info .= $required_documents . "\n\n";
    }
    
    // 締切
    $info .= "⏰ **申請締切**: {$deadline}\n\n";
    
    // 申請の流れ（一般的な流れを提示）
    $info .= "📋 **一般的な申請の流れ**\n";
    $info .= "1. 申請要件の確認\n";
    $info .= "2. 必要書類の準備\n";
    $info .= "3. 申請書の作成・提出\n";
    $info .= "4. 審査・採択通知\n";
    $info .= "5. 交付決定・事業開始\n";
    $info .= "6. 実績報告・確定検査\n\n";
    
    // 問い合わせ先
    if (!empty($contact_info)) {
        $info .= "📞 **問い合わせ先**: {$contact_info}\n\n";
    }
    
    $info .= "各ステップの詳細や注意点について、さらに詳しくお聞かせください。";
    
    return $info;
}


/**
 * 成功のコツ生成
 */
function gi_generate_success_tips($grant_data) {
    $title = $grant_data['title'] ?? '補助金';
    $success_rate = $grant_data['grant_success_rate'] ?? 0;
    $difficulty = $grant_data['grant_difficulty'] ?? 'normal';
    
    $tips = "【{$title}】採択のコツをお教えします。\n\n";
    
    // 採択率情報
    if ($success_rate > 0) {
        $tips .= "📊 **採択率**: 約{$success_rate}%\n\n";
        
        if ($success_rate >= 70) {
            $tips .= "✅ この補助金は比較的採択率が高く、要件を満たせば採択の可能性が高いです。\n\n";
        } elseif ($success_rate >= 50) {
            $tips .= "⚡ 中程度の競争率です。しっかりとした申請書作成が重要です。\n\n";
        } else {
            $tips .= "🔥 競争が激しい補助金です。特に丁寧な申請書作成が必要です。\n\n";
        }
    }
    
    // 難易度別アドバイス
    $tips .= "💡 **成功のポイント**\n";
    
    if ($difficulty === 'easy') {
        $tips .= "• 基本的な要件を確実に満たす\n";
        $tips .= "• 必要書類の漏れがないようチェック\n";
        $tips .= "• 申請期限に余裕を持って提出\n";
    } elseif ($difficulty === 'hard') {
        $tips .= "• 事業計画書の緻密な作成\n";
        $tips .= "• 専門家によるレビューを推奨\n";
        $tips .= "• 過去の採択事例を参考にする\n";
        $tips .= "• 早めの準備開始（2-3ヶ月前から）\n";
    } else {
        $tips .= "• 事業の必要性を明確に示す\n";
        $tips .= "• 具体的で実現可能な計画を作成\n";
        $tips .= "• 経費の妥当性を説明\n";
        $tips .= "• 事業効果を数値で示す\n";
    }
    
    $tips .= "\n🎯 **よくある失敗例**\n";
    $tips .= "• 要件の見落とし\n";
    $tips .= "• 書類の不備・記載ミス\n";
    $tips .= "• 事業計画の具体性不足\n";
    $tips .= "• 締切直前の慌てた申請\n\n";
    
    $tips .= "具体的な申請書作成のポイントについて、さらに詳しくお聞きください。";
    
    return $tips;
}


/**
 * カスタム質問応答生成
 */
function gi_generate_custom_response($grant_data, $question) {
    $title = $grant_data['title'] ?? '補助金';
    
    // キーワードベースの応答
    if (mb_stripos($question, '採択率') !== false || mb_stripos($question, '成功率') !== false) {
        return gi_generate_success_tips($grant_data);
    }
    
    if (mb_stripos($question, '必要書類') !== false || mb_stripos($question, '書類') !== false) {
        return gi_generate_requirements_info($grant_data);
    }
    
    if (mb_stripos($question, '申請') !== false && mb_stripos($question, '流れ') !== false) {
        return gi_generate_process_info($grant_data);
    }
    
    if (mb_stripos($question, '締切') !== false || mb_stripos($question, '期限') !== false) {
        $deadline = $grant_data['deadline_formatted'] ?? '随時';
        return "【{$title}】の申請締切は{$deadline}です。\n\n申請には十分な準備期間が必要ですので、早めの準備をおすすめします。申請手続きの詳細についてもお聞きください。";
    }
    
    if (mb_stripos($question, '金額') !== false || mb_stripos($question, '助成額') !== false) {
        $amount = $grant_data['amount_formatted'] ?? '未定';
        $amount_note = $grant_data['amount_note'] ?? '';
        $response = "【{$title}】の助成額は最大{$amount}です。";
        if (!empty($amount_note)) {
            $response .= "\n\n" . $amount_note;
        }
        return $response;
    }
    
    // デフォルト応答
    return "【{$title}】について、ご質問「{$question}」にお答えします。\n\n申し訳ございませんが、より具体的な質問をしていただけると、詳しい情報をお提供できます。\n\n例えば：\n• 申請要件について\n• 必要書類について\n• 申請の流れについて\n• 採択のコツについて\n\nどの点について詳しく知りたいですか？";
}


/**
 * 補助金固有のコンテキスト提案生成
 */
function gi_generate_contextual_suggestions_for_grant($grant_data, $question = '') {
    $suggestions = [];
    
    // 補助金データの分析
    $grant_title = $grant_data['title'] ?? '';
    $grant_amount = $grant_data['amount'] ?? '';
    $grant_deadline = $grant_data['deadline'] ?? '';
    $grant_organization = $grant_data['organization'] ?? '';
    
    // 質問内容に基づく高度な提案
    if (!empty($question)) {
        // 申請関連の質問
        if (mb_stripos($question, '申請') !== false || mb_stripos($question, '手順') !== false) {
            $suggestions = [
                '必要書類は具体的に何ですか？',
                '申請から結果までのスケジュール',
                'オンライン申請は可能ですか？',
                '申請書記入のコツを教えて'
            ];
        }
        // 採択・審査関連
        elseif (mb_stripos($question, '採択') !== false || mb_stripos($question, '審査') !== false) {
            $suggestions = [
                '採択率を上げる具体的な方法',
                '審査員が重視するポイント',
                '不採択の主な理由とは？',
                '過去の採択事例を教えて'
            ];
        }
        // 金額・費用関連
        elseif (mb_stripos($question, '金額') !== false || mb_stripos($question, '費用') !== false || mb_stripos($question, '経費') !== false) {
            $suggestions = [
                '補助対象となる経費の詳細',
                '補助率と上限額について',
                '自己負担はどのくらい？',
                '概算要求書の書き方'
            ];
        }
        // 締切・期限関連
        elseif (mb_stripos($question, '締切') !== false || mb_stripos($question, '期限') !== false) {
            $suggestions = [
                '申請準備にはどのくらい時間が必要？',
                '締切間近でも申請できる？',
                '次回の募集予定は？',
                '中間報告の期限について'
            ];
        }
        // 書類・準備関連
        elseif (mb_stripos($question, '書類') !== false || mb_stripos($question, '準備') !== false) {
            $suggestions = [
                '事業計画書の書き方のコツ',
                '見積書はいつまでに必要？',
                '専門家の推薦状は必要？',
                '添付書類の注意点'
            ];
        }
        // 要件・条件関連
        elseif (mb_stripos($question, '要件') !== false || mb_stripos($question, '条件') !== false || mb_stripos($question, '対象') !== false) {
            $suggestions = [
                '従業員数の制限はある？',
                '売上要件について詳しく',
                '業種の制限について',
                '地域限定の条件は？'
            ];
        }
    }
    
    // 補助金タイトルから推測される関連質問
    if (empty($suggestions)) {
        // IT系の補助金
        if (mb_stripos($grant_title, 'IT') !== false || mb_stripos($grant_title, 'デジタル') !== false) {
            $suggestions = [
                'どんなITツールが対象？',
                'クラウドサービスも対象？',
                'システム導入の流れは？',
                'IT導入支援事業者の選び方'
            ];
        }
        // ものづくり系
        elseif (mb_stripos($grant_title, 'ものづくり') !== false || mb_stripos($grant_title, '設備') !== false) {
            $suggestions = [
                '対象となる設備の種類',
                '中古設備も対象になる？',
                '設備導入の条件は？',
                'リースでも補助対象？'
            ];
        }
        // 創業・起業系
        elseif (mb_stripos($grant_title, '創業') !== false || mb_stripos($grant_title, '起業') !== false) {
            $suggestions = [
                '開業してからでも申請可能？',
                '個人事業主も対象？',
                '事業計画の審査ポイント',
                '創業後のフォローアップ'
            ];
        }
        // 持続化系
        elseif (mb_stripos($grant_title, '持続化') !== false) {
            $suggestions = [
                '販路開拓の具体例は？',
                'ホームページ制作も対象？',
                '広告宣伝費の上限は？',
                '複数回申請は可能？'
            ];
        }
        // 雇用関連
        elseif (mb_stripos($grant_title, '雇用') !== false || mb_stripos($grant_title, '人材') !== false) {
            $suggestions = [
                '助成対象の雇用条件',
                '研修費用も対象？',
                '雇用継続の義務期間',
                '対象となる職種は？'
            ];
        }
    }
    
    // デフォルト提案（補助金に関する一般的な質問）
    if (empty($suggestions)) {
        $suggestions = [
            'この補助金の最大のメリットは？',
            '申請の難易度レベルは？',
            '類似する他の補助金との違い',
            '申請前の準備チェックリスト',
            '専門家のサポートは必要？'
        ];
    }
    
    // 補助金の特性に応じた追加提案
    $contextual_suggestions = [];
    
    // 金額情報がある場合
    if (!empty($grant_amount) && $grant_amount !== '未定') {
        $contextual_suggestions[] = "最大{$grant_amount}の活用方法";
    }
    
    // 締切情報がある場合
    if (!empty($grant_deadline) && $grant_deadline !== '随時') {
        $contextual_suggestions[] = "{$grant_deadline}までの申請戦略";
    }
    
    // 実施機関の情報がある場合
    if (!empty($grant_organization)) {
        $contextual_suggestions[] = "{$grant_organization}への問い合わせ方法";
    }
    
    // 基本提案と文脈提案を組み合わせ
    $all_suggestions = array_merge($suggestions, $contextual_suggestions);
    
    // 重複を除去し、最大6つまで返す
    $unique_suggestions = array_unique($all_suggestions);
    
    return array_slice($unique_suggestions, 0, 6);
}


/**
 * 質問タイプのテキスト取得
 */
function gi_get_question_text($question_type) {
    $question_texts = [
        'overview' => 'この補助金の概要を教えて',
        'requirements' => '申請要件について教えて',
        'process' => '申請の流れを教えて',
        'tips' => '採択のコツを教えて'
    ];
    
    return $question_texts[$question_type] ?? '補助金について教えて';
}


/**
 * =============================================================================
 * 10. 追加のユーティリティ関数群
 * =============================================================================
 */

/**
 * 安全なメタフィールド取得（フォールバック対応）
 */
if (!function_exists('gi_safe_get_meta')) {
    function gi_safe_get_meta($post_id, $key, $default = '') {
        $value = get_post_meta($post_id, $key, true);
        return !empty($value) ? $value : $default;
    }
}

/**
 * 残り日数計算（重複宣言エラー対策）
 */
if (!function_exists('gi_calculate_days_remaining')) {
    function gi_calculate_days_remaining($deadline_date) {
        if (empty($deadline_date)) return null;
        
        $deadline = is_numeric($deadline_date) ? intval($deadline_date) : strtotime($deadline_date);
        if (!$deadline) return null;
        
        $today = current_time('timestamp');
        $diff = $deadline - $today;
        
        return max(0, floor($diff / (24 * 60 * 60)));
    }
}

/**
 * ステータス表示マッピング
 */
function gi_map_status_for_display($status) {
    $status_map = [
        'open' => '募集中',
        'closed' => '締切済み',
        'upcoming' => '募集予定',
        'suspended' => '一時停止'
    ];
    
    return $status_map[$status] ?? '未定';
}

/**
 * 難易度情報取得
 */
function gi_get_difficulty_info($difficulty) {
    $difficulty_map = [
        'easy' => [
            'label' => '易しい',
            'description' => '基本要件を満たせば採択されやすい',
            'color' => '#10b981'
        ],
        'normal' => [
            'label' => '普通',
            'description' => '一般的な競争率',
            'color' => '#f59e0b'
        ],
        'hard' => [
            'label' => '難しい',
            'description' => '競争が激しく、十分な準備が必要',
            'color' => '#ef4444'
        ]
    ];
    
    return $difficulty_map[$difficulty] ?? $difficulty_map['normal'];
}

/**
 * セマンティック検索の統合関数
 */
function gi_enhanced_semantic_search($query, $filters = []) {
    // セマンティック検索クラスが利用可能な場合
    if (class_exists('GI_Grant_Semantic_Search')) {
        $semantic_search = GI_Grant_Semantic_Search::getInstance();
        return $semantic_search->search($query, $filters);
    }
    
    // フォールバック: 通常のWP_Query
    return gi_fallback_search($query, $filters);
}

/**
 * フォールバック検索
 */
function gi_fallback_search($query, $filters = []) {
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 30,
        'post_status' => 'publish',
        's' => $query
    ];
    
    // フィルター適用
    if (!empty($filters['category'])) {
        $args['tax_query'] = [[
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $filters['category']
        ]];
    }
    
    $query_obj = new WP_Query($args);
    $results = [];
    
    if ($query_obj->have_posts()) {
        while ($query_obj->have_posts()) {
            $query_obj->the_post();
            $post_id = get_the_ID();
            
            $results[] = [
                'id' => $post_id,
                'title' => get_the_title(),
                'excerpt' => get_the_excerpt(),
                'url' => get_permalink(),
                'amount' => gi_safe_get_meta($post_id, 'max_amount', '未定'),
                'deadline' => gi_safe_get_meta($post_id, 'deadline', '随時'),
                'organization' => gi_safe_get_meta($post_id, 'organization', ''),
                'categories' => wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names']),
                'score' => 0.8 // デフォルトスコア
            ];
        }
        wp_reset_postdata();
    }
    
    return [
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'method' => 'fallback_wp_query'
    ];
}


/**
 * 検索クエリ解析
 */
function gi_parse_search_query($query) {
    $params = [
        'intent' => 'general_search',
        'entities' => [],
        'modifiers' => []
    ];
    
    // 意図の分析
    if (mb_stripos($query, '申請') !== false || mb_stripos($query, '応募') !== false) {
        $params['intent'] = 'application_inquiry';
    } elseif (mb_stripos($query, '締切') !== false || mb_stripos($query, '期限') !== false) {
        $params['intent'] = 'deadline_inquiry';
    } elseif (mb_stripos($query, '金額') !== false || mb_stripos($query, '助成額') !== false) {
        $params['intent'] = 'amount_inquiry';
    }
    
    // エンティティ抽出
    $entity_patterns = [
        'amount' => '/(\d+)万円|(\d+)円/',
        'industry' => '/IT|製造業|サービス業|建設業/',
        'region' => '/東京|大阪|名古屋|福岡|北海道/'
    ];
    
    foreach ($entity_patterns as $type => $pattern) {
        if (preg_match($pattern, $query, $matches)) {
            $params['entities'][$type] = $matches[0];
        }
    }
    
    return $params;
}

/**
 * 会話コンテキスト応答生成
 */
function gi_generate_contextual_chat_response($message, $intent, $conversation_context, $related_grants = []) {
    // 基本的な応答生成
    $base_response = '';
    
    switch ($intent['type']) {
        case 'grant_search':
            if (!empty($related_grants)) {
                $count = count($related_grants);
                $base_response = "{$count}件の関連する補助金が見つかりました。";
                
                // 最初の3件の概要を提示
                $top_grants = array_slice($related_grants, 0, 3);
                foreach ($top_grants as $grant) {
                    $base_response .= "\n\n📋 {$grant['title']}\n";
                    $base_response .= "💰 {$grant['amount']}\n";
                    $base_response .= "📅 {$grant['deadline']}";
                }
                
                $base_response .= "\n\nどの補助金について詳しく知りたいですか？";
            } else {
                $base_response = "申し訳ございませんが、該当する補助金が見つかりませんでした。検索条件を変更してお試しください。";
            }
            break;
            
        case 'deadline_inquiry':
            if (!empty($related_grants)) {
                $base_response = "締切が近い補助金をお探しですね。以下の補助金がおすすめです：\n\n";
                foreach (array_slice($related_grants, 0, 3) as $grant) {
                    $base_response .= "• {$grant['title']} - 締切：{$grant['deadline']}\n";
                }
            } else {
                $base_response = "現在募集中の補助金の締切情報をお調べいたします。具体的な業種や用途をお教えいただけますか？";
            }
            break;
            
        case 'amount_inquiry':
            if (!empty($related_grants)) {
                $base_response = "助成金額についてご案内します。以下の補助金が該当します：\n\n";
                foreach (array_slice($related_grants, 0, 3) as $grant) {
                    $base_response .= "• {$grant['title']} - 最大：{$grant['amount']}\n";
                }
            } else {
                $base_response = "ご希望の金額帯の補助金をお探しします。どのくらいの金額をご希望ですか？";
            }
            break;
            
        default:
            $base_response = "ご質問ありがとうございます。「{$message}」について、詳しい情報をお調べいたします。\n\n";
            
            if (!empty($related_grants)) {
                $base_response .= "関連する補助金が見つかりました。どちらについて詳しく知りたいですか？";
            } else {
                $base_response .= "より具体的な条件をお聞かせいただければ、適切な補助金をご提案できます。";
            }
    }
    
    return $base_response;
}

/**
 * コンテキスト提案生成
 */
function gi_generate_contextual_suggestions($intent, $message, $related_grants = []) {
    $suggestions = [];
    
    switch ($intent['type']) {
        case 'grant_search':
            $suggestions = [
                '申請方法を教えて',
                '必要書類は？',
                '締切はいつ？',
                '採択率について'
            ];
            break;
            
        case 'deadline_inquiry':
            $suggestions = [
                '申請準備期間は？',
                '締切延長はある？',
                '申請のスケジュール',
                '早期申請のメリット'
            ];
            break;
            
        case 'amount_inquiry':
            $suggestions = [
                '対象経費について',
                '補助率は？',
                '上限額の詳細',
                '支払い時期は？'
            ];
            break;
            
        default:
            $suggestions = [
                '申請条件について',
                '似た補助金を探す',
                '申請の流れ',
                '成功のコツ'
            ];
    }
    
    return $suggestions;
}

/**
 * 音声認識フォールバック
 */
function gi_fallback_audio_transcription($audio_data) {
    // 基本的な音声認識処理（実装例）
    // 実際の音声認識には外部APIが必要
    error_log('Fallback audio transcription called - OpenAI Whisper not available');
    return '申し訳ございません。音声認識に失敗しました。テキストで入力してください。';
}

/**
 * 補助金タイトル候補取得（改良版）
 */
function gi_get_grant_title_suggestions($query, $limit = 5) {
    global $wpdb;
    
    if (strlen($query) < 2) {
        return [];
    }
    
    $cache_key = 'gi_title_suggestions_' . md5($query) . '_' . $limit;
    $suggestions = wp_cache_get($cache_key);
    
    if (false === $suggestions) {
        $like_query = '%' . $wpdb->esc_like($query) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.post_title as title, p.ID, p.guid as url,
                   pm1.meta_value as amount, pm2.meta_value as deadline
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = 'max_amount')
            LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'deadline')
            WHERE p.post_type = 'grant' 
            AND p.post_status = 'publish' 
            AND p.post_title LIKE %s 
            ORDER BY p.post_date DESC 
            LIMIT %d
        ", $like_query, $limit));
        
        $suggestions = [];
        foreach ($results as $result) {
            $suggestions[] = [
                'title' => $result->title,
                'url' => get_permalink($result->ID),
                'amount' => gi_format_amount_display($result->amount),
                'deadline' => gi_format_deadline_display($result->deadline)
            ];
        }
        
        // キャッシュ（30分）
        wp_cache_set($cache_key, $suggestions, '', 1800);
    }
    
    return $suggestions;
}

/**
 * カテゴリー候補取得（改良版）
 */
function gi_get_category_suggestions($query, $limit = 5) {
    if (strlen($query) < 2) {
        return [];
    }
    
    $cache_key = 'gi_category_suggestions_' . md5($query) . '_' . $limit;
    $suggestions = wp_cache_get($cache_key);
    
    if (false === $suggestions) {
        $terms = get_terms([
            'taxonomy' => 'grant_category',
            'name__like' => $query,
            'number' => $limit,
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC'
        ]);
        
        $suggestions = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $suggestions[] = [
                    'name' => $term->name,
                    'count' => $term->count,
                    'url' => get_term_link($term)
                ];
            }
        }
        
        // キャッシュ（1時間）
        wp_cache_set($cache_key, $suggestions, '', 3600);
    }
    
    return $suggestions;
}


/**
 * gi_calculate_relevance_score - 復元された関数
 */
function gi_calculate_relevance_score($post_id, $keywords, $original_query = '') {
    $score = 0;
    $debug_info = [];
    
    // 基本情報取得
    $title = get_the_title($post_id);
    $content = get_post_field('post_content', $post_id);
    $excerpt = get_the_excerpt($post_id);
    
    // 1. タイトルマッチング（最重要）
    $title_lower = mb_strtolower($title);
    $query_lower = mb_strtolower($original_query);
    
    // 完全一致
    if ($title_lower === $query_lower) {
        $score += 50;
        $debug_info[] = "完全一致:+50";
    }
    // 部分一致
    elseif (mb_strpos($title_lower, $query_lower) !== false) {
        $score += 30;
        $debug_info[] = "タイトル部分一致:+30";
    }
    
    // キーワードごとのマッチング
    foreach ($keywords as $keyword) {
        $keyword_lower = mb_strtolower($keyword);
        
        // タイトルでのキーワードマッチ
        if (mb_stripos($title, $keyword) !== false) {
            $score += 15;
            $debug_info[] = "タイトルキーワード[{$keyword}]:+15";
        }
        
        // 抜粋でのマッチ
        if (mb_stripos($excerpt, $keyword) !== false) {
            $score += 8;
            $debug_info[] = "抜粋キーワード[{$keyword}]:+8";
        }
        
        // コンテンツでのマッチ
        if (mb_stripos($content, $keyword) !== false) {
            $score += 5;
            $debug_info[] = "本文キーワード[{$keyword}]:+5";
        }
    }
    
    // 2. メタデータスコアリング
    $meta_fields = [
        'grant_description' => 12,
        'target_business' => 10,
        'grant_purpose' => 8,
        'eligibility_requirements' => 7,
        'application_process' => 5
    ];
    
    foreach ($meta_fields as $field => $weight) {
        $meta_value = get_post_meta($post_id, $field, true);
        if ($meta_value) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($meta_value, $keyword) !== false) {
                    $score += $weight;
                    $debug_info[] = "メタ[{$field}]に[{$keyword}]:+{$weight}";
                    break; // 同じフィールドで複数マッチしても1回だけカウント
                }
            }
        }
    }
    
    // 3. 品質スコア
    // 注目フラグ
    if (get_post_meta($post_id, 'is_featured', true)) {
        $score += 10;
        $debug_info[] = "注目補助金:+10";
    }
    
    // 成功率
    $success_rate = get_post_meta($post_id, 'grant_success_rate', true) ?: 
                   get_post_meta($post_id, 'success_rate', true);
    if ($success_rate) {
        if ($success_rate >= 80) {
            $score += 15;
            $debug_info[] = "高採択率({$success_rate}%):+15";
        } elseif ($success_rate >= 60) {
            $score += 8;
            $debug_info[] = "中採択率({$success_rate}%):+8";
        }
    }
    
    // 4. 締切スコア（締切が近いものを優先）
    $deadline = get_post_meta($post_id, 'deadline', true) ?: 
               get_post_meta($post_id, 'application_deadline', true);
    if ($deadline) {
        $deadline_time = strtotime($deadline);
        if ($deadline_time) {
            $days_until = ($deadline_time - time()) / 86400;
            if ($days_until > 0 && $days_until <= 30) {
                $score += 5;
                $debug_info[] = "締切間近:+5";
            } elseif ($days_until > 30 && $days_until <= 90) {
                $score += 3;
                $debug_info[] = "締切あり:+3";
            }
        }
    }
    
    // 5. 補助金額の妥当性
    $amount = get_post_meta($post_id, 'max_amount', true) ?: 
             get_post_meta($post_id, 'grant_amount', true);
    if ($amount) {
        // クエリに金額が含まれている場合
        if (preg_match('/(\d+)[万円]?/', $original_query, $matches)) {
            $requested_amount = intval($matches[1]);
            $grant_amount = intval(preg_replace('/[^0-9]/', '', $amount));
            
            // 要求額に近いほど高スコア
            if ($grant_amount >= $requested_amount * 0.8 && $grant_amount <= $requested_amount * 1.5) {
                $score += 10;
                $debug_info[] = "金額マッチ:+10";
            }
        }
    }
    
    // 6. カテゴリマッチング
    $categories = wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names']);
    if ($categories) {
        foreach ($categories as $cat) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($cat, $keyword) !== false) {
                    $score += 7;
                    $debug_info[] = "カテゴリマッチ[{$cat}]:+7";
                    break;
                }
            }
        }
    }
    
    // デバッグ情報を保存（開発時のみ）
    if (defined('WP_DEBUG') && WP_DEBUG) {
        update_post_meta($post_id, '_relevance_debug', [
            'score' => $score,
            'details' => $debug_info,
            'query' => $original_query,
            'keywords' => $keywords
        ]);
    }
    
    return $score;
}

/**
 * =============================================================================
 * AI 検索機能 - メイン検索エンドポイント
 * =============================================================================
 */

/**
 * AI 検索のメインハンドラー
 */
function gi_ajax_ai_search() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_search_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    $filter = sanitize_text_field($_POST['filter'] ?? 'all');
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    
    if (empty($query)) {
        wp_send_json_error(['message' => '検索クエリが空です']);
        return;
    }
    
    try {
        // デバッグ: 検索パラメータをログ
        error_log("AI Search request: query={$query}, filter={$filter}, session={$session_id}");
        
        // 検索実行
        $search_results = gi_perform_ai_search($query, $filter);
        
        // デバッグ: 検索結果をログ
        error_log("Search results count: " . count($search_results['grants'] ?? []));
        
        if (empty($search_results['grants'])) {
            error_log("Warning: No grants found for query: {$query}");
        } else {
            // 最初の補助金のデータをサンプルとしてログ
            $sample_grant = $search_results['grants'][0] ?? null;
            if ($sample_grant) {
                error_log("Sample grant data: " . json_encode($sample_grant, JSON_UNESCAPED_UNICODE));
            }
        }
        
        // AI応答生成
        $ai_response = gi_generate_search_ai_response($query, $search_results);
        
        // セッション保存
        if (!empty($session_id)) {
            try {
                gi_save_search_session($session_id, $query, $search_results['grants'] ?? []);
            } catch (Exception $session_error) {
                error_log("Session save error: " . $session_error->getMessage());
                // セッション保存エラーは無視して続行
            }
        }
        
        // レスポンスデータの構築
        $response_data = [
            'grants' => $search_results['grants'] ?? [],
            'count' => $search_results['total_count'] ?? 0,
            'ai_response' => $ai_response,
            'query' => $query,
            'filter' => $filter,
            'debug' => [
                'grants_found' => count($search_results['grants'] ?? []),
                'keywords' => $search_results['keywords'] ?? [],
                'timestamp' => current_time('mysql')
            ]
        ];
        
        // デバッグ: レスポンスデータをログ
        error_log("Sending response: " . json_encode([
            'success' => true,
            'grants_count' => count($response_data['grants']),
            'has_ai_response' => !empty($response_data['ai_response'])
        ], JSON_UNESCAPED_UNICODE));
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        error_log("AI Search error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        wp_send_json_error(['message' => 'Search error: ' . $e->getMessage()]);
    }
}

/**
 * AI検索の実行
 */
function gi_perform_ai_search($query, $filter = 'all') {
    $keywords = gi_extract_keywords($query);
    
    // WordPressクエリ構築
    $args = [
        'post_type' => 'grant',
        'post_status' => 'publish',
        'posts_per_page' => 20,
        'meta_query' => [],
        'tax_query' => []
    ];
    
    // フィルター適用
    if ($filter !== 'all') {
        $filter_terms = gi_get_filter_terms($filter);
        if (!empty($filter_terms)) {
            $args['tax_query'][] = [
                'taxonomy' => 'grant_category',
                'field' => 'slug',
                'terms' => $filter_terms,
                'operator' => 'IN'
            ];
        }
    }
    
    // テキスト検索
    if (!empty($keywords)) {
        $args['s'] = implode(' ', $keywords);
    }
    
    // クエリ実行
    $wp_query = new WP_Query($args);
    $grants = [];
    
    if ($wp_query->have_posts()) {
        while ($wp_query->have_posts()) {
            $wp_query->the_post();
            $post_id = get_the_ID();
            
            // 関連度スコア計算
            $relevance_score = gi_calculate_relevance_score($post_id, $keywords, $query);
            
            // より安全なメタデータ取得
            $grant_title = get_the_title() ?: '補助金情報';
            $grant_permalink = get_permalink() ?: '';
            $grant_excerpt = get_the_excerpt() ?: '';
            
            // 金額情報を安全に取得
            $amount_raw = get_post_meta($post_id, 'max_amount', true);
            if (empty($amount_raw)) {
                $amount_raw = get_post_meta($post_id, 'grant_amount', true);
            }
            if (empty($amount_raw)) {
                $amount_raw = get_post_meta($post_id, 'max_amount_numeric', true);
            }
            $grant_amount = !empty($amount_raw) ? $amount_raw : '未定';
            
            // 締切情報を安全に取得
            $deadline_raw = get_post_meta($post_id, 'deadline', true);
            if (empty($deadline_raw)) {
                $deadline_raw = get_post_meta($post_id, 'application_deadline', true);
            }
            $grant_deadline = !empty($deadline_raw) ? $deadline_raw : '随時';
            
            // 組織情報を安全に取得
            $org_raw = get_post_meta($post_id, 'organization', true);
            if (empty($org_raw)) {
                $org_raw = get_post_meta($post_id, 'implementing_agency', true);
            }
            $grant_organization = !empty($org_raw) ? $org_raw : '';
            
            // 採択率を安全に取得
            $success_rate_raw = get_post_meta($post_id, 'grant_success_rate', true);
            if (empty($success_rate_raw)) {
                $success_rate_raw = get_post_meta($post_id, 'adoption_rate', true);
            }
            $success_rate = (!empty($success_rate_raw) && is_numeric($success_rate_raw)) ? intval($success_rate_raw) : null;
            
            // 注目フラグを安全に取得
            $is_featured = get_post_meta($post_id, 'is_featured', true);
            $featured = ($is_featured === '1' || $is_featured === 1 || $is_featured === true);

            $grants[] = [
                'id' => $post_id,
                'title' => $grant_title,
                'permalink' => $grant_permalink,
                'excerpt' => $grant_excerpt,
                'amount' => $grant_amount,
                'deadline' => $grant_deadline,
                'organization' => $grant_organization,
                'success_rate' => $success_rate,
                'featured' => $featured,
                'relevance_score' => $relevance_score
            ];
        }
        wp_reset_postdata();
    }
    
    // 関連度でソート
    usort($grants, function($a, $b) {
        return $b['relevance_score'] - $a['relevance_score'];
    });
    
    return [
        'grants' => $grants,
        'total_count' => count($grants),
        'query' => $query,
        'keywords' => $keywords
    ];
}

/**
 * 検索用AI応答生成
 */
function gi_generate_search_ai_response($query, $search_results) {
    $count = $search_results['total_count'];
    $grants = $search_results['grants'];
    
    if ($count === 0) {
        return "申し訳ございませんが、「{$query}」に該当する補助金が見つかりませんでした。\n\n別のキーワードでお試しいただくか、条件を変更してみてください。";
    }
    
    $response = "「{$query}」に関する補助金を{$count}件見つけました。\n\n";
    
    if ($count >= 1 && !empty($grants[0])) {
        $top_grant = $grants[0];
        $response .= "特におすすめは「{$top_grant['title']}」です。";
        
        if (!empty($top_grant['amount']) && $top_grant['amount'] !== '未定') {
            $response .= "最大{$top_grant['amount']}の支援が受けられます。";
        }
        
        if (!empty($top_grant['deadline']) && $top_grant['deadline'] !== '随時') {
            $response .= "締切は{$top_grant['deadline']}となっています。";
        }
        
        $response .= "\n\n詳細については各補助金をクリックしてご確認ください。";
    }
    
    return $response;
}

/**
 * AI チャット機能
 */
function gi_ajax_ai_chat() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_search_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    
    if (empty($message)) {
        wp_send_json_error(['message' => 'メッセージが空です']);
        return;
    }
    
    try {
        // 意図分析
        $intent = gi_analyze_chat_intent($message);
        
        // コンテキスト取得
        $conversation_context = gi_get_conversation_context($session_id);
        
        // 関連補助金検索
        $related_grants = [];
        if ($intent['requires_search']) {
            $search_results = gi_perform_ai_search($message, 'all');
            $related_grants = array_slice($search_results['grants'], 0, 5);
        }
        
        // AI応答生成
        $ai_response = gi_generate_contextual_chat_response($message, $intent, $conversation_context, $related_grants);
        
        // 提案生成
        $suggestions = gi_generate_contextual_suggestions($intent, $message, $related_grants);
        
        // チャット履歴保存
        if (!empty($session_id)) {
            gi_save_chat_message($session_id, $message, $ai_response, $intent);
        }
        
        wp_send_json_success([
            'response' => $ai_response,
            'suggestions' => $suggestions,
            'related_grants' => $related_grants,
            'intent' => $intent
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Chat error: ' . $e->getMessage()]);
    }
}

/**
 * チャット意図分析
 */
function gi_analyze_chat_intent($message) {
    $intent = [
        'type' => 'general',
        'requires_search' => false,
        'entities' => []
    ];
    
    // 検索意図の検出
    if (preg_match('/探して|検索|見つけて|教えて|おすすめ|知りたい/', $message)) {
        $intent['type'] = 'search';
        $intent['requires_search'] = true;
    }
    
    // 申請関連の質問
    if (preg_match('/申請|手続き|書類|必要/', $message)) {
        $intent['type'] = 'application_inquiry';
    }
    
    // 条件・要件の質問
    if (preg_match('/条件|要件|対象|資格/', $message)) {
        $intent['type'] = 'eligibility_inquiry';
    }
    
    // 金額・費用の質問
    if (preg_match('/金額|費用|いくら|補助率/', $message)) {
        $intent['type'] = 'amount_inquiry';
    }
    
    // 締切・期限の質問
    if (preg_match('/締切|期限|いつまで/', $message)) {
        $intent['type'] = 'deadline_inquiry';
    }
    
    return $intent;
}

/**
 * 検索提案機能
 */
function gi_ajax_search_suggestions() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_search_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    
    if (strlen($query) < 2) {
        wp_send_json_success(['suggestions' => []]);
        return;
    }
    
    $suggestions = gi_generate_search_suggestions($query);
    
    wp_send_json_success(['suggestions' => $suggestions]);
}

/**
 * フィルター用語句取得
 */
function gi_get_filter_terms($filter) {
    $filter_map = [
        'it' => ['IT導入', 'デジタル化', 'システム'],
        'manufacturing' => ['ものづくり', '製造業', '設備投資'],
        'startup' => ['創業', '起業', 'スタートアップ'],
        'sustainability' => ['持続化', '販路開拓', '事業継続'],
        'innovation' => ['事業再構築', '新分野', 'イノベーション'],
        'employment' => ['雇用', '人材', '労働']
    ];
    
    return $filter_map[$filter] ?? [];
}

// AJAX アクション登録
add_action('wp_ajax_gi_ai_search', 'gi_ajax_ai_search');
add_action('wp_ajax_nopriv_gi_ai_search', 'gi_ajax_ai_search');

add_action('wp_ajax_gi_ai_chat', 'gi_ajax_ai_chat');
add_action('wp_ajax_nopriv_gi_ai_chat', 'gi_ajax_ai_chat');

add_action('wp_ajax_gi_search_suggestions', 'gi_ajax_search_suggestions');
add_action('wp_ajax_nopriv_gi_search_suggestions', 'gi_ajax_search_suggestions');

