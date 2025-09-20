<?php
/**
 * Emotion Analysis & Learning System - 完全実装版
 * 
 * 日本語対応の感情分析と機械学習システム
 *
 * @package Grant_Insight_Perfect
 * @version 3.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * 日本語感情分析エンジン
 */
class GI_Japanese_Emotion_Analyzer {
    
    private static $instance = null;
    private $openai = null;
    
    // 感情カテゴリー
    private $emotion_categories = [
        'positive' => ['喜び', '期待', '満足', '興味', '感謝'],
        'negative' => ['不安', '困惑', '不満', '怒り', '失望'],
        'neutral' => ['平常', '質問', '確認', '情報収集']
    ];
    
    // 感情を表す日本語キーワード
    private $emotion_keywords = [
        'positive' => [
            '嬉しい', '楽しい', 'ありがとう', '助かる', '素晴らしい', 
            '良い', '最高', '便利', '期待', 'わくわく', '感謝',
            '満足', '安心', '希望', '成功', '達成', '頑張'
        ],
        'negative' => [
            '困った', '難しい', '分からない', '不安', '心配',
            '面倒', '複雑', '失敗', 'だめ', '無理', '諦め',
            '悲しい', '辛い', '疲れ', 'ストレス', '怒'
        ],
        'neutral' => [
            '教えて', '知りたい', 'どう', 'いつ', 'なぜ',
            '確認', '詳細', '情報', '説明', '方法', '手順'
        ]
    ];
    
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
    }
    
    /**
     * 感情分析実行
     */
    public function analyze_emotion($text, $context = []) {
        // 1. キーワードベース分析
        $keyword_analysis = $this->keyword_based_analysis($text);
        
        // 2. 文構造分析
        $structure_analysis = $this->structure_based_analysis($text);
        
        // 3. コンテキスト分析
        $context_analysis = $this->context_based_analysis($text, $context);
        
        // 4. AI分析（OpenAI API使用）
        $ai_analysis = $this->ai_based_analysis($text, $context);
        
        // 5. 総合判定
        return $this->aggregate_analysis([
            'keyword' => $keyword_analysis,
            'structure' => $structure_analysis,
            'context' => $context_analysis,
            'ai' => $ai_analysis
        ]);
    }
    
    /**
     * キーワードベース感情分析
     */
    private function keyword_based_analysis($text) {
        $scores = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0
        ];
        
        foreach ($this->emotion_keywords as $emotion => $keywords) {
            foreach ($keywords as $keyword) {
                $count = mb_substr_count($text, $keyword);
                $scores[$emotion] += $count;
            }
        }
        
        // 絵文字の分析
        $emoji_scores = $this->analyze_emojis($text);
        foreach ($emoji_scores as $emotion => $score) {
            $scores[$emotion] += $score;
        }
        
        // 正規化
        $total = array_sum($scores);
        if ($total > 0) {
            foreach ($scores as &$score) {
                $score = $score / $total;
            }
        }
        
        return $scores;
    }
    
    /**
     * 絵文字分析
     */
    private function analyze_emojis($text) {
        $emoji_patterns = [
            'positive' => '/[😀😃😄😁😊😇🥰😍🤗👍✨🎉💪]/u',
            'negative' => '/[😢😭😞😔😟😕😰😨😱😡👎💔]/u',
            'neutral' => '/[🤔💭❓❔ℹ️📝]/u'
        ];
        
        $scores = [];
        foreach ($emoji_patterns as $emotion => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $scores[$emotion] = count($matches[0]) * 2; // 絵文字は重み付けを高く
        }
        
        return $scores;
    }
    
    /**
     * 文構造ベース分析
     */
    private function structure_based_analysis($text) {
        $indicators = [
            'positive' => [
                'patterns' => ['/でき(る|ます|ました)/u', '/成功/u', '/達成/u', '/良[いく]/u'],
                'weight' => 1.2
            ],
            'negative' => [
                'patterns' => ['/でき(ない|ません)/u', '/失敗/u', '/無理/u', '/ダメ/u'],
                'weight' => 1.3
            ],
            'neutral' => [
                'patterns' => ['/ですか[？?]/u', '/教えて/u', '/どう(すれば|やって)/u'],
                'weight' => 1.0
            ]
        ];
        
        $scores = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($indicators as $emotion => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (preg_match($pattern, $text)) {
                    $scores[$emotion] += $data['weight'];
                }
            }
        }
        
        // 敬語の使用は中立的
        if (preg_match('/です|ます|ございます/u', $text)) {
            $scores['neutral'] += 0.5;
        }
        
        // 感嘆符の数で感情の強さを判定
        $exclamation_count = substr_count($text, '！') + substr_count($text, '!');
        if ($exclamation_count > 0) {
            // 文脈から正負を判定
            if ($scores['positive'] > $scores['negative']) {
                $scores['positive'] += $exclamation_count * 0.3;
            } else {
                $scores['negative'] += $exclamation_count * 0.3;
            }
        }
        
        return $this->normalize_scores($scores);
    }
    
    /**
     * コンテキストベース分析
     */
    private function context_based_analysis($text, $context) {
        $scores = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        // 過去の会話履歴から感情の流れを分析
        if (!empty($context['conversation_history'])) {
            $recent_emotions = $this->analyze_emotion_flow($context['conversation_history']);
            foreach ($recent_emotions as $emotion => $weight) {
                $scores[$emotion] += $weight * 0.3; // 過去の影響は30%
            }
        }
        
        // ユーザープロファイルの考慮
        if (!empty($context['user_profile'])) {
            $profile_emotion = $this->analyze_user_profile($context['user_profile']);
            foreach ($profile_emotion as $emotion => $weight) {
                $scores[$emotion] += $weight * 0.2; // プロファイルの影響は20%
            }
        }
        
        // 時間帯による調整
        $time_emotion = $this->analyze_time_context();
        foreach ($time_emotion as $emotion => $weight) {
            $scores[$emotion] += $weight * 0.1; // 時間の影響は10%
        }
        
        return $this->normalize_scores($scores);
    }
    
    /**
     * AI（OpenAI）ベース分析
     */
    private function ai_based_analysis($text, $context) {
        $prompt = "以下の日本語テキストの感情を分析してください。\n\n";
        $prompt .= "テキスト: {$text}\n\n";
        $prompt .= "以下の形式でJSONで返答してください:\n";
        $prompt .= '{"positive": 0.0-1.0, "negative": 0.0-1.0, "neutral": 0.0-1.0, "dominant_emotion": "感情名", "confidence": 0.0-1.0}';
        
        $messages = [
            ['role' => 'system', 'content' => 'あなたは日本語の感情分析の専門家です。'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $response = $this->openai->chat_completion($messages, [
            'temperature' => 0.3,
            'max_tokens' => 150
        ]);
        
        if (is_wp_error($response)) {
            // エラーの場合はデフォルト値を返す
            return ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
        }
        
        // JSON解析
        $json_match = preg_match('/\{[^}]+\}/', $response, $matches);
        if ($json_match) {
            $analysis = json_decode($matches[0], true);
            if ($analysis) {
                return [
                    'positive' => $analysis['positive'] ?? 0.33,
                    'negative' => $analysis['negative'] ?? 0.33,
                    'neutral' => $analysis['neutral'] ?? 0.34
                ];
            }
        }
        
        return ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
    }
    
    /**
     * 分析結果の集約
     */
    private function aggregate_analysis($analyses) {
        $weights = [
            'keyword' => 0.25,
            'structure' => 0.20,
            'context' => 0.20,
            'ai' => 0.35
        ];
        
        $final_scores = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($analyses as $type => $scores) {
            $weight = $weights[$type];
            foreach ($scores as $emotion => $score) {
                $final_scores[$emotion] += $score * $weight;
            }
        }
        
        // 最終的な感情判定
        $dominant = array_keys($final_scores, max($final_scores))[0];
        $confidence = max($final_scores);
        
        return [
            'scores' => $final_scores,
            'dominant' => $dominant,
            'confidence' => $confidence,
            'details' => $this->get_emotion_details($dominant, $confidence),
            'suggestion' => $this->get_response_suggestion($dominant, $final_scores)
        ];
    }
    
    /**
     * スコアの正規化
     */
    private function normalize_scores($scores) {
        $total = array_sum($scores);
        if ($total == 0) {
            return ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
        }
        
        foreach ($scores as &$score) {
            $score = $score / $total;
        }
        
        return $scores;
    }
    
    /**
     * 感情の詳細情報取得
     */
    private function get_emotion_details($emotion, $confidence) {
        $details = [
            'positive' => [
                'label' => 'ポジティブ',
                'icon' => '😊',
                'color' => '#4CAF50',
                'message' => '前向きな気持ちが伝わります'
            ],
            'negative' => [
                'label' => 'ネガティブ',
                'icon' => '😟',
                'color' => '#F44336',
                'message' => 'お困りのようですね'
            ],
            'neutral' => [
                'label' => 'ニュートラル',
                'icon' => '😐',
                'color' => '#9E9E9E',
                'message' => '情報をお探しですね'
            ]
        ];
        
        $detail = $details[$emotion];
        $detail['confidence_level'] = $this->get_confidence_level($confidence);
        
        return $detail;
    }
    
    /**
     * 信頼度レベル取得
     */
    private function get_confidence_level($confidence) {
        if ($confidence >= 0.8) return '非常に高い';
        if ($confidence >= 0.6) return '高い';
        if ($confidence >= 0.4) return '中程度';
        return '低い';
    }
    
    /**
     * 応答サジェスチョン生成
     */
    private function get_response_suggestion($emotion, $scores) {
        $suggestions = [
            'positive' => [
                'tone' => 'enthusiastic',
                'approach' => 'supportive',
                'templates' => [
                    'その調子で頑張ってください！',
                    '素晴らしいですね！',
                    'お役に立てて嬉しいです！'
                ]
            ],
            'negative' => [
                'tone' => 'empathetic',
                'approach' => 'helpful',
                'templates' => [
                    'お困りのことについて、詳しくお聞かせください。',
                    '大丈夫です、一緒に解決策を見つけましょう。',
                    'ご不安な点について、丁寧にご説明します。'
                ]
            ],
            'neutral' => [
                'tone' => 'informative',
                'approach' => 'clear',
                'templates' => [
                    'ご質問についてお答えします。',
                    '詳細な情報をご提供いたします。',
                    '以下の情報がお役に立つかもしれません。'
                ]
            ]
        ];
        
        return $suggestions[$emotion];
    }
    
    /**
     * 感情の流れ分析
     */
    private function analyze_emotion_flow($history) {
        // 最新5件の履歴から感情の傾向を分析
        $recent = array_slice($history, -5);
        $emotion_counts = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($recent as $item) {
            if (isset($item['emotion'])) {
                $emotion_counts[$item['emotion']]++;
            }
        }
        
        return $this->normalize_scores($emotion_counts);
    }
    
    /**
     * ユーザープロファイル分析
     */
    private function analyze_user_profile($profile) {
        // プロファイルから基本的な感情傾向を推定
        $scores = ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
        
        if (isset($profile['satisfaction_level'])) {
            if ($profile['satisfaction_level'] > 7) {
                $scores['positive'] += 0.2;
            } elseif ($profile['satisfaction_level'] < 4) {
                $scores['negative'] += 0.2;
            }
        }
        
        return $this->normalize_scores($scores);
    }
    
    /**
     * 時間コンテキスト分析
     */
    private function analyze_time_context() {
        $hour = intval(date('G'));
        
        // 時間帯による基本的な感情傾向
        if ($hour >= 9 && $hour <= 17) {
            // 業務時間中は中立的
            return ['positive' => 0.3, 'negative' => 0.3, 'neutral' => 0.4];
        } elseif ($hour >= 6 && $hour <= 9) {
            // 朝はやや前向き
            return ['positive' => 0.4, 'negative' => 0.2, 'neutral' => 0.4];
        } else {
            // 夜間は疲れ気味
            return ['positive' => 0.2, 'negative' => 0.4, 'neutral' => 0.4];
        }
    }
}

/**
 * 学習システム（完全実装版）
 */
class GI_Advanced_Learning_System {
    
    private static $instance = null;
    private $table_name;
    
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gi_ai_learning';
        $this->init_hooks();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        add_action('wp_ajax_gi_ai_feedback', [$this, 'handle_feedback']);
        add_action('wp_ajax_nopriv_gi_ai_feedback', [$this, 'handle_feedback']);
    }
    
    /**
     * フィードバック処理
     */
    public function handle_feedback() {
        // Nonceチェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_nonce')) {
            wp_send_json_error('セキュリティチェックに失敗しました。');
        }
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $message_id = sanitize_text_field($_POST['message_id'] ?? '');
        $feedback_type = sanitize_text_field($_POST['feedback_type'] ?? '');
        $feedback_value = intval($_POST['feedback_value'] ?? 0);
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');
        
        // フィードバック保存
        $result = $this->save_feedback([
            'session_id' => $session_id,
            'message_id' => $message_id,
            'feedback_type' => $feedback_type,
            'feedback_value' => $feedback_value,
            'comment' => $comment,
            'user_id' => get_current_user_id()
        ]);
        
        if ($result) {
            // 学習処理
            $this->learn_from_feedback($feedback_type, $feedback_value, $session_id);
            wp_send_json_success('フィードバックありがとうございます。');
        } else {
            wp_send_json_error('フィードバックの保存に失敗しました。');
        }
    }
    
    /**
     * フィードバック保存
     */
    private function save_feedback($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->table_name,
            [
                'query_hash' => md5($data['session_id'] . $data['message_id']),
                'original_query' => $data['session_id'],
                'processed_query' => $data['message_id'],
                'intent' => $data['feedback_type'],
                'feedback_score' => $data['feedback_value'],
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    /**
     * フィードバックからの学習
     */
    private function learn_from_feedback($type, $value, $session_id) {
        global $wpdb;
        
        // セッションの会話履歴を取得
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gi_ai_conversations 
            WHERE session_id = %s 
            ORDER BY created_at DESC 
            LIMIT 10",
            $session_id
        ));
        
        if (empty($conversations)) {
            return;
        }
        
        // パターン抽出
        $patterns = $this->extract_patterns($conversations, $type, $value);
        
        // パターンをデータベースに保存
        foreach ($patterns as $pattern) {
            $this->save_pattern($pattern);
        }
        
        // モデル更新フラグを立てる
        update_option('gi_ai_model_needs_update', true);
    }
    
    /**
     * パターン抽出
     */
    private function extract_patterns($conversations, $feedback_type, $feedback_value) {
        $patterns = [];
        
        foreach ($conversations as $conv) {
            if ($conv->message_type === 'user') {
                // ユーザーの質問パターンを抽出
                $keywords = $this->extract_important_keywords($conv->message);
                
                $patterns[] = [
                    'keywords' => $keywords,
                    'intent' => $conv->intent ?? 'unknown',
                    'feedback_score' => $feedback_value,
                    'success' => $feedback_value > 3
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * 重要キーワード抽出
     */
    private function extract_important_keywords($text) {
        // 簡易的なキーワード抽出
        $keywords = [];
        
        // 名詞を抽出（簡易版）
        $patterns = [
            '/[一-龠]+/u', // 漢字
            '/[ァ-ヴー]+/u', // カタカナ
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $keywords = array_merge($keywords, $matches[0]);
        }
        
        // 重要度でフィルタリング（文字数3以上）
        $keywords = array_filter($keywords, function($k) {
            return mb_strlen($k) >= 3;
        });
        
        return array_unique($keywords);
    }
    
    /**
     * パターン保存
     */
    private function save_pattern($pattern) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE query_hash = %s",
            md5(implode('', $pattern['keywords']))
        ));
        
        if ($existing) {
            // 既存パターンを更新
            $new_score = ($existing->feedback_score * $existing->usage_count + $pattern['feedback_score']) / ($existing->usage_count + 1);
            
            $wpdb->update(
                $this->table_name,
                [
                    'feedback_score' => $new_score,
                    'usage_count' => $existing->usage_count + 1,
                    'last_used' => current_time('mysql')
                ],
                ['id' => $existing->id]
            );
        } else {
            // 新規パターンを保存
            $wpdb->insert(
                $this->table_name,
                [
                    'query_hash' => md5(implode('', $pattern['keywords'])),
                    'original_query' => implode(' ', $pattern['keywords']),
                    'processed_query' => json_encode($pattern),
                    'intent' => $pattern['intent'],
                    'feedback_score' => $pattern['feedback_score'],
                    'usage_count' => 1,
                    'created_at' => current_time('mysql')
                ]
            );
        }
    }
    
    /**
     * 学習データ取得
     */
    public function get_learning_data($intent = null, $min_score = 3) {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_name} WHERE feedback_score >= %d";
        $params = [$min_score];
        
        if ($intent) {
            $query .= " AND intent = %s";
            $params[] = $intent;
        }
        
        $query .= " ORDER BY feedback_score DESC, usage_count DESC LIMIT 100";
        
        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }
    
    /**
     * 推奨応答取得
     */
    public function get_recommended_response($query, $intent = null) {
        // 学習データから類似パターンを検索
        $learning_data = $this->get_learning_data($intent, 4);
        
        if (empty($learning_data)) {
            return null;
        }
        
        // クエリとの類似度計算
        $query_keywords = $this->extract_important_keywords($query);
        $best_match = null;
        $best_score = 0;
        
        foreach ($learning_data as $data) {
            $pattern = json_decode($data->processed_query, true);
            if (isset($pattern['keywords'])) {
                $similarity = $this->calculate_keyword_similarity($query_keywords, $pattern['keywords']);
                $weighted_score = $similarity * $data->feedback_score * log($data->usage_count + 1);
                
                if ($weighted_score > $best_score) {
                    $best_score = $weighted_score;
                    $best_match = $data;
                }
            }
        }
        
        return $best_match;
    }
    
    /**
     * キーワード類似度計算
     */
    private function calculate_keyword_similarity($keywords1, $keywords2) {
        if (empty($keywords1) || empty($keywords2)) {
            return 0;
        }
        
        $intersection = array_intersect($keywords1, $keywords2);
        $union = array_unique(array_merge($keywords1, $keywords2));
        
        return count($intersection) / count($union);
    }
}

// インスタンス初期化
add_action('init', function() {
    GI_Japanese_Emotion_Analyzer::getInstance();
    GI_Advanced_Learning_System::getInstance();
});