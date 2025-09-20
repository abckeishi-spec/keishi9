<?php
/**
 * Grant Semantic Search - 助成金特化セマンティック検索
 * 
 * 助成金データベースと完全に統合されたセマンティック検索エンジン
 *
 * @package Grant_Insight_Perfect
 * @version 3.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * 助成金セマンティック検索エンジン
 */
class GI_Grant_Semantic_Search {
    
    private static $instance = null;
    private $openai = null;
    private $cache_duration = 3600;
    
    /**
     * シングルトンインスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->openai = GI_OpenAI_Integration::getInstance();
        $this->init_hooks();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        // 助成金投稿が保存されたときにエンベディングを生成（管理画面のみ）
        if (is_admin()) {
            add_action('save_post_grant', [$this, 'update_grant_embedding'], 10, 3);
        }
        
        // 検索クエリフィルタは専用のAJAX経由でのみ実行（直接フックは使用しない）
        // これによりカスタマイザーやその他のクエリとの競合を完全に回避
    }
    
    /**
     * 検索クエリの強化（現在は未使用・AJAX経由でのみ実行）
     * 
     * このメソッドは互換性のために残していますが、
     * 実際の検索はAJAX経由（gi_semantic_search）で実行されます
     */
    public function enhance_grant_search($query) {
        // このメソッドは現在使用されていません
        // pre_get_postsフックとの競合を避けるため、
        // セマンティック検索はAJAX専用として実装しています
        return $query;
    }
    
    /**
     * 助成金データのセマンティック検索
     */
    public function search_grants($query, $filters = []) {
        // 1. クエリのエンベディング生成
        $query_embedding = $this->openai->generate_embedding($query);
        
        // 2. 意図解析
        $intent = $this->analyze_search_intent($query);
        
        // 3. キーワード抽出
        $keywords = $this->extract_keywords($query);
        
        // 4. ACFフィールドを含む検索条件構築
        $search_args = $this->build_search_args($intent, $keywords, $filters);
        
        // 5. セマンティック類似度計算を含む検索実行
        $results = $this->execute_semantic_search($query_embedding, $search_args);
        
        // 6. 結果のランキング調整
        $ranked_results = $this->rank_results($results, $query, $intent);
        
        // 7. 追加情報の付与
        return $this->enrich_results($ranked_results);
    }
    
