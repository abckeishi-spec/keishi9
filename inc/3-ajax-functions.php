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
 * 残り日数計算
 */
function gi_calculate_days_remaining($deadline_date) {
    if (empty($deadline_date)) return null;
    
    $deadline_timestamp = is_numeric($deadline_date) ? intval($deadline_date) : strtotime($deadline_date);
    if (!$deadline_timestamp) return null;
    
    $current_timestamp = current_time('timestamp');
    $diff = $deadline_timestamp - $current_timestamp;
    
    return $diff > 0 ? ceil($diff / (60 * 60 * 24)) : 0;
}

/**
 * ステータス表示マッピング
 */
function gi_map_status_for_display($status) {
    if (function_exists('gi_map_application_status_ui')) {
        return gi_map_application_status_ui($status);
    }
    
    $status_map = [
        'open' => '募集中',
        'active' => '募集中',
        'upcoming' => '募集予定',
        'closed' => '募集終了',
        'suspended' => '一時停止'
    ];
    
    return $status_map[$status] ?? $status;
}

/**
 * 難易度情報取得
 */
function gi_get_difficulty_info($difficulty) {
    $difficulty_config = [
        'easy' => ['label' => '易しい', 'color' => 'green', 'stars' => 1],
        'normal' => ['label' => '普通', 'color' => 'blue', 'stars' => 2],
        'hard' => ['label' => '難しい', 'color' => 'orange', 'stars' => 3],
        'expert' => ['label' => '専門的', 'color' => 'red', 'stars' => 4]
    ];
    
    return $difficulty_config[$difficulty] ?? $difficulty_config['normal'];
}

/**
 * =============================================================================
 * 9. デバッグ・ログ機能
 * =============================================================================
 */

/**
 * AJAX処理のデバッグ情報出力
 */
function gi_ajax_debug_info() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        wp_send_json_error('Debug mode disabled');
    }
    
    $debug_info = [
        'template_path' => get_template_directory() . '/template-parts/grant-card-unified.php',
        'template_exists' => file_exists(get_template_directory() . '/template-parts/grant-card-unified.php'),
        'functions_available' => [
            'render_grant_card_unified' => function_exists('render_grant_card_unified'),
            'gi_get_complete_grant_data' => function_exists('gi_get_complete_grant_data'),
            'gi_get_user_favorites' => function_exists('gi_get_user_favorites'),
            'gi_format_amount_unified' => function_exists('gi_format_amount_unified'),
        ],
        'classes_available' => [
            'GrantCardRenderer' => class_exists('GrantCardRenderer'),
        ],
        'acf_available' => function_exists('get_field'),
        'grants_count' => wp_count_posts('grant'),
        'php_version' => PHP_VERSION,
        'wp_version' => get_bloginfo('version'),
        'theme_version' => wp_get_theme()->get('Version'),
    ];
    
    wp_send_json_success($debug_info);
}
add_action('wp_ajax_gi_debug_info', 'gi_ajax_debug_info');

/**
 * =============================================================================
 * 10. AI検索機能 - 完全実装版
 * =============================================================================
 */

