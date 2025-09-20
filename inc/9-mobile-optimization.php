<?php
/**
 * Grant Insight Perfect - Enhanced Mobile Optimization Functions
 * カーセンサー、Amazon、Google等の優れたプラットフォームから学んだモバイル最適化
 * 
 * @package Grant_Insight_Perfect
 * @version 8.0-enhanced
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 高度なモバイル判定（ユーザーエージェント＋画面サイズ）
 */
if (!function_exists('gi_is_mobile_device')) {
    function gi_is_mobile_device() {
        // WordPressの標準判定 + 詳細判定
        if (wp_is_mobile()) {
            return true;
        }
        
        // タブレット除外判定
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $tablet_keywords = ['iPad', 'tablet', 'Kindle', 'Silk', 'GT-P', 'SM-T'];
        
        foreach ($tablet_keywords as $keyword) {
            if (stripos($user_agent, $keyword) !== false) {
                return false; // タブレットはモバイルとして扱わない
            }
        }
        
        return wp_is_mobile();
    }
}

/**
 * レスポンシブグリッドクラス生成（Tailwind + カスタム）
 */
if (!function_exists('gi_get_responsive_grid_classes')) {
    function gi_get_responsive_grid_classes($desktop_cols = 3, $gap = 6, $mobile_cols = 1) {
        return "grid grid-cols-{$mobile_cols} sm:grid-cols-2 lg:grid-cols-{$desktop_cols} gap-{$gap}";
    }
}

/**
 * Enhanced モバイル向けコンテンツ最適化
 */
function gi_enhanced_mobile_optimizations() {
    // モバイル専用CSSとJSの読み込み
    add_action('wp_enqueue_scripts', function() {
        if (gi_is_mobile_device()) {
            // Enhanced Mobile CSS
            wp_enqueue_style(
                'gi-mobile-enhanced',
                get_template_directory_uri() . '/assets/css/mobile-enhanced.css',
                array(),
                GI_THEME_VERSION,
                'screen'
            );
            
            // Touch Animations CSS
            wp_enqueue_style(
                'gi-touch-animations',
                get_template_directory_uri() . '/assets/css/touch-animations.css',
                array('gi-mobile-enhanced'),
                GI_THEME_VERSION,
                'screen'
            );
            
            // Enhanced Mobile JavaScript
            wp_enqueue_script(
                'gi-mobile-enhanced-js',
                get_template_directory_uri() . '/assets/js/mobile-enhanced.js',
                array('jquery'),
                GI_THEME_VERSION,
                true
            );
            
            // AJAX設定をJavaScriptに渡す
            wp_localize_script('gi-mobile-enhanced-js', 'gi_mobile_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gi_mobile_nonce'),
                'is_mobile' => true,
                'user_id' => get_current_user_id(),
                'strings' => array(
                    'loading' => '読み込み中...',
                    'error' => 'エラーが発生しました',
                    'no_results' => '該当する助成金が見つかりませんでした',
                    'load_more' => 'さらに表示',
                    'filter_applied' => 'フィルターが適用されました',
                    'favorite_added' => 'お気に入りに追加しました',
                    'favorite_removed' => 'お気に入りから削除しました'
                )
            ));
        }
    }, 15);
    
    // モバイル専用のHTMLメタタグ
    add_action('wp_head', function() {
        if (gi_is_mobile_device()) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">';
            echo '<meta name="mobile-web-app-capable" content="yes">';
            echo '<meta name="apple-mobile-web-app-capable" content="yes">';
            echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
            echo '<meta name="theme-color" content="#2563eb">';
            echo '<meta name="format-detection" content="telephone=no">';
            
            // プリロード重要リソース
            echo '<link rel="preload" href="' . get_template_directory_uri() . '/assets/css/mobile-enhanced.css" as="style">';
            echo '<link rel="preload" href="' . get_template_directory_uri() . '/assets/js/mobile-enhanced.js" as="script">';
        }
    }, 1);
    
    // モバイル専用のパフォーマンス最適化
    add_action('wp_head', function() {
        if (gi_is_mobile_device()) {
            echo '<style>
                /* Critical Mobile CSS - Above the fold optimization */
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans JP", sans-serif;
                    font-size: 16px;
                    line-height: 1.5;
                    margin: 0;
                    padding: 60px 0 80px 0;
                    background-color: #f9fafb;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                    overflow-x: hidden;
                }
                
                /* Mobile-first loading states */
                .gi-loading-skeleton {
                    background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
                    background-size: 200% 100%;
                    animation: skeleton-loading 1.5s infinite;
                }
                
                @keyframes skeleton-loading {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }
                
                /* Touch optimization */
                * {
                    -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
                    -webkit-touch-callout: none;
                }
                
                /* Prevent zoom on input focus (iOS) */
                input[type="text"], input[type="search"], textarea {
                    font-size: 16px !important;
                }
                
                /* Safe area adjustments for notched devices */
                @supports (padding: max(0px)) {
                    .gi-mobile-header {
                        padding-top: max(8px, env(safe-area-inset-top));
                    }
                    
                    .gi-bottom-nav {
                        padding-bottom: max(8px, env(safe-area-inset-bottom));
                    }
                }
            </style>';
        }
    }, 5);
}
add_action('init', 'gi_enhanced_mobile_optimizations');

