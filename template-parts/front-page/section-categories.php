<?php
/**
 * Ultra Modern Categories Section - Monochrome Professional Edition
 * カテゴリー別助成金検索セクション - モノクローム・プロフェッショナル版
 *
 * @package Grant_Insight_Perfect
 * @version 22.0-monochrome
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// functions.phpとの連携確認
if (!function_exists('gi_get_acf_field_safely')) {
    require_once get_template_directory() . '/inc/4-helper-functions.php';
}

// データベースから実際のカテゴリと件数を取得
$main_categories = get_terms(array(
    'taxonomy' => 'grant_category',
    'hide_empty' => false,
    'orderby' => 'count',
    'order' => 'DESC',
    'number' => 6
));

$all_categories = get_terms(array(
    'taxonomy' => 'grant_category',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
));

$prefectures = get_terms(array(
    'taxonomy' => 'grant_prefecture',
    'hide_empty' => false,
    'orderby' => 'count',
    'order' => 'DESC'
));

// カテゴリアイコンとカラー設定（モノクローム版）
$category_configs = array(
    0 => array(
        'icon' => 'fas fa-laptop-code',
        'gradient' => 'from-gray-900 to-black',
        'description' => 'IT導入・DX推進・デジタル化支援'
    ),
    1 => array(
        'icon' => 'fas fa-industry',
        'gradient' => 'from-black to-gray-900',
        'description' => 'ものづくり・製造業支援'
    ),
    2 => array(
        'icon' => 'fas fa-rocket',
        'gradient' => 'from-gray-800 to-black',
        'description' => '創業・スタートアップ支援'
    ),
    3 => array(
        'icon' => 'fas fa-store',
        'gradient' => 'from-black to-gray-800',
        'description' => '小規模事業者・商業支援'
    ),
    4 => array(
        'icon' => 'fas fa-leaf',
        'gradient' => 'from-gray-900 to-gray-700',
        'description' => '環境・省エネ・SDGs支援'
    ),
    5 => array(
        'icon' => 'fas fa-users',
        'gradient' => 'from-gray-700 to-black',
        'description' => '人材育成・雇用支援'
    )
);

$archive_base_url = get_post_type_archive_link('grant');

// 統計情報を取得（functions.phpから）
if (function_exists('gi_get_cached_stats')) {
    $stats = gi_get_cached_stats();
} else {
    $stats = array(
        'total_grants' => wp_count_posts('grant')->publish ?? 0,
        'active_grants' => 0,
        'prefecture_count' => count($prefectures)
    );
}
?>

<!-- フォント・アイコン読み込み -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+JP:wght@300;400;500;700;900&display=swap" rel="stylesheet">

<!-- モノクローム・カテゴリーセクション -->
<section class="monochrome-categories" id="grant-categories">
    <!-- 背景エフェクト -->
    <div class="background-effects">
        <div class="grid-pattern"></div>
        <div class="gradient-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <div class="section-container">
        <!-- セクションヘッダー -->
        <div class="section-header" data-aos="fade-up">
            <div class="header-accent"></div>
            
            <h2 class="section-title">
                <span class="title-en">CATEGORY SEARCH</span>
                <span class="title-ja">カテゴリーから探す</span>
            </h2>
            
            <p class="section-description">
                業種・目的別に最適な助成金を簡単検索
            </p>

            <!-- 統計情報 -->
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-value" data-counter="<?php echo esc_attr($stats['total_grants']); ?>">0</span>
                    <span class="stat-label">総助成金数</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" data-counter="<?php echo count($all_categories); ?>">0</span>
                    <span class="stat-label">カテゴリー</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" data-counter="<?php echo esc_attr($stats['prefecture_count']); ?>">0</span>
                    <span class="stat-label">都道府県</span>
                </div>
            </div>
        </div>

        <!-- メインカテゴリーグリッド -->
        <div class="main-categories-grid">
            <?php
            if (!empty($main_categories)) :
                foreach ($main_categories as $index => $category) :
                    if ($index >= 6) break;
                    $config = $category_configs[$index] ?? array(
                        'icon' => 'fas fa-folder',
                        'gradient' => 'from-gray-800 to-black',
                        'description' => ''
                    );
                    $category_url = add_query_arg('grant_category', $category->slug, $archive_base_url);
                    
                    // カテゴリーの最新投稿を取得
                    $recent_grants = get_posts(array(
                        'post_type' => 'grant',
                        'posts_per_page' => 3,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'grant_category',
                                'field' => 'term_id',
                                'terms' => $category->term_id
                            )
                        )
                    ));
            ?>
            <div class="category-card" 
                 data-aos="fade-up" 
                 data-aos-delay="<?php echo $index * 50; ?>"
                 data-category="<?php echo esc_attr($category->slug); ?>">
                
                <div class="card-inner">
                    <!-- グラデーションボーダー -->
                    <div class="card-border"></div>
                    
                    <!-- カードコンテンツ -->
                    <div class="card-content">
                        <!-- アイコンとタイトル -->
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="<?php echo esc_attr($config['icon']); ?>"></i>
                            </div>
                            <div class="card-badge">
                                <span class="badge-count"><?php echo number_format($category->count); ?></span>
                                <span class="badge-label">件</span>
                            </div>
                        </div>
                        
                        <h3 class="card-title"><?php echo esc_html($category->name); ?></h3>
                        
                        <?php if ($config['description']): ?>
                        <p class="card-description"><?php echo esc_html($config['description']); ?></p>
                        <?php endif; ?>
                        
                        <!-- 最新の助成金プレビュー -->
                        <?php if (!empty($recent_grants)): ?>
                        <div class="recent-grants">
                            <div class="recent-grants-label">最新の助成金</div>
                            <?php foreach ($recent_grants as $grant): 
                                $amount = gi_safe_get_meta($grant->ID, 'max_amount', '');
                            ?>
                            <a href="<?php echo esc_url(get_permalink($grant->ID)); ?>" class="recent-grant-item" target="_blank">
                                <span class="grant-title"><?php echo esc_html(mb_substr($grant->post_title, 0, 20)); ?>...</span>
                                <?php if ($amount): ?>
                                <span class="grant-amount"><?php echo esc_html($amount); ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- アクションボタン -->
                        <a href="<?php echo esc_url($category_url); ?>" class="card-link">
                            <span class="link-text">詳細を見る</span>
                            <span class="link-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </a>
                    </div>
                    
                    <!-- ホバーエフェクト -->
                    <div class="hover-effect"></div>
                </div>
            </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>

        <!-- その他のカテゴリー -->
        <?php if (!empty($all_categories) && count($all_categories) > 6) :
            $other_categories = array_slice($all_categories, 6);
        ?>
        <div class="other-categories-section" data-aos="fade-up">
            <button type="button" id="toggle-categories" class="toggle-button">
                <span class="toggle-icon">
                    <i class="fas fa-plus"></i>
                </span>
                <span class="toggle-text">その他のカテゴリーを表示</span>
                <span class="count-badge"><?php echo count($other_categories); ?></span>
            </button>

            <div id="other-categories" class="other-categories-container">
                <div class="categories-grid">
                    <?php foreach ($other_categories as $category) :
                        $category_url = add_query_arg('grant_category', $category->slug, $archive_base_url);
                    ?>
                    <a href="<?php echo esc_url($category_url); ?>" class="mini-category-card">
                        <div class="mini-card-inner">
                            <i class="fas fa-folder mini-icon"></i>
                            <span class="mini-title"><?php echo esc_html($category->name); ?></span>
                            <span class="mini-count"><?php echo $category->count; ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 地域別検索 -->
        <div class="region-section" data-aos="fade-up">
            <div class="region-header">
                <h3 class="region-title">
                    <span class="title-en">REGIONAL SEARCH</span>
                    <span class="title-ja">地域から探す</span>
                </h3>
            </div>

            <div class="regions-container">
                <div class="japan-map">
                    <!-- 日本地図SVG（簡略版） -->
                    <svg viewBox="0 0 500 600" class="map-svg">
                        <!-- 地域ブロック -->
                        <g class="region-blocks">
                            <?php
                            $regions = array(
                                'hokkaido' => array('x' => 400, 'y' => 50, 'name' => '北海道'),
                                'tohoku' => array('x' => 380, 'y' => 150, 'name' => '東北'),
                                'kanto' => array('x' => 350, 'y' => 280, 'name' => '関東'),
                                'chubu' => array('x' => 280, 'y' => 280, 'name' => '中部'),
                                'kinki' => array('x' => 200, 'y' => 350, 'name' => '近畿'),
                                'chugoku' => array('x' => 100, 'y' => 350, 'name' => '中国'),
                                'shikoku' => array('x' => 150, 'y' => 420, 'name' => '四国'),
                                'kyushu' => array('x' => 50, 'y' => 450, 'name' => '九州')
                            );
                            
                            foreach ($regions as $key => $region):
                            ?>
                            <g class="region-block" data-region="<?php echo esc_attr($key); ?>">
                                <circle cx="<?php echo $region['x']; ?>" cy="<?php echo $region['y']; ?>" r="30" />
                                <text x="<?php echo $region['x']; ?>" y="<?php echo $region['y'] + 5; ?>" text-anchor="middle">
                                    <?php echo esc_html($region['name']); ?>
                                </text>
                            </g>
                            <?php endforeach; ?>
                        </g>
                    </svg>
                </div>
                
                <div class="prefecture-list">
                    <?php
                    $popular_prefectures = array_slice($prefectures, 0, 15);
                    foreach ($popular_prefectures as $index => $prefecture) :
                        $prefecture_url = add_query_arg('grant_prefecture', $prefecture->slug, $archive_base_url);
                    ?>
                    <a href="<?php echo esc_url($prefecture_url); ?>" 
                       class="prefecture-item <?php echo $index < 3 ? 'featured' : ''; ?>">
                        <span class="prefecture-name"><?php echo esc_html($prefecture->name); ?></span>
                        <span class="prefecture-count"><?php echo $prefecture->count; ?></span>
                        <?php if ($index < 3): ?>
                        <span class="featured-badge">
                            <i class="fas fa-fire"></i>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta-section" data-aos="fade-up">
            <div class="cta-content">
                <h3 class="cta-title">すべての助成金を探す</h3>
                <p class="cta-description">条件を絞り込んで、あなたに最適な助成金を見つけましょう</p>
                <a href="<?php echo esc_url($archive_base_url); ?>" class="cta-button">
                    <span class="button-text">助成金を検索</span>
                    <span class="button-icon">
                        <i class="fas fa-search"></i>
                    </span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- モノクローム・スタイル -->
<style>
/* ベース設定 */
.monochrome-categories {
    position: relative;
    padding: 100px 0;
    background: #ffffff;
    overflow: hidden;
    font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* 背景エフェクト */
.background-effects {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.grid-pattern {
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(0, 0, 0, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
    background-size: 50px 50px;
}

.gradient-overlay {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 50%, transparent 0%, rgba(255, 255, 255, 0.8) 100%);
}

.floating-shapes {
    position: absolute;
    inset: 0;
}

.shape {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.05;
}

.shape-1 {
    width: 600px;
    height: 600px;
    background: #000000;
    top: -300px;
    right: -200px;
    animation: float 20s ease-in-out infinite;
}

.shape-2 {
    width: 400px;
    height: 400px;
    background: #333333;
    bottom: -200px;
    left: -100px;
    animation: float 25s ease-in-out infinite reverse;
}

.shape-3 {
    width: 300px;
    height: 300px;
    background: #666666;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: pulse 15s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(180deg); }
}

@keyframes pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.05; }
    50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.1; }
}

