<?php
/**
 * Stylish Monochrome Single Grant Template - Photo-like Aesthetics
 * スタイリッシュな白黒助成金詳細ページ - 写真のような美学
 * 
 * @package Grant_Insight_Perfect
 * @version 11.0.0-monochrome
 */

get_header();

// Security and post validation
if (!have_posts()) {
    wp_redirect(home_url('/404'));
    exit;
}

the_post();
$post_id = get_the_ID();

// Essential ACF field retrieval - focused on key information
$grant_data = array(
    // 基本情報
    'organization' => get_field('organization', $post_id) ?: '',
    'organization_type' => get_field('organization_type', $post_id) ?: '',
    
    // 金額情報
    'max_amount' => get_field('max_amount', $post_id) ?: '',
    'max_amount_numeric' => intval(get_field('max_amount_numeric', $post_id)),
    'subsidy_rate' => get_field('subsidy_rate', $post_id) ?: '',
    
    // 期間・締切情報
    'deadline' => get_field('deadline', $post_id) ?: '',
    'deadline_date' => get_field('deadline_date', $post_id) ?: '',
    'application_status' => get_field('application_status', $post_id) ?: 'open',
    
    // 対象・条件
    'grant_target' => get_field('grant_target', $post_id) ?: '',
    'grant_difficulty' => get_field('grant_difficulty', $post_id) ?: 'normal',
    'grant_success_rate' => intval(get_field('grant_success_rate', $post_id)),
    
    // 申請・連絡先
    'application_method' => get_field('application_method', $post_id) ?: '',
    'contact_info' => get_field('contact_info', $post_id) ?: '',
    'official_url' => get_field('official_url', $post_id) ?: '',
    
    // AI関連
    'ai_summary' => get_field('ai_summary', $post_id) ?: get_post_meta($post_id, 'ai_summary', true),
    
    // 管理設定
    'views_count' => intval(get_field('views_count', $post_id)),
);

// Taxonomy data
$taxonomies = array(
    'categories' => get_the_terms($post_id, 'grant_category'),
    'prefectures' => get_the_terms($post_id, 'grant_prefecture'),
    'municipalities' => get_the_terms($post_id, 'grant_municipality'),
    'tags' => get_the_terms($post_id, 'post_tag'),
);

$main_category = ($taxonomies['categories'] && !is_wp_error($taxonomies['categories'])) ? $taxonomies['categories'][0] : null;
$main_prefecture = ($taxonomies['prefectures'] && !is_wp_error($taxonomies['prefectures'])) ? $taxonomies['prefectures'][0] : null;

// Format amount
$formatted_amount = '';
$max_amount_yen = $grant_data['max_amount_numeric'];
if ($max_amount_yen > 0) {
    if ($max_amount_yen >= 100000000) { // 1億円以上
        $formatted_amount = number_format($max_amount_yen / 100000000, 1) . '億円';
    } elseif ($max_amount_yen >= 10000) { // 1万円以上
        $formatted_amount = number_format($max_amount_yen / 10000) . '万円';
    } else {
        $formatted_amount = number_format($max_amount_yen) . '円';
    }
} elseif ($grant_data['max_amount']) {
    $formatted_amount = $grant_data['max_amount'];
}

// Deadline calculation
$deadline_info = '';
$deadline_class = '';
$days_remaining = 0;

if ($grant_data['deadline_date']) {
    $deadline_timestamp = strtotime($grant_data['deadline_date']);
    if ($deadline_timestamp && $deadline_timestamp > 0) {
        $deadline_info = date('Y年n月j日', $deadline_timestamp);
        $current_time = current_time('timestamp');
        $days_remaining = ceil(($deadline_timestamp - $current_time) / (60 * 60 * 24));
        
        if ($days_remaining <= 0) {
            $deadline_class = 'expired';
            $deadline_info .= ' (募集終了)';
        } elseif ($days_remaining <= 7) {
            $deadline_class = 'urgent';
            $deadline_info .= ' (あと' . $days_remaining . '日)';
        } elseif ($days_remaining <= 30) {
            $deadline_class = 'warning';
            $deadline_info .= ' (あと' . $days_remaining . '日)';
        }
    }
} elseif ($grant_data['deadline']) {
    $deadline_info = $grant_data['deadline'];
}

