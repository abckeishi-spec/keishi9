/**
 * AI Assistant - 完全インタラクティブシステム
 * 
 * セマンティック検索、リアルタイムストリーミング、
 * 音声認識、感情分析を統合したAIアシスタント
 *
 * @version 2.0.0
 */

class AIAssistant {
    constructor() {
        this.config = {
            apiEndpoint: gi_ajax.ajax_url,
            nonce: gi_ajax.nonce,
            streamingEnabled: true,
            voiceEnabled: true,
            emotionAnalysis: true,
            autoSuggest: true,
            maxHistoryLength: 50
        };
        
        this.state = {
            isListening: false,
            isStreaming: false,
            isThinking: false,
            currentEmotion: 'neutral',
            conversationHistory: [],
            sessionId: this.generateSessionId(),
            context: {}
        };
        
        this.elements = {};
        this.recognition = null;
        this.eventSource = null;
        
        this.init();
    }
    
    /**
     * 初期化
     */
    init() {
        this.createChatInterface();
        this.bindEvents();
        this.initVoiceRecognition();
        this.loadConversationHistory();
        this.startHeartbeat();
    }
    
    /**
     * チャットインターフェース作成
     */
    createChatInterface() {
        const chatHTML = `
            <div id="ai-assistant-container" class="ai-assistant-container">
                <!-- チャットヘッダー -->
                <div class="ai-header">
                    <div class="ai-status">
                        <span class="status-indicator"></span>
                        <span class="status-text">AIアシスタント</span>
                    </div>
                    <div class="ai-controls">
                        <button class="btn-voice" title="音声入力">
                            <svg class="mic-icon" viewBox="0 0 24 24">
                                <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3z"/>
                                <path d="M19 10v1a7 7 0 0 1-14 0v-1M12 18.75v3.5"/>
                            </svg>
                        </button>
                        <button class="btn-minimize" title="最小化">−</button>
                        <button class="btn-close" title="閉じる">×</button>
                    </div>
                </div>
                
                <!-- 感情インジケーター -->
                <div class="emotion-indicator">
                    <span class="emotion-icon">😊</span>
                    <span class="emotion-label">ポジティブ</span>
                    <div class="emotion-bar">
                        <div class="emotion-level"></div>
                    </div>
                </div>
                
                <!-- チャット履歴 -->
                <div class="ai-chat-history" id="chat-history">
                    <div class="welcome-message">
                        <div class="ai-avatar">🤖</div>
                        <div class="message-content">
                            <p>こんにちは！補助金・助成金のAIアシスタントです。</p>
                            <p>どのようなご相談でしょうか？</p>
                            <div class="quick-actions">
                                <button class="quick-btn" data-query="おすすめの助成金を教えて">
                                    💡 おすすめを見る
                                </button>
                                <button class="quick-btn" data-query="申請方法を教えて">
                                    📝 申請方法
                                </button>
                                <button class="quick-btn" data-query="締切が近い助成金">
                                    ⏰ 締切確認
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 入力エリア -->
                <div class="ai-input-area">
                    <!-- サジェスト -->
                    <div class="suggestions-container" id="suggestions"></div>
                    
                    <!-- タイピングインジケーター -->
                    <div class="typing-indicator" style="display:none;">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    
                    <!-- 入力フォーム -->
                    <form class="ai-input-form" id="ai-input-form">
                        <div class="input-wrapper">
                            <textarea 
                                class="ai-input" 
                                id="ai-input"
                                placeholder="メッセージを入力..."
                                rows="1"
                            ></textarea>
                            <button type="submit" class="btn-send" disabled>
                                <svg viewBox="0 0 24 24">
                                    <path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                    
                    <!-- 音声認識状態 -->
                    <div class="voice-recognition-status" style="display:none;">
                        <div class="voice-wave">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="voice-text">聞き取り中...</span>
                    </div>
                </div>
            </div>
            
            <!-- フローティングボタン -->
            <button id="ai-assistant-toggle" class="ai-assistant-toggle">
                <span class="toggle-icon">💬</span>
                <span class="toggle-badge" style="display:none;">1</span>
            </button>
        `;
        
        // DOMに追加
        document.body.insertAdjacentHTML('beforeend', chatHTML);
        
        // 要素参照保存
        this.elements = {
            container: document.getElementById('ai-assistant-container'),
            chatHistory: document.getElementById('chat-history'),
            input: document.getElementById('ai-input'),
            form: document.getElementById('ai-input-form'),
            suggestions: document.getElementById('suggestions'),
            toggle: document.getElementById('ai-assistant-toggle'),
            voiceBtn: document.querySelector('.btn-voice'),
            sendBtn: document.querySelector('.btn-send'),
            typingIndicator: document.querySelector('.typing-indicator'),
            voiceStatus: document.querySelector('.voice-recognition-status'),
            emotionIndicator: document.querySelector('.emotion-indicator'),
            statusIndicator: document.querySelector('.status-indicator'),
            statusText: document.querySelector('.status-text')
        };
    }
    