/* コンテナ */
.section-container {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* セクションヘッダー */
.section-header {
    text-align: center;
    margin-bottom: 80px;
    position: relative;
}

.header-accent {
    width: 60px;
    height: 4px;
    background: #000000;
    margin: 0 auto 40px;
    position: relative;
    overflow: hidden;
}

.header-accent::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
    animation: shine 3s ease-in-out infinite;
}

@keyframes shine {
    0% { left: -100%; }
    100% { left: 200%; }
}

.section-title {
    margin-bottom: 20px;
}

.title-en {
    display: block;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: #999999;
    margin-bottom: 12px;
}

.title-ja {
    display: block;
    font-size: clamp(36px, 5vw, 48px);
    font-weight: 900;
    color: #000000;
    line-height: 1.2;
    letter-spacing: 0.02em;
}

.section-description {
    font-size: 18px;
    color: #666666;
    margin-bottom: 40px;
    font-weight: 400;
}

/* 統計情報 */
.stats-row {
    display: flex;
    justify-content: center;
    gap: 60px;
    padding: 40px;
    background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
    border-radius: 20px;
    border: 1px solid #e0e0e0;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 42px;
    font-weight: 900;
    color: #000000;
    margin-bottom: 8px;
    font-feature-settings: 'tnum';
    position: relative;
}