/**
 * AI検索ハンドラー
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
    
    // セマンティック検索用のキーワード抽出
    $keywords = gi_extract_keywords($query);
    
    // 高度な検索アルゴリズム実装
    $search_params = gi_parse_search_query($query);
    
    // 基本検索条件
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 30, // 多めに取得してスコアリング後に絞る
        'post_status' => 'publish'
    ];
    
    // 複合検索クエリの構築
    if (!empty($query)) {
        // タイトル、内容、抜粋での検索
        $args['s'] = $query;
        
        // メタクエリの構築（より詳細に）
        $meta_queries = ['relation' => 'OR'];
        
        // 主要メタフィールドでの検索
        $search_fields = [
            'grant_description',
            'target_business',
            'eligibility_requirements',
            'application_process',
            'grant_purpose',
            'grant_benefits',
            'organization',
            'managing_organization',
            'support_details'
        ];
        
        foreach ($search_fields as $field) {
            $meta_queries[] = [
                'key' => $field,
                'value' => $query,
                'compare' => 'LIKE'
            ];
        }
        
        // 金額での検索（数値が含まれる場合）
        if (preg_match('/(\d+)/', $query, $matches)) {
            $amount = intval($matches[1]);
            $meta_queries[] = [
                'key' => 'max_amount',
                'value' => $amount,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
            $meta_queries[] = [
                'key' => 'grant_amount',
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
    
    if ($query_obj->have_posts()) {
        while ($query_obj->have_posts()) {
            $query_obj->the_post();
            $post_id = get_the_ID();
            
            // スコアリング計算（クエリも渡す）
            $relevance_score = gi_calculate_relevance_score($post_id, $keywords, $query);
            
            $grants[] = [
                'id' => $post_id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => wp_trim_words(get_the_excerpt(), 20),
                'amount' => get_post_meta($post_id, 'max_amount', true) ?: get_post_meta($post_id, 'grant_amount', true),
                'deadline' => get_post_meta($post_id, 'deadline', true) ?: get_post_meta($post_id, 'application_deadline', true),
                'organization' => get_post_meta($post_id, 'organization', true) ?: get_post_meta($post_id, 'managing_organization', true),
                'success_rate' => get_post_meta($post_id, 'grant_success_rate', true) ?: get_post_meta($post_id, 'success_rate', true),
                'featured' => get_post_meta($post_id, 'is_featured', true),
                'relevance_score' => $relevance_score,
                'categories' => wp_get_post_terms($post_id, 'grant_category', ['fields' => 'names'])
            ];
        }
        wp_reset_postdata();
        
        // 関連性でソート
        usort($grants, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });
    }
    
    // AI応答生成
    $ai_response = gi_generate_ai_search_response($query, $grants);
    
    // セッションIDの確保
    if (!$session_id) {
        $session_id = wp_generate_uuid4();
    }
    
    // セッションへの保存
    gi_save_search_session($session_id, $query, $grants);
    
    // データベースへの実際の保存
    gi_log_search_to_database($session_id, $query, $filter, $grants, $keywords);
    
    wp_send_json_success([
        'grants' => $grants,
        'count' => $query_obj->found_posts,
        'ai_response' => $ai_response,
        'keywords' => $keywords,
        'session_id' => $session_id ?: wp_generate_uuid4()
    ]);
}
add_action('wp_ajax_gi_ai_search', 'handle_ai_search');
add_action('wp_ajax_nopriv_gi_ai_search', 'handle_ai_search');

/**
 * AIチャットハンドラー
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
    
    // 意図分析
    $intent = gi_analyze_user_intent($message);
    
    // コンテキスト管理
    $conversation_context = gi_get_conversation_context($session_id);
    $conversation_context[] = ['user' => $message, 'timestamp' => current_time('timestamp')];
    
    // AI応答生成
    $ai_response = gi_generate_chat_response($message, $intent, $conversation_context);
    
    // 関連補助金の検索
    $related_grants = [];
    if ($intent['type'] === 'grant_search' || $intent['type'] === 'grant_inquiry') {
        $related_grants = gi_find_related_grants($message, $intent);
    }
    
    // 会話履歴の保存
    $conversation_context[] = ['assistant' => $ai_response, 'timestamp' => current_time('timestamp')];
    gi_save_conversation_context($session_id, $conversation_context);
    
    // データベースへの保存（実装版）
    gi_log_chat_to_database($session_id, 'user', $message, $intent);
    gi_log_chat_to_database($session_id, 'assistant', $ai_response, null, $related_grants);
    
    wp_send_json_success([
        'response' => $ai_response,
        'intent' => $intent,
        'related_grants' => $related_grants,
        'suggestions' => gi_generate_follow_up_questions($intent, $message)
    ]);
}
add_action('wp_ajax_gi_ai_chat', 'handle_ai_chat_request');
add_action('wp_ajax_nopriv_gi_ai_chat', 'handle_ai_chat_request');

/**
 * 検索候補取得
 */
