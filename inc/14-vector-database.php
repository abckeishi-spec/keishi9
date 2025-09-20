<?php
/**
 * Vector Database for Semantic Search
 * 
 * 簡易的なベクトルデータベース実装
 * 実際の本番環境では、Pinecone, Weaviate, Qdrantなどを使用推奨
 *
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * ベクトルデータベースクラス
 */
class GI_Vector_Database {
    
    private $table_name;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'gi_vector_embeddings';
        $this->init_table();
    }
    
    /**
     * テーブル初期化
     */
    private function init_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            embedding_type varchar(50) DEFAULT 'content',
            embedding longtext NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY embedding_type (embedding_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * ベクトル保存
     */
    public function store($post_id, $embedding, $type = 'content', $metadata = null) {
        // 既存のエンベディングを確認
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE post_id = %d AND embedding_type = %s",
            $post_id,
            $type
        ));
        
        $embedding_json = json_encode($embedding);
        $metadata_json = $metadata ? json_encode($metadata) : null;
        
        if ($existing) {
            // 更新
            return $this->wpdb->update(
                $this->table_name,
                [
                    'embedding' => $embedding_json,
                    'metadata' => $metadata_json,
                    'updated_at' => current_time('mysql')
                ],
                [
                    'id' => $existing
                ]
            );
        } else {
            // 新規作成
            return $this->wpdb->insert(
                $this->table_name,
                [
                    'post_id' => $post_id,
                    'embedding_type' => $type,
                    'embedding' => $embedding_json,
                    'metadata' => $metadata_json,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            );
        }
    }
    
    /**
     * ベクトル検索
     */
    public function search($query_embedding, $limit = 10, $type = 'content', $threshold = 0.5) {
        // すべてのベクトルを取得（簡易実装）
        $vectors = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT post_id, embedding, metadata 
             FROM {$this->table_name} 
             WHERE embedding_type = %s",
            $type
        ));
        
        if (empty($vectors)) {
            return [];
        }
        
        $results = [];
        
        foreach ($vectors as $vector) {
            $stored_embedding = json_decode($vector->embedding, true);
            
            // コサイン類似度計算
            $similarity = $this->cosine_similarity($query_embedding, $stored_embedding);
            
            if ($similarity >= $threshold) {
                $results[] = [
                    'post_id' => $vector->post_id,
                    'similarity' => $similarity,
                    'metadata' => json_decode($vector->metadata, true)
                ];
            }
        }
        
        // 類似度でソート
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $limit);
    }
    
    /**
     * コサイン類似度計算
     */
    private function cosine_similarity($vec1, $vec2) {
        if (empty($vec1) || empty($vec2)) {
            return 0;
        }
        
        // 簡易的な実装（実際は最適化が必要）
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        $count = min(count($vec1), count($vec2));
        
        for ($i = 0; $i < $count; $i++) {
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
     * ベクトル削除
     */
    public function delete($post_id, $type = null) {
        if ($type) {
            return $this->wpdb->delete(
                $this->table_name,
                [
                    'post_id' => $post_id,
                    'embedding_type' => $type
                ]
            );
        } else {
            return $this->wpdb->delete(
                $this->table_name,
                ['post_id' => $post_id]
            );
        }
    }
    
    /**
     * 全ベクトル削除
     */
    public function clear() {
        return $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
    
    /**
     * ベクトル取得
     */
    public function get($post_id, $type = 'content') {
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND embedding_type = %s",
            $post_id,
            $type
        ));
        
        if ($result) {
            $result->embedding = json_decode($result->embedding, true);
            $result->metadata = json_decode($result->metadata, true);
        }
        
        return $result;
    }
    
    /**
     * 統計情報取得
     */
    public function get_stats() {
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        $by_type = $this->wpdb->get_results(
            "SELECT embedding_type, COUNT(*) as count 
             FROM {$this->table_name} 
             GROUP BY embedding_type"
        );
        
        return [
            'total' => $total,
            'by_type' => $by_type,
            'table_name' => $this->table_name
        ];
    }
}

/**
 * 簡易的なエンベディング生成（実際はOpenAI APIなどを使用）
 */
class GI_Embedding_Generator {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * テキストからエンベディング生成（簡易版）
     */
    public function generate($text) {
        // 実際の実装ではOpenAI Embeddings APIを使用
        // ここでは簡易的なTF-IDFベースの実装
        
        $words = $this->tokenize($text);
        $vector = $this->create_tfidf_vector($words);
        
        return $vector;
    }
    
    /**
     * トークン化
     */
    private function tokenize($text) {
        // 日本語対応の簡易トークン化
        $text = mb_strtolower($text);
        
        // 記号除去
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // 空白で分割
        $words = preg_split('/\s+/u', $text);
        
        return array_filter($words);
    }
    