.stat-value::after {
    content: '+';
    position: absolute;
    right: -15px;
    top: 0;
    font-size: 24px;
    font-weight: 400;
    color: #999999;
}

.stat-label {
    font-size: 13px;
    color: #999999;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
}

/* メインカテゴリーグリッド */
.main-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

/* カテゴリーカード */
.category-card {
    position: relative;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.category-card:hover {
    transform: translateY(-5px);
}

.card-inner {
    position: relative;
    background: #ffffff;
    border-radius: 20px;
    overflow: hidden;
    height: 100%;
}

.card-border {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #000000, #333333, #000000);
    padding: 2px;
    border-radius: 20px;
    -webkit-mask: 
        linear-gradient(#fff 0 0) content-box, 
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
}

.card-content {
    position: relative;
    padding: 35px;
    background: #ffffff;
    border-radius: 18px;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 25px;
}

.card-icon {
    width: 56px;
    height: 56px;
    background: #000000;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 24px;
    transition: all 0.3s ease;
}

.category-card:hover .card-icon {
    background: #333333;
    transform: rotate(5deg);
}

.card-badge {
    text-align: right;
}

.badge-count {
    font-size: 28px;
    font-weight: 900;
    color: #000000;
    display: block;
}

.badge-label {
    font-size: 12px;
    color: #999999;
    font-weight: 600;
}

.card-title {
    font-size: 22px;
    font-weight: 800;
    color: #000000;
    margin-bottom: 12px;
    line-height: 1.3;
}

.card-description {
    font-size: 14px;
    color: #666666;
    line-height: 1.6;
    margin-bottom: 25px;
}

/* 最新の助成金 */
.recent-grants {
    margin: 20px 0;
    padding: 20px;
    background: #fafafa;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
}

.recent-grants-label {
    font-size: 11px;
    font-weight: 700;
    color: #999999;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 12px;
}

.recent-grant-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
    text-decoration: none;
    transition: all 0.2s ease;
}

a.recent-grant-item:hover {
    background: rgba(0, 0, 0, 0.02);
    padding-left: 8px;
    margin-left: -8px;
    padding-right: 8px;
    margin-right: -8px;
}

.recent-grant-item:last-child {
    border-bottom: none;
}

.grant-title {
    font-size: 13px;
    color: #333333;
    flex: 1;
}

.grant-amount {
    font-size: 13px;
    font-weight: 700;
    color: #000000;
}

.card-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    background: #000000;
    color: #ffffff;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    margin-top: auto;
}

