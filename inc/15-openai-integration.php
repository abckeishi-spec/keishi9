<?php
/**
 * OpenAI Integration - 完全実装版
 * 
 * OpenAI APIとの統合を完全実装し、ストリーミング、
 * エンベディング、高度な対話管理を提供
 *
 * @package Grant_Insight_Perfect
 * @version 3.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * OpenAI API統合クラス
 */
class GI_OpenAI_Integration {
    
    private static $instance = null;
    private $api_key = '';
    private $model = 'gpt-4';
    private $embedding_model = 'text-embedding-3-small';
    private $max_retries = 3;
    private $timeout = 30;
    
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
        $this->load_api_key();
    }
    
    /**
     * APIキー読み込み
     */
    private function load_api_key() {
        $settings = get_option('gi_ai_concierge_settings', []);
        $this->api_key = $settings['openai_api_key'] ?? '';
        $this->model = $settings['model'] ?? 'gpt-4';
        
        if (empty($this->api_key) && defined('OPENAI_API_KEY')) {
            $this->api_key = OPENAI_API_KEY;
        }
    }
    
    /**
     * チャット完了リクエスト（ストリーミング対応）
     */
    public function chat_completion($messages, $options = [], $stream_callback = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'OpenAI APIキーが設定されていません。');
        }
        
        $defaults = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1500,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'stream' => !is_null($stream_callback)
        ];
        
        $params = wp_parse_args($options, $defaults);
        
        if ($params['stream']) {
            return $this->stream_chat_completion($params, $stream_callback);
        } else {
            return $this->standard_chat_completion($params);
        }
    }
    
    /**
     * 標準チャット完了リクエスト
     */
    private function standard_chat_completion($params) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($params),
            'timeout' => $this->timeout,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('openai_error', $data['error']['message']);
        }
        
        return $data['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * ストリーミングチャット完了リクエスト
     */
    private function stream_chat_completion($params, $callback) {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($callback) {
            // SSEデータの解析
            if (strpos($data, 'data: ') === 0) {
                $json_str = substr($data, 6);
                if (trim($json_str) === '[DONE]') {
                    return strlen($data);
                }
                
                $json = json_decode(trim($json_str), true);
                if (isset($json['choices'][0]['delta']['content'])) {
                    call_user_func($callback, $json['choices'][0]['delta']['content']);
                }
            }
            return strlen($data);
        });
        
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return new WP_Error('curl_error', $error);
        }
        
        return true;
    }
    
    /**
     * エンベディング生成
     */
    public function generate_embedding($text) {
        if (empty($this->api_key)) {
            // APIキーがない場合は簡易ベクトルを生成
            return $this->generate_fallback_embedding($text);
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $this->embedding_model,
                'input' => $text,
            ]),
            'timeout' => $this->timeout,
        ]);
        
        if (is_wp_error($response)) {
            return $this->generate_fallback_embedding($text);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['data'][0]['embedding'])) {
            return $data['data'][0]['embedding'];
        }
        
        return $this->generate_fallback_embedding($text);
    }
    
    /**
     * フォールバックエンベディング生成（TF-IDF）
     */
    private function generate_fallback_embedding($text) {
        // 日本語トークン化
        $tokens = $this->tokenize_japanese($text);
        
        // TF-IDFベクトル生成
        $vector = [];
        $total_tokens = count($tokens);
        
        if ($total_tokens === 0) {
            return array_fill(0, 1536, 0); // OpenAIと同じ次元数
        }
        
        $token_counts = array_count_values($tokens);
        
        foreach ($token_counts as $token => $count) {
            $tf = $count / $total_tokens;
            $idf = $this->calculate_idf($token);
            $vector[] = $tf * $idf;
        }
        
        // パディングまたはトランケート
        $target_dim = 1536;
        if (count($vector) < $target_dim) {
            $vector = array_pad($vector, $target_dim, 0);
        } else {
            $vector = array_slice($vector, 0, $target_dim);
        }
        
        // 正規化
        $norm = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector)));
        if ($norm > 0) {
            $vector = array_map(function($x) use ($norm) { return $x / $norm; }, $vector);
        }
        
        return $vector;
    }
    
    /**
     * 日本語トークン化
     */
    private function tokenize_japanese($text) {
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
        
        return $tokens;
    }
    
    /**
     * IDF計算
     */
    private function calculate_idf($token) {
        global $wpdb;
        
        // 全文書数を取得
        $total_docs = $wpdb->get_var("SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts} WHERE post_type = 'grant' AND post_status = 'publish'");
        
        if ($total_docs == 0) {
            return 1;
        }
        
        // トークンを含む文書数を取得
        $docs_with_token = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts} 
            WHERE post_type = 'grant' 
            AND post_status = 'publish' 
            AND (post_title LIKE %s OR post_content LIKE %s)",
            '%' . $wpdb->esc_like($token) . '%',
            '%' . $wpdb->esc_like($token) . '%'
        ));
        
        if ($docs_with_token == 0) {
            return log($total_docs);
        }
        
        return log($total_docs / $docs_with_token);
    }
    
    /**
     * 関数呼び出し（Function Calling）
     */
    public function function_calling($messages, $functions, $function_call = 'auto') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'OpenAI APIキーが設定されていません。');
        }
        
        $params = [
            'model' => $this->model,
            'messages' => $messages,
            'functions' => $functions,
            'function_call' => $function_call,
            'temperature' => 0.7,
            'max_tokens' => 1500
        ];
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($params),
            'timeout' => $this->timeout,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('openai_error', $data['error']['message']);
        }
        
        return $data['choices'][0] ?? null;
    }
    
    /**
     * モデレーション（コンテンツの安全性チェック）
     */
    public function moderate_content($text) {
        if (empty($this->api_key)) {
            return ['safe' => true]; // APIキーがない場合は安全と判定
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/moderations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'input' => $text,
            ]),
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            return ['safe' => true, 'error' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['results'][0])) {
            $result = $data['results'][0];
            return [
                'safe' => !$result['flagged'],
                'categories' => $result['categories'] ?? [],
                'scores' => $result['category_scores'] ?? []
            ];
        }
        
        return ['safe' => true];
    }
    
    /**
     * プロンプトエンジニアリング
     */
    public function build_system_prompt($context = []) {
        $base_prompt = "あなたは日本の補助金・助成金に特化したAIアシスタントです。\n";
        $base_prompt .= "以下の役割を持っています：\n";
        $base_prompt .= "1. ユーザーの事業内容や状況を理解し、最適な補助金・助成金を提案\n";
        $base_prompt .= "2. 申請方法や必要書類について具体的なアドバイスを提供\n";
        $base_prompt .= "3. 締切日や申請条件などの重要な情報を正確に伝える\n";
        $base_prompt .= "4. ユーザーの質問に対して親切で分かりやすい回答を心がける\n\n";
        
        if (!empty($context['user_profile'])) {
            $base_prompt .= "ユーザー情報：\n";
            foreach ($context['user_profile'] as $key => $value) {
                $base_prompt .= "- {$key}: {$value}\n";
            }
            $base_prompt .= "\n";
        }
        
        if (!empty($context['conversation_summary'])) {
            $base_prompt .= "これまでの会話の要約：\n";
            $base_prompt .= $context['conversation_summary'] . "\n\n";
        }
        
        if (!empty($context['available_grants'])) {
            $base_prompt .= "現在利用可能な主な助成金：\n";
            foreach ($context['available_grants'] as $grant) {
                $base_prompt .= "- {$grant['title']} (締切: {$grant['deadline']})\n";
            }
            $base_prompt .= "\n";
        }
        
        $base_prompt .= "常に正確で最新の情報を提供し、ユーザーの成功を支援してください。";
        
        return $base_prompt;
    }
    
    /**
     * トークン数カウント（概算）
     */
    public function count_tokens($text) {
        // 日本語は1文字≒2トークンの概算
        $japanese_chars = preg_match_all('/[\x{4E00}-\x{9FAF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $text);
        $ascii_chars = strlen(preg_replace('/[\x{4E00}-\x{9FAF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', '', $text));
        
        return ($japanese_chars * 2) + ($ascii_chars / 4);
    }
}

