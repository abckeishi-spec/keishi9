/**
 * AI Assistant Enhanced - 完全強化版
 * 
 * リアルタイムストリーミング、感情分析、
 * 動的UI、音声認識を統合した高度なAIアシスタント
 *
 * @version 3.0.0
 */

class AIAssistantEnhanced {
    constructor() {
        this.config = {
            apiEndpoint: gi_ajax.ajax_url,
            nonce: gi_ajax.nonce,
            streamingEnabled: true,
            voiceEnabled: true,
            emotionAnalysis: true,
            autoSuggest: true,
            maxHistoryLength: 50,
            typingSpeed: 30, // ms per character
            enableEffects: true
        };
        
        this.state = {
            isListening: false,
            isStreaming: false,
            isThinking: false,
            currentEmotion: 'neutral',
            conversationHistory: [],
            sessionId: this.generateSessionId(),
            context: {},
            currentGrantResults: [],
            userPreferences: this.loadUserPreferences()
        };
        
        this.elements = {};
        this.recognition = null;
        this.eventSource = null;
        this.messageQueue = [];
        this.currentTypingAnimation = null;
        
        this.init();
    }
    
    /**
     * 初期化
     */
    init() {
        // フローティングボタンを作成しない（ユーザーリクエストにより削除）
        // this.createChatInterface();
        // this.bindEvents();
        // this.initVoiceRecognition();
        // this.loadConversationHistory();
        // this.startHeartbeat();
        // this.initKeyboardShortcuts();
        // this.setupAutoSuggestions();
        
        // AIアシスタント機能は検索セクションから直接呼び出される
        console.log('AI Assistant Enhanced - フローティングボタン無効化');
    }
    
