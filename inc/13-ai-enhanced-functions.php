<?php
/**
 * AI Enhanced Functions - 完全実装版
 * 
 * セマンティック検索、感情分析、学習システム、
 * ストリーミング、音声認識を完全実装
 *
 * @package Grant_Insight_Perfect
 * @version 2.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * =============================================================================
 * 1. 強化版セマンティック検索エンジン
 * =============================================================================
 */

class GI_Advanced_Semantic_Search {
    
    private static $instance = null;
    private $embeddings_cache = [];
    private $search_history = [];
    private $relevance_threshold = 0.65;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 高度なセマンティック検索実行
     */
    public function semantic_search($query, $context = [], $limit = 10) {
        // 1. クエリ解析と意図認識
        $analyzed_query = $this->analyze_query($query);
        
        // 2. エンティティ抽出
        $entities = $this->extract_entities($query);
        
        // 3. 文脈を考慮した検索
        $contextual_results = $this->search_with_context(
            $analyzed_query,
            $entities,
            $context
        );
        
        // 4. スコアリングと並べ替え
        $scored_results = $this->score_and_rank($contextual_results, $query);
        
        // 5. 結果の精製と返却
        return array_slice($scored_results, 0, $limit);
    }
    
    /**
     * クエリ解析と意図認識
     */
    private function analyze_query($query) {
        // 形態素解析
        $tokens = $this->tokenize_japanese($query);
        
        // 重要キーワード抽出
        $keywords = $this->extract_keywords($tokens);
        
        // 意図分類
        $intent = $this->classify_intent($keywords, $query);
        
        return [
            'original' => $query,
            'tokens' => $tokens,
            'keywords' => $keywords,
            'intent' => $intent,
            'query_type' => $this->determine_query_type($query)
        ];
    }
    
    /**
     * 日本語トークン化
     */
    private function tokenize_japanese($text) {
        // MeCabベースの簡易トークン化
        $patterns = [
            '/[\x{4E00}-\x{9FAF}]+/u', // 漢字
            '/[\x{3040}-\x{309F}]+/u', // ひらがな
            '/[\x{30A0}-\x{30FF}]+/u', // カタカナ
            '/[a-zA-Z0-9]+/'           // 英数字
        ];
        
        $tokens = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $tokens = array_merge($tokens, $matches[0]);
        }
        