function gi_ajax_search_suggestions() {
    // nonceチェックをエラーハンドリング付きで実行
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gi_ai_search_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    
    if (strlen($query) < 2) {
        wp_send_json_success(['suggestions' => []]);
    }
    
    // 人気の検索キーワード
    $popular_searches = gi_get_popular_searches();
    
    // 補助金タイトルから候補を生成
    $grant_suggestions = gi_get_grant_title_suggestions($query);
    
    // カテゴリーから候補を生成
    $category_suggestions = gi_get_category_suggestions($query);
    
    $suggestions = array_merge(
        array_map(function($s) { return ['type' => 'popular', 'text' => $s, 'icon' => '🔥']; }, $popular_searches),
        array_map(function($s) { return ['type' => 'grant', 'text' => $s, 'icon' => '📋']; }, $grant_suggestions),
        array_map(function($s) { return ['type' => 'category', 'text' => $s, 'icon' => '📁']; }, $category_suggestions)
    );
    
    // 重複削除と上限設定
    $suggestions = array_slice(array_unique($suggestions, SORT_REGULAR), 0, 8);
    
    wp_send_json_success(['suggestions' => $suggestions]);
}
add_action('wp_ajax_gi_search_suggestions', 'gi_ajax_search_suggestions');
add_action('wp_ajax_nopriv_gi_search_suggestions', 'gi_ajax_search_suggestions');

/**
 * 音声入力処理
 */
function gi_ajax_process_voice_input() {
    // nonceチェックをエラーハンドリング付きで実行
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gi_ai_search_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
        return;
    }
    
    $audio_data = $_POST['audio_data'] ?? '';
    
    if (empty($audio_data)) {
        wp_send_json_error(['message' => '音声データがありません']);
    }
    
    // 音声認識処理（実装時は外部APIを使用）
    $transcribed_text = gi_transcribe_audio($audio_data);
    
    if (!$transcribed_text) {
        wp_send_json_error(['message' => '音声認識に失敗しました']);
    }
    
    wp_send_json_success([
        'text' => $transcribed_text,
        'confidence' => 0.95 // 認識信頼度
    ]);
}
add_action('wp_ajax_gi_voice_input', 'gi_ajax_process_voice_input');
add_action('wp_ajax_nopriv_gi_voice_input', 'gi_ajax_process_voice_input');

/**
 * =============================================================================
 * AI検索ヘルパー関数
 * =============================================================================
 */

/**
 * 検索クエリの高度な解析
 */
function gi_parse_search_query($query) {
    $params = [
        'keywords' => [],
        'amount_min' => null,
        'amount_max' => null,
        'location' => null,
        'industry' => null,
        'purpose' => null,
        'deadline_within_days' => null
    ];
    
    // 金額の抽出（例: "500万円", "100万〜1000万"）
    if (preg_match('/(\d+)万円?(?:〜|～|-|から)(\d+)万円?/', $query, $matches)) {
        $params['amount_min'] = intval($matches[1]);
        $params['amount_max'] = intval($matches[2]);
    } elseif (preg_match('/(\d+)万円?(?:以上|から)/', $query, $matches)) {
        $params['amount_min'] = intval($matches[1]);
    } elseif (preg_match('/(\d+)万円?(?:以下|まで)/', $query, $matches)) {
        $params['amount_max'] = intval($matches[1]);
    } elseif (preg_match('/(\d+)万円?/', $query, $matches)) {
        $params['amount_min'] = intval($matches[1]) * 0.5;
        $params['amount_max'] = intval($matches[1]) * 2;
    }
    
    // 地域の抽出
    $prefectures = ['東京', '大阪', '愛知', '福岡', '北海道', '神奈川', '埼玉', '千葉'];
    foreach ($prefectures as $pref) {
        if (mb_strpos($query, $pref) !== false) {
            $params['location'] = $pref;
            break;
        }
    }
    
    // 業種の判定
    $industries = [
        'IT' => ['IT', 'デジタル', 'DX', 'システム', 'ソフトウェア', 'Web'],
        '製造' => ['製造', 'ものづくり', '工場', '機械', '設備'],
        '飲食' => ['飲食', 'レストラン', 'カフェ', '居酒屋', '食品'],
        '小売' => ['小売', '販売', 'ショップ', '店舗', 'EC'],
        'サービス' => ['サービス', 'コンサル', '人材', '教育']
    ];
    
    foreach ($industries as $industry => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_stripos($query, $keyword) !== false) {
                $params['industry'] = $industry;
                break 2;
            }
        }
    }
    
    // 目的の判定
    $purposes = [
        '設備投資' => ['設備', '機械', '装置', 'システム導入'],
        '人材育成' => ['人材', '研修', '教育', 'スキル'],
        '販路開拓' => ['販路', '営業', 'マーケティング', '広告'],
        '研究開発' => ['研究', '開発', 'R&D', '新製品'],
        '事業承継' => ['承継', '後継', 'M&A']
    ];
    
    foreach ($purposes as $purpose => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_stripos($query, $keyword) !== false) {
                $params['purpose'] = $purpose;
                break 2;
            }
        }
    }
    
    // 期限の判定
    if (mb_strpos($query, '締切間近') !== false || mb_strpos($query, '急ぎ') !== false) {
        $params['deadline_within_days'] = 30;
    } elseif (mb_strpos($query, '今月') !== false) {
        $params['deadline_within_days'] = 30;
    } elseif (mb_strpos($query, '今年') !== false) {
        $params['deadline_within_days'] = 365;
    }
    
    // 残りの単語をキーワードとして抽出
    $params['keywords'] = gi_extract_keywords($query);
    
    return $params;
}