.card-link:hover {
    background: #ffffff;
    color: #000000;
    box-shadow: inset 0 0 0 2px #000000;
}

.link-arrow {
    transition: transform 0.3s ease;
}

.card-link:hover .link-arrow {
    transform: translateX(5px);
}

/* ホバーエフェクト */
.hover-effect {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, transparent, rgba(0, 0, 0, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.category-card:hover .hover-effect {
    opacity: 1;
}

/* その他のカテゴリー */
.other-categories-section {
    margin-bottom: 80px;
}

.toggle-button {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0 auto 40px;
    padding: 18px 32px;
    background: #ffffff;
    border: 2px solid #000000;
    border-radius: 999px;
    font-size: 15px;
    font-weight: 700;
    color: #000000;
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggle-button:hover {
    background: #000000;
    color: #ffffff;
}

.toggle-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}

.toggle-button.active .toggle-icon {
    transform: rotate(45deg);
}

.count-badge {
    padding: 4px 12px;
    background: #000000;
    color: #ffffff;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.toggle-button:hover .count-badge {
    background: #ffffff;
    color: #000000;
}

.other-categories-container {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.5s ease;
}

.other-categories-container.show {
    max-height: 2000px;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    padding: 40px;
    background: #fafafa;
    border-radius: 20px;
    border: 2px solid #000000;
}

/* ミニカテゴリーカード */
.mini-category-card {
    display: block;
    text-decoration: none;
    transition: all 0.3s ease;
}

.mini-card-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.mini-category-card:hover .mini-card-inner {
    background: #000000;
    border-color: #000000;
}

.mini-icon {
    font-size: 18px;
    color: #666666;
    transition: color 0.3s ease;
}

.mini-category-card:hover .mini-icon {
    color: #ffffff;
}

.mini-title {
    flex: 1;
    font-size: 14px;
    font-weight: 600;
    color: #000000;
    transition: color 0.3s ease;
}

.mini-category-card:hover .mini-title {
    color: #ffffff;
}

.mini-count {
    padding: 4px 8px;
    background: #f0f0f0;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #666666;
    transition: all 0.3s ease;
}

.mini-category-card:hover .mini-count {
    background: #ffffff;
    color: #000000;
}

/* 地域セクション */
.region-section {
    margin-bottom: 80px;
}

.region-header {
    text-align: center;
    margin-bottom: 50px;
}

.region-title {
    margin-bottom: 40px;
}

.regions-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 40px;
    align-items: center;
}

/* 日本地図 */
.japan-map {
    position: relative;
    background: #fafafa;
    border-radius: 20px;
    padding: 40px;
    border: 2px solid #000000;
}

.map-svg {
    width: 100%;
    height: auto;
}

.region-block circle {
    fill: #ffffff;
    stroke: #000000;
    stroke-width: 2;
    transition: all 0.3s ease;
    cursor: pointer;
}

.region-block:hover circle,
.region-block.active circle,
.region-block.hover circle {
    fill: #000000;
}

.region-block.active circle {
    stroke-width: 3;
    stroke: #4CAF50;
}

.region-block text {
    font-size: 12px;
    font-weight: 700;
    fill: #000000;
    pointer-events: none;
    transition: fill 0.3s ease;
}

.region-block:hover text,
.region-block.active text,
.region-block.hover text {
    fill: #ffffff;
}

/* 都道府県アイテムのハイライト状態 */
.prefecture-item.highlighted {
    background: #E8F5E9 !important;
    border-color: #4CAF50 !important;
    opacity: 1 !important;
}

/* 都道府県リスト */
.prefecture-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}