// Status mapping
$status_configs = array(
    'open' => array('label' => '募集中', 'class' => 'status-open'),
    'upcoming' => array('label' => '募集予定', 'class' => 'status-upcoming'),
    'closed' => array('label' => '募集終了', 'class' => 'status-closed'),
    'suspended' => array('label' => '一時停止', 'class' => 'status-suspended')
);
$status_data = $status_configs[$grant_data['application_status']] ?? $status_configs['open'];

// Difficulty mapping
$difficulty_configs = array(
    'easy' => array('label' => '易しい', 'class' => 'difficulty-easy'),
    'normal' => array('label' => '普通', 'class' => 'difficulty-normal'),
    'hard' => array('label' => '難しい', 'class' => 'difficulty-hard'),
    'expert' => array('label' => '専門的', 'class' => 'difficulty-expert')
);
$difficulty = $grant_data['grant_difficulty'];
$difficulty_data = $difficulty_configs[$difficulty] ?? $difficulty_configs['normal'];

// Update view count
$grant_data['views_count']++;
update_post_meta($post_id, 'views_count', $grant_data['views_count']);
?>

<style>
/* ===============================================
   STYLISH MONOCHROME GRANT TEMPLATE - PHOTO-LIKE
   スタイリッシュな白黒写真のような助成金テンプレート
   =============================================== */

* {
    box-sizing: border-box;
}

:root {
    /* Monochrome Color System - Photo-like */
    --pure-white: #ffffff;
    --paper-white: #fefefe;
    --light-gray: #f8f9fa;
    --mid-gray: #e9ecef;
    --dark-gray: #6c757d;
    --charcoal: #343a40;
    --deep-black: #212529;
    --ink-black: #000000;
    
    /* Photo-like gradients */
    --gradient-subtle: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    --gradient-strong: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    --gradient-accent: linear-gradient(135deg, #000000 0%, #343a40 100%);
    
    /* Shadow system - like photography depth */
    --shadow-soft: 0 2px 8px rgba(0, 0, 0, 0.08);
    --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.12);
    --shadow-strong: 0 8px 32px rgba(0, 0, 0, 0.16);
    --shadow-dramatic: 0 16px 64px rgba(0, 0, 0, 0.24);
    
    /* Typography - Editorial style */
    --font-display: "Noto Serif JP", Georgia, serif;
    --font-body: "Noto Sans JP", -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: "SFMono-Regular", Consolas, monospace;
}

/* Reset and base styles */
body {
    margin: 0;
    padding: 0;
    font-family: var(--font-body);
    line-height: 1.6;
    color: var(--deep-black);
    background: var(--pure-white);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Main photo-style container */
.grant-monochrome {
    max-width: 1200px;
    margin: 0 auto;
    background: var(--pure-white);
    position: relative;
    overflow: hidden;
}

/* Header with dramatic photo-style layout */
.grant-hero {
    position: relative;
    padding: 4rem 2rem;
    background: var(--gradient-subtle);
    border-bottom: 1px solid var(--mid-gray);
    overflow: hidden;
}

.grant-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(0,0,0,0.02) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(0,0,0,0.03) 0%, transparent 50%);
    pointer-events: none;
}

.grant-hero-content {
    position: relative;
    z-index: 2;
    max-width: 800px;
}

/* Status indicator - minimal and elegant */
.grant-status {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    background: var(--ink-black);
    color: var(--pure-white);
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 2rem;
    position: relative;
}