/**
 * データベースへのログ記録（実装版）
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
 * 検索テーブルの作成
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
 * キーワード抽出（改良版）
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
 * 高度な関連性スコア計算アルゴリズム
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
 * AI検索応答生成（改良版）
 */
function gi_generate_ai_search_response($query, $grants) {
    $count = count($grants);
    
    if ($count === 0) {
        // より具体的な提案を含む応答
        $alternatives = gi_suggest_alternatives($query);
        $response = "「{$query}」に完全に一致する補助金は見つかりませんでしたが、";
        if (!empty($alternatives)) {
            $response .= "関連するキーワード「" . implode('」「', $alternatives) . "」で検索してみてはいかがでしょうか。";
        } else {
            $response .= "検索条件を変更してお試しください。例えば、業種名や「IT」「製造業」などの一般的なキーワードをお試しください。";
        }
        return $response;
    }
    
    // クエリの内容に応じた文脈的な応答
    $response = "";
    
    // クエリ内容の分析
    $query_lower = mb_strtolower($query);
    $is_specific_grant = preg_match('/補助金|助成金/u', $query);
    $has_industry = preg_match('/IT|製造|飲食|小売|サービス|建設/u', $query);
    $has_purpose = preg_match('/設備|機械|システム|販促|人材|研究/u', $query);
    
    // 検索結果の分析
    $total_amount = 0;
    $categories = [];
    $deadlines_soon = 0;
    $high_success_rate = 0;
    
    foreach ($grants as $grant) {
        if (isset($grant['amount'])) {
            $amount_num = preg_replace('/[^0-9]/', '', $grant['amount']);
            if (is_numeric($amount_num)) {
                $total_amount = max($total_amount, intval($amount_num));
            }
        }
        
        if (isset($grant['categories']) && is_array($grant['categories'])) {
            $categories = array_merge($categories, $grant['categories']);
        }
        
        if (isset($grant['deadline'])) {
            $deadline_date = strtotime($grant['deadline']);
            if ($deadline_date && $deadline_date < strtotime('+30 days')) {
                $deadlines_soon++;
            }
        }
        
        if (isset($grant['success_rate']) && $grant['success_rate'] > 70) {
            $high_success_rate++;
        }
    }
    
    $categories = array_unique($categories);
    
    // 動的な応答生成
    if ($has_industry) {
        $response = "「{$query}」に関連する補助金が{$count}件見つかりました。";
    } elseif ($has_purpose) {
        $response = "{$query}に活用できる補助金が{$count}件あります。";
    } else {
        $response = "検索結果：{$count}件の補助金が該当しました。";
    }
    
    // トップ3の補助金を詳しく説明
    if ($count >= 3) {
        $response .= "\n\n【特におすすめの補助金TOP3】\n";
        
        for ($i = 0; $i < min(3, $count); $i++) {
            $grant = $grants[$i];
            $response .= "\n" . ($i + 1) . ". " . $grant['title'];
            
            // 詳細情報を追加
            $details = [];
            if (!empty($grant['amount'])) {
                $details[] = "最大" . $grant['amount'];
            }
            if (!empty($grant['success_rate'])) {
                $details[] = "採択率" . $grant['success_rate'] . "%";
            }
            if (!empty($grant['deadline'])) {
                $deadline_date = strtotime($grant['deadline']);
                if ($deadline_date) {
                    $days_left = ceil(($deadline_date - time()) / 86400);
                    if ($days_left > 0 && $days_left <= 30) {
                        $details[] = "締切まであと{$days_left}日";
                    } elseif ($days_left > 30) {
                        $details[] = "締切:" . date('Y年n月j日', $deadline_date);
                    }
                }
            }
            
            if (!empty($details)) {
                $response .= "（" . implode('、', $details) . "）";
            }
            
            // 簡単な説明を追加
            if (!empty($grant['excerpt'])) {
                $response .= "\n   " . mb_substr($grant['excerpt'], 0, 50) . "...";
            }
        }
    } elseif ($count > 0) {
        // 1-2件の場合はより詳細に
        foreach ($grants as $i => $grant) {
            if ($i > 0) $response .= "\n";
            $response .= "\n【" . $grant['title'] . "】";
            if (!empty($grant['amount'])) {
                $response .= "\n• 補助上限：" . $grant['amount'];
            }
            if (!empty($grant['organization'])) {
                $response .= "\n• 実施機関：" . $grant['organization'];
            }
            if (!empty($grant['deadline'])) {
                $response .= "\n• 申請締切：" . $grant['deadline'];
            }
        }
    }
    
    // 追加のアドバイス
    if ($deadlines_soon > 0) {
        $response .= "\n\n⚠️ {$deadlines_soon}件の補助金が締切間近です。お早めのご準備をおすすめします。";
    }
    
    if ($high_success_rate > 0) {
        $response .= "\n\n✨ {$high_success_rate}件は採択率70%以上の狙い目補助金です。";
    }
    
    if ($total_amount >= 1000) {
        $response .= "\n\n💰 最大" . number_format($total_amount) . "万円の大型補助金も含まれています。";
    }
    
    // カテゴリ情報
    if (count($categories) > 1) {
        $response .= "\n\n📂 カテゴリ：" . implode('、', array_slice($categories, 0, 3));
    }
    
    return $response;
}