.prefecture-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.prefecture-item:hover {
    background: #000000;
    border-color: #000000;
}

.prefecture-item.featured {
    border: 2px solid #000000;
}

.prefecture-name {
    font-size: 14px;
    font-weight: 600;
    color: #000000;
    transition: color 0.3s ease;
}

.prefecture-item:hover .prefecture-name {
    color: #ffffff;
}

.prefecture-count {
    font-size: 12px;
    font-weight: 700;
    color: #666666;
    transition: color 0.3s ease;
}

.prefecture-item:hover .prefecture-count {
    color: #cccccc;
}

.featured-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 24px;
    height: 24px;
    background: #000000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 10px;
}

/* CTA */
.cta-section {
    text-align: center;
    padding: 80px 40px;
    background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
    border-radius: 30px;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        repeating-linear-gradient(
            45deg,
            transparent,
            transparent 10px,
            rgba(255, 255, 255, 0.02) 10px,
            rgba(255, 255, 255, 0.02) 20px
        );
}

.cta-content {
    position: relative;
    z-index: 1;
}

.cta-title {
    font-size: 36px;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 16px;
}

.cta-description {
    font-size: 16px;
    color: #cccccc;
    margin-bottom: 32px;
}

.cta-button {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    padding: 20px 40px;
    background: #ffffff;
    color: #000000;
    border-radius: 999px;
    font-size: 16px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.cta-button::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.6s ease, height 0.6s ease;
}