    /**
     * イベントバインディング
     */
    bindEvents() {
        // トグルボタン
        this.elements.toggle.addEventListener('click', () => this.toggleChat());
        
        // 閉じるボタン
        document.querySelector('.btn-close').addEventListener('click', () => this.closeChat());
        
        // 最小化ボタン
        document.querySelector('.btn-minimize').addEventListener('click', () => this.minimizeChat());
        
        // フォーム送信
        this.elements.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // 入力フィールド
        this.elements.input.addEventListener('input', () => this.handleInputChange());
        this.elements.input.addEventListener('keydown', (e) => this.handleKeyDown(e));
        
        // 音声ボタン
        this.elements.voiceBtn.addEventListener('click', () => this.toggleVoiceRecognition());
        
        // クイックアクション
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-btn')) {
                const query = e.target.dataset.query;
                this.elements.input.value = query;
                this.sendMessage();
            }
        });
        
        // グラントカードアクション
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('grant-card-action')) {
                const grantId = e.target.dataset.grantId;
                const action = e.target.dataset.action;
                this.handleGrantAction(grantId, action);
            }
        });
    }
    
    /**
     * 音声認識初期化
     */
    initVoiceRecognition() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            this.config.voiceEnabled = false;
            this.elements.voiceBtn.style.display = 'none';
            return;
        }
        
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new SpeechRecognition();
        
        this.recognition.lang = 'ja-JP';
        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.maxAlternatives = 3;
        
        this.recognition.onstart = () => {
            this.state.isListening = true;
            this.elements.voiceBtn.classList.add('listening');
            this.elements.voiceStatus.style.display = 'block';
        };
        
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
                this.elements.input.value = finalTranscript;
                this.handleInputChange();
            } else if (interimTranscript) {
                this.elements.input.value = interimTranscript;
            }
        };
        
        this.recognition.onend = () => {
            this.state.isListening = false;
            this.elements.voiceBtn.classList.remove('listening');
            this.elements.voiceStatus.style.display = 'none';
        };
        
        this.recognition.onerror = (event) => {
            console.error('音声認識エラー:', event.error);
            this.showNotification('音声認識エラーが発生しました', 'error');
            this.state.isListening = false;
            this.elements.voiceBtn.classList.remove('listening');
            this.elements.voiceStatus.style.display = 'none';
        };
    }
    
    /**
     * 音声認識トグル
     */
    toggleVoiceRecognition() {
        if (!this.recognition) return;
        
        if (this.state.isListening) {
            this.recognition.stop();
        } else {
            this.recognition.start();
        }
    }
    
    /**
     * メッセージ送信
     */
    async sendMessage() {
        const message = this.elements.input.value.trim();
        if (!message) return;
        
        // ユーザーメッセージ追加
        this.addMessage('user', message);
        
        // 入力クリア
        this.elements.input.value = '';
        this.handleInputChange();
        
        // 感情分析
        if (this.config.emotionAnalysis) {
            this.analyzeEmotion(message);
        }
        
        // AIレスポンス取得
        this.state.isThinking = true;
        this.showTypingIndicator();
        
        try {
            if (this.config.streamingEnabled) {
                await this.streamResponse(message);
            } else {
                await this.normalResponse(message);
            }
        } catch (error) {
            console.error('メッセージ送信エラー:', error);
            this.addMessage('assistant', '申し訳ございません。エラーが発生しました。');
        } finally {
            this.state.isThinking = false;
            this.hideTypingIndicator();
        }
        
        // 会話履歴保存
        this.saveConversationHistory();
    }
    
    /**
     * ストリーミングレスポンス
     */
    async streamResponse(message) {
        const formData = new FormData();
        formData.append('action', 'gi_stream_response');
        formData.append('nonce', this.config.nonce);
        formData.append('message', message);
        formData.append('session_id', this.state.sessionId);
        formData.append('context', JSON.stringify(this.state.context));
        
        // EventSourceを使用してストリーミング
        this.eventSource = new EventSource(
            this.config.apiEndpoint + '?' + new URLSearchParams(formData)
        );
        
        let responseText = '';
        const messageId = this.generateMessageId();
        
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            
            if (data.text) {
                responseText += data.text;
                this.updateStreamingMessage(messageId, responseText);
            }
            
            if (data.grants) {
                this.displayRelatedGrants(data.grants);
            }
        };
        
        this.eventSource.onerror = () => {
            this.eventSource.close();
            this.finalizeStreamingMessage(messageId);
        };
        
        this.eventSource.addEventListener('complete', () => {
            this.eventSource.close();
            this.finalizeStreamingMessage(messageId);
        });
    }
    
    /**
     * 通常レスポンス
     */
    async normalResponse(message) {
        const response = await fetch(this.config.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'gi_enhanced_semantic_search',
                nonce: this.config.nonce,
                query: message,
                context: JSON.stringify(this.state.context)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.processSearchResults(data.data);
        } else {
            throw new Error(data.data?.message || 'Unknown error');
        }
    }
    
    /**
     * 検索結果処理
     */
    processSearchResults(results) {
        if (!results || results.length === 0) {
            this.addMessage('assistant', 'お探しの条件に合う助成金が見つかりませんでした。条件を変更して再度お試しください。');
            return;
        }
        
        let responseHTML = `
            <div class="search-results">
                <p>以下の助成金が見つかりました（${results.length}件）：</p>
                <div class="grant-cards-container">
        `;
        
        results.slice(0, 5).forEach(grant => {
            responseHTML += this.createGrantCard(grant);
        });
        
        responseHTML += `
                </div>
            </div>
        `;
        
        if (results.length > 5) {
            responseHTML += `
                <button class="btn-show-more" data-offset="5" data-total="${results.length}">
                    さらに表示（残り${results.length - 5}件）
                </button>
            `;
        }
        
        this.addMessage('assistant', responseHTML, true);
    }
    
    /**
     * 助成金カード作成
     */
    createGrantCard(grant) {
        return `
            <div class="ai-grant-card" data-grant-id="${grant.id}">
                <div class="grant-header">
                    <h4 class="grant-title">${grant.title}</h4>
                    <span class="relevance-badge">${Math.round(grant.relevance_score * 100)}%一致</span>
                </div>
                <div class="grant-meta">
                    ${grant.meta.max_amount ? `
                        <span class="grant-amount">
                            💰 最大${this.formatAmount(grant.meta.max_amount)}
                        </span>
                    ` : ''}
                    ${grant.meta.application_period ? `
                        <span class="grant-deadline">
                            📅 締切: ${grant.meta.application_period}
                        </span>
                    ` : ''}
                    ${grant.meta.difficulty ? `
                        <span class="grant-difficulty difficulty-${grant.meta.difficulty}">
                            難易度: ${this.getDifficultyLabel(grant.meta.difficulty)}
                        </span>
                    ` : ''}
                </div>
                <p class="grant-excerpt">${grant.excerpt}</p>
                ${grant.matched_keywords && grant.matched_keywords.length > 0 ? `
                    <div class="matched-keywords">
                        <span class="keywords-label">マッチしたキーワード:</span>
                        ${grant.matched_keywords.map(kw => `<span class="keyword-tag">${kw}</span>`).join('')}
                    </div>
                ` : ''}
                <div class="grant-actions">
                    <button class="grant-card-action btn-details" data-grant-id="${grant.id}" data-action="details">
                        詳細を見る
                    </button>
                    <button class="grant-card-action btn-apply-guide" data-grant-id="${grant.id}" data-action="apply">
                        申請ガイド
                    </button>
                    <button class="grant-card-action btn-save" data-grant-id="${grant.id}" data-action="save">
                        保存
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * 感情分析
     */
    async analyzeEmotion(message) {
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_analyze_emotion',
                    nonce: this.config.nonce,
                    message: message,
                    context: JSON.stringify(this.state.context)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateEmotionIndicator(data.data);
                this.state.currentEmotion = data.data.sentiment_label;
                
                // コンテキストに感情を追加
                this.state.context.user_emotion = data.data;
            }
        } catch (error) {
            console.error('感情分析エラー:', error);
        }
    }
    
    /**
     * 感情インジケーター更新
     */
    updateEmotionIndicator(emotionData) {
        const emotionMap = {
            very_positive: { icon: '😄', label: 'とてもポジティブ', color: '#4CAF50' },
            positive: { icon: '😊', label: 'ポジティブ', color: '#8BC34A' },
            neutral: { icon: '😐', label: 'ニュートラル', color: '#FFC107' },
            negative: { icon: '😔', label: 'ネガティブ', color: '#FF9800' },
            very_negative: { icon: '😢', label: 'とてもネガティブ', color: '#F44336' }
        };
        
        const emotion = emotionMap[emotionData.sentiment_label] || emotionMap.neutral;
        
        const indicator = this.elements.emotionIndicator;
        indicator.querySelector('.emotion-icon').textContent = emotion.icon;
        indicator.querySelector('.emotion-label').textContent = emotion.label;
        
        const level = indicator.querySelector('.emotion-level');
        level.style.width = Math.abs(emotionData.sentiment * 100) + '%';
        level.style.backgroundColor = emotion.color;
        
        // アニメーション
        indicator.classList.add('emotion-update');
        setTimeout(() => indicator.classList.remove('emotion-update'), 500);
    }
    
    /**
     * メッセージ追加
     */
    addMessage(role, content, isHTML = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${role}`;
        messageDiv.id = this.generateMessageId();
        
        const avatar = role === 'user' ? '👤' : '🤖';
        
        messageDiv.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-content">
                ${isHTML ? content : `<p>${this.escapeHtml(content)}</p>`}
                <div class="message-time">${new Date().toLocaleTimeString('ja-JP', {hour: '2-digit', minute: '2-digit'})}</div>
            </div>
        `;
        
        this.elements.chatHistory.appendChild(messageDiv);
        this.scrollToBottom();
        
        // 履歴に追加
        this.state.conversationHistory.push({
            role: role,
            content: content,
            timestamp: new Date().toISOString()
        });
        
        // 履歴制限
        if (this.state.conversationHistory.length > this.config.maxHistoryLength) {
            this.state.conversationHistory.shift();
        }
    }
    
    /**
     * ストリーミングメッセージ更新
     */
    updateStreamingMessage(messageId, content) {
        let messageDiv = document.getElementById(messageId);
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.className = 'message message-assistant';
            messageDiv.id = messageId;
            messageDiv.innerHTML = `
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    <p class="streaming-text"></p>
                    <div class="message-time">${new Date().toLocaleTimeString('ja-JP', {hour: '2-digit', minute: '2-digit'})}</div>
                </div>
            `;
            this.elements.chatHistory.appendChild(messageDiv);
        }
        
        const textElement = messageDiv.querySelector('.streaming-text');
        textElement.textContent = content;
        
        // カーソル効果
        if (!textElement.classList.contains('streaming')) {
            textElement.classList.add('streaming');
        }
        
        this.scrollToBottom();
    }
    
    /**
     * ストリーミングメッセージ完了
     */
    finalizeStreamingMessage(messageId) {
        const messageDiv = document.getElementById(messageId);
        if (messageDiv) {
            const textElement = messageDiv.querySelector('.streaming-text');
            if (textElement) {
                textElement.classList.remove('streaming');
            }
        }
    }
    
    /**
     * タイピングインジケーター表示
     */
    showTypingIndicator() {
        this.elements.typingIndicator.style.display = 'flex';
        this.scrollToBottom();
    }
    
    /**
     * タイピングインジケーター非表示
     */
    hideTypingIndicator() {
        this.elements.typingIndicator.style.display = 'none';
    }
    
    /**
     * 入力変更処理
     */
    handleInputChange() {
        const hasText = this.elements.input.value.trim().length > 0;
        this.elements.sendBtn.disabled = !hasText;
        
        // 自動高さ調整
        this.elements.input.style.height = 'auto';
        this.elements.input.style.height = Math.min(this.elements.input.scrollHeight, 120) + 'px';
        
        // サジェスト表示
        if (this.config.autoSuggest && hasText) {
            this.showSuggestions();
        } else {
            this.hideSuggestions();
        }
    }
    
    /**
     * キーダウン処理
     */
    handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            this.sendMessage();
        }
    }
    
    /**
     * サジェスト表示
     */
    async showSuggestions() {
        const query = this.elements.input.value.trim();
        if (query.length < 2) return;
        
        // デバウンス
        clearTimeout(this.suggestTimeout);
        this.suggestTimeout = setTimeout(async () => {
            try {
                const response = await fetch(this.config.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'gi_get_recommendations',
                        nonce: this.config.nonce,
                        context: JSON.stringify(this.state.context)
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    this.displaySuggestions(data.data);
                }
            } catch (error) {
                console.error('サジェスト取得エラー:', error);
            }
        }, 300);
    }
    
    /**
     * サジェスト表示
     */
    displaySuggestions(suggestions) {
        let html = '<div class="suggestions-list">';
        
        suggestions.slice(0, 5).forEach(suggestion => {
            html += `
                <div class="suggestion-item" data-query="${this.escapeHtml(suggestion.query)}">
                    <span class="suggestion-text">${this.escapeHtml(suggestion.query)}</span>
                    <span class="suggestion-meta">人気度: ${suggestion.popularity}</span>
                </div>
            `;
        });
        
        html += '</div>';
        
        this.elements.suggestions.innerHTML = html;
        this.elements.suggestions.style.display = 'block';
        
        // サジェストクリック
        this.elements.suggestions.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                this.elements.input.value = item.dataset.query;
                this.hideSuggestions();
                this.sendMessage();
            });
        });
    }
    
    /**
     * サジェスト非表示
     */
    hideSuggestions() {
        this.elements.suggestions.style.display = 'none';
    }
    
    /**
     * 助成金アクション処理
     */
    async handleGrantAction(grantId, action) {
        switch (action) {
            case 'details':
                // 詳細ページへ遷移
                window.location.href = `/grant/${grantId}`;
                break;
                
            case 'apply':
                // 申請ガイド表示
                this.elements.input.value = `助成金ID ${grantId} の申請方法を教えて`;
                this.sendMessage();
                break;
                
            case 'save':
                // お気に入り保存
                await this.saveGrantToFavorites(grantId);
                break;
        }
    }
    
    /**
     * チャット切り替え
     */
    toggleChat() {
        const isVisible = this.elements.container.classList.contains('visible');
        
        if (isVisible) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }
    
    /**
     * チャット開く
     */
    openChat() {
        this.elements.container.classList.add('visible');
        this.elements.toggle.classList.add('active');
        this.elements.input.focus();
        
        // バッジクリア
        const badge = this.elements.toggle.querySelector('.toggle-badge');
        badge.style.display = 'none';
        badge.textContent = '0';
    }
    
    /**
     * チャット閉じる
     */
    closeChat() {
        this.elements.container.classList.remove('visible');
        this.elements.toggle.classList.remove('active');
    }
    
    /**
     * チャット最小化
     */
    minimizeChat() {
        this.closeChat();
    }
    
    /**
     * 下までスクロール
     */
    scrollToBottom() {
        this.elements.chatHistory.scrollTop = this.elements.chatHistory.scrollHeight;
    }
    
    /**
     * セッションID生成
     */
    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * メッセージID生成
     */
    generateMessageId() {
        return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * HTMLエスケープ
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    /**
     * 金額フォーマット
     */
    formatAmount(amount) {
        if (amount >= 100000000) {
            return Math.floor(amount / 100000000) + '億円';
        } else if (amount >= 10000) {
            return Math.floor(amount / 10000) + '万円';
        }
        return amount.toLocaleString() + '円';
    }
    
    /**
     * 難易度ラベル取得
     */
    getDifficultyLabel(difficulty) {
        const labels = {
            easy: '簡単',
            normal: '普通',
            hard: '難しい',
            expert: '専門的'
        };
        return labels[difficulty] || difficulty;
    }
    
    /**
     * 通知表示
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `ai-notification ai-notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * 会話履歴読み込み
     */
    loadConversationHistory() {
        const saved = localStorage.getItem('ai_conversation_history');
        if (saved) {
            try {
                const history = JSON.parse(saved);
                if (Array.isArray(history)) {
                    this.state.conversationHistory = history;
                }
            } catch (e) {
                console.error('履歴読み込みエラー:', e);
            }
        }
    }
    
    /**
     * 会話履歴保存
     */
    saveConversationHistory() {
        try {
            localStorage.setItem('ai_conversation_history', JSON.stringify(this.state.conversationHistory));
        } catch (e) {
            console.error('履歴保存エラー:', e);
        }
    }
    
    /**
     * お気に入り保存
     */
    async saveGrantToFavorites(grantId) {
        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gi_toggle_favorite',
                    nonce: this.config.nonce,
                    post_id: grantId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('お気に入りに保存しました', 'success');
            }
        } catch (error) {
            console.error('お気に入り保存エラー:', error);
            this.showNotification('保存に失敗しました', 'error');
        }
    }
    
    /**
     * ハートビート
     */
    startHeartbeat() {
        setInterval(() => {
            this.updateStatus();
        }, 30000);
    }
    
    /**
     * ステータス更新
     */
    updateStatus() {
        this.elements.statusIndicator.classList.add('pulse');
        setTimeout(() => {
            this.elements.statusIndicator.classList.remove('pulse');
        }, 1000);
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
    if (typeof gi_ajax !== 'undefined') {
        window.aiAssistant = new AIAssistant();
        console.log('✨ AI Assistant initialized');
    }
});