/**
 * モバイル専用AJAX エンドポイント
 */
function gi_mobile_search_suggestions() {
    check_ajax_referer('gi_mobile_nonce', 'nonce');
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    
    if (strlen($query) < 2) {
        wp_send_json_error('クエリが短すぎます');
    }
    
    // 検索候補を生成
    $suggestions = array();
    
    // 1. 過去の検索から候補生成
    $recent_searches = gi_get_recent_searches($query);
    $suggestions = array_merge($suggestions, $recent_searches);
    
    // 2. タクソノミーから候補生成
    $taxonomy_suggestions = gi_get_taxonomy_suggestions($query);
    $suggestions = array_merge($suggestions, $taxonomy_suggestions);
    
    // 3. 投稿タイトルから候補生成
    $post_suggestions = gi_get_post_suggestions($query);
    $suggestions = array_merge($suggestions, $post_suggestions);
    
    // 重複除去と制限
    $suggestions = array_unique($suggestions);
    $suggestions = array_slice($suggestions, 0, 8);
    
    wp_send_json_success($suggestions);
}
add_action('wp_ajax_gi_mobile_search_suggestions', 'gi_mobile_search_suggestions');
add_action('wp_ajax_nopriv_gi_mobile_search_suggestions', 'gi_mobile_search_suggestions');

/**
 * 検索候補を生成する関数群
 */
function gi_get_recent_searches($query) {
    // セッションまたはcookieから最近の検索を取得
    // 実装は必要に応じてカスタマイズ
    return array();
}

function gi_get_taxonomy_suggestions($query) {
    $suggestions = array();
    
    // カテゴリから候補生成
    $categories = get_terms(array(
        'taxonomy' => 'grant_category',
        'search' => $query,
        'number' => 3,
        'hide_empty' => true
    ));
    
    foreach ($categories as $cat) {
        $suggestions[] = $cat->name . ' 助成金';
    }
    
    // 都道府県から候補生成
    $prefectures = get_terms(array(
        'taxonomy' => 'grant_prefecture', 
        'search' => $query,
        'number' => 3,
        'hide_empty' => true
    ));
    
    foreach ($prefectures as $pref) {
        $suggestions[] = $pref->name . ' ' . $query;
    }
    
    return $suggestions;
}

function gi_get_post_suggestions($query) {
    $posts = get_posts(array(
        'post_type' => 'grant',
        's' => $query,
        'posts_per_page' => 3,
        'post_status' => 'publish'
    ));
    
    $suggestions = array();
    foreach ($posts as $post) {
        // タイトルから重要な単語を抽出
        $title_words = explode(' ', $post->post_title);
        foreach ($title_words as $word) {
            if (strlen($word) > 2 && stripos($word, $query) !== false) {
                $suggestions[] = $word;
            }
        }
    }
    
    return array_unique($suggestions);
}

/**
 * モバイル専用のお気に入り機能
 */
function gi_mobile_toggle_favorite() {
    check_ajax_referer('gi_mobile_nonce', 'nonce');
    
    $post_id = intval($_POST['post_id'] ?? 0);
    $user_id = get_current_user_id();
    
    if (!$post_id) {
        wp_send_json_error('無効な投稿IDです');
    }
    
    if (!$user_id) {
        // 未ログインユーザーはセッションで管理
        $favorites = $_SESSION['gi_favorites'] ?? array();
        
        if (in_array($post_id, $favorites)) {
            $favorites = array_diff($favorites, array($post_id));
            $is_favorited = false;
        } else {
            $favorites[] = $post_id;
            $is_favorited = true;
        }
        
        $_SESSION['gi_favorites'] = $favorites;
    } else {
        // ログインユーザーはuser_metaで管理
        $favorites = get_user_meta($user_id, 'gi_favorites', true) ?: array();
        
        if (in_array($post_id, $favorites)) {
            $favorites = array_diff($favorites, array($post_id));
            $is_favorited = false;
        } else {
            $favorites[] = $post_id;
            $is_favorited = true;
        }
        
        update_user_meta($user_id, 'gi_favorites', $favorites);
    }
    
    wp_send_json_success(array(
        'is_favorited' => $is_favorited,
        'message' => $is_favorited ? 'お気に入りに追加しました' : 'お気に入りから削除しました'
    ));
}
add_action('wp_ajax_gi_mobile_toggle_favorite', 'gi_mobile_toggle_favorite');
add_action('wp_ajax_nopriv_gi_mobile_toggle_favorite', 'gi_mobile_toggle_favorite');

/**
 * PWA対応のmanifest.json生成
 */