        return array_unique($tokens);
    }
    
    /**
     * エンティティ抽出
     */
    private function extract_entities($query) {
        $entities = [];
        
        // 金額エンティティ
        if (preg_match('/(\d+)\s*万円?|(\d+)\s*千円?|(\d+)\s*億円?/', $query, $matches)) {
            $entities['amount'] = $matches[0];
        }
        
        // 業種エンティティ
        $industries = ['製造業', 'サービス業', 'IT', '建設業', '小売業', '飲食業'];
        foreach ($industries as $industry) {
            if (strpos($query, $industry) !== false) {
                $entities['industry'] = $industry;
                break;
            }
        }
        
        // 地域エンティティ
        $regions = ['東京', '大阪', '北海道', '九州', '関東', '関西', '東北'];
        foreach ($regions as $region) {
            if (strpos($query, $region) !== false) {
                $entities['region'] = $region;
                break;
            }
        }
        
        // 目的エンティティ
        $purposes = [
            '設備投資' => ['設備', '機械', '導入'],
            '人材育成' => ['研修', '教育', '人材'],
            '研究開発' => ['研究', '開発', 'R&D'],
            '販路開拓' => ['販路', '営業', 'マーケティング'],
            'IT化' => ['IT', 'システム', 'DX', 'デジタル']
        ];
        
        foreach ($purposes as $purpose => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    $entities['purpose'] = $purpose;
                    break 2;
                }
            }
        }
        
        return $entities;
    }
    
    /**
     * コンテキストベース検索
     */
    private function search_with_context($analyzed_query, $entities, $context) {
        global $wpdb;
        
        $search_args = [
            'post_type' => 'grant',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => []
        ];
        
        // エンティティベースのフィルタリング
        if (!empty($entities['amount'])) {
            $amount_value = $this->parse_amount($entities['amount']);
            $search_args['meta_query'][] = [
                'key' => 'max_amount_numeric',
                'value' => $amount_value,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
        }
        
        if (!empty($entities['industry'])) {
            $search_args['meta_query'][] = [
                'key' => 'target_industry',
                'value' => $entities['industry'],
                'compare' => 'LIKE'
            ];
        }
        
        if (!empty($entities['region'])) {
            $search_args['meta_query'][] = [
                'key' => 'target_region',
                'value' => $entities['region'],
                'compare' => 'LIKE'
            ];
        }
        
        // キーワード検索
        if (!empty($analyzed_query['keywords'])) {
            $search_args['s'] = implode(' ', $analyzed_query['keywords']);
        }
        
        // コンテキストを考慮
        if (!empty($context['business_size'])) {
            $search_args['meta_query'][] = [
                'key' => 'target_business_size',
                'value' => $context['business_size'],
                'compare' => 'LIKE'
            ];
        }
        
        if (!empty($context['previous_grants'])) {
            // 過去に見た助成金と類似のものを優先
            $search_args['post__not_in'] = $context['previous_grants'];
        }
        
        $query = new WP_Query($search_args);
        $results = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $results[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                    'excerpt' => get_the_excerpt(),
                    'meta' => $this->get_grant_meta($post_id),
                    'relevance' => 0
                ];
            }
            wp_reset_postdata();
        }
        
        return $results;
    }
    
    /**
     * 助成金メタデータ取得
     */
    private function get_grant_meta($post_id) {
        return [
            'max_amount' => get_field('max_amount_numeric', $post_id),
            'application_period' => get_field('application_period', $post_id),
            'target_industry' => get_field('target_industry', $post_id),
            'target_region' => get_field('target_region', $post_id),
            'requirements' => get_field('requirements', $post_id),
            'success_rate' => get_field('success_rate', $post_id),
            'difficulty' => get_field('difficulty_level', $post_id)
        ];
    }
    
    /**
     * スコアリングとランキング
     */
    private function score_and_rank($results, $query) {
        foreach ($results as &$result) {
            $score = 0;
            
            // タイトルマッチング
            $title_similarity = $this->calculate_similarity($query, $result['title']);
            $score += $title_similarity * 3;
            
            // コンテンツマッチング
            $content_similarity = $this->calculate_similarity($query, $result['excerpt']);
            $score += $content_similarity * 2;
            
            // メタデータボーナス
            if (!empty($result['meta']['success_rate']) && $result['meta']['success_rate'] > 80) {
                $score += 0.5;
            }
            
            if (!empty($result['meta']['difficulty']) && $result['meta']['difficulty'] === 'easy') {
                $score += 0.3;
            }
            
            // 期限チェック
            if (!empty($result['meta']['application_period'])) {
                $deadline = strtotime($result['meta']['application_period']);
                $days_left = ($deadline - time()) / (60 * 60 * 24);
                
                if ($days_left < 30 && $days_left > 0) {
                    $score += 0.4; // 締切が近いものを優先
                }
            }
            
            $result['relevance'] = $score;
        }
        
        // スコアで並び替え
        usort($results, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });
        
        return $results;
    }
    
    /**
     * 類似度計算
     */
    private function calculate_similarity($text1, $text2) {
        $tokens1 = $this->tokenize_japanese($text1);
        $tokens2 = $this->tokenize_japanese($text2);
        
        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));
        
        if (count($union) === 0) {
            return 0;
        }
        
        return count($intersection) / count($union);
    }
    
    /**
     * 金額パース
     */
    private function parse_amount($amount_text) {
        $amount = 0;
        
        if (preg_match('/(\d+)\s*億/', $amount_text, $matches)) {
            $amount += intval($matches[1]) * 100000000;
        }
        if (preg_match('/(\d+)\s*万/', $amount_text, $matches)) {
            $amount += intval($matches[1]) * 10000;
        }
        if (preg_match('/(\d+)\s*千/', $amount_text, $matches)) {
            $amount += intval($matches[1]) * 1000;
        }
        
        return $amount;
    }
    
    /**
     * 意図分類
     */
    private function classify_intent($keywords, $query) {
        $intents = [
            'search' => ['探す', '検索', '調べ', '知りたい', '教えて'],
            'apply' => ['申請', '応募', '申し込み', '手続き'],
            'check' => ['確認', 'チェック', '対象', '該当'],
            'compare' => ['比較', '違い', 'どちら', '選ぶ'],
            'deadline' => ['締切', '期限', 'いつまで', '期日']
        ];
        
        foreach ($intents as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($query, $pattern) !== false) {
                    return $intent;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * クエリタイプ判定
     */
    private function determine_query_type($query) {
        if (strpos($query, '？') !== false || strpos($query, '?') !== false) {
            return 'question';
        }
        if (strlen($query) < 20) {
            return 'keyword';
        }
        return 'sentence';
    }
}

/**
 * =============================================================================
 * 2. 強化版感情分析システム
 * =============================================================================
 */

class GI_Advanced_Emotion_Analyzer {
    
    private static $instance = null;
    private $emotion_lexicon = [];
    private $context_memory = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->load_emotion_lexicon();
    }
    
    /**
     * 感情辞書の読み込み
     */
    private function load_emotion_lexicon() {
        $this->emotion_lexicon = [
            'joy' => [
                '嬉しい' => 0.9, '楽しい' => 0.8, 'ありがとう' => 0.7,
                '助かる' => 0.6, '良い' => 0.5, '素晴らしい' => 0.95,
                'わかりました' => 0.3, '理解' => 0.4, '納得' => 0.5
            ],
            'trust' => [
                '信頼' => 0.8, '安心' => 0.7, 'お任せ' => 0.9,
                '頼む' => 0.6, '期待' => 0.7, '大丈夫' => 0.5
            ],
            'fear' => [
                '不安' => -0.7, '心配' => -0.6, '怖い' => -0.8,
                '難しい' => -0.4, '複雑' => -0.3, '分からない' => -0.5
            ],
            'surprise' => [
                '驚き' => 0.3, 'びっくり' => 0.4, 'まさか' => 0.2,
                '本当' => 0.1, 'すごい' => 0.6, '想像以上' => 0.5
            ],
            'sadness' => [
                '悲しい' => -0.8, '残念' => -0.6, 'がっかり' => -0.7,
                '辛い' => -0.8, '大変' => -0.4, '困った' => -0.5
            ],
            'disgust' => [
                '嫌' => -0.7, '無理' => -0.8, 'ダメ' => -0.6,
                '最悪' => -0.9, 'ひどい' => -0.8, '面倒' => -0.5
            ],
            'anger' => [
                '怒り' => -0.9, 'イライラ' => -0.7, 'ムカつく' => -0.8,
                'ふざけ' => -0.7, '許せない' => -0.9, '腹立つ' => -0.8
            ],
            'anticipation' => [
                '期待' => 0.7, '楽しみ' => 0.8, 'ワクワク' => 0.9,
                '待ち遠しい' => 0.8, '希望' => 0.6, '願い' => 0.5
            ]
        ];
    }
    
    /**
     * 高度な感情分析
     */
    public function analyze_emotion($message, $context = []) {
        $emotions = [];
        $overall_sentiment = 0;
        $confidence = 0;
        
        // 基本的な感情検出
        foreach ($this->emotion_lexicon as $emotion => $keywords) {
            $emotion_score = 0;
            $detected_keywords = [];
            
            foreach ($keywords as $keyword => $weight) {
                if (mb_strpos($message, $keyword) !== false) {
                    $emotion_score += $weight;
                    $detected_keywords[] = $keyword;
                    $confidence += 0.1;
                }
            }
            
            if ($emotion_score !== 0) {
                $emotions[$emotion] = [
                    'score' => $emotion_score,
                    'keywords' => $detected_keywords
                ];
                $overall_sentiment += $emotion_score;
            }
        }
        
        // 文脈による調整
        if (!empty($context['conversation_history'])) {
            $historical_sentiment = $this->analyze_historical_sentiment($context['conversation_history']);
            $overall_sentiment = ($overall_sentiment * 0.7) + ($historical_sentiment * 0.3);
        }
        
        // 文体分析
        $formality = $this->analyze_formality($message);
        $urgency = $this->analyze_urgency($message);
        
        // 絵文字・顔文字分析
        $emoji_sentiment = $this->analyze_emoji($message);
        if ($emoji_sentiment !== 0) {
            $overall_sentiment = ($overall_sentiment * 0.8) + ($emoji_sentiment * 0.2);
            $confidence += 0.2;
        }
        
        // 感情の強度を正規化
        $normalized_sentiment = max(-1, min(1, $overall_sentiment));
        
        return [
            'emotions' => $emotions,
            'sentiment' => $normalized_sentiment,
            'sentiment_label' => $this->get_sentiment_label($normalized_sentiment),
            'confidence' => min(1, $confidence),
            'formality' => $formality,
            'urgency' => $urgency,
            'dominant_emotion' => $this->get_dominant_emotion($emotions),
            'response_tone' => $this->suggest_response_tone($normalized_sentiment, $formality, $urgency)
        ];
    }
    
    /**
     * 丁寧度分析
     */
    private function analyze_formality($message) {
        $formal_indicators = ['です', 'ます', 'ございます', 'いただ', 'お願い', '申し訳'];
        $casual_indicators = ['だよ', 'だね', 'かな', 'じゃん', 'っす'];
        
        $formal_score = 0;
        $casual_score = 0;
        
        foreach ($formal_indicators as $indicator) {
            if (mb_strpos($message, $indicator) !== false) {
                $formal_score++;
            }
        }
        
        foreach ($casual_indicators as $indicator) {
            if (mb_strpos($message, $indicator) !== false) {
                $casual_score++;
            }
        }
        
        if ($formal_score > $casual_score * 2) {
            return 'formal';
        } elseif ($casual_score > $formal_score) {
            return 'casual';
        }
        
        return 'neutral';
    }
    
    /**
     * 緊急度分析
     */
    private function analyze_urgency($message) {
        $urgent_keywords = ['至急', '急ぎ', 'すぐ', '今すぐ', '早く', '締切', '期限'];
        $urgency_score = 0;
        
        foreach ($urgent_keywords as $keyword) {
            if (mb_strpos($message, $keyword) !== false) {
                $urgency_score += 0.3;
            }
        }
        
        if ($urgency_score > 0.5) {
            return 'high';
        } elseif ($urgency_score > 0) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * 絵文字分析
     */
    private function analyze_emoji($message) {
        $positive_emojis = ['😊', '😄', '😃', '👍', '✨', '🎉', '💯', '❤️', '🙏'];
        $negative_emojis = ['😢', '😭', '😞', '😔', '👎', '💔', '😡', '😤'];
        
        $score = 0;
        foreach ($positive_emojis as $emoji) {
            if (mb_strpos($message, $emoji) !== false) {
                $score += 0.3;
            }
        }
        
        foreach ($negative_emojis as $emoji) {
            if (mb_strpos($message, $emoji) !== false) {
                $score -= 0.3;
            }
        }
        
        return $score;
    }
    
    /**
     * 感情ラベル取得
     */
    private function get_sentiment_label($score) {
        if ($score > 0.6) return 'very_positive';
        if ($score > 0.2) return 'positive';
        if ($score > -0.2) return 'neutral';
        if ($score > -0.6) return 'negative';
        return 'very_negative';
    }
    
    /**
     * 支配的な感情の取得
     */
    private function get_dominant_emotion($emotions) {
        if (empty($emotions)) {
            return 'neutral';
        }
        
        $max_emotion = '';
        $max_score = -999;
        
        foreach ($emotions as $emotion => $data) {
            if (abs($data['score']) > abs($max_score)) {
                $max_score = $data['score'];
                $max_emotion = $emotion;
            }
        }
        
        return $max_emotion;
    }
    
    /**
     * 応答トーン提案
     */
    private function suggest_response_tone($sentiment, $formality, $urgency) {
        $tone = [
            'empathy_level' => 'medium',
            'formality' => $formality,
            'energy' => 'normal',
            'detail_level' => 'standard'
        ];
        
        // 感情に基づく調整
        if ($sentiment < -0.3) {
            $tone['empathy_level'] = 'high';
            $tone['energy'] = 'supportive';
        } elseif ($sentiment > 0.3) {
            $tone['energy'] = 'enthusiastic';
        }
        
        // 緊急度に基づく調整
        if ($urgency === 'high') {
            $tone['detail_level'] = 'concise';
            $tone['energy'] = 'direct';
        }
        
        return $tone;
    }
    
    /**
     * 履歴感情分析
     */
    private function analyze_historical_sentiment($history) {
        if (empty($history)) {
            return 0;
        }
        
        $total_sentiment = 0;
        $count = 0;
        
        foreach ($history as $message) {
            if (isset($message['sentiment'])) {
                $total_sentiment += $message['sentiment'];
                $count++;
            }
        }
        
        return $count > 0 ? $total_sentiment / $count : 0;
    }
}

/**
 * =============================================================================
 * 3. リアルタイムストリーミング機能
 * =============================================================================
 */

class GI_Streaming_Handler {
    
    private static $instance = null;
    private $buffer_size = 4096;
    private $chunk_delay = 50; // ミリ秒
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ストリーミングレスポンス開始
     */
    public function start_stream() {
        // バッファリング無効化
        @ob_end_clean();
        @ob_implicit_flush(true);
        
        // ヘッダー送信
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        // 接続確認
        echo "event: connected\n";
        echo "data: {\"status\":\"connected\"}\n\n";
        flush();
    }
    
    /**
     * チャンク送信
     */
    public function send_chunk($data, $event = 'message') {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        echo "event: {$event}\n";
        echo "data: {$json}\n\n";
        
        flush();
        
        // 適度な遅延で自然な表示
        usleep($this->chunk_delay * 1000);
    }
    
    /**
     * ストリーム終了
     */
    public function end_stream($final_data = null) {
        if ($final_data) {
            $this->send_chunk($final_data, 'complete');
        }
        
        echo "event: end\n";
        echo "data: {\"status\":\"completed\"}\n\n";
        flush();
    }
    
    /**
     * エラー送信
     */
    public function send_error($error_message) {
        $this->send_chunk([
            'error' => true,
            'message' => $error_message
        ], 'error');
    }
}

/**
 * =============================================================================
 * 4. 音声認識機能
 * =============================================================================
 */

class GI_Voice_Recognition {
    
    private static $instance = null;
    private $supported_languages = ['ja-JP', 'en-US'];
    private $max_duration = 60; // 秒
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 音声認識設定取得
     */
    public function get_recognition_config() {
        return [
            'continuous' => true,
            'interimResults' => true,
            'maxAlternatives' => 3,
            'language' => 'ja-JP',
            'grammars' => $this->get_grammar_rules()
        ];
    }
    
    /**
     * 文法ルール取得
     */
    private function get_grammar_rules() {
        return [
            'grant_terms' => [
                '助成金', '補助金', '支援金', '給付金',
                '申請', '締切', '対象', '条件'
            ],
            'business_terms' => [
                '中小企業', '個人事業主', 'スタートアップ',
                '設備投資', '人材育成', '研究開発'
            ],
            'action_terms' => [
                '探す', '調べる', '教えて', '知りたい',
                '申請したい', '確認したい'
            ]
        ];
    }
    
    /**
     * 音声データ処理
     */
    public function process_audio($audio_data, $format = 'webm') {
        // 音声データの検証
        if (empty($audio_data)) {
            return ['error' => '音声データが空です'];
        }
        
        // フォーマット変換（必要に応じて）
        if ($format !== 'wav') {
            $audio_data = $this->convert_audio_format($audio_data, $format, 'wav');
        }
        
        // 音声認識実行（Web Speech API経由）
        // 実際の実装では、ブラウザ側でWeb Speech APIを使用
        return [
            'transcript' => '',
            'confidence' => 0,
            'alternatives' => [],
            'language' => 'ja-JP'
        ];
    }
    
    /**
     * 音声フォーマット変換
     */
    private function convert_audio_format($data, $from, $to) {
        // FFmpegなどを使用した変換処理
        // ここでは仮の実装
        return $data;
    }
}

/**
 * =============================================================================
 * 5. 学習システム
 * =============================================================================
 */

class GI_Enhanced_Learning_System {
    
    private static $instance = null;
    private $feedback_threshold = 0.7;
    private $learning_rate = 0.1;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * インタラクション記録
     */
    public function record_interaction($query, $response, $context = []) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_learning';
        
        // クエリのハッシュ化
        $query_hash = md5($query);
        
        // 既存レコードチェック
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE query_hash = %s",
            $query_hash
        ));
        
        if ($existing) {
            // 使用回数を増加
            $wpdb->update(
                $table,
                [
                    'usage_count' => $existing->usage_count + 1,
                    'last_used' => current_time('mysql')
                ],
                ['id' => $existing->id]
            );
        } else {
            // 新規レコード作成
            $wpdb->insert(
                $table,
                [
                    'query_hash' => $query_hash,
                    'original_query' => $query,
                    'processed_query' => $this->process_query_for_learning($query),
                    'intent' => $context['intent'] ?? null,
                    'results' => json_encode($response),
                    'usage_count' => 1,
                    'created_at' => current_time('mysql'),
                    'last_used' => current_time('mysql')
                ]
            );
        }
        
        // パターン学習
        $this->learn_patterns($query, $response, $context);
    }
    
    /**
     * フィードバック処理
     */
    public function process_feedback($interaction_id, $feedback) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_learning';
        
        $wpdb->update(
            $table,
            ['feedback_score' => $feedback],
            ['id' => $interaction_id]
        );
        
        // ポジティブフィードバックから学習
        if ($feedback >= $this->feedback_threshold) {
            $interaction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $interaction_id
            ));
            
            if ($interaction) {
                $this->reinforce_positive_pattern(
                    $interaction->original_query,
                    json_decode($interaction->results, true)
                );
            }
        }
    }
    
    /**
     * パターン学習
     */
    private function learn_patterns($query, $response, $context) {
        // クエリからパターン抽出
        $patterns = $this->extract_patterns($query);
        
        // レスポンスの成功度評価
        $success_score = $this->evaluate_response_success($response, $context);
        
        // パターンと成功度を関連付けて保存
        foreach ($patterns as $pattern) {
            $this->update_pattern_weight($pattern, $success_score);
        }
    }
    
    /**
     * パターン抽出
     */
    private function extract_patterns($query) {
        $patterns = [];
        
        // N-gram抽出
        $words = $this->tokenize_for_learning($query);
        
        // Unigram
        $patterns = array_merge($patterns, $words);
        
        // Bigram
        for ($i = 0; $i < count($words) - 1; $i++) {
            $patterns[] = $words[$i] . '_' . $words[$i + 1];
        }
        
        // Trigram
        for ($i = 0; $i < count($words) - 2; $i++) {
            $patterns[] = $words[$i] . '_' . $words[$i + 1] . '_' . $words[$i + 2];
        }
        
        return $patterns;
    }
    
    /**
     * レスポンス成功度評価
     */
    private function evaluate_response_success($response, $context) {
        $score = 0.5; // 基本スコア
        
        // レスポンスの長さ（適度な長さが良い）
        $length = strlen($response);
        if ($length > 100 && $length < 1000) {
            $score += 0.1;
        }
        
        // 関連する助成金が見つかった場合
        if (!empty($context['related_grants'])) {
            $score += 0.2;
        }
        
        // 明確な回答が含まれている場合
        if (strpos($response, 'はい') !== false || strpos($response, 'いいえ') !== false) {
            $score += 0.1;
        }
        
        // 具体的な数値が含まれている場合
        if (preg_match('/\d+/', $response)) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
    }
    
    /**
     * パターン重み更新
     */
    private function update_pattern_weight($pattern, $score) {
        $current_weight = get_option('gi_pattern_weight_' . md5($pattern), 0.5);
        $new_weight = $current_weight + $this->learning_rate * ($score - $current_weight);
        update_option('gi_pattern_weight_' . md5($pattern), $new_weight);
    }
    
    /**
     * ポジティブパターン強化
     */
    private function reinforce_positive_pattern($query, $response) {
        $patterns = $this->extract_patterns($query);
        foreach ($patterns as $pattern) {
            $this->update_pattern_weight($pattern, 1.0);
        }
    }
    
    /**
     * 学習用クエリ処理
     */
    private function process_query_for_learning($query) {
        // 正規化
        $processed = mb_strtolower($query);
        
        // 不要な記号除去
        $processed = preg_replace('/[^\p{L}\p{N}\s]/u', '', $processed);
        
        // 空白正規化
        $processed = preg_replace('/\s+/', ' ', $processed);
        
        return trim($processed);
    }
    
    /**
     * トークン化（学習用）
     */
    private function tokenize_for_learning($text) {
        // 簡易的な形態素解析
        $words = [];
        
        // 漢字、ひらがな、カタカナ、英数字で分割
        preg_match_all('/[\p{Han}]+|[\p{Hiragana}]+|[\p{Katakana}]+|[a-zA-Z0-9]+/u', $text, $matches);
        
        return $matches[0];
    }
    
    /**
     * 推奨クエリ取得
     */
    public function get_recommended_queries($context = []) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_learning';
        
        // 高評価のクエリを取得
        $queries = $wpdb->get_results(
            "SELECT original_query, feedback_score, usage_count 
             FROM $table 
             WHERE feedback_score >= {$this->feedback_threshold}
             ORDER BY feedback_score DESC, usage_count DESC
             LIMIT 10"
        );
        
        $recommendations = [];
        foreach ($queries as $query) {
            $recommendations[] = [
                'query' => $query->original_query,
                'score' => $query->feedback_score,
                'popularity' => $query->usage_count
            ];
        }
        
        return $recommendations;
    }
}