.grant-status.status-open::after {
    content: '';
    position: absolute;
    top: 50%;
    right: -0.5rem;
    width: 0.5rem;
    height: 0.5rem;
    background: #22c55e;
    border-radius: 50%;
    transform: translateY(-50%);
    animation: pulse 2s infinite;
}

.grant-status.status-urgent {
    background: var(--deep-black);
    animation: urgentPulse 1s ease-in-out infinite alternate;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes urgentPulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.02); }
}

/* Typography - Editorial magazine style */
.grant-title {
    font-family: var(--font-display);
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 700;
    line-height: 1.1;
    margin: 0 0 1.5rem 0;
    color: var(--ink-black);
    letter-spacing: -0.02em;
}

.grant-summary {
    font-size: 1.25rem;
    line-height: 1.5;
    color: var(--dark-gray);
    margin-bottom: 2.5rem;
    font-weight: 400;
}

/* Key metrics - photo frame style */
.grant-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin: 3rem 0;
    padding: 2rem;
    background: var(--paper-white);
    border: 1px solid var(--mid-gray);
    position: relative;
}

.grant-metrics::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 49%, rgba(0,0,0,0.01) 50%, transparent 51%);
    pointer-events: none;
}

.metric-item {
    text-align: center;
    padding: 1rem;
    position: relative;
}

.metric-label {
    display: block;
    font-size: 0.875rem;
    color: var(--dark-gray);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.metric-value {
    display: block;
    font-family: var(--font-display);
    font-size: 2rem;
    font-weight: 700;
    color: var(--ink-black);
    line-height: 1;
}

.metric-value.highlight {
    position: relative;
}

.metric-value.highlight::after {
    content: '';
    position: absolute;
    bottom: -0.25rem;
    left: 50%;
    width: 3rem;
    height: 2px;
    background: var(--ink-black);
    transform: translateX(-50%);
}

/* Content layout - Magazine style */
.grant-content {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 4rem;
    padding: 4rem 2rem;
    position: relative;
}

@media (max-width: 1024px) {
    .grant-content {
        grid-template-columns: 1fr;
        gap: 3rem;
        padding: 3rem 2rem;
    }
}

/* Main content sections */
.content-main {
    max-width: none;
}

.content-section {
    margin-bottom: 4rem;
    position: relative;
}

.section-title {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--ink-black);
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 1rem;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 4rem;
    height: 1px;
    background: var(--ink-black);
}

.section-content {
    font-size: 1.125rem;
    line-height: 1.7;
    color: var(--charcoal);
}

.section-content p {
    margin-bottom: 1.5rem;
}

.section-content ul, 
.section-content ol {
    padding-left: 2rem;
    margin-bottom: 1.5rem;
}

.section-content li {
    margin-bottom: 0.75rem;
}

/* Information table - Clean data presentation */
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin: 2rem 0;
    background: var(--pure-white);
    box-shadow: var(--shadow-soft);
}

.info-table th,
.info-table td {
    padding: 1.25rem 1.5rem;
    text-align: left;
    border-bottom: 1px solid var(--mid-gray);
}

.info-table th {
    background: var(--light-gray);
    font-weight: 600;
    color: var(--dark-gray);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    width: 40%;
}

.info-table td {
    font-weight: 500;
    color: var(--charcoal);
    font-size: 1rem;
}

.info-table tr:last-child th,
.info-table tr:last-child td {
    border-bottom: none;
}

.info-table .highlight {
    font-weight: 700;
    color: var(--ink-black);
}

.info-table .urgent {
    color: #dc3545;
    font-weight: 700;
}

/* Sidebar - Photo caption style */
.content-sidebar {
    position: relative;
}

.sidebar-section {
    margin-bottom: 3rem;
    padding: 2rem;
    background: var(--light-gray);
    border: 1px solid var(--mid-gray);
    position: relative;
}

.sidebar-title {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--ink-black);
    margin-bottom: 1.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 1rem;
}

