<?php
/**
 * Grant Insight Perfect - Core Setup File
 *
 * テーマの基本設定、投稿タイプ、タクソノミーを管理
 * 
 * @package Grant_Insight_Perfect
 * @version 8.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =============================================================================
 * 1. テーマ基本設定
 * =============================================================================
 */

/**
 * テーマ基本設定
 */
function gi_setup() {
    // 基本的なテーマサポート
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption'
    ));
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-width'  => true,
        'flex-height' => true,
    ));
    add_theme_support('automatic-feed-links');
    
    // 助成金関連の画像サイズ
    add_image_size('grant-thumbnail', 400, 300, true);
    add_image_size('grant-featured', 800, 450, true);
    
    // 言語ファイル
    load_theme_textdomain('grant-insight', get_template_directory() . '/languages');
    
    // メニュー登録
    register_nav_menus(array(
        'primary' => 'メインメニュー',
        'footer' => 'フッターメニュー'
    ));
}
add_action('after_setup_theme', 'gi_setup');

/**
 * コンテンツ幅設定
 */
function gi_content_width() {
    $GLOBALS['content_width'] = 1200;
}
add_action('after_setup_theme', 'gi_content_width', 0);

/**
 * スクリプト・スタイルの読み込み
 */
function gi_enqueue_scripts() {
    // メインスタイルシート
    wp_enqueue_style('gi-style', get_stylesheet_uri(), array(), GI_THEME_VERSION);
    
    // 統合されたメインCSS
    wp_enqueue_style('gi-main-css', get_template_directory_uri() . '/assets/css/main.css', array(), GI_THEME_VERSION);
    
    // Google Fonts（日本語フォント）
    wp_enqueue_style('google-fonts-noto', 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap', array(), null);
    
    // メインJavaScript
    wp_enqueue_script('gi-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), GI_THEME_VERSION, true);
    
    // AJAX設定
    wp_localize_script('gi-main', 'gi_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gi_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'gi_enqueue_scripts');

/**
 * ウィジェットエリア登録
 */
function gi_widgets_init() {
    // サイドバー
    register_sidebar(array(
        'name'          => 'サイドバー',
        'id'            => 'sidebar-1',
        'description'   => 'サイドバーウィジェットエリア',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    // フッター
    register_sidebar(array(
        'name'          => 'フッター',
        'id'            => 'footer-1',
        'description'   => 'フッターウィジェットエリア',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ));
}
add_action('widgets_init', 'gi_widgets_init');

/**
 * カスタマイザー設定
 */
function gi_customize_register($wp_customize) {
    // 助成金表示設定セクション
    $wp_customize->add_section('gi_grant_display', array(
        'title' => '助成金表示設定',
        'priority' => 30,
    ));
    
    // 1ページあたりの表示件数
    $wp_customize->add_setting('gi_grants_per_page', array(
        'default' => 12,
        'sanitize_callback' => 'absint',
    ));
    
    $wp_customize->add_control('gi_grants_per_page', array(
        'label' => '1ページあたりの表示件数',
        'section' => 'gi_grant_display',
        'type' => 'number',
        'input_attrs' => array(
            'min' => 6,
            'max' => 30,
            'step' => 3,
        ),
    ));
    
    // グリッド表示の列数
    $wp_customize->add_setting('gi_grid_columns', array(
        'default' => 3,
        'sanitize_callback' => 'absint',
    ));
    
    $wp_customize->add_control('gi_grid_columns', array(
        'label' => 'グリッド表示の列数',
        'section' => 'gi_grant_display',
        'type' => 'select',
        'choices' => array(
            2 => '2列',
            3 => '3列',
            4 => '4列',
        ),
    ));
}
add_action('customize_register', 'gi_customize_register');

/**
 * 助成金検索機能の強化
 */
function gi_enhance_grant_search($query) {
    if (!is_admin() && $query->is_main_query()) {
        // 助成金アーカイブページの表示件数
        if (is_post_type_archive('grant') || is_tax('grant_category') || is_tax('grant_prefecture')) {
            $per_page = get_theme_mod('gi_grants_per_page', 12);
            $query->set('posts_per_page', $per_page);
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
        
        // 検索結果に助成金を含める
        if ($query->is_search()) {
            $post_types = $query->get('post_type');
            if (empty($post_types)) {
                $query->set('post_type', array('post', 'grant'));
            }
        }
    }
}
add_action('pre_get_posts', 'gi_enhance_grant_search');

/**
 * セキュリティ強化
 */
function gi_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}
add_action('send_headers', 'gi_security_headers');

// 不要なヘッダー情報を削除
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

/**
 * =============================================================================
 * 2. カスタム投稿タイプ・タクソノミー
 * =============================================================================
 */

/**
 * カスタム投稿タイプ登録
 */
function gi_register_post_types() {
    // 助成金投稿タイプ
    register_post_type('grant', array(
        'labels' => array(
            'name' => '助成金・補助金',
            'singular_name' => '助成金・補助金',
            'add_new' => '新規追加',
            'add_new_item' => '新しい助成金・補助金を追加',
            'edit_item' => '助成金・補助金を編集',
            'new_item' => '新しい助成金・補助金',
            'view_item' => '助成金・補助金を表示',
            'search_items' => '助成金・補助金を検索',
            'not_found' => '助成金・補助金が見つかりませんでした',
            'not_found_in_trash' => 'ゴミ箱に助成金・補助金はありません',
            'all_items' => 'すべての助成金・補助金',
            'menu_name' => '助成金・補助金'
        ),
        'description' => '助成金・補助金情報を管理します',
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grants',
            'with_front' => false
        ),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-money-alt',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'),
        'show_in_rest' => true
    ));
}
add_action('init', 'gi_register_post_types');

/**
 * カスタムタクソノミー登録
 */
function gi_register_taxonomies() {
    // 助成金カテゴリー
    register_taxonomy('grant_category', 'grant', array(
        'labels' => array(
            'name' => '助成金カテゴリー',
            'singular_name' => '助成金カテゴリー',
            'search_items' => 'カテゴリーを検索',
            'all_items' => 'すべてのカテゴリー',
            'parent_item' => '親カテゴリー',
            'parent_item_colon' => '親カテゴリー:',
            'edit_item' => 'カテゴリーを編集',
            'update_item' => 'カテゴリーを更新',
            'add_new_item' => '新しいカテゴリーを追加',
            'new_item_name' => '新しいカテゴリー名'
        ),
        'description' => '助成金・補助金をカテゴリー別に分類します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grant-category',
            'with_front' => false,
            'hierarchical' => true
        )
    ));
    
    // 都道府県タクソノミー
    register_taxonomy('grant_prefecture', 'grant', array(
        'labels' => array(
            'name' => '対象都道府県',
            'singular_name' => '都道府県',
            'search_items' => '都道府県を検索',
            'all_items' => 'すべての都道府県',
            'edit_item' => '都道府県を編集',
            'update_item' => '都道府県を更新',
            'add_new_item' => '新しい都道府県を追加',
            'new_item_name' => '新しい都道府県名'
        ),
        'description' => '助成金・補助金の対象都道府県を管理します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'prefecture',
            'with_front' => false
        )
    ));
    
    // 助成金タグ
    register_taxonomy('grant_tag', 'grant', array(
        'labels' => array(
            'name' => '助成金タグ',
            'singular_name' => '助成金タグ',
            'search_items' => 'タグを検索',
            'all_items' => 'すべてのタグ',
            'edit_item' => 'タグを編集',
            'update_item' => 'タグを更新',
            'add_new_item' => '新しいタグを追加',
            'new_item_name' => '新しいタグ名'
        ),
        'description' => '助成金・補助金をタグで分類します',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_tagcloud' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'grant-tag',
            'with_front' => false
        )
    ));
}
add_action('init', 'gi_register_taxonomies');

/**
 * パーマリンク設定の強制フラッシュ
 */
function gi_flush_rewrite_rules() {
    // カスタム投稿タイプとタクソノミーが登録された後にパーマリンクを更新
    flush_rewrite_rules();
}
// テーマが有効化された時のみ実行
add_action('after_switch_theme', 'gi_flush_rewrite_rules');

/**
 * 投稿タイプとタクソノミーのパーマリンク修正
 */
function gi_fix_grant_permalinks() {
    global $wp_rewrite;
    
    // カスタム投稿タイプのrewrite ruleを追加
    add_rewrite_rule(
        'grants/?$', 
        'index.php?post_type=grant', 
        'top'
    );
    
    add_rewrite_rule(
        'grants/page/?([0-9]{1,})/?$', 
        'index.php?post_type=grant&paged=$matches[1]', 
        'top'
    );
    
    add_rewrite_rule(
        'grants/([^/]+)/?$', 
        'index.php?post_type=grant&name=$matches[1]', 
        'top'
    );
}
add_action('init', 'gi_fix_grant_permalinks', 11);

/**
 * =============================================================================
 * 3. 都道府県データ初期化
 * =============================================================================
 */

/**
 * 47都道府県の初期データを登録
 */
function gi_init_prefecture_terms() {
    // タクソノミーが存在するか確認
    if (!taxonomy_exists('grant_prefecture')) {
        return;
    }
    
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
    
    // 各都道府県を登録
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
add_action('after_setup_theme', 'gi_init_prefecture_terms');

/**
 * 都道府県データを取得するヘルパー関数
 */
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

/**
 * =============================================================================
 * 4. ユーティリティ関数
 * =============================================================================
 */

/**
 * パンくずリスト生成関数
 */
function gi_breadcrumbs() {
    if (is_front_page()) return;
    
    echo '<nav class="breadcrumbs">';
    echo '<a href="' . home_url() . '">ホーム</a>';
    
    if (is_post_type_archive('grant')) {
        echo ' > 助成金・補助金一覧';
    } elseif (is_tax('grant_category')) {
        echo ' > <a href="' . get_post_type_archive_link('grant') . '">助成金・補助金一覧</a>';
        echo ' > ' . single_term_title('', false);
    } elseif (is_tax('grant_prefecture')) {
        echo ' > <a href="' . get_post_type_archive_link('grant') . '">助成金・補助金一覧</a>';
        echo ' > ' . single_term_title('', false);
    } elseif (is_singular('grant')) {
        echo ' > <a href="' . get_post_type_archive_link('grant') . '">助成金・補助金一覧</a>';
        echo ' > ' . get_the_title();
    } elseif (is_page()) {
        echo ' > ' . get_the_title();
    } elseif (is_single()) {
        $categories = get_the_category();
        if ($categories) {
            echo ' > <a href="' . get_category_link($categories[0]->term_id) . '">' . $categories[0]->name . '</a>';
        }
        echo ' > ' . get_the_title();
    }
    
    echo '</nav>';
}

/**
 * ページネーション関数
 */
function gi_pagination($pages = '') {
    global $paged;
    
    if (empty($paged)) $paged = 1;
    
    if ($pages == '') {
        global $wp_query;
        $pages = $wp_query->max_num_pages;
        if (!$pages) $pages = 1;
    }
    
    if ($pages != 1) {
        echo '<div class="pagination">';
        
        if ($paged > 1) {
            echo '<a href="' . get_pagenum_link($paged - 1) . '" class="prev">前へ</a>';
        }
        
        for ($i = 1; $i <= $pages; $i++) {
            if ($paged == $i) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                echo '<a href="' . get_pagenum_link($i) . '">' . $i . '</a>';
            }
        }
        
        if ($paged < $pages) {
            echo '<a href="' . get_pagenum_link($paged + 1) . '" class="next">次へ</a>';
        }
        
        echo '</div>';
    }
}

/**
 * 管理画面用のスタイル
 */
function gi_admin_styles() {
    echo '<style>
        #adminmenu .menu-icon-grant div.wp-menu-image:before {
            content: "\f155";
        }
        .grant-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .grant-status-active { background: #7ad03a; color: white; }
        .grant-status-soon { background: #ffba00; color: white; }
        .grant-status-expired { background: #dd3333; color: white; }
    </style>';
}
add_action('admin_head', 'gi_admin_styles');

/**
 * デバッグ: テンプレート階層の表示（開発時のみ）
 */
function gi_debug_template_hierarchy($template) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $queried_object = get_queried_object();
        
        error_log('=== Template Debug ===');
        error_log('Current template: ' . $template);
        error_log('Is archive: ' . (is_archive() ? 'YES' : 'NO'));
        error_log('Is post type archive: ' . (is_post_type_archive() ? 'YES' : 'NO'));
        error_log('Is post type archive grant: ' . (is_post_type_archive('grant') ? 'YES' : 'NO'));
        error_log('Is singular: ' . (is_singular() ? 'YES' : 'NO'));
        error_log('Is singular grant: ' . (is_singular('grant') ? 'YES' : 'NO'));
        error_log('Current post type: ' . get_post_type());
        
        if ($queried_object) {
            error_log('Queried object: ' . json_encode([
                'ID' => $queried_object->ID ?? 'N/A',
                'post_type' => $queried_object->post_type ?? 'N/A',
                'name' => $queried_object->name ?? 'N/A'
            ]));
        }
        
        error_log('Available templates: ' . json_encode([
            'archive-grant.php' => file_exists(get_template_directory() . '/archive-grant.php'),
            'single-grant.php' => file_exists(get_template_directory() . '/single-grant.php'),
            'archive.php' => file_exists(get_template_directory() . '/archive.php'),
            'index.php' => file_exists(get_template_directory() . '/index.php')
        ]));
        error_log('======================');
    }
    
    return $template;
}
add_filter('template_include', 'gi_debug_template_hierarchy');

/**
 * 助成金URLの確実な処理
 */
function gi_ensure_grant_queries($query) {
    if (!is_admin() && $query->is_main_query()) {
        // /grants/ のアクセスを確実に助成金アーカイブとして処理
        if ($query->get('pagename') === 'grants' || 
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/grants') === 0)) {
            $query->set('post_type', 'grant');
            $query->set('pagename', '');
            $query->is_page = false;
            $query->is_post_type_archive = true;
        }
    }
}
add_action('pre_get_posts', 'gi_ensure_grant_queries', 1);