    /**
     * チャットインターフェース作成（強化版）
     */
    createChatInterface() {
        const chatHTML = `
            <div id="ai-assistant-container" class="ai-assistant-container enhanced">
                <!-- フローティングボタン -->
                <button id="ai-chat-toggle" class="ai-chat-toggle">
                    <span class="chat-icon">💬</span>
                    <span class="notification-badge" style="display:none;">0</span>
                </button>
                
                <!-- メインチャットウィンドウ -->
                <div id="ai-chat-window" class="ai-chat-window" style="display:none;">
                    <!-- ヘッダー -->
                    <div class="ai-header">
                        <div class="ai-status">
                            <span class="status-indicator pulse"></span>
                            <span class="status-text">AIアシスタント</span>
                            <span class="emotion-indicator" data-emotion="neutral">😊</span>
                        </div>
                        <div class="ai-controls">
                            <button class="btn-settings" title="設定">⚙️</button>
                            <button class="btn-voice" title="音声入力">🎤</button>
                            <button class="btn-minimize" title="最小化">−</button>
                            <button class="btn-close" title="閉じる">×</button>
                        </div>
                    </div>
                    
                    <!-- 感情バー -->
                    <div class="emotion-bar">
                        <div class="emotion-level" data-emotion="neutral">
                            <span class="emotion-label">分析中...</span>
                            <div class="emotion-progress"></div>
                        </div>
                    </div>
                    
                    <!-- チャット履歴 -->
                    <div class="ai-chat-history" id="chat-history">
                        <div class="welcome-message animated fadeIn">
                            <div class="ai-avatar animated bounce">
                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiM0Q0FGNTAL8+CjxwYXRoIGQ9Ik0xNSAxNUgxNVYyNUgxNVYxNVoiIHN0cm9rZT0id2hpdGUiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+CjxwYXRoIGQ9Ik0yNSAxNUgyNVYyNUgyNVYxNVoiIHN0cm9rZT0id2hpdGUiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+Cjwvc3ZnPg==" alt="AI">
                            </div>
                            <div class="message-content">
                                <h3>こんにちは！AI補助金アシスタントです 👋</h3>
                                <p>補助金・助成金に関するご質問をお気軽にどうぞ。</p>
                                <div class="quick-actions">
                                    <button class="quick-btn animated fadeInUp" data-query="おすすめの助成金を教えて" style="animation-delay: 0.1s">
                                        <span class="icon">💡</span>
                                        <span class="text">おすすめ助成金</span>
                                    </button>
                                    <button class="quick-btn animated fadeInUp" data-query="申請方法を教えて" style="animation-delay: 0.2s">
                                        <span class="icon">📝</span>
                                        <span class="text">申請方法</span>
                                    </button>
                                    <button class="quick-btn animated fadeInUp" data-query="締切が近い助成金" style="animation-delay: 0.3s">
                                        <span class="icon">⏰</span>
                                        <span class="text">締切確認</span>
                                    </button>
                                    <button class="quick-btn animated fadeInUp" data-query="IT関連の補助金" style="animation-delay: 0.4s">
                                        <span class="icon">💻</span>
                                        <span class="text">IT補助金</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- タイピングインジケーター -->
                    <div class="typing-indicator" style="display:none;">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="typing-text">AIが考えています...</span>
                    </div>
                    
                    <!-- 助成金結果表示エリア -->
                    <div class="grant-results-area" id="grant-results" style="display:none;">
                        <div class="results-header">
                            <h4>🎯 マッチした助成金</h4>
                            <button class="btn-close-results">×</button>
                        </div>
                        <div class="results-container"></div>
                    </div>
                    
                    <!-- 入力エリア -->
                    <div class="ai-input-area">
                        <div class="suggestions-bar" id="suggestions-bar" style="display:none;">
                            <span class="suggestion-label">候補:</span>
                            <div class="suggestions-list"></div>
                        </div>
                        <div class="input-wrapper">
                            <textarea 
                                id="ai-message-input" 
                                class="message-input" 
                                placeholder="メッセージを入力... (Shift+Enterで改行)"
                                rows="1"
                            ></textarea>
                            <div class="input-actions">
                                <button class="btn-attachment" title="ファイル添付">📎</button>
                                <button class="btn-emoji" title="絵文字">😊</button>
                                <button class="btn-send" id="send-message" disabled>
                                    <span class="send-icon">📤</span>
                                </button>
                            </div>
                        </div>
                        <div class="input-info">
                            <span class="char-count">0 / 1000</span>
                            <span class="status-info">準備完了</span>
                        </div>
                    </div>
                    
                    <!-- フィードバックエリア -->
                    <div class="feedback-area" id="feedback-area" style="display:none;">
                        <p>この回答は役に立ちましたか？</p>
                        <div class="feedback-buttons">
                            <button class="feedback-btn positive" data-value="5">👍 役立った</button>
                            <button class="feedback-btn neutral" data-value="3">😐 普通</button>
                            <button class="feedback-btn negative" data-value="1">👎 改善が必要</button>
                        </div>
                    </div>
                </div>
                
                <!-- 設定パネル -->
                <div id="settings-panel" class="settings-panel" style="display:none;">
                    <h3>設定</h3>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="enable-streaming" checked>
                            リアルタイムストリーミング
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="enable-voice" checked>
                            音声入力
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="enable-effects" checked>
                            アニメーション効果
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            応答速度:
                            <select id="response-speed">
                                <option value="fast">高速</option>
                                <option value="normal" selected>標準</option>
                                <option value="slow">ゆっくり</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn-save-settings">保存</button>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', chatHTML);
        this.cacheElements();
        this.applyUserPreferences();
    }
    
    /**
     * DOM要素のキャッシュ
     */
    cacheElements() {
        this.elements = {
            container: document.getElementById('ai-assistant-container'),
            toggle: document.getElementById('ai-chat-toggle'),
            window: document.getElementById('ai-chat-window'),
            history: document.getElementById('chat-history'),
            input: document.getElementById('ai-message-input'),
            sendBtn: document.getElementById('send-message'),
            voiceBtn: document.querySelector('.btn-voice'),
            typingIndicator: document.querySelector('.typing-indicator'),
            emotionBar: document.querySelector('.emotion-bar'),
            feedbackArea: document.getElementById('feedback-area'),
            grantResults: document.getElementById('grant-results'),
            suggestionsBar: document.getElementById('suggestions-bar'),
            settingsPanel: document.getElementById('settings-panel'),
            notificationBadge: document.querySelector('.notification-badge')
        };
    }
    
    /**
     * イベントバインディング
     */
    bindEvents() {
        // チャットトグル
        this.elements.toggle.addEventListener('click', () => this.toggleChat());
        
        // ウィンドウコントロール
        document.querySelector('.btn-minimize').addEventListener('click', () => this.minimizeChat());
        document.querySelector('.btn-close').addEventListener('click', () => this.closeChat());
        document.querySelector('.btn-settings').addEventListener('click', () => this.toggleSettings());
        
        // メッセージ送信
        this.elements.sendBtn.addEventListener('click', () => this.sendMessage());
        this.elements.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // 入力監視
        this.elements.input.addEventListener('input', () => this.handleInputChange());
        
        // 音声入力
        this.elements.voiceBtn.addEventListener('click', () => this.toggleVoiceRecognition());
        
        // クイックアクション
        document.querySelectorAll('.quick-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const query = e.currentTarget.dataset.query;
                this.elements.input.value = query;
                this.sendMessage();
            });
        });
        
        // フィードバック
        document.querySelectorAll('.feedback-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const value = e.currentTarget.dataset.value;
                this.submitFeedback(value);
            });
        });
        
        // 設定保存
        document.querySelector('.btn-save-settings')?.addEventListener('click', () => this.saveSettings());
    }
    
    /**
     * メッセージ送信（強化版）
     */
    async sendMessage() {
        const message = this.elements.input.value.trim();
        if (!message || this.state.isStreaming) return;
        
        // UIリセット
        this.elements.input.value = '';
        this.elements.sendBtn.disabled = true;
        this.updateCharCount();
        
        // ユーザーメッセージ表示
        this.addMessage(message, 'user');
        
        // 感情分析
        this.analyzeEmotion(message);
        
        // タイピングインジケーター表示
        this.showTypingIndicator();
        
        // セマンティック検索実行
        const searchResults = await this.performSemanticSearch(message);
        
        // ストリーミングレスポンス取得
        this.startStreaming(message, searchResults);
    }
    
    /**
     * セマンティック検索実行
     */
    async performSemanticSearch(query) {
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_semantic_search',
                    nonce: this.config.nonce,
                    query: query,
                    filters: JSON.stringify(this.state.context.filters || {})
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.data.results) {
                this.state.currentGrantResults = data.data.results;
                return data.data.results;
            }
        } catch (error) {
            console.error('Semantic search error:', error);
        }
        
        return [];
    }
    
    /**
     * ストリーミング開始（実装版）
     */
    startStreaming(message, searchResults) {
        this.state.isStreaming = true;
        
        // EventSourceを使用してSSE接続
        const params = new URLSearchParams({
            action: 'gi_ai_chat_stream',
            nonce: this.config.nonce,
            message: message,
            session_id: this.state.sessionId,
            context: JSON.stringify({
                history: this.state.conversationHistory.slice(-10),
                search_results: searchResults.slice(0, 5),
                user_preferences: this.state.userPreferences,
                current_emotion: this.state.currentEmotion
            })
        });
        
        this.eventSource = new EventSource(`${this.config.apiEndpoint}?${params}`);
        
        let messageContainer = null;
        let accumulatedText = '';
        
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            switch(data.type) {
                case 'connected':
                    this.hideTypingIndicator();
                    messageContainer = this.addMessage('', 'assistant', true);
                    break;
                    
                case 'chunk':
                    accumulatedText += data.content;
                    this.updateStreamingMessage(messageContainer, accumulatedText);
                    break;
                    
                case 'done':
                    this.finalizeStreamingMessage(messageContainer, accumulatedText);
                    this.eventSource.close();
                    this.state.isStreaming = false;
                    this.elements.sendBtn.disabled = false;
                    
                    // 助成金結果表示
                    if (searchResults.length > 0) {
                        this.displayGrantResults(searchResults);
                    }
                    
                    // フィードバック要求
                    this.showFeedbackArea();
                    
                    // 会話履歴更新
                    this.updateConversationHistory(message, accumulatedText);
                    break;
                    
                case 'error':
                    this.handleStreamError(data.message);
                    break;
            }
        };
        
        this.eventSource.onerror = (error) => {
            console.error('Streaming error:', error);
            this.handleStreamError('接続エラーが発生しました。');
            this.eventSource.close();
            this.state.isStreaming = false;
        };
    }
    
    /**
     * メッセージ追加（アニメーション付き）
     */
    addMessage(content, type = 'user', isStreaming = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message animated fadeInUp`;
        
        const avatar = type === 'user' ? '👤' : '🤖';
        const timestamp = new Date().toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-bubble">
                <div class="message-content">${this.formatMessage(content)}</div>
                <div class="message-meta">
                    <span class="timestamp">${timestamp}</span>
                    ${type === 'assistant' ? '<span class="confidence">信頼度: 高</span>' : ''}
                </div>
            </div>
        `;
        
        this.elements.history.appendChild(messageDiv);
        this.scrollToBottom();
        
        return messageDiv.querySelector('.message-content');
    }
    
    /**
     * メッセージフォーマット（リッチテキスト対応）
     */
    formatMessage(content) {
        if (!content) return '';
        
        // Markdown風の簡易フォーマット
        let formatted = content
            // 見出し
            .replace(/^### (.+)$/gm, '<h4>$1</h4>')
            .replace(/^## (.+)$/gm, '<h3>$1</h3>')
            .replace(/^# (.+)$/gm, '<h2>$1</h2>')
            // 太字
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            // リスト
            .replace(/^- (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
            // リンク
            .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank">$1</a>')
            // コードブロック
            .replace(/```(.+?)```/gs, '<pre><code>$1</code></pre>')
            // インラインコード
            .replace(/`(.+?)`/g, '<code>$1</code>')
            // 改行
            .replace(/\n/g, '<br>');
        
        // 助成金名のハイライト
        formatted = this.highlightGrantNames(formatted);
        
        return formatted;
    }
    
    /**
     * 助成金名のハイライト
     */
    highlightGrantNames(text) {
        const grantKeywords = ['補助金', '助成金', '支援金', '給付金', '融資'];
        
        grantKeywords.forEach(keyword => {
            const regex = new RegExp(`(${keyword})`, 'g');
            text = text.replace(regex, '<span class="highlight-grant">$1</span>');
        });
        
        return text;
    }
    
    /**
     * ストリーミングメッセージ更新
     */
    updateStreamingMessage(container, text) {
        if (!container) return;
        
        // タイピングエフェクト
        if (this.config.enableEffects) {
            container.innerHTML = this.formatMessage(text) + '<span class="cursor">|</span>';
        } else {
            container.innerHTML = this.formatMessage(text);
        }
        
        this.scrollToBottom();
    }
    
    /**
     * ストリーミングメッセージ完了
     */
    finalizeStreamingMessage(container, text) {
        if (!container) return;
        
        // カーソル削除
        container.innerHTML = this.formatMessage(text);
        
        // 完了アニメーション
        container.parentElement.parentElement.classList.add('message-complete');
    }
    
    /**
     * 助成金結果表示（カード形式）
     */
    displayGrantResults(results) {
        const resultsContainer = this.elements.grantResults.querySelector('.results-container');
        resultsContainer.innerHTML = '';
        
        results.slice(0, 5).forEach((grant, index) => {
            const card = document.createElement('div');
            card.className = 'grant-card animated fadeIn';
            card.style.animationDelay = `${index * 0.1}s`;
            
            const deadline = grant.deadline ? new Date(grant.deadline) : null;
            const daysLeft = deadline ? Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24)) : null;
            const urgencyClass = daysLeft && daysLeft < 30 ? 'urgent' : '';
            
            card.innerHTML = `
                <div class="grant-header">
                    <h5>${grant.title}</h5>
                    <span class="match-score">${Math.round(grant.score * 100)}%</span>
                </div>
                <div class="grant-body">
                    <p class="grant-excerpt">${grant.excerpt}</p>
                    <div class="grant-meta">
                        ${grant.amount ? `<span class="meta-item">💰 最大${this.formatAmount(grant.amount)}円</span>` : ''}
                        ${grant.subsidy_rate ? `<span class="meta-item">📊 補助率${grant.subsidy_rate}%</span>` : ''}
                        ${deadline ? `<span class="meta-item ${urgencyClass}">📅 締切まで${daysLeft}日</span>` : ''}
                    </div>
                    <div class="grant-tags">
                        ${grant.categories ? grant.categories.map(cat => `<span class="tag">${cat}</span>`).join('') : ''}
                    </div>
                </div>
                <div class="grant-actions">
                    <a href="${grant.url}" target="_blank" class="btn-detail">詳細を見る</a>
                    <button class="btn-bookmark" data-id="${grant.id}">
                        <span class="bookmark-icon">🔖</span>
                        保存
                    </button>
                </div>
            `;
            
            resultsContainer.appendChild(card);
        });
        
        this.elements.grantResults.style.display = 'block';
        this.bindGrantCardEvents();
    }
    
    /**
     * 金額フォーマット
     */
    formatAmount(amount) {
        if (amount >= 10000) {
            return (amount / 10000).toFixed(0) + '万';
        }
        return amount.toLocaleString();
    }
    
    /**
     * 感情分析実行
     */
    async analyzeEmotion(text) {
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_analyze_emotion',
                    nonce: this.config.nonce,
                    text: text,
                    context: JSON.stringify(this.state.context)
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.data) {
                this.updateEmotionDisplay(data.data);
                this.state.currentEmotion = data.data.dominant;
            }
        } catch (error) {
            console.error('Emotion analysis error:', error);
        }
    }
    
    /**
     * 感情表示更新
     */
    updateEmotionDisplay(emotionData) {
        const emotionBar = this.elements.emotionBar;
        const emotionIndicator = document.querySelector('.emotion-indicator');
        
        // 感情アイコン更新
        const emotionIcons = {
            'positive': '😊',
            'negative': '😟',
            'neutral': '😐'
        };
        
        emotionIndicator.textContent = emotionIcons[emotionData.dominant];
        emotionIndicator.dataset.emotion = emotionData.dominant;
        
        // 感情バー更新
        const emotionLevel = emotionBar.querySelector('.emotion-level');
        const emotionProgress = emotionBar.querySelector('.emotion-progress');
        const emotionLabel = emotionBar.querySelector('.emotion-label');
        
        emotionLevel.dataset.emotion = emotionData.dominant;
        emotionProgress.style.width = `${emotionData.confidence * 100}%`;
        emotionLabel.textContent = `${emotionData.details.label} (${Math.round(emotionData.confidence * 100)}%)`;
        
        // 色の更新
        emotionProgress.style.backgroundColor = emotionData.details.color;
    }
    
    /**
     * 音声認識初期化
     */
    initVoiceRecognition() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            console.warn('音声認識はサポートされていません');
            this.elements.voiceBtn.style.display = 'none';
            return;
        }
        
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new SpeechRecognition();
        this.recognition.lang = 'ja-JP';
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        
        this.recognition.onresult = (event) => {
            let finalTranscript = '';
            let interimTranscript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }
            
            if (finalTranscript) {
                this.elements.input.value += finalTranscript;
                this.handleInputChange();
            } else if (interimTranscript) {
                // リアルタイム表示
                this.showInterimTranscript(interimTranscript);
            }
        };
        
        this.recognition.onerror = (event) => {
            console.error('音声認識エラー:', event.error);
            this.stopVoiceRecognition();
        };
    }
    
    /**
     * 音声認識トグル
     */
    toggleVoiceRecognition() {
        if (this.state.isListening) {
            this.stopVoiceRecognition();
        } else {
            this.startVoiceRecognition();
        }
    }
    
    /**
     * 音声認識開始
     */
    startVoiceRecognition() {
        if (!this.recognition) return;
        
        this.recognition.start();
        this.state.isListening = true;
        this.elements.voiceBtn.classList.add('listening');
        this.showNotification('音声入力を開始しました 🎤');
    }
    
    /**
     * 音声認識停止
     */
    stopVoiceRecognition() {
        if (!this.recognition) return;
        
        this.recognition.stop();
        this.state.isListening = false;
        this.elements.voiceBtn.classList.remove('listening');
    }
    
    /**
     * 自動提案セットアップ
     */
    setupAutoSuggestions() {
        let suggestionTimeout;
        
        this.elements.input.addEventListener('input', () => {
            clearTimeout(suggestionTimeout);
            suggestionTimeout = setTimeout(() => {
                this.showSuggestions();
            }, 300);
        });
    }
    
    /**
     * 提案表示
     */
    async showSuggestions() {
        const input = this.elements.input.value.trim();
        if (input.length < 2) {
            this.elements.suggestionsBar.style.display = 'none';
            return;
        }
        
        // 提案候補生成
        const suggestions = this.generateSuggestions(input);
        
        if (suggestions.length > 0) {
            const suggestionsList = this.elements.suggestionsBar.querySelector('.suggestions-list');
            suggestionsList.innerHTML = suggestions.map(s => 
                `<button class="suggestion-item" data-text="${s}">${s}</button>`
            ).join('');
            
            this.elements.suggestionsBar.style.display = 'flex';
            
            // イベントバインド
            suggestionsList.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    this.elements.input.value = e.target.dataset.text;
                    this.elements.suggestionsBar.style.display = 'none';
                    this.elements.input.focus();
                });
            });
        }
    }
    
    /**
     * 提案生成
     */
    generateSuggestions(input) {
        const suggestions = [
            'IT導入補助金について教えて',
            'ものづくり補助金の申請方法',
            '事業再構築補助金の要件',
            '小規模事業者持続化補助金',
            '雇用調整助成金の申請書類'
        ];
        
        return suggestions.filter(s => s.includes(input)).slice(0, 3);
    }
    
    /**
     * キーボードショートカット
     */
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + A でチャット開く
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'A') {
                e.preventDefault();
                this.toggleChat();
            }
            
            // ESCで閉じる
            if (e.key === 'Escape' && this.elements.window.style.display !== 'none') {
                this.closeChat();
            }
        });
    }
    
    /**
     * ユーザー設定の読み込み
     */
    loadUserPreferences() {
        const saved = localStorage.getItem('ai_assistant_preferences');
        return saved ? JSON.parse(saved) : {
            theme: 'light',
            fontSize: 'normal',
            soundEnabled: true,
            notificationsEnabled: true
        };
    }
    
    /**
     * ユーザー設定の適用
     */
    applyUserPreferences() {
        const prefs = this.state.userPreferences;
        
        if (prefs.theme === 'dark') {
            this.elements.container.classList.add('dark-theme');
        }
        
        if (prefs.fontSize) {
            this.elements.container.classList.add(`font-${prefs.fontSize}`);
        }
    }
    
    /**
     * 設定保存
     */
    saveSettings() {
        const settings = {
            streamingEnabled: document.getElementById('enable-streaming').checked,
            voiceEnabled: document.getElementById('enable-voice').checked,
            enableEffects: document.getElementById('enable-effects').checked,
            responseSpeed: document.getElementById('response-speed').value
        };
        
        Object.assign(this.config, settings);
        localStorage.setItem('ai_assistant_settings', JSON.stringify(settings));
        
        this.showNotification('設定を保存しました ✅');
        this.elements.settingsPanel.style.display = 'none';
    }
    
    /**
     * 通知表示
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} animated fadeInDown`;
        notification.textContent = message;
        
        this.elements.container.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('fadeOutUp');
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }
    