    /**
     * TF-IDFベクトル作成（簡易版）
     */
    private function create_tfidf_vector($words) {
        // 語彙辞書を取得（キャッシュから）
        $vocabulary = $this->get_vocabulary();
        
        // ベクトル初期化
        $vector = array_fill(0, count($vocabulary), 0);
        
        // 単語頻度計算
        $word_counts = array_count_values($words);
        $total_words = count($words);
        
        foreach ($word_counts as $word => $count) {
            if (isset($vocabulary[$word])) {
                $tf = $count / $total_words;
                $idf = $this->get_idf($word);
                $vector[$vocabulary[$word]] = $tf * $idf;
            }
        }
        
        // 正規化
        $vector = $this->normalize_vector($vector);
        
        return $vector;
    }
    
    /**
     * 語彙辞書取得
     */
    private function get_vocabulary() {
        $vocab = get_transient('gi_embedding_vocabulary');
        
        if (false === $vocab) {
            $vocab = $this->build_vocabulary();
            set_transient('gi_embedding_vocabulary', $vocab, DAY_IN_SECONDS);
        }
        
        return $vocab;
    }
    
    /**
     * 語彙辞書構築
     */
    private function build_vocabulary() {
        $vocab = [];
        $index = 0;
        
        // 助成金関連の重要キーワード
        $important_words = [
            '助成金', '補助金', '支援金', '給付金',
            '中小企業', '個人事業主', 'スタートアップ',
            '設備投資', '人材育成', '研究開発', 'IT化',
            '申請', '締切', '対象', '条件', '金額'
        ];
        
        foreach ($important_words as $word) {
            $vocab[$word] = $index++;
        }
        
        // 投稿から追加の語彙を抽出
        $posts = get_posts([
            'post_type' => 'grant',
            'posts_per_page' => 100,
            'post_status' => 'publish'
        ]);
        
        foreach ($posts as $post) {
            $text = $post->post_title . ' ' . $post->post_content;
            $words = $this->tokenize($text);
            
            foreach ($words as $word) {
                if (!isset($vocab[$word]) && $index < 1000) { // 語彙サイズ制限
                    $vocab[$word] = $index++;
                }
            }
        }
        
        return $vocab;
    }
    
    /**
     * IDF値取得
     */
    private function get_idf($word) {
        // 簡易的なIDF値（実際は文書頻度から計算）
        $common_words = ['の', 'に', 'を', 'は', 'が', 'と', 'で', 'て', 'た'];
        
        if (in_array($word, $common_words)) {
            return 0.1;
        }
        
        return 1.0;
    }
    
    /**
     * ベクトル正規化
     */
    private function normalize_vector($vector) {
        $norm = 0;
        
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        
        if ($norm == 0) {
            return $vector;
        }
        
        $norm = sqrt($norm);
        
        foreach ($vector as &$value) {
            $value /= $norm;
        }
        
        return $vector;
    }
}

/**
 * 助成金テキスト準備
 */
function gi_prepare_grant_text_for_embedding($post) {
    $text = '';
    
    // タイトル
    $text .= $post->post_title . ' ';
    
    // 内容
    $text .= wp_strip_all_tags($post->post_content) . ' ';
    
    // ACFフィールド
    if (function_exists('get_field')) {
        $fields = [
            'target_industry' => '対象業種',
            'target_region' => '対象地域',
            'requirements' => '申請要件',
            'purpose' => '目的',
            'max_amount' => '最大金額'
        ];
        
        foreach ($fields as $field => $label) {
            $value = get_field($field, $post->ID);
            if ($value) {
                $text .= $label . ': ' . $value . ' ';
            }
        }
    }
    
    // タクソノミー
    $categories = wp_get_post_terms($post->ID, 'grant_category', ['fields' => 'names']);
    if (!empty($categories)) {
        $text .= 'カテゴリー: ' . implode(' ', $categories) . ' ';
    }
    
    $prefectures = wp_get_post_terms($post->ID, 'grant_prefecture', ['fields' => 'names']);
    if (!empty($prefectures)) {
        $text .= '都道府県: ' . implode(' ', $prefectures) . ' ';
    }
    
    return $text;
}

// ベクトルデータベース更新フック
add_action('save_post_grant', function($post_id) {
    // 自動保存の場合は処理しない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    $post = get_post($post_id);
    if ($post->post_status !== 'publish') {
        return;
    }
    
    // テキスト準備
    $text = gi_prepare_grant_text_for_embedding($post);
    
    // エンベディング生成
    $generator = GI_Embedding_Generator::getInstance();
    $embedding = $generator->generate($text);
    
    // ベクトルデータベースに保存
    $vector_db = new GI_Vector_Database();
    $vector_db->store($post_id, $embedding, 'content', [
        'title' => $post->post_title,
        'updated' => current_time('mysql')
    ]);
}, 10, 1);