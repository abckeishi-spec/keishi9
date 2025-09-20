/**
 * Grant Insight Perfect - Service Worker
 * カーセンサー、Amazon、Google等の優れたプラットフォームから学んだPWA最適化
 * 
 * @version 8.0-mobile-enhanced
 */

const CACHE_NAME = 'gi-perfect-v8.0';
const STATIC_CACHE_NAME = 'gi-static-v8.0';
const DYNAMIC_CACHE_NAME = 'gi-dynamic-v8.0';
const API_CACHE_NAME = 'gi-api-v8.0';

// キャッシュする静的リソース
const STATIC_ASSETS = [
    '/',
    '/grants/',
    '/assets/css/mobile-enhanced.css',
    '/assets/js/mobile-enhanced.js',
    '/assets/js/main.js',
    '/assets/css/optimized.css',
    '/manifest.json',
    // CDN resources
    'https://cdn.tailwindcss.com',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700&display=swap'
];

// API エンドポイント
const API_ENDPOINTS = [
    '/wp-admin/admin-ajax.php',
    '/wp-json/wp/v2/',
    '/wp-json/gi/v1/'
];

// インストール時の処理
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        Promise.all([
            // 静的リソースをプリキャッシュ
            caches.open(STATIC_CACHE_NAME)
                .then(cache => {
                    console.log('Service Worker: Precaching static assets');
                    return cache.addAll(STATIC_ASSETS);
                }),
            
            // 動的キャッシュとAPIキャッシュを初期化
            caches.open(DYNAMIC_CACHE_NAME),
            caches.open(API_CACHE_NAME)
        ]).then(() => {
            console.log('Service Worker: Installation complete');
            // 新しいService Workerを即座に有効化
            return self.skipWaiting();
        })
    );
});

// アクティベート時の処理
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        // 古いキャッシュを削除
        caches.keys().then(cacheNames => {
            const deletePromises = cacheNames.map(cacheName => {
                if (cacheName !== STATIC_CACHE_NAME && 
                    cacheName !== DYNAMIC_CACHE_NAME && 
                    cacheName !== API_CACHE_NAME) {
                    console.log('Service Worker: Deleting old cache:', cacheName);
                    return caches.delete(cacheName);
                }
            });
            
            return Promise.all(deletePromises);
        }).then(() => {
            console.log('Service Worker: Activation complete');
            // 新しいService Workerですべてのタブを制御
            return self.clients.claim();
        })
    );
});

// フェッチイベントの処理
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // GETリクエストのみ処理
    if (request.method !== 'GET') {
        return;
    }
    
    // Chrome extension やファイル等のスキームは無視
    if (!request.url.startsWith('http')) {
        return;
    }
    
    event.respondWith(handleFetchRequest(request, url));
});

/**
 * フェッチリクエストの処理
 */
async function handleFetchRequest(request, url) {
    try {
        // 1. 静的リソースの処理
        if (isStaticAsset(url)) {
            return handleStaticAsset(request);
        }
        
        // 2. APIリクエストの処理
        if (isApiRequest(url)) {
            return handleApiRequest(request);
        }
        
        // 3. HTML ページの処理
        if (isHtmlRequest(request)) {
            return handleHtmlRequest(request);
        }
        
        // 4. 画像リソースの処理
        if (isImageRequest(request)) {
            return handleImageRequest(request);
        }
        
        // 5. その他のリソース（CSS, JS等）
        return handleOtherResource(request);
        
    } catch (error) {
        console.error('Service Worker: Fetch error:', error);
        return handleFetchError(request, error);
    }
}

/**
 * 静的アセット判定
 */
function isStaticAsset(url) {
    return STATIC_ASSETS.some(asset => url.pathname.includes(asset) || url.href === asset);
}

/**
 * APIリクエスト判定
 */
function isApiRequest(url) {
    return API_ENDPOINTS.some(endpoint => url.pathname.includes(endpoint)) ||
           url.searchParams.has('action') ||
           url.pathname.includes('/wp-json/');
}

/**
 * HTMLリクエスト判定
 */
function isHtmlRequest(request) {
    return request.headers.get('Accept')?.includes('text/html');
}

/**
 * 画像リクエスト判定
 */
function isImageRequest(request) {
    return request.headers.get('Accept')?.includes('image/') ||
           /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(request.url);
}

/**
 * 静的アセットの処理 - Cache First Strategy
 */
async function handleStaticAsset(request) {
    const cache = await caches.open(STATIC_CACHE_NAME);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('Static asset fetch failed:', error);
        throw error;
    }
}

/**
 * APIリクエストの処理 - Network First with Cache Fallback
 */