    /**
     * 検索意図の解析
     */
    private function analyze_search_intent($query) {
        $intents = [
            'deadline' => ['締切', '期限', 'いつまで', '〆切', 'デッドライン'],
            'amount' => ['金額', '上限', '最大', '補助率', '助成額', '万円', '円'],
            'eligibility' => ['対象', '条件', '資格', '該当', '申請できる'],
            'industry' => ['業種', '業界', '分野', '事業', 'IT', '製造', '飲食', '小売'],
            'purpose' => ['目的', '用途', '使い道', '活用', '投資', '設備', '人材'],
            'region' => ['地域', '地区', 'エリア', '都道府県', '市区町村'],
            'new' => ['新規', '最新', '新しい', '今月', '今年度'],
            'popular' => ['人気', 'おすすめ', '注目', '話題', '評判'],
            'easy' => ['簡単', '楽', '手軽', '初心者', '初めて']
        ];
        
        $detected_intents = [];
        
        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($query, $keyword) !== false) {
                    $detected_intents[] = $intent;
                    break;
                }
            }
        }
        
        return $detected_intents;
    }
    
    /**
     * キーワード抽出
     */
    private function extract_keywords($query) {
        // 形態素解析の簡易実装
        $keywords = [];
        
        // 重要な名詞を抽出
        $patterns = [
            '/[ぁ-ん]+|[ァ-ヴー]+/u', // ひらがな・カタカナの連続
            '/[一-龠]+/u', // 漢字の連続
            '/[a-zA-Z0-9]+/' // 英数字
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $query, $matches);
            $keywords = array_merge($keywords, $matches[0]);
        }
        
        // ストップワード除去
        $stopwords = ['の', 'を', 'に', 'は', 'が', 'と', 'で', 'や', 'も', 'から', 'まで', 'として'];
        $keywords = array_diff($keywords, $stopwords);
        
        return array_values($keywords);
    }
    
    /**
     * 検索引数構築
     */
    private function build_search_args($intent, $keywords, $filters) {
        $args = [
            'post_type' => 'grant',
            'post_status' => 'publish',
            'posts_per_page' => 30,
            'meta_query' => ['relation' => 'AND'],
            'tax_query' => ['relation' => 'OR']
        ];
        
        // 締切日でのフィルタリング
        if (in_array('deadline', $intent)) {
            $args['meta_query'][] = [
                'key' => 'application_deadline',
                'value' => current_time('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            ];
            $args['orderby'] = 'meta_value';
            $args['meta_key'] = 'application_deadline';
            $args['order'] = 'ASC';
        }
        
        // 金額でのフィルタリング
        if (in_array('amount', $intent)) {
            // 金額を抽出
            preg_match('/(\d+)(万)?円/', implode(' ', $keywords), $amount_match);
            if ($amount_match) {
                $amount = intval($amount_match[1]);
                if (isset($amount_match[2]) && $amount_match[2] === '万') {
                    $amount *= 10000;
                }
                
                $args['meta_query'][] = [
                    'key' => 'max_grant_amount',
                    'value' => $amount,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ];
            }
        }
        
        // 業種でのフィルタリング
        if (in_array('industry', $intent)) {
            $industry_terms = $this->match_industry_terms($keywords);
            if (!empty($industry_terms)) {
                $args['tax_query'][] = [
                    'taxonomy' => 'grant_industry',
                    'field' => 'slug',
                    'terms' => $industry_terms
                ];
            }
        }
        
        // 地域でのフィルタリング
        if (in_array('region', $intent)) {
            $region_terms = $this->match_region_terms($keywords);
            if (!empty($region_terms)) {
                $args['tax_query'][] = [
                    'taxonomy' => 'grant_region',
                    'field' => 'slug',
                    'terms' => $region_terms
                ];
            }
        }
        
        // カスタムフィルター適用
        if (!empty($filters)) {
            $args = array_merge_recursive($args, $filters);
        }
        
        // キーワード検索
        if (!empty($keywords)) {
            $args['s'] = implode(' ', $keywords);
        }
        
        return $args;
    }
    
    /**
     * セマンティック検索実行
     */
    private function execute_semantic_search($query_embedding, $search_args) {
        global $wpdb;
        
        // 基本検索実行
        $query = new WP_Query($search_args);
        $posts = $query->posts;
        
        if (empty($posts)) {
            return [];
        }
        
        // エンベディングテーブルから類似度計算
        $results = [];
        foreach ($posts as $post) {
            $post_embedding = $this->get_post_embedding($post->ID);
            
            if ($post_embedding) {
                $similarity = $this->calculate_cosine_similarity($query_embedding, $post_embedding);
            } else {
                // エンベディングがない場合はTF-IDFで類似度計算
                $similarity = $this->calculate_text_similarity($query_embedding, $post);
            }
            
            $results[] = [
                'post' => $post,
                'similarity' => $similarity,
                'relevance_score' => $this->calculate_relevance_score($post, $search_args)
            ];
        }
        
        // 類似度でソート
        usort($results, function($a, $b) {
            $score_a = ($a['similarity'] * 0.6) + ($a['relevance_score'] * 0.4);
            $score_b = ($b['similarity'] * 0.6) + ($b['relevance_score'] * 0.4);
            return $score_b <=> $score_a;
        });
        
        return $results;
    }
    
    /**
     * 投稿のエンベディング取得
     */
    private function get_post_embedding($post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_vector_embeddings';
        $embedding = $wpdb->get_var($wpdb->prepare(
            "SELECT embedding FROM {$table} WHERE post_id = %d AND embedding_type = 'content'",
            $post_id
        ));
        
        if ($embedding) {
            return json_decode($embedding, true);
        }
        
        // エンベディングがない場合は生成
        $post = get_post($post_id);
        if ($post) {
            return $this->generate_and_save_embedding($post);
        }
        
        return null;
    }
    
    /**
     * エンベディング生成と保存
     */
    private function generate_and_save_embedding($post) {
        // 投稿内容とACFフィールドを結合
        $content = $this->prepare_grant_content($post);
        
        // エンベディング生成
        $embedding = $this->openai->generate_embedding($content);
        
        // データベースに保存
        global $wpdb;
        $table = $wpdb->prefix . 'gi_vector_embeddings';
        
        $wpdb->replace($table, [
            'post_id' => $post->ID,
            'embedding_type' => 'content',
            'embedding' => json_encode($embedding),
            'metadata' => json_encode([
                'generated_at' => current_time('mysql'),
                'content_hash' => md5($content)
            ])
        ]);
        
        return $embedding;
    }
    
    /**
     * 助成金コンテンツの準備
     */
    private function prepare_grant_content($post) {
        $content = $post->post_title . "\n" . $post->post_content;
        
        // ACFフィールドの追加
        if (function_exists('get_field')) {
            $acf_fields = [
                'grant_overview' => '概要',
                'target_business' => '対象事業者',
                'grant_amount' => '助成金額',
                'application_deadline' => '申請締切',
                'requirements' => '要件',
                'application_process' => '申請プロセス',
                'required_documents' => '必要書類',
                'contact_information' => '問い合わせ先'
            ];
            
            foreach ($acf_fields as $field => $label) {
                $value = get_field($field, $post->ID);
                if ($value) {
                    $content .= "\n{$label}: " . (is_array($value) ? implode(', ', $value) : $value);
                }
            }
        }
        
        // タクソノミー情報の追加
        $taxonomies = ['grant_category', 'grant_industry', 'grant_region', 'grant_purpose'];
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'names']);
            if (!empty($terms)) {
                $content .= "\n" . implode(', ', $terms);
            }
        }
        
        return $content;
    }
    
    /**
     * コサイン類似度計算
     */
    private function calculate_cosine_similarity($vec1, $vec2) {
        if (empty($vec1) || empty($vec2)) {
            return 0;
        }
        
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        $length = min(count($vec1), count($vec2));
        
        for ($i = 0; $i < $length; $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }
    
    /**
     * テキスト類似度計算（フォールバック）
     */
    private function calculate_text_similarity($query_embedding, $post) {
        $post_content = $this->prepare_grant_content($post);
        $post_embedding = $this->openai->generate_embedding($post_content);
        
        return $this->calculate_cosine_similarity($query_embedding, $post_embedding);
    }
    
    /**
     * 関連性スコア計算
     */
    private function calculate_relevance_score($post, $search_args) {
        $score = 0;
        
        // 締切日の近さでスコア加算
        if (function_exists('get_field')) {
            $deadline = get_field('application_deadline', $post->ID);
            if ($deadline) {
                $days_until_deadline = (strtotime($deadline) - time()) / (60 * 60 * 24);
                if ($days_until_deadline > 0 && $days_until_deadline <= 30) {
                    $score += (30 - $days_until_deadline) / 30 * 0.3;
                }
            }
            
            // 人気度（申請数）でスコア加算
            $application_count = get_field('application_count', $post->ID);
            if ($application_count) {
                $score += min($application_count / 1000, 0.2);
            }
            
            // 成功率でスコア加算
            $success_rate = get_field('success_rate', $post->ID);
            if ($success_rate) {
                $score += $success_rate / 100 * 0.2;
            }
        }
        
        // 更新日の新しさでスコア加算
        $days_since_modified = (time() - strtotime($post->post_modified)) / (60 * 60 * 24);
        if ($days_since_modified <= 7) {
            $score += (7 - $days_since_modified) / 7 * 0.1;
        }
        
        // コメント数でスコア加算
        $score += min($post->comment_count / 50, 0.1);
        
        // 閲覧数でスコア加算
        $views = get_post_meta($post->ID, 'post_views', true);
        if ($views) {
            $score += min($views / 10000, 0.1);
        }
        
        return min($score, 1.0); // 最大1.0に正規化
    }
    
    /**
     * 結果のランキング調整
     */
    private function rank_results($results, $query, $intent) {
        // 意図に基づく重み付け
        foreach ($results as &$result) {
            $post = $result['post'];
            $boost = 1.0;
            
            // 締切が近い場合は優先度を上げる
            if (in_array('deadline', $intent)) {
                $deadline = get_field('application_deadline', $post->ID);
                if ($deadline) {
                    $days_until = (strtotime($deadline) - time()) / (60 * 60 * 24);
                    if ($days_until > 0 && $days_until <= 14) {
                        $boost *= 1.5;
                    }
                }
            }
            
            // 簡単さを求めている場合
            if (in_array('easy', $intent)) {
                $difficulty = get_field('application_difficulty', $post->ID);
                if ($difficulty === 'easy') {
                    $boost *= 1.3;
                }
            }
            
            // 新規を求めている場合
            if (in_array('new', $intent)) {
                $days_since_publish = (time() - strtotime($post->post_date)) / (60 * 60 * 24);
                if ($days_since_publish <= 30) {
                    $boost *= 1.2;
                }
            }
            
            $result['final_score'] = (($result['similarity'] * 0.6) + ($result['relevance_score'] * 0.4)) * $boost;
        }
        
        // 最終スコアでソート
        usort($results, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });
        
        return $results;
    }
    
    /**
     * 結果の情報付与
     */
    private function enrich_results($results) {
        $enriched = [];
        
        foreach ($results as $result) {
            $post = $result['post'];
            
            $grant_data = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 50),
                'url' => get_permalink($post->ID),
                'score' => $result['final_score'],
                'similarity' => $result['similarity'],
                'relevance' => $result['relevance_score']
            ];
            
            // ACFフィールドの追加
            if (function_exists('get_field')) {
                $grant_data['deadline'] = get_field('application_deadline', $post->ID);
                $grant_data['amount'] = get_field('max_grant_amount', $post->ID);
                $grant_data['subsidy_rate'] = get_field('subsidy_rate', $post->ID);
                $grant_data['target'] = get_field('target_business', $post->ID);
                $grant_data['region'] = get_field('target_region', $post->ID);
                $grant_data['difficulty'] = get_field('application_difficulty', $post->ID);
                $grant_data['success_rate'] = get_field('success_rate', $post->ID);
                $grant_data['required_docs'] = get_field('required_documents', $post->ID);
            }
            
            // タクソノミー情報
            $grant_data['categories'] = wp_get_post_terms($post->ID, 'grant_category', ['fields' => 'names']);
            $grant_data['industries'] = wp_get_post_terms($post->ID, 'grant_industry', ['fields' => 'names']);
            $grant_data['purposes'] = wp_get_post_terms($post->ID, 'grant_purpose', ['fields' => 'names']);
            
            // メタ情報
            $grant_data['views'] = intval(get_post_meta($post->ID, 'post_views', true));
            $grant_data['bookmarks'] = intval(get_post_meta($post->ID, 'bookmark_count', true));
            $grant_data['applications'] = intval(get_post_meta($post->ID, 'application_count', true));
            
            $enriched[] = $grant_data;
        }
        
        return $enriched;
    }
    
    /**
     * 業種タームのマッチング
     */
    private function match_industry_terms($keywords) {
        $industry_map = [
            'it' => ['IT', 'ソフトウェア', 'システム', 'アプリ', 'Web'],
            'manufacturing' => ['製造', 'ものづくり', '工場', '生産'],
            'retail' => ['小売', '店舗', 'ショップ', '販売'],
            'food' => ['飲食', 'レストラン', 'カフェ', '食品'],
            'medical' => ['医療', '介護', 'ヘルスケア', '病院'],
            'education' => ['教育', '学習', 'スクール', '研修'],
            'construction' => ['建設', '建築', '土木', '工事'],
            'logistics' => ['物流', '運送', '配送', '倉庫']
        ];
        
        $matched = [];
        foreach ($keywords as $keyword) {
            foreach ($industry_map as $term => $synonyms) {
                foreach ($synonyms as $synonym) {
                    if (mb_stripos($keyword, $synonym) !== false) {
                        $matched[] = $term;
                        break 2;
                    }
                }
            }
        }
        
        return array_unique($matched);
    }
    
    /**
     * 地域タームのマッチング
     */
    private function match_region_terms($keywords) {
        // 都道府県リスト
        $prefectures = [
            '北海道', '青森', '岩手', '宮城', '秋田', '山形', '福島',
            '茨城', '栃木', '群馬', '埼玉', '千葉', '東京', '神奈川',
            '新潟', '富山', '石川', '福井', '山梨', '長野', '岐阜',
            '静岡', '愛知', '三重', '滋賀', '京都', '大阪', '兵庫',
            '奈良', '和歌山', '鳥取', '島根', '岡山', '広島', '山口',
            '徳島', '香川', '愛媛', '高知', '福岡', '佐賀', '長崎',
            '熊本', '大分', '宮崎', '鹿児島', '沖縄'
        ];
        
        $matched = [];
        foreach ($keywords as $keyword) {
            foreach ($prefectures as $prefecture) {
                if (mb_strpos($keyword, $prefecture) !== false) {
                    $matched[] = sanitize_title($prefecture);
                }
            }
        }
        
        return array_unique($matched);
    }
    
    /**
     * 助成金エンベディング更新
     */
    public function update_grant_embedding($post_id, $post, $update) {
        // 自動保存の場合はスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // 投稿タイプチェック
        if ($post->post_type !== 'grant') {
            return;
        }
        
        // 公開状態でない場合はスキップ
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // エンベディング生成と保存
        $this->generate_and_save_embedding($post);
    }
}

