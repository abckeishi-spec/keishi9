<?php
/**
 * AI Concierge Functions - Advanced Grant Assistance System
 * ChatGPT連携による高度な助成金相談・検索システム
 *
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 * @author AI Concierge Team
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * =============================================================================
 * 1. AIコンセルジュ・メインクラス
 * =============================================================================
 */

class GI_AI_Concierge {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * 設定値
     */
    private $settings = [];
    
    /**
     * ChatGPT APIクライント
     */
    private $chatgpt_client = null;
    
    /**
     * セッション管理
     */
    private $session_manager = null;
    
    /**
     * セマンティック検索エンジン
     */
    private $search_engine = null;
    
    /**
     * コンテキスト管理
     */
    private $context_manager = null;
    
    /**
     * 感情分析エンジン
     */
    private $emotion_analyzer = null;
    
    /**
     * 学習システム
     */
    private $learning_system = null;
    
    /**
     * シングルトンパターン
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
        $this->load_settings();
        $this->init_components();
        $this->register_hooks();
        $this->setup_database();
    }
    
    /**
     * 設定読み込み
     */
    private function load_settings() {
        $defaults = [
            'openai_api_key' => '',
            'model' => 'gpt-4',
            'max_tokens' => 1500,
            'temperature' => 0.7,
            'conversation_memory_limit' => 10,
            'enable_emotion_analysis' => true,
            'enable_learning_system' => true,
            'enable_personalization' => true,
            'enable_multilingual' => true,
            'cache_duration' => 3600,
            'rate_limit_per_user' => 60,
            'max_conversation_length' => 50
        ];
        
        $stored_settings = get_option('gi_ai_concierge_settings', []);
        $this->settings = array_merge($defaults, $stored_settings);
    }
    
    /**
     * コンポーネント初期化
     */
    private function init_components() {
        $this->chatgpt_client = new GI_ChatGPT_Client($this->settings);
        $this->session_manager = new GI_Session_Manager();
        $this->search_engine = new GI_Semantic_Search_Engine();
        $this->context_manager = new GI_Context_Manager();
        $this->emotion_analyzer = new GI_Emotion_Analyzer();
        $this->learning_system = new GI_Learning_System();
    }
    
    /**
     * WordPress フック登録
     */
    private function register_hooks() {
        // AJAX エンドポイント
        add_action('wp_ajax_gi_ai_chat', [$this, 'handle_ai_chat']);
        add_action('wp_ajax_nopriv_gi_ai_chat', [$this, 'handle_ai_chat']);
        add_action('wp_ajax_gi_semantic_search', [$this, 'handle_semantic_search']);
        add_action('wp_ajax_nopriv_gi_semantic_search', [$this, 'handle_semantic_search']);
        add_action('wp_ajax_gi_search_suggestions', [$this, 'handle_search_suggestions']);
        add_action('wp_ajax_nopriv_gi_search_suggestions', [$this, 'handle_search_suggestions']);
        add_action('wp_ajax_gi_conversation_feedback', [$this, 'handle_conversation_feedback']);
        add_action('wp_ajax_nopriv_gi_conversation_feedback', [$this, 'handle_conversation_feedback']);
        
        // 管理画面
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // スケジュール実行
        add_action('gi_daily_ai_maintenance', [$this, 'daily_maintenance']);
        if (!wp_next_scheduled('gi_daily_ai_maintenance')) {
            wp_schedule_event(time(), 'daily', 'gi_daily_ai_maintenance');
        }
        
        // スクリプト・スタイル
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * データベース設定
     */
    private function setup_database() {
        global $wpdb;
        
        $conversation_table = $wpdb->prefix . 'gi_ai_conversations';
        $analytics_table = $wpdb->prefix . 'gi_ai_analytics';
        $learning_table = $wpdb->prefix . 'gi_ai_learning';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 会話履歴テーブル
        $sql1 = "CREATE TABLE IF NOT EXISTS $conversation_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            message_type enum('user','assistant','system') NOT NULL DEFAULT 'user',
            message longtext NOT NULL,
            context longtext DEFAULT NULL,
            emotion_score decimal(3,2) DEFAULT NULL,
            intent varchar(100) DEFAULT NULL,
            confidence decimal(3,2) DEFAULT NULL,
            response_time decimal(5,3) DEFAULT NULL,
            tokens_used int(11) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // 分析データテーブル
        $sql2 = "CREATE TABLE IF NOT EXISTS $analytics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            total_conversations int(11) DEFAULT 0,
            total_messages int(11) DEFAULT 0,
            avg_response_time decimal(5,3) DEFAULT NULL,
            satisfaction_score decimal(3,2) DEFAULT NULL,
            top_intents text DEFAULT NULL,
            popular_queries text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date (date)
        ) $charset_collate;";
        
        // 学習データテーブル
        $sql3 = "CREATE TABLE IF NOT EXISTS $learning_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            query_hash varchar(64) NOT NULL,
            original_query text NOT NULL,
            processed_query text NOT NULL,
            intent varchar(100) DEFAULT NULL,
            results longtext DEFAULT NULL,
            feedback_score tinyint(4) DEFAULT NULL,
            usage_count int(11) DEFAULT 1,
            last_used timestamp DEFAULT CURRENT_TIMESTAMP,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY query_hash (query_hash),
            KEY intent (intent),
            KEY last_used (last_used)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }
    
    /**
     * AI チャット処理
     */
    public function handle_ai_chat() {
        try {
            // セキュリティチェック
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_concierge_nonce')) {
                wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            }
            
            // レート制限チェック
            if (!$this->check_rate_limit()) {
                wp_send_json_error(['message' => 'リクエスト制限に達しました。しばらくお待ちください。']);
            }
            
            $user_message = sanitize_textarea_field($_POST['message'] ?? '');
            $session_id = sanitize_text_field($_POST['session_id'] ?? '');
            $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
            
            if (empty($user_message)) {
                wp_send_json_error(['message' => 'メッセージが空です']);
            }
            
            // セッション管理
            if (empty($session_id)) {
                $session_id = $this->session_manager->create_session();
            }
            
            $start_time = microtime(true);
            
            // 意図認識と感情分析
            $intent = $this->analyze_intent($user_message);
            $emotion = $this->emotion_analyzer->analyze($user_message);
            
            // コンテキスト管理
            $context = $this->context_manager->get_context($session_id);
            $context = $this->context_manager->update_context($context, $user_message, $intent);
            
            // 会話履歴の取得と管理
            $conversation_history = $this->get_conversation_history($session_id);
            
            // AI応答生成
            $ai_response = $this->generate_ai_response($user_message, $context, $conversation_history, $intent, $emotion);
            
            // レスポンス時間計算
            $response_time = microtime(true) - $start_time;
            
            // 会話履歴保存
            $this->save_conversation_message($session_id, 'user', $user_message, $context, $emotion['score'], $intent['intent'], $intent['confidence']);
            $this->save_conversation_message($session_id, 'assistant', $ai_response['content'], $context, null, null, null, $response_time, $ai_response['tokens_used']);
            
            // 学習システムへのフィードバック
            $this->learning_system->record_interaction($user_message, $ai_response['content'], $intent);
            
            // 関連助成金の提案（改良版）
            $related_grants = $this->get_related_grants($user_message, $intent, $context);
            
            // キーワードマッチした助成金がある場合、応答に追加
            if (!empty($related_grants)) {
                $grant_html = $this->format_grant_results_for_response($related_grants, $user_message);
                $ai_response['content'] .= $grant_html;
            }
            
            // 追加提案の生成
            $suggestions = $this->generate_suggestions($intent, $context, $conversation_history);
            
            wp_send_json_success([
                'response' => $ai_response['content'],
                'session_id' => $session_id,
                'conversation_id' => $conversation_id,
                'intent' => $intent,
                'emotion' => $emotion,
                'related_grants' => $related_grants,
                'suggestions' => $suggestions,
                'response_time' => $response_time,
                'context_updated' => true
            ]);
            
        } catch (Exception $e) {
            error_log('AI Concierge Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'システムエラーが発生しました。しばらくお待ちください。']);
        }
    }
    