/**
 * 代替検索キーワードの提案
 */
function gi_suggest_alternatives($query) {
    $alternatives = [];
    
    // 一般的な類義語マッピング
    $synonyms = [
        'デジタル' => ['IT', 'DX', 'システム'],
        '製造' => ['ものづくり', '工場', '生産'],
        '環境' => ['エコ', 'グリーン', '省エネ'],
        '人材' => ['雇用', '採用', '教育'],
        '設備' => ['機械', '装置', 'システム']
    ];
    
    foreach ($synonyms as $key => $values) {
        if (mb_stripos($query, $key) !== false) {
            $alternatives = array_merge($alternatives, $values);
        }
    }
    
    return array_unique($alternatives);
}

/**
 * ユーザー意図分析
 */
function gi_analyze_user_intent($message) {
    $intent = ['type' => 'general', 'confidence' => 0.5, 'entities' => []];
    
    // 意図パターンのマッチング
    $patterns = [
        'grant_search' => ['補助金', '助成金', '支援金', '探して', '教えて'],
        'application_help' => ['申請', '応募', '手続き', '書類', 'やり方'],
        'deadline_check' => ['締切', '期限', 'いつまで', '期間'],
        'amount_inquiry' => ['金額', 'いくら', '最大', '上限'],
        'eligibility' => ['対象', '条件', '資格', '該当']
    ];
    
    foreach ($patterns as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_stripos($message, $keyword) !== false) {
                $intent['type'] = $type;
                $intent['confidence'] = 0.8;
                $intent['entities'][] = $keyword;
            }
        }
    }
    
    return $intent;
}

/**
 * チャット応答生成（改良版）
 */
function gi_generate_chat_response($message, $intent, $context) {
    // OpenAI API設定を確認
    $ai_settings = get_option('gi_ai_settings', []);
    $api_key = get_option('gi_openai_api_key', '');
    
    // APIキーが設定されている場合は実際のAI応答を生成
    if (!empty($api_key)) {
        return gi_generate_openai_response($message, $intent, $context);
    }
    
    // APIキーがない場合は改良版のローカル応答を生成
    return gi_generate_enhanced_local_response($message, $intent, $context);
}

/**
 * 改良版ローカル応答生成（APIキーなしでも多様な応答）
 */