async function handleApiRequest(request) {
    try {
        // ネットワークを優先
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // 成功したらキャッシュに保存
            const cache = await caches.open(API_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // ネットワークエラー時はキャッシュから返す
        console.log('API request failed, trying cache:', request.url);
        const cache = await caches.open(API_CACHE_NAME);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // キャッシュもない場合は適切なエラーレスポンスを返す
        return new Response(
            JSON.stringify({
                error: 'ネットワークに接続できません',
                offline: true
            }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

/**
 * HTMLページの処理 - Network First with Offline Fallback
 */
async function handleHtmlRequest(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // オフライン時はキャッシュから返す
        const cache = await caches.open(DYNAMIC_CACHE_NAME);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // メインページのキャッシュがあるかチェック
        const homePage = await cache.match('/');
        if (homePage) {
            return homePage;
        }
        
        // 最終的なフォールバック
        return createOfflinePage();
    }
}

/**
 * 画像リクエストの処理 - Cache First with Network Fallback
 */
async function handleImageRequest(request) {
    const cache = await caches.open(DYNAMIC_CACHE_NAME);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        // 画像が見つからない場合のプレースホルダー
        return createImagePlaceholder();
    }
}

/**
 * その他のリソースの処理 - Stale While Revalidate
 */
async function handleOtherResource(request) {
    const cache = await caches.open(DYNAMIC_CACHE_NAME);
    const cachedResponse = await cache.match(request);
    
    // バックグラウンドでネットワークから取得
    const networkPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(error => {
        console.error('Background fetch failed:', error);
    });
    
    // キャッシュがあればすぐに返す、なければネットワークを待つ
    if (cachedResponse) {
        networkPromise; // バックグラウンドで更新
        return cachedResponse;
    }
    
    return networkPromise;
}

/**
 * エラー時の処理
 */
function handleFetchError(request, error) {
    console.error('Fetch failed:', error);
    
    if (isHtmlRequest(request)) {
        return createOfflinePage();
    }
    
    if (isImageRequest(request)) {
        return createImagePlaceholder();
    }
    
    return new Response('Network error', { status: 503 });
}

/**
 * オフラインページの生成
 */
function createOfflinePage() {
    const offlineHtml = `
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>オフライン - 助成金検索</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    margin: 0;
                    padding: 2rem 1rem;
                    text-align: center;
                    background: #f9fafb;
                    color: #374151;
                }
                .offline-container {
                    max-width: 400px;
                    margin: 0 auto;
                    padding-top: 4rem;
                }
                .offline-icon {
                    font-size: 4rem;
                    margin-bottom: 2rem;
                    color: #9ca3af;
                }
                .offline-title {
                    font-size: 1.5rem;
                    font-weight: 700;
                    margin-bottom: 1rem;
                    color: #1f2937;
                }
                .offline-message {
                    font-size: 1rem;
                    margin-bottom: 2rem;
                    line-height: 1.6;
                }
                .offline-button {
                    display: inline-block;
                    padding: 0.75rem 2rem;
                    background: #2563eb;
                    color: white;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                }
                .offline-button:hover {
                    background: #1d4ed8;
                }
            </style>
        </head>
        <body>
            <div class="offline-container">
                <div class="offline-icon">📱</div>
                <h1 class="offline-title">オフラインです</h1>
                <p class="offline-message">
                    インターネット接続を確認してください。<br>
                    一部のコンテンツはオフラインでも利用できます。
                </p>
                <button class="offline-button" onclick="window.location.reload()">
                    再試行
                </button>
            </div>
            
            <script>
                // オンラインになったら自動でリロード
                window.addEventListener('online', () => {
                    window.location.reload();
                });
            </script>
        </body>
        </html>
    `;
    
    return new Response(offlineHtml, {
        headers: { 'Content-Type': 'text/html' }
    });
}

/**
 * 画像プレースホルダーの生成
 */
function createImagePlaceholder() {
    const svg = `
        <svg width="300" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%" height="100%" fill="#f3f4f6"/>
            <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="14" fill="#9ca3af" text-anchor="middle" dy=".3em">
                画像を読み込めません
            </text>
        </svg>
    `;
    
    return new Response(svg, {
        headers: { 'Content-Type': 'image/svg+xml' }
    });
}

// プッシュ通知の処理
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const options = {
        body: event.data.text(),
        icon: '/assets/images/icon-192x192.png',
        badge: '/assets/images/badge-72x72.png',
        tag: 'grant-notification',
        requireInteraction: false,
        actions: [
            {
                action: 'view',
                title: '詳細を見る',
                icon: '/assets/images/action-view.png'
            },
            {
                action: 'dismiss',
                title: '閉じる',
                icon: '/assets/images/action-close.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('新しい助成金情報', options)
    );
});

// 通知クリック時の処理
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('/grants/')
        );
    }
});

// バックグラウンド同期
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(performBackgroundSync());
    }
});

/**
 * バックグラウンド同期処理
 */
async function performBackgroundSync() {
    try {
        // お気に入りデータの同期
        await syncFavorites();
        
        // 検索履歴の同期
        await syncSearchHistory();
        
        console.log('Background sync completed');
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

async function syncFavorites() {
    // お気に入りデータの同期ロジック
    // IndexedDBまたはローカルストレージからデータを取得してサーバーと同期
}

async function syncSearchHistory() {
    // 検索履歴の同期ロジック
    // 検索パフォーマンス向上のためのデータ同期
}

// Service Worker更新チェック
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

console.log('Service Worker: Loaded and ready');