.cta-button:hover::before {
    width: 300px;
    height: 300px;
}

.button-text,
.button-icon {
    position: relative;
    z-index: 1;
}

.button-icon {
    transition: transform 0.3s ease;
}

.cta-button:hover .button-icon {
    transform: rotate(90deg);
}

/* アニメーション */
[data-aos] {
    opacity: 0;
    transition: opacity 0.6s ease, transform 0.6s ease;
}

[data-aos="fade-up"] {
    transform: translateY(30px);
}

[data-aos].aos-animate {
    opacity: 1;
    transform: translateY(0);
}

/* レスポンシブ */
@media (max-width: 1024px) {
    .main-categories-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .regions-container {
        grid-template-columns: 1fr;
    }
    
    .japan-map {
        max-width: 400px;
        margin: 0 auto;
    }
}

@media (max-width: 640px) {
    .monochrome-categories {
        padding: 60px 0;
    }
    
    .stats-row {
        flex-direction: column;
        gap: 30px;
    }
    
    .main-categories-grid {
        grid-template-columns: 1fr;
    }
    
    .card-content {
        padding: 25px;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .prefecture-list {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .cta-section {
        padding: 60px 20px;
    }
    
    .cta-title {
        font-size: 28px;
    }
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // カウンターアニメーション
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };
    
    const counterObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-counter'));
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current).toLocaleString();
                }, 30);
                counterObserver.unobserve(counter);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('[data-counter]').forEach(counter => {
        counterObserver.observe(counter);
    });
    
    // AOS風アニメーション
    const aosElements = document.querySelectorAll('[data-aos]');
    const aosObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = entry.target.getAttribute('data-aos-delay') || 0;
                setTimeout(() => {
                    entry.target.classList.add('aos-animate');
                }, delay);
                aosObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    aosElements.forEach(element => {
        aosObserver.observe(element);
    });
    
    // カテゴリー開閉
    const toggleCategories = document.getElementById('toggle-categories');
    const otherCategories = document.getElementById('other-categories');
    
    if (toggleCategories && otherCategories) {
        toggleCategories.addEventListener('click', function() {
            const isOpen = otherCategories.classList.contains('show');
            
            if (isOpen) {
                otherCategories.classList.remove('show');
                this.classList.remove('active');
                this.querySelector('.toggle-text').textContent = 'その他のカテゴリーを表示';
                this.querySelector('.toggle-icon i').className = 'fas fa-plus';
            } else {
                otherCategories.classList.add('show');
                this.classList.add('active');
                this.querySelector('.toggle-text').textContent = 'カテゴリーを閉じる';
                this.querySelector('.toggle-icon i').className = 'fas fa-minus';
                
                // スムーズスクロール
                setTimeout(() => {
                    otherCategories.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest' 
                    });
                }, 100);
            }
        });
    }
    
    // カードホバーエフェクト
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('mouseenter', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            this.style.setProperty('--mouse-x', x + 'px');
            this.style.setProperty('--mouse-y', y + 'px');
        });
    });
    
    // 地域と都道府県のマッピング
    const regionPrefectureMap = {
        'hokkaido': ['北海道'],
        'tohoku': ['青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
        'kanto': ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県'],
        'chubu': ['新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県'],
        'kinki': ['三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
        'chugoku': ['鳥取県', '島根県', '岡山県', '広島県', '山口県'],
        'shikoku': ['徳島県', '香川県', '愛媛県', '高知県'],
        'kyushu': ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県']
    };
    
    // 地域ブロッククリック
    document.querySelectorAll('.region-block').forEach(block => {
        block.addEventListener('click', function() {
            const region = this.getAttribute('data-region');
            const prefectures = regionPrefectureMap[region] || [];
            
            // 全ての地域ブロックの選択状態をリセット
            document.querySelectorAll('.region-block').forEach(b => {
                b.classList.remove('active');
            });
            
            // クリックされた地域をアクティブに
            this.classList.add('active');
            
            // 該当する都道府県をハイライト
            document.querySelectorAll('.prefecture-item').forEach(item => {
                const prefName = item.querySelector('.prefecture-name').textContent;
                if (prefectures.includes(prefName)) {
                    item.classList.add('highlighted');
                    item.style.opacity = '1';
                    item.style.background = '#f0f0f0';
                } else {
                    item.classList.remove('highlighted');
                    item.style.opacity = '0.3';
                    item.style.background = '';
                }
            });
            
            // 都道府県リストをスクロール
            const prefectureList = document.querySelector('.prefecture-list');
            if (prefectureList) {
                prefectureList.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });
    
    // 都道府県アイテムクリック時に地域も連動
    document.querySelectorAll('.prefecture-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            const prefName = this.querySelector('.prefecture-name').textContent;
            
            // 該当する地域を探す
            for (const [region, prefs] of Object.entries(regionPrefectureMap)) {
                if (prefs.includes(prefName)) {
                    const regionBlock = document.querySelector(`.region-block[data-region="${region}"]`);
                    if (regionBlock) {
                        regionBlock.classList.add('hover');
                    }
                    break;
                }
            }
        });
        
        item.addEventListener('mouseleave', function() {
            document.querySelectorAll('.region-block').forEach(block => {
                block.classList.remove('hover');
            });
        });
    });
    
    // パフォーマンス最適化：Intersection Observerでの遅延読み込み
    const lazyLoadObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // ここで追加のコンテンツを読み込み
                const element = entry.target;
                element.classList.add('loaded');
                lazyLoadObserver.unobserve(element);
            }
        });
    }, {
        rootMargin: '100px'
    });
    
    // functions.phpとの連携：AJAX呼び出し例
    function loadCategoryGrants(categorySlug) {
        if (typeof gi_ajax === 'undefined') {
            console.warn('gi_ajax object not found');
            return;
        }
        
        fetch(gi_ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'gi_load_grants',
                nonce: gi_ajax.nonce,
                categories: JSON.stringify([categorySlug]),
                view: 'grid'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Grants loaded:', data.data);
                // ここで取得したデータを表示
            }
        })
        .catch(error => {
            console.error('Error loading grants:', error);
        });
    }
    
    // カテゴリーカードクリック時のプレビュー機能
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.card-link')) {
                return; // リンククリック時は通常の動作
            }
            
            const category = this.getAttribute('data-category');
            if (category && typeof loadCategoryGrants === 'function') {
                loadCategoryGrants(category);
            }
        });
    });
    
    console.log('Monochrome Categories Section initialized successfully');
});
</script>

<?php
// デバッグ情報（開発環境のみ）
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo '<!-- Categories Section Debug Info -->';
    echo '<!-- Total Categories: ' . count($all_categories) . ' -->';
    echo '<!-- Total Prefectures: ' . count($prefectures) . ' -->';
    echo '<!-- Theme Version: ' . GI_THEME_VERSION . ' -->';
}
?>