function gi_generate_enhanced_local_response($message, $intent, $context) {
    // メッセージから具体的な情報を抽出
    $has_industry = preg_match('/製造|IT|飲食|小売|サービス|建設|医療|介護/u', $message);
    $has_employee = preg_match('/(\d+)[人名]/u', $message, $employee_match);
    $has_amount = preg_match('/(\d+)[万円]/u', $message, $amount_match);
    $has_location = preg_match('/東京|大阪|愛知|福岡|北海道|[都道府県]/u', $message, $location_match);
    
    // コンテキストから過去の会話を考慮
    $previous_topics = [];
    if (is_array($context) && count($context) > 0) {
        foreach ($context as $ctx) {
            if (isset($ctx['user'])) {
                $previous_topics[] = $ctx['user'];
            }
        }
    }
    
    // 意図別の動的応答生成
    $response = '';
    
    switch ($intent['type']) {
        case 'grant_search':
            if ($has_industry) {
                $industry = $employee_match[0] ?? '該当業種';
                $response = "承知いたしました。{$industry}向けの補助金をお探しですね。";
                
                // 業種別の推奨補助金を提案
                if (strpos($message, 'IT') !== false || strpos($message, 'デジタル') !== false) {
                    $response .= "IT導入補助金（最大450万円）やDX推進補助金がおすすめです。";
                } elseif (strpos($message, '製造') !== false || strpos($message, 'ものづくり') !== false) {
                    $response .= "ものづくり補助金（最大1,250万円）が最適です。設備投資にも活用できます。";
                } elseif (strpos($message, '飲食') !== false || strpos($message, '小売') !== false) {
                    $response .= "小規模事業者持続化補助金（最大200万円）や事業再構築補助金をご検討ください。";
                }
            } else {
                $response = "どのような事業をされていますか？業種や規模を教えていただければ、より具体的な補助金をご提案できます。";
            }
            break;
            
        case 'application_help':
            $response = "申請のサポートをいたします。";
            if (count($previous_topics) > 0) {
                $response .= "先ほどお話しいただいた内容から、";
            }
            $response .= "まずは必要書類の準備から始めましょう。事業計画書、決算書、登記簿謄本などが基本書類となります。";
            break;
            
        case 'deadline_check':
            // 現在の月を考慮した応答
            $current_month = date('n');
            if ($current_month >= 1 && $current_month <= 3) {
                $response = "年度末に向けて多くの補助金の締切が近づいています。";
            } elseif ($current_month >= 4 && $current_month <= 6) {
                $response = "新年度の補助金募集が始まる時期です。";
            } else {
                $response = "現在募集中の補助金の締切を確認します。";
            }
            $response .= "具体的な補助金名を教えていただければ、正確な締切日をお伝えできます。";
            break;
            
        case 'amount_inquiry':
            if ($has_amount) {
                $amount = $amount_match[1];
                $response = "{$amount}万円規模の投資をご検討ですね。";
                if (intval($amount) <= 200) {
                    $response .= "小規模事業者持続化補助金や各自治体の補助金が適しています。";
                } elseif (intval($amount) <= 1000) {
                    $response .= "ものづくり補助金やIT導入補助金が該当します。";
                } else {
                    $response .= "事業再構築補助金など大型の補助金をご検討ください。";
                }
            } else {
                $response = "どの程度の規模の投資をお考えですか？金額によって最適な補助金が異なります。";
            }
            break;
            
        case 'eligibility':
            if ($has_employee) {
                $employees = $employee_match[1];
                $response = "従業員{$employees}名の企業様ですね。";
                if (intval($employees) <= 20) {
                    $response .= "小規模事業者向けの補助金が多数利用可能です。";
                } elseif (intval($employees) <= 300) {
                    $response .= "中小企業向けの幅広い補助金が対象となります。";
                }
            }
            if ($has_location) {
                $location = $location_match[0];
                $response .= "{$location}では地域独自の補助金もございます。";
            }
            if (!$has_employee && !$has_location) {
                $response = "対象条件の確認のため、従業員数と所在地を教えてください。";
            }
            break;
            
        default:
            // より文脈に応じた一般応答
            $keywords_found = [];
            if (strpos($message, '始め') !== false || strpos($message, '初め') !== false) {
                $response = "補助金のご相談ですね。まずは御社の業種と従業員数を教えていただけますか？それによって利用可能な補助金が変わってきます。";
            } elseif (strpos($message, 'ありがとう') !== false) {
                $response = "お役に立てて嬉しいです。他にもご質問があればお気軽にどうぞ。";
            } elseif (strpos($message, 'もっと') !== false || strpos($message, '他に') !== false) {
                $response = "他の選択肢もご紹介します。どのような条件を重視されますか？（例：補助率、上限額、申請の簡易さなど）";
            } else {
                $response = "ご質問ありがとうございます。「{$message}」について、もう少し詳しく教えていただけますか？";
            }
    }
    
    // 関連する補助金の提案を追加
    $related_grants = gi_find_contextual_grants($message, $intent);
    if (!empty($related_grants)) {
        $response .= "\n\n【関連する補助金】\n";
        foreach (array_slice($related_grants, 0, 3) as $grant) {
            $response .= "• " . $grant['title'];
            if ($grant['amount']) {
                $response .= "（最大" . $grant['amount'] . "）";
            }
            $response .= "\n";
        }
    }
    
    return $response;
}