/**
 * ストリーミングレスポンスハンドラー（強化版）
 */
class GI_Enhanced_Streaming_Handler {
    
    /**
     * SSEレスポンス送信
     */
    public static function send_sse_response($data) {
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Nginx対応
        }
        
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * ストリーミング開始
     */
    public static function start_streaming() {
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        
        while (@ob_end_flush());
        
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
        }
        
        // 接続確認
        echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
        flush();
    }
    
    /**
     * ストリーミング終了
     */
    public static function end_streaming() {
        echo "data: " . json_encode(['type' => 'done']) . "\n\n";
        flush();
    }
}

// AJAXハンドラー登録
add_action('wp_ajax_gi_ai_chat_stream', 'gi_handle_streaming_chat');
add_action('wp_ajax_nopriv_gi_ai_chat_stream', 'gi_handle_streaming_chat');

/**
 * ストリーミングチャットハンドラー
 */
function gi_handle_streaming_chat() {
    // NonCEチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ai_nonce')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    $message = sanitize_text_field($_POST['message'] ?? '');
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
    
    if (empty($message)) {
        wp_send_json_error('メッセージが入力されていません。');
    }
    
    // OpenAI統合インスタンス取得
    $openai = GI_OpenAI_Integration::getInstance();
    
    // ストリーミング開始
    GI_Enhanced_Streaming_Handler::start_streaming();
    
    // システムプロンプト構築
    $system_prompt = $openai->build_system_prompt($context);
    
    // メッセージ配列構築
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $message]
    ];
    
    // 会話履歴を追加
    if (!empty($context['history'])) {
        $history_messages = array_slice($context['history'], -10); // 最新10件
        array_splice($messages, 1, 0, $history_messages);
    }
    
    // ストリーミングコールバック
    $buffer = '';
    $callback = function($chunk) use (&$buffer) {
        $buffer .= $chunk;
        GI_Enhanced_Streaming_Handler::send_sse_response([
            'type' => 'chunk',
            'content' => $chunk
        ]);
    };
    
    // OpenAI APIコール
    $result = $openai->chat_completion($messages, [
        'temperature' => 0.7,
        'max_tokens' => 1500,
        'stream' => true
    ], $callback);
    
    if (is_wp_error($result)) {
        GI_Enhanced_Streaming_Handler::send_sse_response([
            'type' => 'error',
            'message' => $result->get_error_message()
        ]);
    }
    
    // 完了通知
    GI_Enhanced_Streaming_Handler::end_streaming();
    
    // 会話履歴を保存
    gi_save_conversation($session_id, $message, $buffer);
    
    exit;
}

/**
 * 会話履歴保存
 */
function gi_save_conversation($session_id, $user_message, $ai_response) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'gi_ai_conversations';
    
    // ユーザーメッセージ保存
    $wpdb->insert($table, [
        'session_id' => $session_id,
        'user_id' => get_current_user_id(),
        'message_type' => 'user',
        'message' => $user_message,
        'created_at' => current_time('mysql')
    ]);
    
    // AIレスポンス保存
    $wpdb->insert($table, [
        'session_id' => $session_id,
        'user_id' => get_current_user_id(),
        'message_type' => 'assistant',
        'message' => $ai_response,
        'created_at' => current_time('mysql')
    ]);
}