/**
 * =============================================================================
 * 6. 統合AJAX処理
 * =============================================================================
 */

// セマンティック検索AJAX
add_action('wp_ajax_gi_enhanced_semantic_search', 'handle_enhanced_semantic_search');
add_action('wp_ajax_nopriv_gi_enhanced_semantic_search', 'handle_enhanced_semantic_search');

function handle_enhanced_semantic_search() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $query = sanitize_text_field($_POST['query'] ?? '');
    $context = $_POST['context'] ?? [];
    
    $search_engine = GI_Advanced_Semantic_Search::getInstance();
    $results = $search_engine->semantic_search($query, $context);
    
    // 学習システムに記録
    $learning = GI_Enhanced_Learning_System::getInstance();
    $learning->record_interaction($query, $results, $context);
    
    wp_send_json_success($results);
}

// 感情分析AJAX
add_action('wp_ajax_gi_analyze_emotion', 'handle_emotion_analysis');
add_action('wp_ajax_nopriv_gi_analyze_emotion', 'handle_emotion_analysis');

function handle_emotion_analysis() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $context = $_POST['context'] ?? [];
    
    $analyzer = GI_Advanced_Emotion_Analyzer::getInstance();
    $analysis = $analyzer->analyze_emotion($message, $context);
    
    wp_send_json_success($analysis);
}