    /**
     * その他のユーティリティメソッド
     */
    
    toggleChat() {
        const isVisible = this.elements.window.style.display !== 'none';
        this.elements.window.style.display = isVisible ? 'none' : 'flex';
        
        if (!isVisible) {
            this.elements.input.focus();
            this.elements.notificationBadge.style.display = 'none';
            this.elements.notificationBadge.textContent = '0';
        }
    }
    
    minimizeChat() {
        this.elements.window.style.display = 'none';
    }
    
    closeChat() {
        this.elements.window.style.display = 'none';
        this.stopVoiceRecognition();
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
    
    toggleSettings() {
        const isVisible = this.elements.settingsPanel.style.display !== 'none';
        this.elements.settingsPanel.style.display = isVisible ? 'none' : 'block';
    }
    
    showTypingIndicator() {
        this.elements.typingIndicator.style.display = 'flex';
        this.scrollToBottom();
    }
    
    hideTypingIndicator() {
        this.elements.typingIndicator.style.display = 'none';
    }
    
    showFeedbackArea() {
        this.elements.feedbackArea.style.display = 'block';
        setTimeout(() => {
            this.elements.feedbackArea.style.display = 'none';
        }, 30000); // 30秒後に自動非表示
    }
    
    async submitFeedback(value) {
        const lastMessage = this.state.conversationHistory[this.state.conversationHistory.length - 1];
        
        try {
            await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_ai_feedback',
                    nonce: this.config.nonce,
                    session_id: this.state.sessionId,
                    message_id: lastMessage?.id || '',
                    feedback_type: 'rating',
                    feedback_value: value
                })
            });
            
            this.showNotification('フィードバックありがとうございます！', 'success');
            this.elements.feedbackArea.style.display = 'none';
        } catch (error) {
            console.error('Feedback error:', error);
        }
    }
    
    handleInputChange() {
        const length = this.elements.input.value.length;
        document.querySelector('.char-count').textContent = `${length} / 1000`;
        
        this.elements.sendBtn.disabled = length === 0 || length > 1000;
        
        // 自動高さ調整
        this.elements.input.style.height = 'auto';
        this.elements.input.style.height = Math.min(this.elements.input.scrollHeight, 120) + 'px';
    }
    
    updateCharCount() {
        document.querySelector('.char-count').textContent = '0 / 1000';
    }
    
    scrollToBottom() {
        this.elements.history.scrollTop = this.elements.history.scrollHeight;
    }
    
    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    updateConversationHistory(userMessage, aiResponse) {
        this.state.conversationHistory.push(
            { role: 'user', content: userMessage, timestamp: Date.now() },
            { role: 'assistant', content: aiResponse, timestamp: Date.now() }
        );
        
        // 最大履歴数を超えたら古いものを削除
        if (this.state.conversationHistory.length > this.config.maxHistoryLength * 2) {
            this.state.conversationHistory = this.state.conversationHistory.slice(-this.config.maxHistoryLength * 2);
        }
        
        // ローカルストレージに保存
        this.saveConversationHistory();
    }
    
    saveConversationHistory() {
        localStorage.setItem('ai_conversation_' + this.state.sessionId, 
            JSON.stringify(this.state.conversationHistory));
    }
    
    loadConversationHistory() {
        const saved = localStorage.getItem('ai_conversation_' + this.state.sessionId);
        if (saved) {
            this.state.conversationHistory = JSON.parse(saved);
        }
    }
    
    bindGrantCardEvents() {
        document.querySelectorAll('.btn-bookmark').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const grantId = e.currentTarget.dataset.id;
                this.bookmarkGrant(grantId);
                e.currentTarget.classList.toggle('bookmarked');
            });
        });
        
        document.querySelector('.btn-close-results')?.addEventListener('click', () => {
            this.elements.grantResults.style.display = 'none';
        });
    }
    
    async bookmarkGrant(grantId) {
        try {
            await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_bookmark_grant',
                    nonce: this.config.nonce,
                    grant_id: grantId
                })
            });
            
            this.showNotification('助成金を保存しました 🔖', 'success');
        } catch (error) {
            console.error('Bookmark error:', error);
        }
    }
    
    showInterimTranscript(text) {
        // 音声認識の中間結果を表示
        const indicator = document.createElement('div');
        indicator.className = 'interim-transcript';
        indicator.textContent = text;
        this.elements.input.parentElement.appendChild(indicator);
        
        setTimeout(() => indicator.remove(), 1000);
    }
    
    handleStreamError(message) {
        this.hideTypingIndicator();
        this.addMessage(`エラー: ${message}`, 'assistant');
        this.state.isStreaming = false;
        this.elements.sendBtn.disabled = false;
    }
    
    startHeartbeat() {
        // 5分ごとにセッションを維持
        setInterval(() => {
            if (this.elements.window.style.display !== 'none') {
                fetch(this.config.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'gi_session_heartbeat',
                        nonce: this.config.nonce,
                        session_id: this.state.sessionId
                    })
                });
            }
        }, 300000);
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    window.aiAssistantEnhanced = new AIAssistantEnhanced();
});