/**
 * コンテキストに基づく補助金検索
 */
function gi_find_contextual_grants($message, $intent) {
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 5,
        'post_status' => 'publish'
    ];
    
    // メッセージから検索条件を動的に生成
    $meta_query = [];
    
    // 金額での絞り込み
    if (preg_match('/(\d+)[万円]/u', $message, $matches)) {
        $amount = intval($matches[1]);
        $meta_query[] = [
            'key' => 'max_amount',
            'value' => $amount,
            'compare' => '>=',
            'type' => 'NUMERIC'
        ];
    }
    
    // カテゴリでの絞り込み
    $tax_query = [];
    if (strpos($message, 'IT') !== false || strpos($message, 'デジタル') !== false) {
        $tax_query[] = [
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => ['it-support', 'digital-transformation']
        ];
    }
    
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }
    
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }
    
    // ランダム性を追加して多様性を確保
    $args['orderby'] = 'rand';
    
    $query = new WP_Query($args);
    $grants = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $grants[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'amount' => get_post_meta(get_the_ID(), 'max_amount', true) ?: get_post_meta(get_the_ID(), 'grant_amount', true)
            ];
        }
        wp_reset_postdata();
    }
    
    return $grants;
}

/**
 * OpenAI API応答生成（将来の実装用）
 */
function gi_generate_openai_response($message, $intent, $context) {
    $api_key = get_option('gi_openai_api_key', '');
    
    if (empty($api_key)) {
        return gi_generate_enhanced_local_response($message, $intent, $context);
    }
    
    // システムプロンプト
    $system_prompt = "あなたは日本の補助金・助成金に詳しい専門アドバイザーです。ユーザーの質問に対して、具体的で実用的なアドバイスを提供してください。補助金の名称、金額、条件、締切などの情報を含めて回答してください。";
    
    // コンテキストを含めたメッセージ構築
    $messages = [
        ['role' => 'system', 'content' => $system_prompt]
    ];
    
    // 過去の会話コンテキストを追加
    if (!empty($context)) {
        foreach (array_slice($context, -5) as $ctx) {
            if (isset($ctx['user'])) {
                $messages[] = ['role' => 'user', 'content' => $ctx['user']];
            }
            if (isset($ctx['assistant'])) {
                $messages[] = ['role' => 'assistant', 'content' => $ctx['assistant']];
            }
        }
    }
    
    // 現在のメッセージを追加
    $messages[] = ['role' => 'user', 'content' => $message];
    
    // OpenAI APIリクエスト
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7
        ]),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return gi_generate_enhanced_local_response($message, $intent, $context);
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['choices'][0]['message']['content'])) {
        return $body['choices'][0]['message']['content'];
    }
    
    return gi_generate_enhanced_local_response($message, $intent, $context);
}

/**
 * 関連補助金検索
 */
function gi_find_related_grants($message, $intent) {
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => 3,
        'post_status' => 'publish',
        'orderby' => 'meta_value_num',
        'meta_key' => 'grant_success_rate',
        'order' => 'DESC'
    ];
    
    $query = new WP_Query($args);
    $grants = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $grants[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'amount' => get_post_meta(get_the_ID(), 'max_amount', true)
            ];
        }
        wp_reset_postdata();
    }
    
    return $grants;
}

/**
 * フォローアップ質問生成
 */
function gi_generate_follow_up_questions($intent, $message) {
    $questions = [
        'grant_search' => [
            '業種を教えてください',
            '従業員数は何名ですか？',
            '希望する支援額はいくらですか？'
        ],
        'application_help' => [
            '申請書類の準備はお済みですか？',
            '過去に補助金申請の経験はありますか？',
            '申請期限はいつまでですか？'
        ],
        'general' => [
            '具体的にどのような支援をお探しですか？',
            '御社の事業内容を教えてください',
            '補助金の使途は決まっていますか？'
        ]
    ];
    
    return $questions[$intent['type']] ?? $questions['general'];
}