    /**
     * セマンティック検索処理
     */
    public function handle_semantic_search() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_concierge_nonce')) {
                wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            }
            
            $query = sanitize_text_field($_POST['query'] ?? '');
            $filters = $_POST['filters'] ?? [];
            $page = intval($_POST['page'] ?? 1);
            $per_page = min(20, intval($_POST['per_page'] ?? 10));
            
            if (empty($query)) {
                wp_send_json_error(['message' => '検索クエリが空です']);
            }
            
            // セマンティック検索実行
            $search_results = $this->search_engine->search($query, $filters, $page, $per_page);
            
            wp_send_json_success($search_results);
            
        } catch (Exception $e) {
            error_log('Semantic Search Error: ' . $e->getMessage());
            wp_send_json_error(['message' => '検索エラーが発生しました']);
        }
    }
    
    /**
     * 検索候補処理
     */
    public function handle_search_suggestions() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_concierge_nonce')) {
                wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            }
            
            $partial_query = sanitize_text_field($_POST['query'] ?? '');
            $limit = min(10, intval($_POST['limit'] ?? 5));
            
            if (strlen($partial_query) < 2) {
                wp_send_json_success(['suggestions' => []]);
            }
            
            $suggestions = $this->generate_search_suggestions($partial_query, $limit);
            
            wp_send_json_success(['suggestions' => $suggestions]);
            
        } catch (Exception $e) {
            error_log('Search Suggestions Error: ' . $e->getMessage());
            wp_send_json_error(['message' => '候補取得エラーが発生しました']);
        }
    }
    
    /**
     * 会話フィードバック処理
     */
    public function handle_conversation_feedback() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_concierge_nonce')) {
                wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            }
            
            $session_id = sanitize_text_field($_POST['session_id'] ?? '');
            $message_id = intval($_POST['message_id'] ?? 0);
            $feedback_type = sanitize_text_field($_POST['feedback_type'] ?? '');
            $rating = intval($_POST['rating'] ?? 0);
            $comment = sanitize_textarea_field($_POST['comment'] ?? '');
            
            $this->save_feedback($session_id, $message_id, $feedback_type, $rating, $comment);
            
            wp_send_json_success(['message' => 'フィードバックを受け付けました']);
            
        } catch (Exception $e) {
            error_log('Feedback Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'フィードバック送信エラーが発生しました']);
        }
    }
    
    /**
     * レート制限チェック
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $cache_key = 'ai_rate_limit_' . ($user_id ?: md5($ip));
        
        $current_count = wp_cache_get($cache_key) ?: 0;
        
        if ($current_count >= $this->settings['rate_limit_per_user']) {
            return false;
        }
        
        wp_cache_set($cache_key, $current_count + 1, '', 3600);
        return true;
    }
    
    /**
     * 意図認識
     */
    private function analyze_intent($message) {
        // 事前定義された意図パターン
        $intent_patterns = [
            'search_grants' => [
                'keywords' => ['助成金', '補助金', '支援金', '探す', '検索', '見つける'],
                'patterns' => ['/助成金.*探し/', '/補助金.*ある/', '/支援.*制度/']
            ],
            'application_help' => [
                'keywords' => ['申請', '応募', '手続き', '書類', '方法', 'やり方'],
                'patterns' => ['/申請.*方法/', '/書類.*作成/', '/手続き.*流れ/']
            ],
            'eligibility_check' => [
                'keywords' => ['対象', '条件', '資格', '要件', '該当'],
                'patterns' => ['/対象.*確認/', '/条件.*満たす/', '/資格.*ある/']
            ],
            'deadline_inquiry' => [
                'keywords' => ['締切', '期限', 'いつまで', '期間'],
                'patterns' => ['/締切.*いつ/', '/期限.*確認/', '/いつまで.*申請/']
            ],
            'amount_inquiry' => [
                'keywords' => ['金額', '額', 'いくら', '最大', '上限'],
                'patterns' => ['/いくら.*もらえる/', '/金額.*教え/', '/最大.*額/']
            ],
            'general_question' => [
                'keywords' => ['教え', 'わから', '質問', 'どう', 'なに'],
                'patterns' => ['/教えて/', '/わからない/', '/どうすれば/']
            ]
        ];
        
        $message_lower = mb_strtolower($message);
        $best_intent = 'general_question';
        $best_confidence = 0;
        
        foreach ($intent_patterns as $intent => $config) {
            $confidence = 0;
            
            // キーワードマッチング
            foreach ($config['keywords'] as $keyword) {
                if (strpos($message_lower, $keyword) !== false) {
                    $confidence += 0.3;
                }
            }
            
            // パターンマッチング
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $message_lower)) {
                    $confidence += 0.5;
                }
            }
            
            if ($confidence > $best_confidence) {
                $best_confidence = $confidence;
                $best_intent = $intent;
            }
        }
        
        return [
            'intent' => $best_intent,
            'confidence' => min(1.0, $best_confidence),
            'alternatives' => $this->get_alternative_intents($intent_patterns, $message_lower, $best_intent)
        ];
    }
    
    /**
     * 代替意図の取得
     */
    private function get_alternative_intents($intent_patterns, $message_lower, $best_intent) {
        $alternatives = [];
        
        foreach ($intent_patterns as $intent => $config) {
            if ($intent === $best_intent) continue;
            
            $confidence = 0;
            foreach ($config['keywords'] as $keyword) {
                if (strpos($message_lower, $keyword) !== false) {
                    $confidence += 0.3;
                }
            }
            
            if ($confidence > 0) {
                $alternatives[] = [
                    'intent' => $intent,
                    'confidence' => min(1.0, $confidence)
                ];
            }
        }
        
        // 信頼度順でソート
        usort($alternatives, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return array_slice($alternatives, 0, 3);
    }
    
    /**
     * AI応答生成
     */
    private function generate_ai_response($user_message, $context, $conversation_history, $intent, $emotion) {
        // システムプロンプト構築
        $system_prompt = $this->build_system_prompt($intent, $context, $emotion);
        
        // 会話履歴をOpenAI形式に変換
        $messages = $this->format_conversation_for_api($system_prompt, $conversation_history, $user_message);
        
        // ChatGPT API 呼び出し
        $response = $this->chatgpt_client->generate_response($messages);
        
        // 応答の後処理
        $processed_response = $this->post_process_response($response, $intent, $context);
        
        return $processed_response;
    }
    
    /**
     * システムプロンプト構築
     */
    private function build_system_prompt($intent, $context, $emotion) {
        $base_prompt = "あなたは助成金・補助金の専門コンサルタントです。";
        $base_prompt .= "日本の中小企業や個人事業主に対して、最適な助成金情報を提供し、申請をサポートします。";
        $base_prompt .= "常に正確で実用的なアドバイスを心がけ、ユーザーの状況に応じた個別対応を行います。";
        
        // 意図に応じたプロンプト調整
        switch ($intent['intent']) {
            case 'search_grants':
                $base_prompt .= "\n現在は助成金検索に関する質問を受けています。具体的な条件に基づいて最適な助成金を提案してください。";
                break;
            case 'application_help':
                $base_prompt .= "\n申請手続きに関する質問を受けています。段階的で分かりやすい説明を心がけてください。";
                break;
            case 'eligibility_check':
                $base_prompt .= "\n対象資格の確認に関する質問です。明確な判定基準と根拠を示してください。";
                break;
            case 'deadline_inquiry':
                $base_prompt .= "\n締切に関する緊急性の高い質問です。正確な日程と注意点を明示してください。";
                break;
            case 'amount_inquiry':
                $base_prompt .= "\n金額に関する質問です。具体的な数字と計算方法を示してください。";
                break;
        }
        
        // 感情に応じた調整
        if ($emotion['score'] < 0.3) {
            $base_prompt .= "\nユーザーは困惑や不安を感じているようです。丁寧で親しみやすい対応を心がけてください。";
        } elseif ($emotion['score'] > 0.7) {
            $base_prompt .= "\nユーザーは積極的で前向きです。効率的で具体的な情報提供を行ってください。";
        }
        
        // コンテキスト情報の追加
        if (!empty($context['user_business_type'])) {
            $base_prompt .= "\nユーザーの事業種別: " . $context['user_business_type'];
        }
        if (!empty($context['user_location'])) {
            $base_prompt .= "\nユーザーの所在地: " . $context['user_location'];
        }
        if (!empty($context['current_focus'])) {
            $base_prompt .= "\n現在の関心事: " . $context['current_focus'];
        }
        
        $base_prompt .= "\n\n回答の際は以下を必ず守ってください：";
        $base_prompt .= "\n- 簡潔で分かりやすい日本語を使用";
        $base_prompt .= "\n- 具体的で実行可能なアドバイスを提供";
        $base_prompt .= "\n- 必要に応じて追加質問を促す";
        $base_prompt .= "\n- 専門用語は分かりやすく説明";
        $base_prompt .= "\n- 回答の根拠や参考情報を明示";
        
        return $base_prompt;
    }
    
    /**
     * 会話履歴をAPI形式に変換
     */
    private function format_conversation_for_api($system_prompt, $conversation_history, $current_message) {
        $messages = [
            ['role' => 'system', 'content' => $system_prompt]
        ];
        
        // 直近の会話履歴を追加（メモリ制限内）
        $recent_history = array_slice($conversation_history, -$this->settings['conversation_memory_limit']);
        
        foreach ($recent_history as $history_item) {
            if ($history_item['message_type'] === 'user') {
                $messages[] = ['role' => 'user', 'content' => $history_item['message']];
            } elseif ($history_item['message_type'] === 'assistant') {
                $messages[] = ['role' => 'assistant', 'content' => $history_item['message']];
            }
        }
        
        // 現在のメッセージを追加
        $messages[] = ['role' => 'user', 'content' => $current_message];
        
        return $messages;
    }
    
    /**
     * 応答の後処理
     */
    private function post_process_response($response, $intent, $context) {
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // マークダウン形式の改善
        $content = $this->improve_markdown_formatting($content);
        
        // 助成金名の自動リンク化
        $content = $this->add_grant_links($content);
        
        // 関連情報の付加
        $content = $this->add_contextual_information($content, $intent, $context);
        
        return [
            'content' => $content,
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
            'model_used' => $this->settings['model']
        ];
    }
    
    /**
     * マークダウン形式の改善
     */
    private function improve_markdown_formatting($content) {
        // リスト項目の改善
        $content = preg_replace('/^- /m', '• ', $content);
        
        // 重要な情報のハイライト
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        
        // 段落の改善
        $content = preg_replace('/\n\n/', '</p><p>', $content);
        $content = '<p>' . $content . '</p>';
        
        return $content;
    }
    
    /**
     * 助成金名の自動リンク化
     */
    private function add_grant_links($content) {
        global $wpdb;
        
        // データベースから助成金名を取得
        $grant_names = wp_cache_get('gi_grant_names');
        if ($grant_names === false) {
            $grant_names = $wpdb->get_col("
                SELECT post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = 'grant' 
                AND post_status = 'publish'
                ORDER BY LENGTH(post_title) DESC
                LIMIT 100
            ");
            wp_cache_set('gi_grant_names', $grant_names, '', 3600);
        }
        
        foreach ($grant_names as $grant_name) {
            if (strpos($content, $grant_name) !== false) {
                $grant_post = get_page_by_title($grant_name, OBJECT, 'grant');
                if ($grant_post) {
                    $link = get_permalink($grant_post->ID);
                    $content = str_replace(
                        $grant_name,
                        '<a href="' . $link . '" class="ai-grant-link" target="_blank">' . $grant_name . '</a>',
                        $content
                    );
                }
            }
        }
        
        return $content;
    }
    
    /**
     * 文脈情報の付加
     */
    private function add_contextual_information($content, $intent, $context) {
        // 意図に応じた追加情報
        switch ($intent['intent']) {
            case 'deadline_inquiry':
                $content .= '<div class="ai-info-box deadline-warning">';
                $content .= '<i class="fas fa-clock"></i> ';
                $content .= '<strong>重要：</strong>締切日は変更される場合があります。申請前に必ず公式サイトで最新情報をご確認ください。';
                $content .= '</div>';
                break;
                
            case 'application_help':
                $content .= '<div class="ai-info-box application-tip">';
                $content .= '<i class="fas fa-lightbulb"></i> ';
                $content .= '<strong>ヒント：</strong>申請書類の準備には時間がかかります。早めの準備をお勧めします。';
                $content .= '</div>';
                break;
        }
        
        return $content;
    }
    
    /**
     * 会話履歴の取得
     */
    private function get_conversation_history($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE session_id = %s 
            ORDER BY created_at ASC 
            LIMIT %d
        ", $session_id, $this->settings['conversation_memory_limit']), ARRAY_A);
    }
    
    /**
     * 会話メッセージの保存
     */
    private function save_conversation_message($session_id, $type, $message, $context = null, $emotion_score = null, $intent = null, $confidence = null, $response_time = null, $tokens_used = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        
        $wpdb->insert($table, [
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'message_type' => $type,
            'message' => $message,
            'context' => wp_json_encode($context),
            'emotion_score' => $emotion_score,
            'intent' => $intent,
            'confidence' => $confidence,
            'response_time' => $response_time,
            'tokens_used' => $tokens_used,
            'created_at' => current_time('mysql')
        ]);
        
        return $wpdb->insert_id;
    }
    
    /**
     * 関連助成金の取得
     */
    private function get_related_grants($message, $intent, $context) {
        // キーワード抽出（より詳細に）
        $keywords = $this->extract_keywords_from_message($message);
        $extracted_info = $this->extract_business_info($message);
        
        // 検索クエリの構築
        $search_args = [
            'post_type' => 'grant',
            'post_status' => 'publish',
            'posts_per_page' => 10, // より多くの候補から選択
            'meta_query' => [],
            'tax_query' => []
        ];
        
        // キーワード検索（タイトル、内容、カスタムフィールド）
        if (!empty($keywords)) {
            $search_args['s'] = implode(' ', $keywords);
            
            // メタクエリでも検索
            $meta_queries = [];
            foreach ($keywords as $keyword) {
                $meta_queries[] = [
                    'relation' => 'OR',
                    [
                        'key' => 'grant_purpose',
                        'value' => $keyword,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'grant_target',
                        'value' => $keyword,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'grant_description',
                        'value' => $keyword,
                        'compare' => 'LIKE'
                    ]
                ];
            }
            
            if (!empty($meta_queries)) {
                $search_args['meta_query'] = array_merge(
                    ['relation' => 'OR'],
                    $meta_queries
                );
            }
        }
        
        // カテゴリーフィルター
        if (!empty($extracted_info['category'])) {
            $search_args['tax_query'][] = [
                'taxonomy' => 'grant_category',
                'field' => 'slug',
                'terms' => $extracted_info['category']
            ];
        }
        
        // 業種フィルター
        if (!empty($extracted_info['business_type'])) {
            $meta_queries[] = [
                'key' => 'grant_target',
                'value' => $extracted_info['business_type'],
                'compare' => 'LIKE'
            ];
        }
        
        // 地域フィルター
        if (!empty($extracted_info['location'])) {
            $search_args['tax_query'][] = [
                'taxonomy' => 'grant_prefecture',
                'field' => 'name',
                'terms' => $extracted_info['location']
            ];
        }
        
        // 税クエリの関係設定
        if (count($search_args['tax_query']) > 1) {
            $search_args['tax_query']['relation'] = 'AND';
        }
        
        $grants = get_posts($search_args);
        
        // スコアリング（関連性の高い順に並べ替え）
        $scored_grants = [];
        foreach ($grants as $grant) {
            $score = $this->calculate_grant_relevance_score($grant, $keywords, $extracted_info);
            $scored_grants[] = [
                'grant' => $grant,
                'score' => $score
            ];
        }
        
        // スコア順にソート
        usort($scored_grants, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // 上位5件を返す
        $result = [];
        foreach (array_slice($scored_grants, 0, 5) as $item) {
            $grant = $item['grant'];
            $deadline = get_post_meta($grant->ID, 'deadline', true);
            $amount = get_post_meta($grant->ID, 'max_amount', true);
            
            // 締切日のフォーマット
            $deadline_text = '';
            if ($deadline) {
                $deadline_date = strtotime($deadline);
                if ($deadline_date) {
                    $days_left = ceil(($deadline_date - time()) / (60 * 60 * 24));
                    if ($days_left > 0) {
                        $deadline_text = $days_left . '日後';
                    } else {
                        $deadline_text = '終了';
                    }
                }
            }
            
            $result[] = [
                'id' => $grant->ID,
                'title' => $grant->post_title,
                'url' => get_permalink($grant->ID),
                'excerpt' => wp_trim_words(get_the_excerpt($grant->ID), 30, '...'),
                'amount' => $amount ? '最大' . number_format((int)$amount) . '円' : '金額情報なし',
                'deadline' => $deadline,
                'deadline_text' => $deadline_text,
                'organization' => get_post_meta($grant->ID, 'organization', true),
                'score' => $item['score'],
                'categories' => wp_get_post_terms($grant->ID, 'grant_category', ['fields' => 'names']),
                'prefectures' => wp_get_post_terms($grant->ID, 'grant_prefecture', ['fields' => 'names'])
            ];
        }
        
        return $result;
    }
    
    /**
     * キーワード抽出（改良版）
     */
    private function extract_keywords_from_message($message) {
        // 重要なキーワードパターン
        $important_keywords = [
            '雇用' => ['雇用', '採用', '人材', '従業員', '雇い入れ', 'キャリアアップ'],
            'IT' => ['IT', 'DX', 'デジタル', 'システム', 'ソフトウェア', 'EC', 'ウェブ'],
            '設備' => ['設備', '機械', '装置', '導入', '更新'],
            '創業' => ['創業', '起業', 'スタートアップ', '開業', '新規事業'],
            '販促' => ['販促', '販売促進', 'マーケティング', '広告', 'PR'],
            '研究開発' => ['研究', '開発', 'R&D', '技術開発', 'イノベーション'],
            '省エネ' => ['省エネ', '環境', 'SDGs', 'カーボンニュートラル', 'エコ'],
            '事業承継' => ['事業承継', '後継者', 'M&A', '継承'],
            '働き方' => ['働き方改革', 'テレワーク', 'リモートワーク', '在宅勤務'],
            '生産性' => ['生産性', '効率化', '改善', '合理化']
        ];
        
        $keywords = [];
        foreach ($important_keywords as $category => $terms) {
            foreach ($terms as $term) {
                if (mb_stripos($message, $term) !== false) {
                    $keywords[] = $term;
                }
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * 助成金の関連性スコア計算
     */
    private function calculate_grant_relevance_score($grant, $keywords, $extracted_info) {
        $score = 0;
        
        // タイトルマッチ（最重要）
        foreach ($keywords as $keyword) {
            if (mb_stripos($grant->post_title, $keyword) !== false) {
                $score += 10;
            }
        }
        
        // 内容マッチ
        $content = $grant->post_content . ' ' . $grant->post_excerpt;
        foreach ($keywords as $keyword) {
            if (mb_stripos($content, $keyword) !== false) {
                $score += 5;
            }
        }
        
        // カスタムフィールドマッチ
        $custom_fields = [
            'grant_purpose' => 3,
            'grant_target' => 3,
            'grant_description' => 2
        ];
        
        foreach ($custom_fields as $field => $weight) {
            $value = get_post_meta($grant->ID, $field, true);
            foreach ($keywords as $keyword) {
                if (mb_stripos($value, $keyword) !== false) {
                    $score += $weight;
                }
            }
        }
        
        // 締切日の近さ（緊急度）
        $deadline = get_post_meta($grant->ID, 'deadline', true);
        if ($deadline) {
            $days_left = ceil((strtotime($deadline) - time()) / (60 * 60 * 24));
            if ($days_left > 0 && $days_left <= 30) {
                $score += (31 - $days_left) / 3; // 締切が近いほど高スコア
            }
        }
        
        return $score;
    }
    
    /**
     * ビジネス情報の抽出（改良版）
     */
    private function extract_business_info($message) {
        $business_types = [
            '製造業' => ['製造', 'メーカー', '工場', '生産', 'ものづくり'],
            '小売業' => ['小売', '販売', '店舗', 'ショップ', '物販'],
            'IT業' => ['IT', 'システム', 'ソフトウェア', 'アプリ', 'Web', 'プログラム'],
            '建設業' => ['建設', '工事', '建築', 'リフォーム', '土木'],
            'サービス業' => ['サービス', 'コンサルティング', '相談', '支援'],
            '飲食業' => ['飲食', 'レストラン', 'カフェ', '居酒屋', '食堂'],
            '医療・福祉' => ['医療', '介護', '福祉', '病院', 'クリニック'],
            '運輸業' => ['運輸', '物流', '配送', 'トラック', '運送'],
            '不動産業' => ['不動産', '賃貸', '管理', '仲介'],
            '教育' => ['教育', '学習', '塾', 'スクール', '研修']
        ];
        
        // カテゴリー抽出
        $categories = [
            'it-digital' => ['IT', 'DX', 'デジタル', 'システム'],
            'manufacturing' => ['ものづくり', '製造', '生産'],
            'startup' => ['創業', '起業', 'スタートアップ'],
            'employment' => ['雇用', '採用', '人材'],
            'environment' => ['環境', '省エネ', 'SDGs'],
            'small-business' => ['小規模', '個人事業']
        ];
        
        // 全都道府県リスト
        $prefectures = [
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
            '岐阜県', '静岡県', '愛知県', '三重県',
            '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
            '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県',
            '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
        ];
        
        $extracted = ['business_type' => '', 'location' => '', 'category' => ''];
        
        // 業種抽出
        foreach ($business_types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($message, $keyword) !== false) {
                    $extracted['business_type'] = $type;
                    break 2;
                }
            }
        }
        
        // カテゴリー抽出
        foreach ($categories as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($message, $keyword) !== false) {
                    $extracted['category'] = $slug;
                    break 2;
                }
            }
        }
        
        // 地域抽出
        foreach ($prefectures as $prefecture) {
            if (mb_stripos($message, $prefecture) !== false) {
                $extracted['location'] = $prefecture;
                break;
            }
        }
        
        return $extracted;
    }
    
    /**
     * 助成金結果のHTML形式フォーマット
     */
    private function format_grant_results_for_response($grants, $query) {
        if (empty($grants)) return '';
        
        $html = '<div class="ai-grant-results">';
        $html .= '<h4>🎯 あなたの質問にマッチする助成金</h4>';
        $html .= '<div class="grant-list">';
        
        foreach ($grants as $index => $grant) {
            $num = $index + 1;
            $html .= '<div class="grant-item">';
            $html .= '<div class="grant-number">' . $num . '</div>';
            $html .= '<div class="grant-info">';
            $html .= '<h5><a href="' . esc_url($grant['url']) . '" target="_blank" class="grant-link">';
            $html .= esc_html($grant['title']) . '</a></h5>';
            
            // 説明
            $html .= '<p class="grant-excerpt">' . esc_html($grant['excerpt']) . '</p>';
            
            // メタ情報
            $html .= '<div class="grant-meta-info">';
            if (!empty($grant['amount'])) {
                $html .= '<span class="meta-badge">💰 ' . esc_html($grant['amount']) . '</span>';
            }
            if (!empty($grant['deadline_text']) && $grant['deadline_text'] !== '終了') {
                $urgency = (strtotime($grant['deadline']) - time()) < (30 * 24 * 60 * 60) ? 'urgent' : '';
                $html .= '<span class="meta-badge ' . $urgency . '">📅 締切まで' . esc_html($grant['deadline_text']) . '</span>';
            }
            if (!empty($grant['categories'])) {
                foreach ($grant['categories'] as $cat) {
                    $html .= '<span class="meta-badge category">🏷️ ' . esc_html($cat) . '</span>';
                }
            }
            if (!empty($grant['prefectures'])) {
                foreach ($grant['prefectures'] as $pref) {
                    $html .= '<span class="meta-badge location">📍 ' . esc_html($pref) . '</span>';
                }
            }
            $html .= '</div>';
            
            // 詳細リンク
            $html .= '<div class="grant-action">';
            $html .= '<a href="' . esc_url($grant['url']) . '" target="_blank" class="view-detail-link">詳細を見る →</a>';
            $html .= '</div>';
            
            $html .= '</div></div>';
        }
        
        $html .= '</div>';
        
        // 追加情報
        $html .= '<div class="ai-info-note">';
        $html .= '<p>💡 <strong>ヒント：</strong>各助成金の詳細ページで、申請条件や必要書類をご確認ください。</p>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 提案生成
     */
    private function generate_suggestions($intent, $context, $conversation_history) {
        $suggestions = [];
        
        switch ($intent['intent']) {
            case 'search_grants':
                $suggestions = [
                    '業種別に助成金を探す',
                    '申請難易度で絞り込む',
                    '申請期限が近いものを確認',
                    '最大支援額で並び替え'
                ];
                break;
                
            case 'application_help':
                $suggestions = [
                    '必要書類の一覧を確認',
                    '申請スケジュールを立てる',
                    '記入例やサンプルを見る',
                    '専門家への相談を検討'
                ];
                break;
                
            case 'eligibility_check':
                $suggestions = [
                    '詳細な要件を確認',
                    '類似の助成金を探す',
                    '要件を満たすための準備',
                    '事前相談の申し込み'
                ];
                break;
        }
        
        return array_slice($suggestions, 0, 4);
    }
    
    /**
     * 検索候補生成
     */
    private function generate_search_suggestions($partial_query, $limit) {
        global $wpdb;
        
        $suggestions = [];
        
        // 学習データから候補を取得
        $learning_table = $wpdb->prefix . 'gi_ai_learning';
        $learned_suggestions = $wpdb->get_results($wpdb->prepare("
            SELECT original_query, usage_count 
            FROM $learning_table 
            WHERE original_query LIKE %s 
            ORDER BY usage_count DESC, last_used DESC 
            LIMIT %d
        ", '%' . $wpdb->esc_like($partial_query) . '%', $limit), ARRAY_A);
        
        foreach ($learned_suggestions as $suggestion) {
            $suggestions[] = [
                'text' => $suggestion['original_query'],
                'type' => 'learned',
                'popularity' => intval($suggestion['usage_count'])
            ];
        }
        
        // 助成金タイトルから候補を取得
        if (count($suggestions) < $limit) {
            $remaining = $limit - count($suggestions);
            $grant_suggestions = $wpdb->get_results($wpdb->prepare("
                SELECT post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = 'grant' 
                AND post_status = 'publish' 
                AND post_title LIKE %s 
                ORDER BY post_date DESC 
                LIMIT %d
            ", '%' . $wpdb->esc_like($partial_query) . '%', $remaining), ARRAY_A);
            
            foreach ($grant_suggestions as $suggestion) {
                $suggestions[] = [
                    'text' => $suggestion['post_title'],
                    'type' => 'grant',
                    'popularity' => 0
                ];
            }
        }
        
        // 一般的な検索パターンから候補を取得
        if (count($suggestions) < $limit) {
            $common_patterns = [
                $partial_query . ' 申請方法',
                $partial_query . ' 対象条件',
                $partial_query . ' 締切日',
                $partial_query . ' 金額',
                $partial_query . ' 必要書類'
            ];
            
            foreach ($common_patterns as $pattern) {
                if (count($suggestions) >= $limit) break;
                
                $suggestions[] = [
                    'text' => $pattern,
                    'type' => 'pattern',
                    'popularity' => 0
                ];
            }
        }
        
        return array_slice($suggestions, 0, $limit);
    }
    
    /**
     * フィードバック保存
     */
    private function save_feedback($session_id, $message_id, $feedback_type, $rating, $comment) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        
        // フィードバック情報をJSONとして保存
        $feedback_data = wp_json_encode([
            'type' => $feedback_type,
            'rating' => $rating,
            'comment' => $comment,
            'timestamp' => current_time('mysql')
        ]);
        
        $wpdb->update(
            $table,
            ['context' => $feedback_data],
            [
                'session_id' => $session_id,
                'id' => $message_id
            ]
        );
        
        // 学習システムへのフィードバック
        $this->learning_system->record_feedback($session_id, $message_id, $rating, $feedback_type);
    }
    
    /**
     * スクリプト・スタイル読み込み
     */
    public function enqueue_scripts() {
        if (is_page_template('page-grants.php') || is_post_type_archive('grant')) {
            wp_enqueue_script(
                'gi-ai-concierge',
                get_template_directory_uri() . '/assets/js/concierge.js',
                ['jquery'],
                GI_THEME_VERSION,
                true
            );
            
            wp_enqueue_style(
                'gi-ai-concierge-css',
                get_template_directory_uri() . '/assets/css/concierge.css',
                [],
                GI_THEME_VERSION
            );
            
            wp_localize_script('gi-ai-concierge', 'gi_ai_concierge', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gi_ai_concierge_nonce'),
                'settings' => [
                    'typing_speed' => 30,
                    'response_timeout' => 30000,
                    'max_message_length' => 1000,
                    'enable_sound' => true,
                    'enable_animation' => true
                ],
                'strings' => [
                    'thinking' => 'AI が考えています...',
                    'error' => 'エラーが発生しました',
                    'network_error' => 'ネットワークエラーが発生しました',
                    'rate_limit' => 'リクエスト制限に達しました',
                    'max_length_exceeded' => 'メッセージが長すぎます'
                ]
            ]);
        }
    }
    
    /**
     * 管理画面メニュー追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'AI コンセルジュ',
            'AI コンセルジュ',
            'manage_options',
            'gi-ai-concierge',
            [$this, 'admin_page_dashboard'],
            'dashicons-robot',
            30
        );
        
        add_submenu_page(
            'gi-ai-concierge',
            '設定',
            '設定',
            'manage_options',
            'gi-ai-concierge-settings',
            [$this, 'admin_page_settings']
        );
        
        add_submenu_page(
            'gi-ai-concierge',
            '会話ログ',
            '会話ログ',
            'manage_options',
            'gi-ai-concierge-logs',
            [$this, 'admin_page_logs']
        );
        
        add_submenu_page(
            'gi-ai-concierge',
            '分析・統計',
            '分析・統計',
            'manage_options',
            'gi-ai-concierge-analytics',
            [$this, 'admin_page_analytics']
        );
    }
    
    /**
     * 設定登録
     */
    public function register_settings() {
        register_setting('gi_ai_concierge_settings_group', 'gi_ai_concierge_settings');
    }
    
    /**
     * ダッシュボードページ
     */
    public function admin_page_dashboard() {
        $stats = $this->get_dashboard_stats();
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-robot"></span> AI コンセルジュ ダッシュボード</h1>
            
            <div class="gi-dashboard-stats">
                <div class="gi-stat-card">
                    <h3>今日の会話数</h3>
                    <div class="gi-stat-number"><?php echo number_format($stats['conversations_today']); ?></div>
                </div>
                
                <div class="gi-stat-card">
                    <h3>総メッセージ数</h3>
                    <div class="gi-stat-number"><?php echo number_format($stats['total_messages']); ?></div>
                </div>
                
                <div class="gi-stat-card">
                    <h3>平均満足度</h3>
                    <div class="gi-stat-number"><?php echo number_format($stats['avg_satisfaction'], 1); ?>/5.0</div>
                </div>
                
                <div class="gi-stat-card">
                    <h3>平均応答時間</h3>
                    <div class="gi-stat-number"><?php echo number_format($stats['avg_response_time'], 2); ?>秒</div>
                </div>
            </div>
            
            <div class="gi-dashboard-recent">
                <h2>最近の会話</h2>
                <div class="gi-recent-conversations">
                    <?php foreach ($stats['recent_conversations'] as $conversation): ?>
                    <div class="gi-conversation-item">
                        <div class="gi-conversation-message"><?php echo esc_html(mb_substr($conversation['message'], 0, 100)); ?>...</div>
                        <div class="gi-conversation-meta">
                            <?php echo esc_html($conversation['created_at']); ?> - 
                            意図: <?php echo esc_html($conversation['intent']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <style>
        .gi-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .gi-stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #2271b1;
        }
        
        .gi-stat-card h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
            font-size: 14px;
        }
        
        .gi-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .gi-recent-conversations {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .gi-conversation-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .gi-conversation-item:last-child {
            border-bottom: none;
        }
        
        .gi-conversation-message {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .gi-conversation-meta {
            font-size: 12px;
            color: #757575;
        }
        </style>
        <?php
    }
    
    /**
     * 設定ページ
     */
    public function admin_page_settings() {
        if (isset($_POST['submit'])) {
            $settings = [
                'openai_api_key' => sanitize_text_field($_POST['openai_api_key'] ?? ''),
                'model' => sanitize_text_field($_POST['model'] ?? 'gpt-4'),
                'max_tokens' => intval($_POST['max_tokens'] ?? 1500),
                'temperature' => floatval($_POST['temperature'] ?? 0.7),
                'conversation_memory_limit' => intval($_POST['conversation_memory_limit'] ?? 10),
                'enable_emotion_analysis' => !empty($_POST['enable_emotion_analysis']),
                'enable_learning_system' => !empty($_POST['enable_learning_system']),
                'enable_personalization' => !empty($_POST['enable_personalization']),
                'enable_multilingual' => !empty($_POST['enable_multilingual']),
                'rate_limit_per_user' => intval($_POST['rate_limit_per_user'] ?? 60),
                'max_conversation_length' => intval($_POST['max_conversation_length'] ?? 50)
            ];
            
            update_option('gi_ai_concierge_settings', $settings);
            echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
        }
        
        $settings = $this->settings;
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-admin-settings"></span> AI コンセルジュ設定</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('gi_ai_concierge_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI API キー</th>
                        <td>
                            <input type="password" name="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key']); ?>" class="regular-text" />
                            <p class="description">OpenAI の API キーを入力してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">使用モデル</th>
                        <td>
                            <select name="model">
                                <option value="gpt-3.5-turbo" <?php selected($settings['model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (高速・低コスト)</option>
                                <option value="gpt-4" <?php selected($settings['model'], 'gpt-4'); ?>>GPT-4 (高品質・推奨)</option>
                                <option value="gpt-4-turbo" <?php selected($settings['model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo (最新)</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">最大トークン数</th>
                        <td>
                            <input type="number" name="max_tokens" value="<?php echo esc_attr($settings['max_tokens']); ?>" min="100" max="4000" />
                            <p class="description">応答の最大長さ（推奨: 1500）</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">創造性レベル</th>
                        <td>
                            <input type="number" name="temperature" value="<?php echo esc_attr($settings['temperature']); ?>" min="0" max="1" step="0.1" />
                            <p class="description">0.0-1.0（推奨: 0.7）</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">会話記憶数</th>
                        <td>
                            <input type="number" name="conversation_memory_limit" value="<?php echo esc_attr($settings['conversation_memory_limit']); ?>" min="1" max="50" />
                            <p class="description">AIが記憶する過去の会話数</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">感情分析機能</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_emotion_analysis" <?php checked($settings['enable_emotion_analysis']); ?> />
                                ユーザーの感情を分析して適切に対応
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">学習システム</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_learning_system" <?php checked($settings['enable_learning_system']); ?> />
                                過去の会話から学習して回答を改善
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">パーソナライゼーション</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_personalization" <?php checked($settings['enable_personalization']); ?> />
                                ユーザーごとに個別化された体験を提供
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">多言語対応</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_multilingual" <?php checked($settings['enable_multilingual']); ?> />
                                英語など他言語での質問に対応
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">利用制限（時間あたり）</th>
                        <td>
                            <input type="number" name="rate_limit_per_user" value="<?php echo esc_attr($settings['rate_limit_per_user']); ?>" min="10" max="1000" />
                            <p class="description">1ユーザーあたりの1時間の利用回数制限</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * ログページ
     */
    public function admin_page_logs() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        $page = intval($_GET['paged'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d
        ", $per_page, $offset), ARRAY_A);
        
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_pages = ceil($total_items / $per_page);
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-format-chat"></span> 会話ログ</h1>
            
            <div class="gi-logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="gi-ai-concierge-logs" />
                    <label>期間:</label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" />
                    <label>〜</label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" />
                    <label>意図:</label>
                    <select name="intent">
                        <option value="">すべて</option>
                        <option value="search_grants" <?php selected($_GET['intent'] ?? '', 'search_grants'); ?>>助成金検索</option>
                        <option value="application_help" <?php selected($_GET['intent'] ?? '', 'application_help'); ?>>申請支援</option>
                        <option value="eligibility_check" <?php selected($_GET['intent'] ?? '', 'eligibility_check'); ?>>対象確認</option>
                        <option value="deadline_inquiry" <?php selected($_GET['intent'] ?? '', 'deadline_inquiry'); ?>>締切確認</option>
                        <option value="amount_inquiry" <?php selected($_GET['intent'] ?? '', 'amount_inquiry'); ?>>金額確認</option>
                    </select>
                    <?php submit_button('フィルター', 'secondary', 'filter', false); ?>
                </form>
            </div>
            
            <div class="gi-logs-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>日時</th>
                            <th>セッション</th>
                            <th>タイプ</th>
                            <th>メッセージ</th>
                            <th>意図</th>
                            <th>感情スコア</th>
                            <th>応答時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                            <td><?php echo esc_html(substr($log['session_id'], 0, 8)); ?>...</td>
                            <td>
                                <span class="gi-message-type gi-type-<?php echo esc_attr($log['message_type']); ?>">
                                    <?php echo esc_html($log['message_type']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(mb_substr($log['message'], 0, 100)); ?>...</td>
                            <td><?php echo esc_html($log['intent'] ?? '-'); ?></td>
                            <td><?php echo $log['emotion_score'] ? number_format($log['emotion_score'], 2) : '-'; ?></td>
                            <td><?php echo $log['response_time'] ? number_format($log['response_time'], 3) . 's' : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ]);
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .gi-logs-filters {
            background: #fff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .gi-logs-filters form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .gi-message-type {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .gi-type-user {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .gi-type-assistant {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .gi-type-system {
            background: #fff3e0;
            color: #ef6c00;
        }
        </style>
        <?php
    }
    
    /**
     * 分析ページ
     */
    public function admin_page_analytics() {
        $analytics = $this->get_analytics_data();
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-chart-bar"></span> 分析・統計</h1>
            
            <div class="gi-analytics-overview">
                <div class="gi-analytics-card">
                    <h3>利用統計</h3>
                    <div class="gi-analytics-stats">
                        <div class="gi-stat">
                            <span class="gi-stat-label">総会話数</span>
                            <span class="gi-stat-value"><?php echo number_format($analytics['total_conversations']); ?></span>
                        </div>
                        <div class="gi-stat">
                            <span class="gi-stat-label">総メッセージ数</span>
                            <span class="gi-stat-value"><?php echo number_format($analytics['total_messages']); ?></span>
                        </div>
                        <div class="gi-stat">
                            <span class="gi-stat-label">平均応答時間</span>
                            <span class="gi-stat-value"><?php echo number_format($analytics['avg_response_time'], 2); ?>秒</span>
                        </div>
                    </div>
                </div>
                
                <div class="gi-analytics-card">
                    <h3>人気の質問意図</h3>
                    <div class="gi-intent-chart">
                        <?php foreach ($analytics['popular_intents'] as $intent => $count): ?>
                        <div class="gi-intent-bar">
                            <span class="gi-intent-label"><?php echo esc_html($intent); ?></span>
                            <div class="gi-intent-progress">
                                <div class="gi-intent-fill" style="width: <?php echo ($count / max($analytics['popular_intents']) * 100); ?>%"></div>
                            </div>
                            <span class="gi-intent-count"><?php echo $count; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="gi-analytics-charts">
                <div class="gi-chart-container">
                    <h3>日別利用状況（過去30日）</h3>
                    <canvas id="gi-daily-usage-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="gi-chart-container">
                    <h3>満足度推移</h3>
                    <canvas id="gi-satisfaction-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // 日別利用状況チャート
        const dailyUsageCtx = document.getElementById('gi-daily-usage-chart').getContext('2d');
        new Chart(dailyUsageCtx, {
            type: 'line',
            data: {
                labels: <?php echo wp_json_encode(array_keys($analytics['daily_usage'])); ?>,
                datasets: [{
                    label: '会話数',
                    data: <?php echo wp_json_encode(array_values($analytics['daily_usage'])); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // 満足度チャート
        const satisfactionCtx = document.getElementById('gi-satisfaction-chart').getContext('2d');
        new Chart(satisfactionCtx, {
            type: 'bar',
            data: {
                labels: ['1星', '2星', '3星', '4星', '5星'],
                datasets: [{
                    label: '件数',
                    data: <?php echo wp_json_encode(array_values($analytics['satisfaction_distribution'])); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        </script>
        
        <style>
        .gi-analytics-overview {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .gi-analytics-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .gi-analytics-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .gi-stat {
            text-align: center;
        }
        
        .gi-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .gi-stat-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .gi-intent-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .gi-intent-label {
            width: 120px;
            font-size: 12px;
        }
        
        .gi-intent-progress {
            flex: 1;
            height: 20px;
            background: #f0f0f1;
            border-radius: 10px;
            margin: 0 10px;
            overflow: hidden;
        }
        
        .gi-intent-fill {
            height: 100%;
            background: linear-gradient(90deg, #2271b1, #72aee6);
            transition: width 0.3s ease;
        }
        
        .gi-intent-count {
            width: 40px;
            text-align: right;
            font-weight: bold;
        }
        
        .gi-analytics-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        .gi-chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .gi-analytics-overview,
            .gi-analytics-charts {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * ダッシュボード統計取得
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        
        // 今日の会話数
        $conversations_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT session_id) 
            FROM $table 
            WHERE DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        // 総メッセージ数
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        // 平均満足度（フィードバックから）
        $avg_satisfaction = $wpdb->get_var("
            SELECT AVG(
                CAST(
                    JSON_UNQUOTE(JSON_EXTRACT(context, '$.rating')) 
                    AS UNSIGNED
                )
            ) 
            FROM $table 
            WHERE context LIKE '%rating%' 
            AND JSON_VALID(context)
        ") ?: 0;
        
        // 平均応答時間
        $avg_response_time = $wpdb->get_var("
            SELECT AVG(response_time) 
            FROM $table 
            WHERE response_time IS NOT NULL
        ") ?: 0;
        
        // 最近の会話
        $recent_conversations = $wpdb->get_results("
            SELECT message, intent, created_at 
            FROM $table 
            WHERE message_type = 'user' 
            ORDER BY created_at DESC 
            LIMIT 10
        ", ARRAY_A);
        
        return [
            'conversations_today' => intval($conversations_today),
            'total_messages' => intval($total_messages),
            'avg_satisfaction' => floatval($avg_satisfaction),
            'avg_response_time' => floatval($avg_response_time),
            'recent_conversations' => $recent_conversations
        ];
    }
    
    /**
     * 分析データ取得
     */
    private function get_analytics_data() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        
        // 総統計
        $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $table");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $avg_response_time = $wpdb->get_var("SELECT AVG(response_time) FROM $table WHERE response_time IS NOT NULL") ?: 0;
        
        // 人気の意図
        $popular_intents = $wpdb->get_results("
            SELECT intent, COUNT(*) as count 
            FROM $table 
            WHERE intent IS NOT NULL 
            GROUP BY intent 
            ORDER BY count DESC 
            LIMIT 10
        ", ARRAY_A);
        
        $intent_data = [];
        foreach ($popular_intents as $intent) {
            $intent_data[$intent['intent']] = intval($intent['count']);
        }
        
        // 日別利用状況（過去30日）
        $daily_usage = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as date, COUNT(DISTINCT session_id) as conversations 
            FROM $table 
            WHERE created_at >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ", date('Y-m-d', strtotime('-30 days'))), ARRAY_A);
        
        $usage_data = [];
        foreach ($daily_usage as $day) {
            $usage_data[$day['date']] = intval($day['conversations']);
        }
        
        // 満足度分布
        $satisfaction_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $satisfaction_data = $wpdb->get_results("
            SELECT 
                CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.rating')) AS UNSIGNED) as rating,
                COUNT(*) as count
            FROM $table 
            WHERE context LIKE '%rating%' 
            AND JSON_VALID(context) 
            GROUP BY rating
        ", ARRAY_A);
        
        foreach ($satisfaction_data as $rating) {
            if (isset($satisfaction_distribution[$rating['rating']])) {
                $satisfaction_distribution[$rating['rating']] = intval($rating['count']);
            }
        }
        
        return [
            'total_conversations' => intval($total_conversations),
            'total_messages' => intval($total_messages),
            'avg_response_time' => floatval($avg_response_time),
            'popular_intents' => $intent_data,
            'daily_usage' => $usage_data,
            'satisfaction_distribution' => $satisfaction_distribution
        ];
    }
    
    /**
     * 日次メンテナンス
     */
    public function daily_maintenance() {
        global $wpdb;
        
        // 古いセッションデータの削除（30日以上前）
        $conversation_table = $wpdb->prefix . 'gi_ai_conversations';
        $wpdb->query($wpdb->prepare("
            DELETE FROM $conversation_table 
            WHERE created_at < %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
        
        // 学習データの最適化
        $this->learning_system->optimize_learning_data();
        
        // 統計データの更新
        $this->update_daily_analytics();
        
        // キャッシュのクリア
        wp_cache_flush_group('gi_ai_concierge');
    }
    
    /**
     * 日次分析データ更新
     */
    private function update_daily_analytics() {
        global $wpdb;
        
        $conversation_table = $wpdb->prefix . 'gi_ai_conversations';
        $analytics_table = $wpdb->prefix . 'gi_ai_analytics';
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // 昨日の統計を計算
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT session_id) as conversations,
                COUNT(*) as messages,
                AVG(response_time) as avg_response_time,
                AVG(
                    CASE 
                        WHEN JSON_VALID(context) AND JSON_EXTRACT(context, '$.rating') IS NOT NULL 
                        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.rating')) AS DECIMAL(3,2))
                        ELSE NULL 
                    END
                ) as satisfaction_score
            FROM $conversation_table 
            WHERE DATE(created_at) = %s
        ", $yesterday), ARRAY_A);
        
        // 人気の意図を取得
        $top_intents = $wpdb->get_results($wpdb->prepare("
            SELECT intent, COUNT(*) as count 
            FROM $conversation_table 
            WHERE DATE(created_at) = %s AND intent IS NOT NULL 
            GROUP BY intent 
            ORDER BY count DESC 
            LIMIT 5
        ", $yesterday), ARRAY_A);
        
        // 人気のクエリを取得
        $popular_queries = $wpdb->get_results($wpdb->prepare("
            SELECT message, COUNT(*) as count 
            FROM $conversation_table 
            WHERE DATE(created_at) = %s AND message_type = 'user' 
            GROUP BY message 
            ORDER BY count DESC 
            LIMIT 10
        ", $yesterday), ARRAY_A);
        
        // データベースに保存
        $wpdb->replace($analytics_table, [
            'date' => $yesterday,
            'total_conversations' => intval($stats['conversations']),
            'total_messages' => intval($stats['messages']),
            'avg_response_time' => floatval($stats['avg_response_time']),
            'satisfaction_score' => floatval($stats['satisfaction_score']),
            'top_intents' => wp_json_encode($top_intents),
            'popular_queries' => wp_json_encode($popular_queries),
            'created_at' => current_time('mysql')
        ]);
    }
}

/**
 * OpenAI ChatGPT API 完全実装
 * エラーハンドリング、レート制限、ストリーミング対応
 */

/**
 * ChatGPT_Client クラスの完全実装
 */
class GI_ChatGPT_Client {
    
    private $api_key;
    private $model;
    private $max_tokens;
    private $temperature;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $timeout = 60;
    private $max_retries = 3;
    
    public function __construct($settings) {
        $this->api_key = $settings['openai_api_key'];
        $this->model = $settings['model'] ?? 'gpt-4';
        $this->max_tokens = $settings['max_tokens'] ?? 1500;
        $this->temperature = $settings['temperature'] ?? 0.7;
    }
    
    /**
     * ChatGPT API 呼び出し（完全実装版）
     */
    public function generate_response($messages, $stream = false) {
        if (empty($this->api_key)) {
            throw new Exception('OpenAI API key not configured');
        }
        
        // レート制限チェック
        if (!$this->check_rate_limit()) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        $attempt = 0;
        $last_error = null;
        
        while ($attempt < $this->max_retries) {
            try {
                $response = $this->make_api_request($messages, $stream);
                
                // 成功時のレスポンス処理
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    return $this->process_success_response($response, $stream);
                }
                
                // エラーレスポンスの処理
                $error_info = $this->process_error_response($response);
                
                // リトライ可能なエラーかチェック
                if (!$this->is_retryable_error($error_info)) {
                    throw new Exception($error_info['message'], $error_info['code']);
                }
                
                $last_error = $error_info;
                
            } catch (Exception $e) {
                $last_error = ['message' => $e->getMessage(), 'code' => $e->getCode()];
                
                // リトライ不可能なエラーは即座に投げる
                if (!$this->is_retryable_error($last_error)) {
                    throw $e;
                }
            }
            
            $attempt++;
            if ($attempt < $this->max_retries) {
                // 指数バックオフでリトライ
                sleep(pow(2, $attempt));
            }
        }
        
        // 全てのリトライが失敗
        throw new Exception(
            $last_error['message'] ?? 'Failed to get response after maximum retries',
            $last_error['code'] ?? 500
        );
    }
    
    /**
     * 実際のAPI リクエスト実行
     */
    private function make_api_request($messages, $stream = false) {
        // リクエストボディの構築
        $request_body = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'stream' => $stream
        ];
        
        // 日本語対応の改善
        if ($this->is_japanese_content($messages)) {
            $request_body['temperature'] = min(0.8, $this->temperature + 0.1);
        }
        
        // リクエストヘッダー
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' Grant-Insight-AI/1.0'
        ];
        
        // wp_remote_post の引数
        $args = [
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => wp_json_encode($request_body),
            'method' => 'POST',
            'data_format' => 'body',
            'blocking' => true,
            'stream' => false,
            'decompress' => true
        ];
        
        // ストリーミング対応
        if ($stream) {
            return $this->make_streaming_request($args);
        }
        
        // 通常のリクエスト
        return wp_remote_post($this->api_url, $args);
    }
    
    /**
     * ストリーミングリクエスト処理
     */
    private function make_streaming_request($args) {
        // ストリーミング用の設定
        $args['stream'] = true;
        $args['blocking'] = false;
        
        // Server-Sent Events 用のヘッダー追加
        $args['headers']['Accept'] = 'text/event-stream';
        $args['headers']['Cache-Control'] = 'no-cache';
        
        // ストリーミングレスポンス処理
        return $this->handle_streaming_response($args);
    }
    
    /**
     * ストリーミングレスポンス処理
     */
    private function handle_streaming_response($args) {
        $response_chunks = [];
        $accumulated_content = '';
        
        // カスタムHTTPクライアントでストリーミング処理
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->api_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $args['body'],
            CURLOPT_HTTPHEADER => $this->format_curl_headers($args['headers']),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$response_chunks, &$accumulated_content) {
                return $this->process_streaming_chunk($data, $response_chunks, $accumulated_content);
            },
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => $args['headers']['User-Agent']
        ]);
        
        $exec_result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($exec_result === false) {
            throw new Exception('cURL error: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('HTTP error: ' . $http_code);
        }
        
        return [
            'body' => wp_json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => $accumulated_content,
                            'role' => 'assistant'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'total_tokens' => $this->estimate_tokens($accumulated_content),
                    'prompt_tokens' => 0,
                    'completion_tokens' => $this->estimate_tokens($accumulated_content)
                ],
                'streaming_chunks' => $response_chunks
            ]),
            'response' => [
                'code' => $http_code,
                'message' => 'OK'
            ]
        ];
    }
    
    /**
     * ストリーミングチャンク処理
     */
    private function process_streaming_chunk($data, &$response_chunks, &$accumulated_content) {
        $lines = explode("\n", $data);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }
            
            if (strpos($line, 'data: ') === 0) {
                $json_data = substr($line, 6);
                $decoded = json_decode($json_data, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['choices'][0]['delta']['content'])) {
                    $content = $decoded['choices'][0]['delta']['content'];
                    $accumulated_content .= $content;
                    $response_chunks[] = $content;
                    
                    // リアルタイム更新のためのフック
                    do_action('gi_ai_streaming_chunk', $content, $accumulated_content);
                }
            }
        }
        
        return strlen($data);
    }
    
    /**
     * cURL ヘッダー形式変換
     */
    private function format_curl_headers($headers) {
        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        return $curl_headers;
    }
    
    /**
     * 成功レスポンスの処理
     */
    private function process_success_response($response, $stream = false) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI API');
        }
        
        // レスポンス構造の検証
        if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
            throw new Exception('Invalid response structure from OpenAI API');
        }
        
        $choice = $data['choices'][0];
        
        if (!isset($choice['message']['content'])) {
            throw new Exception('No content in API response');
        }
        
        // 使用量の記録
        $this->record_token_usage($data['usage'] ?? []);
        
        // 品質チェック
        $content = $choice['message']['content'];
        $quality_score = $this->assess_response_quality($content);
        
        if ($quality_score < 0.3) {
            error_log('Low quality response detected: ' . $content);
        }
        
        return [
            'content' => $content,
            'finish_reason' => $choice['finish_reason'] ?? 'unknown',
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
            'quality_score' => $quality_score,
            'model_used' => $this->model,
            'streaming' => $stream,
            'response_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];
    }
    
    /**
     * エラーレスポンスの処理
     */
    private function process_error_response($response) {
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // WordPress エラーの場合
        if (is_wp_error($response)) {
            return [
                'code' => 'wp_error',
                'message' => 'Network error: ' . $response->get_error_message(),
                'retryable' => true
            ];
        }
        
        // JSON パースを試行
        $error_data = json_decode($body, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($error_data['error'])) {
            $error = $error_data['error'];
            
            return [
                'code' => $error['code'] ?? $http_code,
                'message' => $this->format_error_message($error, $http_code),
                'type' => $error['type'] ?? 'unknown',
                'retryable' => $this->is_error_retryable($error['type'] ?? '', $http_code)
            ];
        }
        
        // 一般的なHTTPエラー
        return [
            'code' => $http_code,
            'message' => 'HTTP Error ' . $http_code . ': ' . wp_remote_retrieve_response_message($response),
            'retryable' => in_array($http_code, [429, 500, 502, 503, 504])
        ];
    }
    
    /**
     * エラーメッセージの整形
     */
    private function format_error_message($error, $http_code) {
        $message = $error['message'] ?? 'Unknown error occurred';
        
        // 日本語でのエラーメッセージ提供
        $error_translations = [
            'insufficient_quota' => 'API使用量の上限に達しました。プランをアップグレードするか、しばらくお待ちください。',
            'invalid_api_key' => 'APIキーが無効です。設定を確認してください。',
            'rate_limit_exceeded' => 'リクエスト制限に達しました。しばらくお待ちください。',
            'model_overloaded' => 'AIモデルが過負荷状態です。しばらくお待ちください。',
            'invalid_request_error' => 'リクエストが無効です。入力内容を確認してください。'
        ];
        
        $error_type = $error['type'] ?? '';
        
        if (isset($error_translations[$error_type])) {
            return $error_translations[$error_type];
        }
        
        // HTTPコードベースの翻訳
        switch ($http_code) {
            case 401:
                return 'API認証に失敗しました。APIキーを確認してください。';
            case 429:
                return 'リクエスト制限に達しました。しばらくお待ちください。';
            case 500:
                return 'OpenAIサーバーでエラーが発生しました。しばらくお待ちください。';
            case 503:
                return 'OpenAIサービスが一時的に利用できません。';
            default:
                return $message;
        }
    }
    
    /**
     * リトライ可能エラーの判定
     */
    private function is_retryable_error($error_info) {
        if (is_array($error_info)) {
            return $error_info['retryable'] ?? false;
        }
        
        return false;
    }
    
    /**
     * エラータイプからリトライ可能性を判定
     */
    private function is_error_retryable($error_type, $http_code) {
        $retryable_types = [
            'server_error',
            'rate_limit_exceeded',
            'model_overloaded'
        ];
        
        $retryable_codes = [429, 500, 502, 503, 504];
        
        return in_array($error_type, $retryable_types) || in_array($http_code, $retryable_codes);
    }
    
    /**
     * レート制限チェック
     */
    private function check_rate_limit() {
        $cache_key = 'gi_openai_rate_limit_' . md5($this->api_key);
        $current_minute = floor(time() / 60);
        $rate_data = wp_cache_get($cache_key);
        
        if ($rate_data === false) {
            $rate_data = ['minute' => $current_minute, 'count' => 0];
        }
        
        // 分が変わった場合はリセット
        if ($rate_data['minute'] < $current_minute) {
            $rate_data = ['minute' => $current_minute, 'count' => 0];
        }
        
        // レート制限チェック（1分間に60リクエスト）
        if ($rate_data['count'] >= 60) {
            return false;
        }
        
        // カウンターを増やして保存
        $rate_data['count']++;
        wp_cache_set($cache_key, $rate_data, '', 70); // 70秒でキャッシュ期限切れ
        
        return true;
    }
    
    /**
     * 日本語コンテンツの検出
     */
    private function is_japanese_content($messages) {
        $text = '';
        foreach ($messages as $message) {
            $text .= $message['content'] ?? '';
        }
        
        // ひらがな、カタカナ、漢字の検出
        return preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}]/u', $text);
    }
    
    /**
     * トークン使用量の記録
     */
    private function record_token_usage($usage) {
        if (empty($usage)) return;
        
        $daily_usage_key = 'gi_openai_daily_usage_' . date('Y-m-d');
        $current_usage = get_transient($daily_usage_key) ?: [
            'total_tokens' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'requests' => 0
        ];
        
        $current_usage['total_tokens'] += $usage['total_tokens'] ?? 0;
        $current_usage['prompt_tokens'] += $usage['prompt_tokens'] ?? 0;
        $current_usage['completion_tokens'] += $usage['completion_tokens'] ?? 0;
        $current_usage['requests']++;
        
        set_transient($daily_usage_key, $current_usage, DAY_IN_SECONDS);
        
        // 使用量アラート
        $this->check_usage_alerts($current_usage);
    }
    
    /**
     * 使用量アラートチェック
     */
    private function check_usage_alerts($usage) {
        $settings = get_option('gi_ai_concierge_settings', []);
        $alert_threshold = $settings['daily_token_limit'] ?? 100000;
        
        if ($usage['total_tokens'] > $alert_threshold * 0.8) {
            // 管理者に通知
            $this->send_usage_alert($usage, $alert_threshold);
        }
    }
    
    /**
     * 使用量アラート送信
     */
    private function send_usage_alert($usage, $threshold) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = '[' . $site_name . '] OpenAI API使用量アラート';
        $message = "OpenAI APIの1日の使用量が閾値の80%に達しました。\n\n";
        $message .= "本日の使用量: " . number_format($usage['total_tokens']) . " トークン\n";
        $message .= "設定された閾値: " . number_format($threshold) . " トークン\n";
        $message .= "リクエスト数: " . number_format($usage['requests']) . " 回\n\n";
        $message .= "使用量を確認し、必要に応じて設定を調整してください。";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * レスポンス品質評価
     */
    private function assess_response_quality($content) {
        $score = 1.0;
        
        // 長さチェック
        if (strlen($content) < 10) {
            $score -= 0.5;
        }
        
        // 繰り返しパターンの検出
        if (preg_match('/(.{10,})\1{3,}/', $content)) {
            $score -= 0.3;
        }
        
        // 不適切な内容の検出
        $inappropriate_patterns = [
            'I cannot', 'I\'m sorry', 'As an AI', 'I don\'t have access'
        ];
        
        foreach ($inappropriate_patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $score -= 0.2;
            }
        }
        
        // 日本語として自然かチェック
        if ($this->is_japanese_content([['content' => $content]])) {
            // ひらがな・カタカナ・漢字のバランスチェック
            $hiragana_count = preg_match_all('/[\x{3040}-\x{309F}]/u', $content);
            $katakana_count = preg_match_all('/[\x{30A0}-\x{30FF}]/u', $content);
            $kanji_count = preg_match_all('/[\x{4E00}-\x{9FAF}]/u', $content);
            
            $total_japanese = $hiragana_count + $katakana_count + $kanji_count;
            
            if ($total_japanese > 0) {
                $hiragana_ratio = $hiragana_count / $total_japanese;
                
                // ひらがなが適切な割合であることを確認
                if ($hiragana_ratio < 0.3 || $hiragana_ratio > 0.8) {
                    $score -= 0.1;
                }
            }
        }
        
        return max(0.0, min(1.0, $score));
    }
    
    /**
     * トークン数の推定
     */
    private function estimate_tokens($text) {
        // 日本語テキストの場合、文字数 * 1.5 で概算
        if ($this->is_japanese_content([['content' => $text]])) {
            return intval(mb_strlen($text) * 1.5);
        }
        
        // 英語の場合、単語数 * 1.3 で概算
        return intval(str_word_count($text) * 1.3);
    }
    
    /**
     * APIキーの検証
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return ['valid' => false, 'message' => 'APIキーが設定されていません'];
        }
        
        try {
            $test_messages = [
                ['role' => 'user', 'content' => 'Hello']
            ];
            
            $response = $this->make_api_request($test_messages);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                return ['valid' => true, 'message' => 'APIキーが有効です'];
            } else {
                $error_info = $this->process_error_response($response);
                return ['valid' => false, 'message' => $error_info['message']];
            }
            
        } catch (Exception $e) {
            return ['valid' => false, 'message' => 'APIキー検証エラー: ' . $e->getMessage()];
        }
    }
    
    /**
     * 利用可能なモデル一覧取得
     */
    public function get_available_models() {
        $models_response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($models_response)) {
            return ['gpt-4', 'gpt-3.5-turbo']; // フォールバック
        }
        
        $models_data = json_decode(wp_remote_retrieve_body($models_response), true);
        
        if (!isset($models_data['data'])) {
            return ['gpt-4', 'gpt-3.5-turbo']; // フォールバック
        }
        
        $chat_models = [];
        foreach ($models_data['data'] as $model) {
            if (strpos($model['id'], 'gpt') === 0) {
                $chat_models[] = $model['id'];
            }
        }
        
        return $chat_models;
    }
    
    /**
     * 今日の使用量取得
     */
    public function get_daily_usage() {
        $daily_usage_key = 'gi_openai_daily_usage_' . date('Y-m-d');
        return get_transient($daily_usage_key) ?: [
            'total_tokens' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'requests' => 0
        ];
    }
}

/**
 * =============================================================================
 * 管理画面でのAPI設定テスト機能追加
 * =============================================================================
 */

// AJAX エンドポイントでAPIキー検証
function gi_ajax_test_openai_api() {
    check_ajax_referer('gi_ai_concierge_settings_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('権限がありません');
    }
    
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    
    if (empty($api_key)) {
        wp_send_json_error('APIキーが入力されていません');
    }
    
    // 一時的にクライアントを作成してテスト
    $test_settings = [
        'openai_api_key' => $api_key,
        'model' => 'gpt-3.5-turbo',
        'max_tokens' => 100,
        'temperature' => 0.7
    ];
    
    $client = new GI_ChatGPT_Client($test_settings);
    $validation_result = $client->validate_api_key();
    
    if ($validation_result['valid']) {
        // 利用可能なモデルも取得
        $available_models = $client->get_available_models();
        
        wp_send_json_success([
            'message' => $validation_result['message'],
            'available_models' => $available_models,
            'daily_usage' => $client->get_daily_usage()
        ]);
    } else {
        wp_send_json_error($validation_result['message']);
    }
}
add_action('wp_ajax_gi_test_openai_api', 'gi_ajax_test_openai_api');

/**
 * =============================================================================
 * 使用量監視とアラート機能
 * =============================================================================
 */

// 日次使用量レポート生成
function gi_generate_daily_usage_report() {
    $settings = get_option('gi_ai_concierge_settings', []);
    
    if (empty($settings['openai_api_key'])) {
        return;
    }
    
    $client = new GI_ChatGPT_Client($settings);
    $usage = $client->get_daily_usage();
    
    // 使用量をデータベースに記録
    global $wpdb;
    $analytics_table = $wpdb->prefix . 'gi_ai_analytics';
    
    $wpdb->replace($analytics_table, [
        'date' => current_time('Y-m-d'),
        'openai_tokens_used' => $usage['total_tokens'],
        'openai_requests_made' => $usage['requests'],
        'created_at' => current_time('mysql')
    ]);
}
add_action('gi_daily_ai_maintenance', 'gi_generate_daily_usage_report');

// 緊急停止機能（使用量が上限を超えた場合）
function gi_emergency_api_stop_check() {
    $settings = get_option('gi_ai_concierge_settings', []);
    $emergency_limit = $settings['emergency_token_limit'] ?? 200000;
    
    $client = new GI_ChatGPT_Client($settings);
    $usage = $client->get_daily_usage();
    
    if ($usage['total_tokens'] > $emergency_limit) {
        // 緊急停止フラグを設定
        update_option('gi_ai_emergency_stop', true);
        
        // 管理者に緊急通知
        $admin_email = get_option('admin_email');
        wp_mail(
            $admin_email,
            '【緊急】AI Concierge API使用量上限達成',
            "OpenAI APIの使用量が緊急停止レベルに達したため、AIチャット機能を一時停止しました。\n\n使用量: " . number_format($usage['total_tokens']) . " tokens\n\n管理画面から設定を確認してください。"
        );
    }
}
add_action('gi_ai_token_usage_check', 'gi_emergency_api_stop_check');

// 緊急停止状態のチェック
function gi_is_api_emergency_stopped() {
    return get_option('gi_ai_emergency_stop', false);
}

// 緊急停止の解除（管理者のみ）
function gi_reset_emergency_stop() {
    if (current_user_can('manage_options')) {
        delete_option('gi_ai_emergency_stop');
        return true;
    }
    return false;
}

/**
 * =============================================================================
 * 3. セッション管理クラス
 * =============================================================================
 */

class GI_Session_Manager {
    
    /**
     * 新しいセッション作成
     */
    public function create_session() {
        return 'gi_session_' . wp_generate_uuid4();
    }
    
    /**
     * セッションの検証
     */
    public function validate_session($session_id) {
        return !empty($session_id) && strpos($session_id, 'gi_session_') === 0;
    }
    
    /**
     * セッション情報の取得
     */
    public function get_session_info($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                session_id,
                MIN(created_at) as started_at,
                MAX(created_at) as last_activity,
                COUNT(*) as message_count,
                COUNT(DISTINCT CASE WHEN message_type = 'user' THEN id END) as user_messages,
                COUNT(DISTINCT CASE WHEN message_type = 'assistant' THEN id END) as assistant_messages
            FROM $table 
            WHERE session_id = %s 
            GROUP BY session_id
        ", $session_id), ARRAY_A);
    }
}

/**
 * =============================================================================
 * 4. セマンティック検索エンジン
 * =============================================================================
 */

class GI_Semantic_Search_Engine {
    
    private $synonyms_map = [];
    private $embeddings_cache = [];
    private $vector_db = null;
    private $similarity_threshold = 0.7;
    
    public function __construct() {
        $this->load_synonyms();
        $this->init_vector_database();
    }
    
    /**
     * 同義語マップ読み込み（拡張版）
     */
    private function load_synonyms() {
        $this->synonyms_map = [
            '助成金' => ['補助金', '支援金', '給付金', '支援制度', 'サポート', '援助', 'ファンド'],
            '中小企業' => ['小規模事業者', 'SME', '零細企業', '個人事業主', 'ベンチャー'],
            '創業' => ['起業', '開業', 'スタートアップ', '新規事業', '独立', '法人設立'],
            '設備投資' => ['機械導入', '設備購入', '機器更新', 'DX投資', '生産設備', '機械化'],
            '人材育成' => ['教育訓練', '研修', 'スキルアップ', '人材開発', '社員教育', 'OJT'],
            '海外展開' => ['輸出', '国際展開', 'グローバル展開', '越境EC', '海外進出'],
            '研究開発' => ['R&D', 'イノベーション', '技術開発', '製品開発', '新技術'],
            'IT化' => ['デジタル化', 'DX', 'システム導入', 'デジタルトランスフォーメーション', 'ICT'],
            '省エネ' => ['環境対策', 'グリーン化', '脱炭素', '再生可能エネルギー', 'SDGs', 'カーボンニュートラル'],
            '雇用' => ['採用', '就職', 'キャリア', '人材確保', '労働', 'ワークライフバランス'],
            '資金調達' => ['融資', '借入', 'ローン', 'ファイナンス', '投資', 'クラウドファンディング'],
            '生産性向上' => ['効率化', '改善', '最適化', 'コスト削減', '業務改善']
        ];
    }
    
    /**
     * ベクトルデータベース初期化
     */
    private function init_vector_database() {
        // ベクトル検索用のインデックスを初期化
        $this->vector_db = new GI_Vector_Database();
        $this->build_grant_embeddings();
    }
    
    /**
     * 助成金データのベクトル化
     */
    private function build_grant_embeddings() {
        // ベクトルデータベースが利用可能な場合のみ実行
        if (!class_exists('GI_Vector_Database')) {
            return;
        }
        
        $grants = get_posts([
            'post_type' => 'grant',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        foreach ($grants as $grant) {
            $text = $this->prepare_grant_text($grant);
            $embedding = $this->generate_embedding($text);
            if ($this->vector_db) {
                $this->vector_db->store($grant->ID, $embedding);
            }
        }
    }
    
    /**
     * 助成金テキスト準備
     */
    private function prepare_grant_text($grant) {
        $text = '';
        
        // タイトル
        $text .= $grant->post_title . ' ';
        
        // 内容
        $text .= wp_strip_all_tags($grant->post_content) . ' ';
        
        // ACFフィールド
        if (function_exists('get_field')) {
            $fields = [
                'target_industry' => '対象業種',
                'target_region' => '対象地域',
                'requirements' => '申請要件',
                'max_amount' => '最大金額'
            ];
            
            foreach ($fields as $field => $label) {
                $value = get_field($field, $grant->ID);
                if ($value) {
                    $text .= $label . ': ' . $value . ' ';
                }
            }
        }
        
        return $text;
    }
    
    /**
     * エンベディング生成（簡易版）
     */
    private function generate_embedding($text) {
        // 簡易的なベクトル化（実際はOpenAI APIなどを使用）
        $words = $this->tokenize_text($text);
        $vector = [];
        
        // 単語頻度ベースの簡易ベクトル
        $word_counts = array_count_values($words);
        $max_count = max($word_counts);
        
        foreach ($word_counts as $word => $count) {
            $vector[] = $count / $max_count;
        }
        
        // ベクトルサイズを固定（例：100次元）
        while (count($vector) < 100) {
            $vector[] = 0;
        }
        
        return array_slice($vector, 0, 100);
    }
    
    /**
     * テキストのトークン化
     */
    private function tokenize_text($text) {
        // 簡易的な日本語トークン化
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text);
        return array_filter($words);
    }
    
    /**
     * セマンティック検索実行（完全版）
     */
    public function search($query, $filters = [], $page = 1, $per_page = 10) {
        // クエリの前処理と分析
        $processed_query = $this->preprocess_query($query);
        $intent = $this->analyze_search_intent($processed_query);
        
        // 同義語展開とクエリ拡張
        $expanded_query = $this->expand_synonyms($processed_query);
        $semantic_keywords = $this->extract_semantic_keywords($expanded_query);
        
        // ベクトル類似度検索
        $vector_results = [];
        if ($this->vector_db) {
            $query_embedding = $this->generate_embedding($expanded_query);
            $vector_results = $this->vector_db->search($query_embedding, 20);
        }
        
        // ハイブリッド検索（キーワード + セマンティック）
        $search_args = $this->build_hybrid_search_args(
            $expanded_query,
            $semantic_keywords,
            $vector_results,
            $filters,
            $page,
            $per_page
        );
        
        $query_obj = new WP_Query($search_args);
        
        $results = [];
        if ($query_obj->have_posts()) {
            while ($query_obj->have_posts()) {
                $query_obj->the_post();
                $post_id = get_the_ID();
                
                // スコアリング計算（既存のメソッドを使用）
                $relevance_score = $this->calculate_relevance_score(
                    $post_id,
                    $processed_query
                );
                
                $results[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'relevance_score' => $relevance_score,
                    'matched_keywords' => $this->get_matched_keywords($post_id, $semantic_keywords),
                    'relevance_score' => $this->calculate_relevance_score($post_id, $processed_query),
                    'meta' => $this->get_post_meta_for_search($post_id)
                ];
            }
            wp_reset_postdata();
        }
        
        // 関連度でソート
        usort($results, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        return [
            'results' => $results,
            'total_found' => $query_obj->found_posts,
            'current_page' => $page,
            'total_pages' => $query_obj->max_num_pages,
            'query_info' => [
                'original_query' => $query,
                'processed_query' => $processed_query,
                'expanded_terms' => $expanded_query
            ]
        ];
    }
    
    /**
     * クエリ前処理
     */
    private function preprocess_query($query) {
        // 全角→半角変換
        $query = mb_convert_kana($query, 'as');
        
        // 不要な文字の削除
        $query = preg_replace('/[^\p{L}\p{N}\s\-_]/u', ' ', $query);
        
        // 連続する空白の正規化
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        return $query;
    }
    
    /**
     * 同義語展開
     */
    private function expand_synonyms($query) {
        $expanded_terms = [$query];
        
        foreach ($this->synonyms_map as $base_term => $synonyms) {
            if (strpos($query, $base_term) !== false) {
                foreach ($synonyms as $synonym) {
                    $expanded_terms[] = str_replace($base_term, $synonym, $query);
                }
            }
            
            foreach ($synonyms as $synonym) {
                if (strpos($query, $synonym) !== false) {
                    $expanded_terms[] = str_replace($synonym, $base_term, $query);
                    foreach ($synonyms as $other_synonym) {
                        if ($other_synonym !== $synonym) {
                            $expanded_terms[] = str_replace($synonym, $other_synonym, $query);
                        }
                    }
                }
            }
        }
        
        return array_unique($expanded_terms);
    }
    
    /**
     * 検索引数構築
     */
    private function build_search_args($expanded_query, $filters, $page, $per_page) {
        $args = [
            'post_type' => 'grant',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => implode(' ', $expanded_query),
            'meta_query' => ['relation' => 'AND'],
            'tax_query' => ['relation' => 'AND']
        ];
        
        // フィルター適用
        if (!empty($filters['categories'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'grant_category',
                'field' => 'slug',
                'terms' => $filters['categories'],
                'operator' => 'IN'
            ];
        }
        
        if (!empty($filters['prefectures'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'grant_prefecture',
                'field' => 'slug',
                'terms' => $filters['prefectures'],
                'operator' => 'IN'
            ];
        }
        
        if (!empty($filters['status'])) {
            $args['meta_query'][] = [
                'key' => 'application_status',
                'value' => $filters['status'],
                'compare' => 'IN'
            ];
        }
        
        if (!empty($filters['amount_min']) || !empty($filters['amount_max'])) {
            $amount_query = [
                'key' => 'max_amount_numeric',
                'type' => 'NUMERIC'
            ];
            
            if (!empty($filters['amount_min']) && !empty($filters['amount_max'])) {
                $amount_query['value'] = [$filters['amount_min'], $filters['amount_max']];
                $amount_query['compare'] = 'BETWEEN';
            } elseif (!empty($filters['amount_min'])) {
                $amount_query['value'] = $filters['amount_min'];
                $amount_query['compare'] = '>=';
            } elseif (!empty($filters['amount_max'])) {
                $amount_query['value'] = $filters['amount_max'];
                $amount_query['compare'] = '<=';
            }
            
            $args['meta_query'][] = $amount_query;
        }
        
        return $args;
    }
    
    /**
     * 関連度スコア計算
     */
    private function calculate_relevance_score($post_id, $query) {
        $score = 0;
        $query_terms = explode(' ', strtolower($query));
        
        // タイトルでのマッチング (重み: 3.0)
        $title = strtolower(get_the_title($post_id));
        foreach ($query_terms as $term) {
            if (strpos($title, $term) !== false) {
                $score += 3.0;
            }
        }
        
        // 抜粋でのマッチング (重み: 2.0)
        $excerpt = strtolower(get_the_excerpt($post_id));
        foreach ($query_terms as $term) {
            if (strpos($excerpt, $term) !== false) {
                $score += 2.0;
            }
        }
        
        // メタフィールドでのマッチング (重み: 1.5)
        $meta_fields = ['organization', 'grant_target', 'eligible_expenses'];
        foreach ($meta_fields as $field) {
            $meta_value = strtolower(get_post_meta($post_id, $field, true));
            foreach ($query_terms as $term) {
                if (strpos($meta_value, $term) !== false) {
                    $score += 1.5;
                }
            }
        }
        
        // タクソノミーでのマッチング (重み: 1.0)
        $taxonomies = get_object_taxonomies('grant');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
            foreach ($terms as $term_name) {
                $term_name = strtolower($term_name);
                foreach ($query_terms as $term) {
                    if (strpos($term_name, $term) !== false) {
                        $score += 1.0;
                    }
                }
            }
        }
        
        return $score;
    }
    
    /**
     * 検索用メタ情報取得
     */
    private function get_post_meta_for_search($post_id) {
        return [
            'organization' => get_post_meta($post_id, 'organization', true),
            'amount' => get_post_meta($post_id, 'max_amount', true),
            'deadline' => get_post_meta($post_id, 'deadline', true),
            'status' => get_post_meta($post_id, 'application_status', true),
            'difficulty' => get_post_meta($post_id, 'grant_difficulty', true),
            'success_rate' => get_post_meta($post_id, 'grant_success_rate', true)
        ];
    }
    
    /**
     * 検索意図分析（既存のメソッドを補完）
     */
    private function analyze_search_intent($query) {
        $intent = [
            'intent' => 'search_grants',
            'confidence' => 0.8,
            'type' => 'general'
        ];
        
        // キーワードベースの意図判定
        if (strpos($query, '申請') !== false || strpos($query, '手続き') !== false) {
            $intent['intent'] = 'application_help';
        } elseif (strpos($query, '締切') !== false || strpos($query, '期限') !== false) {
            $intent['intent'] = 'deadline_inquiry';
        } elseif (strpos($query, '金額') !== false || strpos($query, 'いくら') !== false) {
            $intent['intent'] = 'amount_inquiry';
        } elseif (strpos($query, '対象') !== false || strpos($query, '該当') !== false) {
            $intent['intent'] = 'eligibility_check';
        }
        
        return $intent;
    }
    
    /**
     * セマンティックキーワード抽出（既存のメソッドを補完）
     */
    private function extract_semantic_keywords($query) {
        $keywords = [];
        
        // 重要キーワードのパターン
        $patterns = [
            '助成金', '補助金', '支援金', '給付金',
            '中小企業', '個人事業主', 'スタートアップ',
            '設備投資', '人材育成', '研究開発', 'IT化',
            '申請', '締切', '対象', '条件'
        ];
        
        foreach ($patterns as $pattern) {
            if (strpos($query, $pattern) !== false) {
                $keywords[] = $pattern;
            }
        }
        
        return $keywords;
    }
    
    /**
     * ハイブリッド検索引数構築（既存のメソッドを補完）
     */
    private function build_hybrid_search_args($expanded_query, $semantic_keywords, $vector_results, $filters, $page, $per_page) {
        $search_args = [
            'post_type' => 'grant',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => []
        ];
        
        // キーワード検索
        if (!empty($semantic_keywords)) {
            $search_args['s'] = implode(' ', $semantic_keywords);
        } elseif (!empty($expanded_query)) {
            $search_args['s'] = $expanded_query;
        }
        
        // フィルター適用
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    $search_args['meta_query'][] = [
                        'key' => $key,
                        'value' => $value,
                        'compare' => 'LIKE'
                    ];
                }
            }
        }
        
        // ベクトル検索結果がある場合は優先
        if (!empty($vector_results)) {
            $post_ids = array_column($vector_results, 'post_id');
            if (!empty($post_ids)) {
                $search_args['post__in'] = $post_ids;
                $search_args['orderby'] = 'post__in';
            }
        }
        
        return $search_args;
    }
    
    /**
     * マッチしたキーワード取得（新規追加）
     */
    private function get_matched_keywords($post_id, $keywords) {
        $matched = [];
        $content = get_post_field('post_content', $post_id);
        $title = get_the_title($post_id);
        $text = $title . ' ' . $content;
        
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $matched[] = $keyword;
            }
        }
        
        return $matched;
    }
}

/**
 * =============================================================================
 * 5. コンテキスト管理クラス
 * =============================================================================
 */

class GI_Context_Manager {
    
    /**
     * コンテキスト取得
     */
    public function get_context($session_id) {
        $context = wp_cache_get('gi_context_' . $session_id);
        
        if ($context === false) {
            $context = $this->load_context_from_session($session_id);
            wp_cache_set('gi_context_' . $session_id, $context, '', 1800); // 30分キャッシュ
        }
        
        return $context;
    }
    
    /**
     * コンテキスト更新
     */
    public function update_context($current_context, $message, $intent) {
        // ユーザーの事業情報を抽出・更新
        $business_info = $this->extract_business_context($message);
        if (!empty($business_info)) {
            $current_context = array_merge($current_context, $business_info);
        }
        
        // 現在の関心事を更新
        $current_context['current_focus'] = $this->determine_current_focus($intent, $message);
        
        // 最終更新時刻
        $current_context['last_updated'] = current_time('mysql');
        
        // セッション情報を保存
        $this->save_context_to_session($current_context['session_id'] ?? '', $current_context);
        
        return $current_context;
    }
    
    /**
     * セッションからコンテキスト読み込み
     */
    private function load_context_from_session($session_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_conversations';
        $latest_context = $wpdb->get_var($wpdb->prepare("
            SELECT context 
            FROM $table 
            WHERE session_id = %s 
            AND context IS NOT NULL 
            ORDER BY created_at DESC 
            LIMIT 1
        ", $session_id));
        
        if ($latest_context && is_string($latest_context)) {
            $context = json_decode($latest_context, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $context;
            }
        }
        
        // デフォルトコンテキスト
        return [
            'session_id' => $session_id,
            'user_business_type' => '',
            'user_location' => '',
            'current_focus' => '',
            'preferences' => [],
            'history_summary' => '',
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * セッションにコンテキスト保存
     */
    private function save_context_to_session($session_id, $context) {
        if (empty($session_id)) return;
        
        wp_cache_set('gi_context_' . $session_id, $context, '', 1800);
    }
    
    /**
     * ビジネスコンテキストの抽出
     */
    private function extract_business_context($message) {
        $context = [];
        
        // 業種の抽出
        $business_patterns = [
            '製造業' => ['製造', 'メーカー', '工場', '生産', '製品'],
            'IT業' => ['IT', 'システム', 'ソフトウェア', 'アプリ', 'Web', 'デジタル'],
            '小売業' => ['小売', '販売', '店舗', 'ショップ', '商店'],
            '建設業' => ['建設', '工事', '建築', 'リフォーム', '施工'],
            'サービス業' => ['サービス', 'コンサル', '相談', '支援'],
            '飲食業' => ['飲食', 'レストラン', 'カフェ', '居酒屋', '料理'],
            '農業' => ['農業', '農家', '農産', '野菜', '果物', '畜産'],
            '運輸業' => ['運送', '物流', '配送', 'トラック', '輸送'],
            '医療・介護' => ['医療', '介護', '病院', 'クリニック', '福祉', '看護']
        ];
        
        foreach ($business_patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $context['user_business_type'] = $type;
                    break 2;
                }
            }
        }
        
        // 地域の抽出
        $prefectures = [
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
            '岐阜県', '静岡県', '愛知県', '三重県',
            '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
            '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県',
            '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
        ];
        
        foreach ($prefectures as $prefecture) {
            if (strpos($message, $prefecture) !== false) {
                $context['user_location'] = $prefecture;
                break;
            }
        }
        
        // 事業規模の抽出
        $size_patterns = [
            '個人事業主' => ['個人事業主', '個人事業', 'フリーランス', '自営業'],
            '小規模事業者' => ['小規模', '従業員5名', '従業員10名'],
            '中小企業' => ['中小企業', '従業員20名', '従業員50名', '従業員100名'],
            '中堅企業' => ['中堅', '従業員200名', '従業員300名']
        ];
        
        foreach ($size_patterns as $size => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $context['business_size'] = $size;
                    break 2;
                }
            }
        }
        
        return $context;
    }
    
    /**
     * 現在の関心事の決定
     */
    private function determine_current_focus($intent, $message) {
        $focus_keywords = [
            '設備投資' => ['設備', '機械', '導入', '購入', 'システム', 'IT'],
            '人材育成' => ['研修', '教育', '人材', 'スキルアップ', '訓練'],
            '新規事業' => ['新規', '新事業', '新サービス', '開発', '創業'],
            '海外展開' => ['海外', '輸出', '国際', 'グローバル', '展開'],
            '研究開発' => ['研究', '開発', 'R&D', 'イノベーション', '技術'],
            '環境対策' => ['環境', '省エネ', 'エコ', '脱炭素', 'グリーン'],
            '働き方改革' => ['働き方', 'テレワーク', '在宅', 'リモート', '時短'],
            '事業承継' => ['承継', '後継者', '引き継ぎ', 'M&A', '事業継承']
        ];
        
        foreach ($focus_keywords as $focus => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $focus;
                }
            }
        }
        
        // 意図ベースのフォーカス決定
        $intent_focus_map = [
            'search_grants' => '助成金検索',
            'application_help' => '申請支援',
            'eligibility_check' => '対象確認',
            'deadline_inquiry' => '締切確認',
            'amount_inquiry' => '金額確認'
        ];
        
        return $intent_focus_map[$intent['intent']] ?? '一般相談';
    }
}

/**
 * =============================================================================
 * 6. 感情分析エンジン
 * =============================================================================
 */

class GI_Emotion_Analyzer {
    
    private $emotion_keywords = [];
    
    public function __construct() {
        $this->load_emotion_keywords();
    }
    
    /**
     * 感情キーワード読み込み
     */
    private function load_emotion_keywords() {
        $this->emotion_keywords = [
            'positive' => [
                'ありがとう' => 0.8,
                '嬉しい' => 0.7,
                '助かる' => 0.6,
                '良い' => 0.5,
                '素晴らしい' => 0.9,
                '期待' => 0.6,
                '楽しみ' => 0.7,
                '満足' => 0.8,
                '安心' => 0.6,
                '希望' => 0.7
            ],
            'negative' => [
                '困っ' => -0.6,
                '分からない' => -0.4,
                '難しい' => -0.5,
                '不安' => -0.7,
                '心配' => -0.6,
                '大変' => -0.5,
                '厳しい' => -0.6,
                '無理' => -0.8,
                '諦め' => -0.9,
                'ダメ' => -0.7
            ],
            'neutral' => [
                '教え' => 0.0,
                '確認' => 0.0,
                '質問' => 0.0,
                '聞き' => 0.0,
                '知り' => 0.0,
                '調べ' => 0.0
            ]
        ];
    }
    
    /**
     * 感情分析実行
     */
    public function analyze($message) {
        $score = 0.0;
        $detected_emotions = [];
        $confidence = 0.0;
        
        $message_lower = mb_strtolower($message);
        
        // キーワードベース分析
        foreach ($this->emotion_keywords as $emotion_type => $keywords) {
            foreach ($keywords as $keyword => $weight) {
                if (strpos($message_lower, $keyword) !== false) {
                    $score += $weight;
                    $detected_emotions[] = [
                        'keyword' => $keyword,
                        'type' => $emotion_type,
                        'weight' => $weight
                    ];
                    $confidence += 0.1;
                }
            }
        }
        
        // 疑問詞や文末表現による調整
        $question_patterns = ['？', '?', 'でしょうか', 'ですか', 'ますか'];
        foreach ($question_patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                $score += 0.1; // 質問は若干ポジティブに
                $confidence += 0.05;
            }
        }
        
        // 丁寧語による調整
        $polite_patterns = ['です', 'ます', 'ございます', 'お願い'];
        foreach ($polite_patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                $score += 0.1;
                $confidence += 0.05;
            }
        }
        
        // 緊急性・切迫感の検出
        $urgency_patterns = ['急い', '至急', 'すぐに', '早く', '間に合', '締切'];
        $urgency_score = 0;
        foreach ($urgency_patterns as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                $urgency_score += 0.2;
            }
        }
        
        // スコア正規化
        $score = max(-1.0, min(1.0, $score));
        $confidence = max(0.0, min(1.0, $confidence));
        $urgency_score = max(0.0, min(1.0, $urgency_score));
        
        return [
            'score' => $score,
            'confidence' => $confidence,
            'urgency' => $urgency_score,
            'detected_emotions' => $detected_emotions,
            'interpretation' => $this->interpret_emotion($score, $urgency_score),
            'response_style' => $this->determine_response_style($score, $urgency_score)
        ];
    }
    
    /**
     * 感情の解釈
     */
    private function interpret_emotion($score, $urgency) {
        if ($score > 0.5) {
            return 'positive';
        } elseif ($score < -0.5) {
            return $urgency > 0.5 ? 'stressed' : 'confused';
        } elseif ($urgency > 0.5) {
            return 'urgent';
        } else {
            return 'neutral';
        }
    }
    
    /**
     * 応答スタイルの決定
     */
    private function determine_response_style($score, $urgency) {
        if ($urgency > 0.7) {
            return 'urgent_helpful';
        } elseif ($score < -0.5) {
            return 'compassionate';
        } elseif ($score > 0.5) {
            return 'enthusiastic';
        } else {
            return 'professional';
        }
    }
}

/**
 * =============================================================================
 * 7. 学習システム
 * =============================================================================
 */

class GI_Learning_System {
    
    /**
     * インタラクションの記録
     */
    public function record_interaction($user_query, $ai_response, $intent) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_learning';
        $query_hash = md5($user_query);
        
        // 既存レコードの更新または新規作成
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table WHERE query_hash = %s
        ", $query_hash), ARRAY_A);
        
        if ($existing) {
            // 使用回数を増やして更新
            $wpdb->update($table, [
                'usage_count' => $existing['usage_count'] + 1,
                'last_used' => current_time('mysql'),
                'processed_query' => $user_query,
                'results' => wp_json_encode(['response' => $ai_response])
            ], ['id' => $existing['id']]);
        } else {
            // 新規レコード作成
            $wpdb->insert($table, [
                'query_hash' => $query_hash,
                'original_query' => $user_query,
                'processed_query' => $user_query,
                'intent' => $intent['intent'],
                'results' => wp_json_encode(['response' => $ai_response]),
                'usage_count' => 1,
                'last_used' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ]);
        }
    }
    
    /**
     * フィードバックの記録
     */
    public function record_feedback($session_id, $message_id, $rating, $feedback_type) {
        global $wpdb;
        
        $conversation_table = $wpdb->prefix . 'gi_ai_conversations';
        $learning_table = $wpdb->prefix . 'gi_ai_learning';
        
        // 該当する会話を取得
        $conversation = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $conversation_table 
            WHERE session_id = %s AND id = %d
        ", $session_id, $message_id), ARRAY_A);
        
        if ($conversation && $conversation['message_type'] === 'user') {
            // 学習データを更新
            $query_hash = md5($conversation['message']);
            
            $wpdb->update($learning_table, [
                'feedback_score' => $rating
            ], ['query_hash' => $query_hash]);
        }
    }
    
    /**
     * 学習データの最適化
     */
    public function optimize_learning_data() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gi_ai_learning';
        
        // 30日以上使用されていない低評価データを削除
        $wpdb->query($wpdb->prepare("
            DELETE FROM $table 
            WHERE last_used < %s 
            AND usage_count <= 2 
            AND (feedback_score IS NULL OR feedback_score <= 2)
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
        
        // 高頻度・高評価のデータを特定してキャッシュ
        $popular_queries = $wpdb->get_results("
            SELECT * FROM $table 
            WHERE usage_count >= 5 
            AND (feedback_score IS NULL OR feedback_score >= 4)
            ORDER BY usage_count DESC, feedback_score DESC 
            LIMIT 100
        ", ARRAY_A);
        
        wp_cache_set('gi_popular_queries', $popular_queries, '', 86400); // 24時間キャッシュ
    }
    
    /**
     * 人気クエリの取得
     */
    public function get_popular_queries($limit = 10) {
        $cached = wp_cache_get('gi_popular_queries');
        if ($cached !== false) {
            return array_slice($cached, 0, $limit);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'gi_ai_learning';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT original_query, usage_count, feedback_score
            FROM $table 
            WHERE usage_count >= 2
            ORDER BY usage_count DESC, feedback_score DESC 
            LIMIT %d
        ", $limit), ARRAY_A);
    }
}

/**
 * =============================================================================
 * 8. 初期化・フック登録
 * =============================================================================
 */

// AIコンセルジュシステムの初期化
function gi_init_ai_concierge() {
    GI_AI_Concierge::getInstance();
}
add_action('init', 'gi_init_ai_concierge');

/**
 * プラグイン無効化時のクリーンアップ
 */
function gi_ai_concierge_cleanup() {
    // スケジュールされたイベントをクリア
    wp_clear_scheduled_hook('gi_daily_ai_maintenance');
    
    // キャッシュをクリア
    wp_cache_flush_group('gi_ai_concierge');
}
register_deactivation_hook(__FILE__, 'gi_ai_concierge_cleanup');

/**
 * ショートコード登録
 */
function gi_ai_concierge_shortcode($atts) {
    $atts = shortcode_atts([
        'height' => '600px',
        'theme' => 'default',
        'position' => 'bottom-right'
    ], $atts, 'gi_ai_concierge');
    
    ob_start();
    ?>
    <div class="gi-ai-concierge-widget" 
         data-height="<?php echo esc_attr($atts['height']); ?>"
         data-theme="<?php echo esc_attr($atts['theme']); ?>"
         data-position="<?php echo esc_attr($atts['position']); ?>">
        <div class="gi-concierge-trigger">
            <i class="fas fa-robot"></i>
            <span>AI相談</span>
        </div>
        <div class="gi-concierge-chat-container" style="display: none;">
            <div class="gi-chat-header">
                <h3>助成金AI相談</h3>
                <button class="gi-chat-close">&times;</button>
            </div>
            <div class="gi-chat-messages" id="gi-chat-messages"></div>
            <div class="gi-chat-input-container">
                <input type="text" id="gi-chat-input" placeholder="助成金について何でもお聞きください...">
                <button id="gi-chat-send">送信</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gi_ai_concierge', 'gi_ai_concierge_shortcode');

/**
 * REST API エンドポイント登録
 */
function gi_register_ai_concierge_rest_routes() {
    register_rest_route('gi/v1', '/ai-chat', [
        'methods' => 'POST',
        'callback' => function($request) {
            $concierge = GI_AI_Concierge::getInstance();
            return $concierge->handle_ai_chat();
        },
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('gi/v1', '/semantic-search', [
        'methods' => 'POST', 
        'callback' => function($request) {
            $concierge = GI_AI_Concierge::getInstance();
            return $concierge->handle_semantic_search();
        },
        'permission_callback' => '__return_true'
    ]);
}
add_action('rest_api_init', 'gi_register_ai_concierge_rest_routes');

/**
 * テーマサポート通知
 */
function gi_ai_concierge_theme_support_notice() {
    if (is_admin() && current_user_can('manage_options')) {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>AI Concierge:</strong> システムが正常に初期化されました。';
        echo '<a href="' . admin_url('admin.php?page=gi-ai-concierge') . '">設定ページ</a>からAPIキーを設定してください。';
        echo '</p></div>';
    }
}
add_action('admin_notices', 'gi_ai_concierge_theme_support_notice');

// デバッグ情報出力（開発時のみ）
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('AI Concierge Functions loaded successfully - ' . date('Y-m-d H:i:s'));
}