function gi_generate_pwa_manifest() {
    if (!gi_is_mobile_device()) return;
    
    add_action('wp_head', function() {
        echo '<link rel="manifest" href="' . home_url('/manifest.json') . '">';
    });
}
add_action('init', 'gi_generate_pwa_manifest');

/**
 * Service Worker設定
 */
function gi_register_service_worker() {
    if (!gi_is_mobile_device()) return;
    
    add_action('wp_footer', function() {
        echo '<script>
            if ("serviceWorker" in navigator) {
                window.addEventListener("load", function() {
                    navigator.serviceWorker.register("/sw.js")
                        .then(function(registration) {
                            console.log("SW registered: ", registration);
                        })
                        .catch(function(registrationError) {
                            console.log("SW registration failed: ", registrationError);
                        });
                });
            }
        </script>';
    });
}
add_action('init', 'gi_register_service_worker');

/**
 * モバイル用のカード表示最適化
 */
if (!function_exists('gi_render_mobile_card')) {
    function gi_render_mobile_card($post_id, $show_excerpt = false) {
        $post = get_post($post_id);
        if (!$post) return '';
        
        $amount = gi_safe_get_meta($post_id, 'max_amount', '');
        $organization = gi_safe_get_meta($post_id, 'organization', '');
        $deadline = gi_safe_get_meta($post_id, 'deadline', '');
        $status = gi_safe_get_meta($post_id, 'application_status', 'open');
        
        // モバイル最適化されたカードHTML
        ob_start();
        ?>
        <article class="gi-grant-card-enhanced" data-post-id="<?php echo $post_id; ?>">
            <div class="gi-card-image-container">
                <?php if (has_post_thumbnail($post_id)): ?>
                    <img class="gi-card-image" 
                         src="<?php echo get_the_post_thumbnail_url($post_id, 'medium'); ?>" 
                         alt="<?php echo esc_attr($post->post_title); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="gi-card-placeholder">
                        <i class="fas fa-file-alt"></i>
                    </div>
                <?php endif; ?>
                
                <div class="gi-card-badges">
                    <?php if ($status === 'open'): ?>
                        <span class="gi-card-badge new">募集中</span>
                    <?php endif; ?>
                    
                    <?php if ($amount): ?>
                        <span class="gi-card-badge featured"><?php echo esc_html($amount); ?>万円</span>
                    <?php endif; ?>
                </div>
                
                <div class="gi-card-quick-actions">
                    <button class="gi-quick-action-btn" data-action="favorite" aria-label="お気に入り">
                        <i class="fas fa-heart"></i>
                    </button>
                    <button class="gi-quick-action-btn" data-action="share" aria-label="共有">
                        <i class="fas fa-share-alt"></i>
                    </button>
                </div>
            </div>
            
            <div class="gi-card-content">
                <h3 class="gi-card-title">
                    <a href="<?php echo get_permalink($post_id); ?>">
                        <?php echo esc_html($post->post_title); ?>
                    </a>
                </h3>
                
                <div class="gi-card-meta">
                    <?php if ($amount): ?>
                        <div class="gi-card-amount"><?php echo esc_html($amount); ?>万円</div>
                    <?php endif; ?>
                    
                    <?php if ($organization): ?>
                        <div class="gi-card-organization"><?php echo esc_html($organization); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($deadline): ?>
                        <div class="gi-card-deadline">
                            <i class="fas fa-clock"></i> 
                            <?php echo esc_html(gi_get_formatted_deadline($deadline)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($show_excerpt && $post->post_excerpt): ?>
                    <p class="gi-card-excerpt"><?php echo esc_html($post->post_excerpt); ?></p>
                <?php endif; ?>
                
                <div class="gi-card-actions">
                    <a href="<?php echo get_permalink($post_id); ?>" class="gi-btn-card-primary gi-ripple">
                        <i class="fas fa-eye"></i>
                        詳細を見る
                    </a>
                    <button class="gi-btn-card-secondary" data-action="quick-contact">
                        <i class="fas fa-phone"></i>
                        問い合わせ
                    </button>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }
}

/**
 * モバイル専用のページネーション
 */
if (!function_exists('gi_mobile_pagination')) {
    function gi_mobile_pagination($query = null) {
        global $wp_query;
        $query = $query ?: $wp_query;
        
        if ($query->max_num_pages <= 1) return '';
        
        $current_page = max(1, get_query_var('paged', 1));
        $max_pages = $query->max_num_pages;
        
        ob_start();
        ?>
        <div class="gi-mobile-pagination">
            <?php if ($current_page < $max_pages): ?>
                <button class="gi-load-more-btn gi-ripple" 
                        data-page="<?php echo $current_page + 1; ?>"
                        data-max-pages="<?php echo $max_pages; ?>">
                    <span class="gi-load-more-text">さらに表示</span>
                    <div class="gi-load-more-spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </button>
            <?php endif; ?>
            
            <div class="gi-pagination-info">
                <?php echo $current_page; ?> / <?php echo $max_pages; ?> ページ
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}