<?php
/**
 * Grant Insight Perfect - 2. Post Types & Taxonomies File
 *
 * サイトで使用するカスタム投稿タイプとカスタムタクソノミーを登録します。
 *
 * @package Grant_Insight_Perfect
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * カスタム投稿タイプ登録（完全版）
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
 * カスタムタクソノミー登録（完全版・都道府県対応・修正版）
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
    
    // ツールカテゴリー
    
    // 成功事例カテゴリー

    // 【修正】申請のコツカテゴリー（不足していたタクソノミー）
}
add_action('init', 'gi_register_taxonomies');

/**
 * 47都道府県の初期データを登録
 */
function gi_init_prefecture_terms() {
    // タクソノミーが存在するか確認
    if (!taxonomy_exists('grant_prefecture')) {
        return;
    }
    
    // 47都道府県の完全なリスト
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
// テーマ有効化時に実行
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
 * サンプルデータを作成する関数（テスト用）
 */
function gi_create_sample_grants() {
    // 管理者のみ実行可能
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // サンプルデータがすでに存在するか確認
    $existing = get_posts(array(
        'post_type' => 'grant',
        'posts_per_page' => 1,
        'post_status' => 'any'
    ));
    
    if (!empty($existing)) {
        return; // すでにデータが存在する場合は作成しない
    }
    
    // カテゴリを取得または作成
    $categories = array(
        'IT導入・デジタル化' => 'it-digital',
        'ものづくり・製造業' => 'manufacturing',
        '創業・スタートアップ' => 'startup',
        '小規模事業者' => 'small-business',
        '環境・省エネ' => 'environment',
        '人材育成・雇用' => 'employment'
    );
    
    foreach ($categories as $name => $slug) {
        if (!term_exists($slug, 'grant_category')) {
            wp_insert_term($name, 'grant_category', array('slug' => $slug));
        }
    }
    
    // サンプル助成金データ
    $sample_grants = array(
        array(
            'title' => 'IT導入補助金2024',
            'content' => '中小企業のITツール導入を支援する補助金です。',
            'category' => 'it-digital',
            'prefecture' => 'tokyo',
            'amount' => '450万円',
            'amount_numeric' => 4500000
        ),
        array(
            'title' => 'ものづくり補助金',
            'content' => '製造業の設備投資を支援する補助金です。',
            'category' => 'manufacturing',
            'prefecture' => 'osaka',
            'amount' => '1000万円',
            'amount_numeric' => 10000000
        ),
        array(
            'title' => '創業支援補助金',
            'content' => '新規創業者への支援補助金です。',
            'category' => 'startup',
            'prefecture' => 'fukuoka',
            'amount' => '200万円',
            'amount_numeric' => 2000000
        ),
        array(
            'title' => '小規模事業者持続化補助金',
            'content' => '小規模事業者の事業継続を支援します。',
            'category' => 'small-business',
            'prefecture' => 'aichi',
            'amount' => '200万円',
            'amount_numeric' => 2000000
        ),
        array(
            'title' => '省エネルギー補助金',
            'content' => '省エネ設備導入を支援する補助金です。',
            'category' => 'environment',
            'prefecture' => 'kanagawa',
            'amount' => '300万円',
            'amount_numeric' => 3000000
        )
    );
    
    // サンプル投稿を作成
    foreach ($sample_grants as $grant_data) {
        $post_id = wp_insert_post(array(
            'post_title' => $grant_data['title'],
            'post_content' => $grant_data['content'],
            'post_type' => 'grant',
            'post_status' => 'publish'
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            // カテゴリを設定
            $term = get_term_by('slug', $grant_data['category'], 'grant_category');
            if ($term) {
                wp_set_object_terms($post_id, $term->term_id, 'grant_category');
            }
            
            // 都道府県を設定
            $pref_term = get_term_by('slug', $grant_data['prefecture'], 'grant_prefecture');
            if ($pref_term) {
                wp_set_object_terms($post_id, $pref_term->term_id, 'grant_prefecture');
            }
            
            // メタデータを設定
            update_post_meta($post_id, 'max_amount', $grant_data['amount']);
            update_post_meta($post_id, 'max_amount_numeric', $grant_data['amount_numeric']);
            update_post_meta($post_id, 'application_status', 'open');
            update_post_meta($post_id, 'deadline', date('Y年m月d日', strtotime('+3 months')));
        }
    }
}
// テーマ有効化時に一度だけ実行
add_action('after_switch_theme', 'gi_create_sample_grants');