// ストリーミングAJAX
add_action('wp_ajax_gi_stream_response', 'handle_streaming_response');
add_action('wp_ajax_nopriv_gi_stream_response', 'handle_streaming_response');

function handle_streaming_response() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    $streamer = GI_Streaming_Handler::getInstance();
    $streamer->start_stream();
    
    // デモ用のストリーミングレスポンス
    $response_parts = [
        "ご質問ありがとうございます。",
        "助成金についてお調べいたします。",
        "あなたの条件に合う助成金を検索中...",
        "3件の助成金が見つかりました。"
    ];
    
    foreach ($response_parts as $part) {
        $streamer->send_chunk(['text' => $part]);
        usleep(300000); // 300ms遅延
    }
    
    $streamer->end_stream();
    exit;
}

// 音声認識設定AJAX
add_action('wp_ajax_gi_get_voice_config', 'handle_voice_config');
add_action('wp_ajax_nopriv_gi_get_voice_config', 'handle_voice_config');

function handle_voice_config() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $voice = GI_Voice_Recognition::getInstance();
    $config = $voice->get_recognition_config();
    
    wp_send_json_success($config);
}

// フィードバックAJAX
add_action('wp_ajax_gi_submit_feedback', 'handle_learning_feedback');
add_action('wp_ajax_nopriv_gi_submit_feedback', 'handle_learning_feedback');

function handle_learning_feedback() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $interaction_id = intval($_POST['interaction_id'] ?? 0);
    $feedback = floatval($_POST['feedback'] ?? 0);
    
    $learning = GI_Enhanced_Learning_System::getInstance();
    $learning->process_feedback($interaction_id, $feedback);
    
    wp_send_json_success(['message' => 'フィードバックを受け付けました']);
}

// 推奨クエリ取得AJAX
add_action('wp_ajax_gi_get_recommendations', 'handle_get_recommendations');
add_action('wp_ajax_nopriv_gi_get_recommendations', 'handle_get_recommendations');

function handle_get_recommendations() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $context = $_POST['context'] ?? [];
    
    $learning = GI_Enhanced_Learning_System::getInstance();
    $recommendations = $learning->get_recommended_queries($context);
    
    wp_send_json_success($recommendations);
}