// インスタンス初期化（安全な遅延初期化）
add_action('init', function() {
    // OpenAI統合クラスが存在する場合のみ初期化
    if (class_exists('GI_OpenAI_Integration')) {
        // シングルトンインスタンスを生成（フックは内部で条件付きで登録）
        GI_Grant_Semantic_Search::getInstance();
    }
}, 999); // 最後に実行して他のプラグイン/テーマとの競合を避ける

// AJAX検索ハンドラー
add_action('wp_ajax_gi_semantic_search', 'gi_handle_semantic_search');
add_action('wp_ajax_nopriv_gi_semantic_search', 'gi_handle_semantic_search');

/**
 * セマンティック検索AJAXハンドラー
 */
function gi_handle_semantic_search() {
    // Nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_nonce')) {
        wp_json_error('セキュリティチェックに失敗しました。');
    }
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    $filters = json_decode(stripslashes($_POST['filters'] ?? '{}'), true);
    
    if (empty($query)) {
        wp_send_json_error('検索クエリが入力されていません。');
    }
    
    // セマンティック検索実行
    $search_engine = GI_Grant_Semantic_Search::getInstance();
    $results = $search_engine->search_grants($query, $filters);
    
    wp_send_json_success([
        'query' => $query,
        'count' => count($results),
        'results' => $results
    ]);
}