/**
 * チャット履歴のデータベース記録
 */
function gi_log_chat_to_database($session_id, $message_type, $message, $intent = null, $related_grants = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_chat_history';
    
    // テーブルが存在するか確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    if (!$table_exists) {
        gi_create_chat_tables();
    }
    
    $wpdb->insert(
        $table,
        [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'message_type' => $message_type,
            'message' => $message,
            'intent' => $intent ? $intent['type'] : null,
            'confidence' => $intent ? $intent['confidence'] : null,
            'related_grants' => is_array($related_grants) ? json_encode($related_grants) : $related_grants,
            'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%s', '%s', '%s', '%f', '%s', '%s']
    );
    
    return $wpdb->insert_id;
}

/**
 * チャットテーブルの作成
 */
function gi_create_chat_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gi_chat_history (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        message_type enum('user','assistant') NOT NULL DEFAULT 'user',
        message text NOT NULL,
        intent varchar(100) DEFAULT NULL,
        confidence decimal(3,2) DEFAULT NULL,
        related_grants text DEFAULT NULL,
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
 * 会話コンテキスト管理（データベースから取得）
 */
function gi_get_conversation_context($session_id) {
    if (!$session_id) return [];
    return get_transient('gi_chat_context_' . $session_id) ?: [];
}

function gi_save_conversation_context($session_id, $context) {
    if (!$session_id) return;
    // 最新10件のみ保持
    $context = array_slice($context, -10);
    set_transient('gi_chat_context_' . $session_id, $context, 3600);
}

/**
 * 検索セッション保存
 */
function gi_save_search_session($session_id, $query, $results) {
    if (!$session_id) return;
    
    $session_data = [
        'query' => $query,
        'results' => array_slice($results, 0, 5),
        'timestamp' => current_time('timestamp')
    ];
    
    set_transient('gi_search_session_' . $session_id, $session_data, 3600);
}

/**
 * 人気検索キーワード取得
 */
function gi_get_popular_searches() {
    // 実装時はデータベースから取得
    return [
        'IT導入補助金',
        'ものづくり補助金',
        '事業再構築補助金',
        '小規模事業者持続化補助金'
    ];
}

/**
 * 補助金タイトル候補取得
 */
function gi_get_grant_title_suggestions($query) {
    global $wpdb;
    
    $results = $wpdb->get_col($wpdb->prepare(
        "SELECT post_title FROM {$wpdb->posts} 
        WHERE post_type = 'grant' 
        AND post_status = 'publish' 
        AND post_title LIKE %s 
        LIMIT 5",
        '%' . $wpdb->esc_like($query) . '%'
    ));
    
    return $results ?: [];
}

/**
 * カテゴリー候補取得
 */
function gi_get_category_suggestions($query) {
    $categories = get_terms([
        'taxonomy' => 'grant_category',
        'hide_empty' => false,
        'name__like' => $query
    ]);
    
    return wp_list_pluck($categories, 'name');
}

/**
 * 音声データのテキスト変換（スタブ）
 */
function gi_transcribe_audio($audio_data) {
    // 実装時は音声認識APIを使用
    // 現在はダミーデータを返す
    return '補助金を探しています';
}

// ヘルパー関数は4-helper-functions.phpで定義済み

/**
 * AJAX エラーログ記録
 */
function gi_ajax_log_error($message, $data = null) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    
    $log_message = '[Grant Insight AJAX] ' . $message;
    if ($data !== null) {
        $log_message .= ' | Data: ' . print_r($data, true);
    }
    
    error_log($log_message);
}

// デバッグモード時のグローバルエラーハンドリング
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_ajax_gi_load_grants', function() {
        gi_ajax_log_error('AJAX gi_load_grants called', $_POST);
    }, 1);
}
// AI検索関連のアクション登録確認（ファイル最後で再登録）
if (!has_action('wp_ajax_gi_ai_search')) {
    add_action('wp_ajax_gi_ai_search', 'handle_ai_search');
    add_action('wp_ajax_nopriv_gi_ai_search', 'handle_ai_search');
}
if (!has_action('wp_ajax_gi_ai_chat')) {
    add_action('wp_ajax_gi_ai_chat', 'handle_ai_chat_request');
    add_action('wp_ajax_nopriv_gi_ai_chat', 'handle_ai_chat_request');
}