/* Action buttons - Minimalist style */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    background: var(--ink-black);
    color: var(--pure-white);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    background: var(--charcoal);
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.action-btn.secondary {
    background: transparent;
    color: var(--ink-black);
    border: 2px solid var(--ink-black);
}

.action-btn.secondary:hover {
    background: var(--ink-black);
    color: var(--pure-white);
}

/* Tags and taxonomies - Film strip style */
.tags-section {
    margin-top: 2rem;
}

.tags-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
}

.tag-item {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: var(--pure-white);
    border: 1px solid var(--mid-gray);
    color: var(--charcoal);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.tag-item:hover {
    background: var(--ink-black);
    color: var(--pure-white);
    border-color: var(--ink-black);
}

/* Stats display - Dashboard style */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.stat-item {
    text-align: center;
    padding: 1.5rem 1rem;
    background: var(--pure-white);
    border: 1px solid var(--mid-gray);
}

.stat-number {
    display: block;
    font-family: var(--font-display);
    font-size: 2rem;
    font-weight: 700;
    color: var(--ink-black);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--dark-gray);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Contact info - Business card style */
.contact-card {
    background: var(--paper-white);
    border: 1px solid var(--mid-gray);
    padding: 2rem;
    margin: 2rem 0;
    position: relative;
}

.contact-card::before {
    content: '';
    position: absolute;
    top: 1rem;
    left: 1rem;
    width: 0.5rem;
    height: 0.5rem;
    background: var(--ink-black);
    border-radius: 50%;
}

/* Responsive design */
@media (max-width: 768px) {
    .grant-hero {
        padding: 3rem 1.5rem;
    }
    
    .grant-title {
        font-size: 2rem;
    }
    
    .grant-summary {
        font-size: 1.125rem;
    }
    
    .grant-metrics {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .grant-content {
        padding: 2rem 1.5rem;
    }
    
    .info-table th,
    .info-table td {
        padding: 1rem;
    }
    
    .info-table th {
        width: auto;
        display: block;
        background: var(--charcoal);
        color: var(--pure-white);
        font-size: 0.75rem;
    }
    
    .info-table td {
        display: block;
        padding: 1rem 1rem 1.5rem 1rem;
        border-bottom: 2px solid var(--mid-gray);
    }
}

/* Print styles - Clean newspaper style */
@media print {
    .grant-monochrome {
        box-shadow: none;
    }
    
    .content-sidebar,
    .action-buttons {
        display: none;
    }
    
    .grant-content {
        grid-template-columns: 1fr;
    }
    
    .grant-hero {
        background: transparent;
        border-bottom: 2px solid var(--ink-black);
    }
    
    .section-title::after {
        background: var(--ink-black);
    }
}

/* Animation for smooth loading */
.grant-monochrome {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Subtle hover effects */
.content-section {
    transition: all 0.3s ease;
}

.content-section:hover {
    transform: translateY(-2px);
}

.sidebar-section {
    transition: all 0.3s ease;
}

.sidebar-section:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-soft);
}
</style>

<main class="grant-monochrome">
    <!-- Hero Section - Photo-style header -->
    <section class="grant-hero">
        <div class="grant-hero-content">
            <!-- Status indicator -->
            <div class="grant-status <?php echo $status_data['class']; ?> <?php echo $deadline_class; ?>">
                <?php echo $status_data['label']; ?>
                <?php if ($days_remaining > 0 && $days_remaining <= 30): ?>
                    - <?php echo $days_remaining; ?>日残り
                <?php endif; ?>
            </div>
            
            <!-- Main title -->
            <h1 class="grant-title"><?php the_title(); ?></h1>
            
            <!-- AI Summary -->
            <?php if ($grant_data['ai_summary']): ?>
            <p class="grant-summary"><?php echo esc_html($grant_data['ai_summary']); ?></p>
            <?php endif; ?>
            
            <!-- Key metrics grid -->
            <div class="grant-metrics">
                <?php if ($formatted_amount): ?>
                <div class="metric-item">
                    <span class="metric-label">最大助成額</span>
                    <span class="metric-value highlight"><?php echo esc_html($formatted_amount); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($grant_data['subsidy_rate']): ?>
                <div class="metric-item">
                    <span class="metric-label">補助率</span>
                    <span class="metric-value"><?php echo esc_html($grant_data['subsidy_rate']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($deadline_info): ?>
                <div class="metric-item">
                    <span class="metric-label">申請締切</span>
                    <span class="metric-value <?php echo $deadline_class === 'urgent' ? 'urgent' : ''; ?>">
                        <?php echo esc_html($deadline_info); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($grant_data['grant_success_rate'] > 0): ?>
                <div class="metric-item">
                    <span class="metric-label">採択率</span>
                    <span class="metric-value"><?php echo $grant_data['grant_success_rate']; ?>%</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Main content grid -->
    <div class="grant-content">
        <!-- Main content area -->
        <div class="content-main">
            <!-- Main content -->
            <section class="content-section">
                <h2 class="section-title">詳細情報</h2>
                <div class="section-content">
                    <?php the_content(); ?>
                </div>
            </section>
            
            <!-- Information table -->
            <section class="content-section">
                <h2 class="section-title">基本情報</h2>
                <div class="section-content">
                    <table class="info-table">
                        <?php if ($grant_data['organization']): ?>
                        <tr>
                            <th>実施機関</th>
                            <td><?php echo esc_html($grant_data['organization']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($formatted_amount): ?>
                        <tr>
                            <th>助成額</th>
                            <td class="highlight"><?php echo esc_html($formatted_amount); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($grant_data['subsidy_rate']): ?>
                        <tr>
                            <th>補助率</th>
                            <td><?php echo esc_html($grant_data['subsidy_rate']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($deadline_info): ?>
                        <tr>
                            <th>申請締切</th>
                            <td class="<?php echo $deadline_class === 'urgent' ? 'urgent' : ''; ?>">
                                <?php echo esc_html($deadline_info); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($grant_data['application_method']): ?>
                        <tr>
                            <th>申請方法</th>
                            <td>
                                <?php
                                $method_labels = array(
                                    'online' => 'オンライン申請',
                                    'mail' => '郵送申請',
                                    'visit' => '窓口申請',
                                    'mixed' => 'オンライン・郵送併用'
                                );
                                echo $method_labels[$grant_data['application_method']] ?? esc_html($grant_data['application_method']);
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($grant_data['grant_success_rate'] > 0): ?>
                        <tr>
                            <th>採択率</th>
                            <td class="highlight"><?php echo $grant_data['grant_success_rate']; ?>%</td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($grant_data['grant_difficulty'] !== 'normal'): ?>
                        <tr>
                            <th>申請難易度</th>
                            <td><?php echo $difficulty_data['label']; ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </section>
            
            <?php if ($grant_data['grant_target']): ?>
            <!-- Target details -->
            <section class="content-section">
                <h2 class="section-title">対象者・対象事業</h2>
                <div class="section-content">
                    <?php echo wp_kses_post($grant_data['grant_target']); ?>
                </div>
            </section>
            <?php endif; ?>
            
            <?php if ($grant_data['contact_info']): ?>
            <!-- Contact information -->
            <section class="content-section">
                <h2 class="section-title">お問い合わせ先</h2>
                <div class="section-content">
                    <div class="contact-card">
                        <?php echo nl2br(esc_html($grant_data['contact_info'])); ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <aside class="content-sidebar">
            <!-- Action buttons -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">申請・詳細</h3>
                <div class="action-buttons">
                    <?php if ($grant_data['official_url']): ?>
                    <a href="<?php echo esc_url($grant_data['official_url']); ?>" class="action-btn" target="_blank" rel="noopener">
                        公式サイトで申請
                    </a>
                    <?php endif; ?>
                    
                    <button class="action-btn secondary" onclick="toggleBookmark(<?php echo $post_id; ?>)">
                        ブックマーク
                    </button>
                    
                    <button class="action-btn secondary" onclick="shareGrant()">
                        シェア
                    </button>
                    
                    <button class="action-btn secondary" onclick="window.print()">
                        印刷
                    </button>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">統計情報</h3>
                <div class="stats-grid">
                    <?php if ($grant_data['grant_success_rate'] > 0): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $grant_data['grant_success_rate']; ?>%</span>
                        <span class="stat-label">採択率</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($grant_data['views_count']); ?></span>
                        <span class="stat-label">閲覧数</span>
                    </div>
                    
                    <?php if ($days_remaining > 0): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $days_remaining; ?></span>
                        <span class="stat-label">残り日数</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $difficulty_data['label']; ?></span>
                        <span class="stat-label">申請難易度</span>
                    </div>
                </div>
            </div>
            
            <!-- Tags and categories -->
            <?php if ($taxonomies['categories'] || $taxonomies['prefectures'] || $taxonomies['municipalities'] || $taxonomies['tags']): ?>
            <div class="sidebar-section">
                <h3 class="sidebar-title">関連タグ</h3>
                <div class="tags-section">
                    <?php if ($taxonomies['categories'] && !is_wp_error($taxonomies['categories'])): ?>
                        <div class="tags-grid">
                            <?php foreach ($taxonomies['categories'] as $category): ?>
                            <a href="<?php echo get_term_link($category); ?>" class="tag-item">
                                <?php echo esc_html($category->name); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($taxonomies['prefectures'] && !is_wp_error($taxonomies['prefectures'])): ?>
                        <div class="tags-grid" style="margin-top: 1rem;">
                            <?php foreach ($taxonomies['prefectures'] as $prefecture): ?>
                            <a href="<?php echo get_term_link($prefecture); ?>" class="tag-item">
                                📍 <?php echo esc_html($prefecture->name); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($taxonomies['municipalities'] && !is_wp_error($taxonomies['municipalities'])): ?>
                        <div class="tags-grid" style="margin-top: 1rem;">
                            <?php foreach ($taxonomies['municipalities'] as $municipality): ?>
                            <a href="<?php echo get_term_link($municipality); ?>" class="tag-item">
                                🏘️ <?php echo esc_html($municipality->name); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($taxonomies['tags'] && !is_wp_error($taxonomies['tags'])): ?>
                        <div class="tags-grid" style="margin-top: 1rem;">
                            <?php foreach ($taxonomies['tags'] as $tag): ?>
                            <a href="<?php echo get_term_link($tag); ?>" class="tag-item">
                                # <?php echo esc_html($tag->name); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<script>
// Minimal, elegant functionality
function toggleBookmark(postId) {
    const button = event.target;
    const isBookmarked = button.textContent.includes('済み');
    
    if (isBookmarked) {
        button.textContent = 'ブックマーク';
        button.classList.remove('bookmarked');
    } else {
        button.textContent = 'ブックマーク済み';
        button.classList.add('bookmarked');
    }
    
    // Add your bookmark logic here
    console.log('Toggle bookmark for post:', postId);
}

function shareGrant() {
    const title = document.title;
    const url = window.location.href;
    const text = '<?php echo esc_js(wp_trim_words($grant_data["ai_summary"] ?: get_the_excerpt(), 20, "")); ?>';
    
    if (navigator.share) {
        navigator.share({
            title: title,
            text: text,
            url: url
        }).catch(err => console.log('Share error:', err));
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('URLをコピーしました');
        });
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Smooth reveal animation
    const sections = document.querySelectorAll('.content-section, .sidebar-section');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });
    
    sections.forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'all 0.6s ease';
        observer.observe(section);
    });
});
</script>

<?php get_footer(); ?>