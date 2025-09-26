<?php
/**
 * Grant Single Page - Stylish Monochrome Design
 * 助成金詳細ページ - スタイリッシュモノクロームデザイン
 * 
 * @package Grant_Insight_Perfect
 * @version 11.0.0-stylish
 */

get_header();

// Security and post validation
if (!have_posts()) {
    wp_redirect(home_url('/404'));
    exit;
}

the_post();
$post_id = get_the_ID();

// Comprehensive ACF field retrieval
$grant_data = array(
    // 基本情報
    'organization' => get_field('organization', $post_id) ?: '',
    'organization_type' => get_field('organization_type', $post_id) ?: '',
    
    // 金額情報
    'max_amount' => get_field('max_amount', $post_id) ?: '',
    'max_amount_numeric' => intval(get_field('max_amount_numeric', $post_id)),
    'min_amount' => intval(get_field('min_amount', $post_id)),
    'subsidy_rate' => get_field('subsidy_rate', $post_id) ?: '',
    'amount_note' => get_field('amount_note', $post_id) ?: '',
    
    // 期間・締切情報
    'deadline' => get_field('deadline', $post_id) ?: '',
    'deadline_date' => get_field('deadline_date', $post_id) ?: '',
    'application_period' => get_field('application_period', $post_id) ?: '',
    'deadline_note' => get_field('deadline_note', $post_id) ?: '',
    'application_status' => get_field('application_status', $post_id) ?: 'open',
    
    // 対象・条件
    'grant_target' => get_field('grant_target', $post_id) ?: '',
    'eligible_expenses' => get_field('eligible_expenses', $post_id) ?: '',
    'grant_difficulty' => get_field('grant_difficulty', $post_id) ?: 'normal',
    'grant_success_rate' => intval(get_field('grant_success_rate', $post_id)),
    'required_documents' => get_field('required_documents', $post_id) ?: '',
    
    // 申請・連絡先
    'application_method' => get_field('application_method', $post_id) ?: '',
    'contact_info' => get_field('contact_info', $post_id) ?: '',
    'official_url' => get_field('official_url', $post_id) ?: '',
    'external_link' => get_field('external_link', $post_id) ?: '',
    
    // 管理設定
    'is_featured' => get_field('is_featured', $post_id) ?: false,
    'views_count' => intval(get_field('views_count', $post_id)),
    'last_updated' => get_field('last_updated', $post_id) ?: '',
    
    // AI関連
    'ai_summary' => get_field('ai_summary', $post_id) ?: get_post_meta($post_id, 'ai_summary', true),
);

// Comprehensive taxonomy data
$taxonomies = array(
    'categories' => get_the_terms($post_id, 'grant_category'),
    'prefectures' => get_the_terms($post_id, 'grant_prefecture'),
    'municipalities' => get_the_terms($post_id, 'grant_municipality'),
    'tags' => get_the_tags($post_id),
);

$main_category = ($taxonomies['categories'] && !is_wp_error($taxonomies['categories'])) ? $taxonomies['categories'][0] : null;
$main_prefecture = ($taxonomies['prefectures'] && !is_wp_error($taxonomies['prefectures'])) ? $taxonomies['prefectures'][0] : null;

// Format amounts
$formatted_amount = '';
$max_amount_yen = $grant_data['max_amount_numeric'];
if ($max_amount_yen > 0) {
    if ($max_amount_yen >= 100000000) {
        $formatted_amount = number_format($max_amount_yen / 100000000, 1) . '億円';
    } elseif ($max_amount_yen >= 10000) {
        $formatted_amount = number_format($max_amount_yen / 10000) . '万円';
    } else {
        $formatted_amount = number_format($max_amount_yen) . '円';
    }
} elseif ($grant_data['max_amount']) {
    $formatted_amount = $grant_data['max_amount'];
}

// Organization type mapping
$org_type_labels = array(
    'national' => '国（省庁）',
    'prefecture' => '都道府県',
    'city' => '市区町村', 
    'public_org' => '公的機関',
    'private_org' => '民間団体',
    'foundation' => '財団法人',
    'jgrants' => 'Jグランツ',
    'other' => 'その他'
);

// Application method mapping
$method_labels = array(
    'online' => 'オンライン申請',
    'mail' => '郵送申請',
    'visit' => '窓口申請',
    'mixed' => 'オンライン・郵送併用'
);

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

// Difficulty configuration
$difficulty_configs = array(
    'easy' => array('label' => '易しい', 'dots' => 1),
    'normal' => array('label' => '普通', 'dots' => 2),
    'hard' => array('label' => '難しい', 'dots' => 3),
    'expert' => array('label' => '専門的', 'dots' => 4)
);
$difficulty = $grant_data['grant_difficulty'];
$difficulty_data = $difficulty_configs[$difficulty] ?? $difficulty_configs['normal'];

// Status mapping
$status_configs = array(
    'open' => array('label' => '募集中', 'class' => 'open'),
    'upcoming' => array('label' => '募集予定', 'class' => 'upcoming'),
    'closed' => array('label' => '募集終了', 'class' => 'closed'),
    'suspended' => array('label' => '一時停止', 'class' => 'suspended')
);
$status_data = $status_configs[$grant_data['application_status']] ?? $status_configs['open'];

// Update view count
$grant_data['views_count']++;
update_post_meta($post_id, 'views_count', $grant_data['views_count']);
?>

<style>
/* ===============================================
   STYLISH MONOCHROME GRANT SINGLE PAGE
   =============================================== */

:root {
    /* Monochrome Color Palette - Photo-like */
    --mono-black: #000000;
    --mono-charcoal: #1a1a1a;
    --mono-dark-gray: #2d2d2d;
    --mono-gray: #4a4a4a;
    --mono-mid-gray: #6b6b6b;
    --mono-light-gray: #9a9a9a;
    --mono-pale-gray: #d4d4d4;
    --mono-off-white: #f8f8f8;
    --mono-white: #ffffff;
    
    /* Accent colors for status */
    --accent-danger: #dc2626;
    --accent-warning: #f59e0b;
    --accent-success: #059669;
    --accent-info: #2563eb;
    
    /* Typography scale */
    --text-xs: 0.75rem;
    --text-sm: 0.875rem;
    --text-base: 1rem;
    --text-lg: 1.125rem;
    --text-xl: 1.25rem;
    --text-2xl: 1.5rem;
    --text-3xl: 1.875rem;
    --text-4xl: 2.25rem;
    --text-5xl: 3rem;
    
    /* Spacing scale */
    --space-1: 0.25rem;
    --space-2: 0.5rem;
    --space-3: 0.75rem;
    --space-4: 1rem;
    --space-5: 1.25rem;
    --space-6: 1.5rem;
    --space-8: 2rem;
    --space-10: 2.5rem;
    --space-12: 3rem;
    --space-16: 4rem;
    --space-20: 5rem;
    
    /* Shadows - Photo-like depth */
    --shadow-soft: 0 2px 15px rgba(0, 0, 0, 0.08);
    --shadow-medium: 0 4px 25px rgba(0, 0, 0, 0.12);
    --shadow-hard: 0 10px 40px rgba(0, 0, 0, 0.15);
    --shadow-dramatic: 0 20px 60px rgba(0, 0, 0, 0.25);
    
    /* Border radius */
    --radius-sm: 0.25rem;
    --radius-base: 0.5rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
    --radius-2xl: 2rem;
    
    /* Transitions */
    --transition-fast: 0.15s ease-out;
    --transition-base: 0.3s ease-out;
    --transition-slow: 0.5s ease-out;
}

/* Reset and base styles */
* {
    box-sizing: border-box;
}

/* Main container with photo-like styling */
.grant-stylish {
    max-width: 1200px;
    margin: 0 auto;
    padding: var(--space-8) var(--space-4);
    background: var(--mono-white);
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    line-height: 1.6;
    color: var(--mono-charcoal);
    position: relative;
}

@media (min-width: 768px) {
    .grant-stylish {
        padding: var(--space-16) var(--space-8);
    }
}

/* Film strip header effect */
.grant-stylish::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: repeating-linear-gradient(
        90deg,
        var(--mono-black) 0px,
        var(--mono-black) 10px,
        var(--mono-white) 10px,
        var(--mono-white) 20px
    );
    z-index: 1;
}

/* Hero Section - Magazine style */
.grant-hero {
    text-align: center;
    padding: var(--space-16) 0;
    background: linear-gradient(135deg, var(--mono-off-white) 0%, var(--mono-white) 100%);
    margin: 0 calc(-1 * var(--space-8)) var(--space-12);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-soft);
    position: relative;
}

@media (min-width: 768px) {
    .grant-hero {
        margin: 0 calc(-1 * var(--space-16)) var(--space-16);
        padding: var(--space-20) var(--space-8);
    }
}

/* Status badge - Polaroid style */
.status-badge {
    display: inline-block;
    padding: var(--space-2) var(--space-4);
    background: var(--mono-black);
    color: var(--mono-white);
    font-size: var(--text-sm);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-radius: var(--radius-base);
    margin-bottom: var(--space-6);
    box-shadow: var(--shadow-soft);
    position: relative;
}

.status-badge.open {
    background: var(--accent-success);
}

.status-badge.warning {
    background: var(--accent-warning);
}

.status-badge.urgent {
    background: var(--accent-danger);
}

.status-badge.closed {
    background: var(--mono-gray);
}

/* Typography - Editorial style */
.grant-title {
    font-size: var(--text-3xl);
    font-weight: 800;
    line-height: 1.2;
    color: var(--mono-black);
    margin: 0 0 var(--space-6);
    letter-spacing: -0.02em;
}

@media (min-width: 768px) {
    .grant-title {
        font-size: var(--text-5xl);
    }
}

.grant-subtitle {
    font-size: var(--text-lg);
    color: var(--mono-gray);
    margin-bottom: var(--space-8);
    font-weight: 400;
    line-height: 1.5;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

/* Key Information Grid - Newspaper layout */
.key-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-6);
    margin-bottom: var(--space-16);
}

.info-card {
    background: var(--mono-white);
    border: 2px solid var(--mono-pale-gray);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    text-align: center;
    transition: all var(--transition-base);
    position: relative;
    overflow: hidden;
}

.info-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left var(--transition-slow);
}

.info-card:hover {
    border-color: var(--mono-black);
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.info-card:hover::before {
    left: 100%;
}

.info-icon {
    width: 48px;
    height: 48px;
    background: var(--mono-black);
    color: var(--mono-white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-4);
    font-size: var(--text-xl);
}

.info-label {
    font-size: var(--text-xs);
    color: var(--mono-mid-gray);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.1em;
    margin-bottom: var(--space-2);
}

.info-value {
    font-size: var(--text-2xl);
    font-weight: 700;
    color: var(--mono-black);
    line-height: 1.2;
}

.info-value.highlight {
    background: linear-gradient(135deg, var(--mono-black), var(--mono-dark-gray));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Content Layout - Magazine columns */
.content-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--space-12);
    align-items: start;
}

@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
        gap: var(--space-8);
    }
}

/* Main content sections */
.content-main {
    display: flex;
    flex-direction: column;
    gap: var(--space-10);
}

.content-section {
    background: var(--mono-white);
    border-radius: var(--radius-lg);
    padding: var(--space-8);
    box-shadow: var(--shadow-soft);
    border-left: 4px solid var(--mono-black);
    position: relative;
}

.section-header {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
    padding-bottom: var(--space-4);
    border-bottom: 1px solid var(--mono-pale-gray);
}

.section-icon {
    width: 32px;
    height: 32px;
    color: var(--mono-black);
}

.section-title {
    font-size: var(--text-xl);
    font-weight: 700;
    color: var(--mono-black);
    margin: 0;
}

.section-content {
    color: var(--mono-charcoal);
    line-height: 1.7;
}

.section-content p {
    margin-bottom: var(--space-4);
}

.section-content ul,
.section-content ol {
    margin: var(--space-4) 0;
    padding-left: var(--space-6);
}

.section-content li {
    margin-bottom: var(--space-2);
}

/* Information table - Technical specs style */
.info-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--mono-white);
    border-radius: var(--radius-base);
    overflow: hidden;
    box-shadow: var(--shadow-soft);
}

.info-table th,
.info-table td {
    padding: var(--space-4) var(--space-5);
    text-align: left;
    border-bottom: 1px solid var(--mono-pale-gray);
}

.info-table th {
    background: var(--mono-off-white);
    font-weight: 600;
    color: var(--mono-dark-gray);
    font-size: var(--text-sm);
    width: 35%;
}

.info-table td {
    font-weight: 500;
    color: var(--mono-charcoal);
}

.info-table tr:hover {
    background: var(--mono-off-white);
}

.info-table tr:last-child th,
.info-table tr:last-child td {
    border-bottom: none;
}

/* Sidebar - Vintage photo frame style */
.sidebar {
    position: sticky;
    top: var(--space-8);
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
}

.sidebar-card {
    background: var(--mono-white);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    box-shadow: var(--shadow-medium);
    border: 1px solid var(--mono-pale-gray);
}

.sidebar-title {
    font-size: var(--text-lg);
    font-weight: 700;
    color: var(--mono-black);
    margin-bottom: var(--space-5);
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

/* Action buttons - Film camera style */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-3);
    padding: var(--space-4) var(--space-6);
    border-radius: var(--radius-base);
    text-decoration: none;
    font-weight: 600;
    font-size: var(--text-sm);
    transition: all var(--transition-base);
    border: none;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.btn-primary {
    background: var(--mono-black);
    color: var(--mono-white);
    box-shadow: var(--shadow-soft);
}

.btn-primary:hover {
    background: var(--mono-charcoal);
    transform: translateY(-1px);
    box-shadow: var(--shadow-medium);
}

.btn-secondary {
    background: transparent;
    color: var(--mono-charcoal);
    border: 2px solid var(--mono-pale-gray);
}

.btn-secondary:hover {
    border-color: var(--mono-black);
    background: var(--mono-off-white);
}

/* Statistics - Darkroom timer style */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-4);
}

.stat-item {
    text-align: center;
    padding: var(--space-4);
    background: var(--mono-off-white);
    border-radius: var(--radius-base);
    border: 1px solid var(--mono-pale-gray);
    transition: transform var(--transition-base);
}

.stat-item:hover {
    transform: scale(1.02);
}

.stat-number {
    font-size: var(--text-2xl);
    font-weight: 800;
    color: var(--mono-black);
    display: block;
    line-height: 1;
}

.stat-label {
    font-size: var(--text-xs);
    color: var(--mono-mid-gray);
    margin-top: var(--space-1);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Difficulty indicator - Film grain effect */
.difficulty-indicator {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.difficulty-dots {
    display: flex;
    gap: var(--space-1);
}

.difficulty-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--mono-pale-gray);
}

.difficulty-dot.filled {
    background: var(--mono-black);
}

/* Tags - Contact sheet style */
.tags-section {
    margin-top: var(--space-5);
}

.tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}

.tag {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    background: var(--mono-off-white);
    color: var(--mono-dark-gray);
    border: 1px solid var(--mono-pale-gray);
    border-radius: var(--radius-base);
    font-size: var(--text-xs);
    text-decoration: none;
    transition: all var(--transition-fast);
    font-weight: 500;
}

.tag:hover {
    background: var(--mono-black);
    color: var(--mono-white);
    transform: translateY(-1px);
}

/* Progress bar - Film loading effect */
.progress-bar {
    width: 100%;
    height: 4px;
    background: var(--mono-pale-gray);
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-top: var(--space-2);
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--mono-black), var(--mono-dark-gray));
    border-radius: var(--radius-sm);
    transition: width 0.8s ease-out;
    position: relative;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .grant-stylish {
        padding: var(--space-6) var(--space-4);
    }
    
    .grant-hero {
        margin: 0 calc(-1 * var(--space-4)) var(--space-8);
        padding: var(--space-12) var(--space-4);
    }
    
    .grant-title {
        font-size: var(--text-2xl);
    }
    
    .key-info-grid {
        grid-template-columns: 1fr;
        gap: var(--space-4);
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .sidebar {
        position: static;
    }
}

/* Print styles - High contrast */
@media print {
    .grant-stylish {
        background: white;
        color: black;
        box-shadow: none;
    }
    
    .sidebar {
        display: none;
    }
    
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .btn {
        display: none;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --mono-black: #ffffff;
        --mono-white: #000000;
        --mono-charcoal: #e5e5e5;
        --mono-off-white: #0a0a0a;
        --mono-pale-gray: #2d2d2d;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .info-card {
        border-width: 3px;
    }
    
    .btn {
        border-width: 2px;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<main class="grant-stylish">
    <!-- Hero Section -->
    <header class="grant-hero">
        <div class="status-badge <?php echo $status_data['class']; ?> <?php echo $deadline_class; ?>">
            <?php echo $status_data['label']; ?>
            <?php if ($days_remaining > 0 && $days_remaining <= 30): ?>
                · <?php echo $days_remaining; ?>日
            <?php endif; ?>
        </div>
        
        <h1 class="grant-title"><?php the_title(); ?></h1>
        
        <?php if ($grant_data['ai_summary']): ?>
        <p class="grant-subtitle"><?php echo esc_html(wp_trim_words($grant_data['ai_summary'], 30, '...')); ?></p>
        <?php endif; ?>
        
        <!-- Key Information Grid -->
        <div class="key-info-grid">
            <?php if ($formatted_amount): ?>
            <div class="info-card">
                <div class="info-icon">¥</div>
                <div class="info-label">最大助成額</div>
                <div class="info-value highlight"><?php echo esc_html($formatted_amount); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($deadline_info): ?>
            <div class="info-card">
                <div class="info-icon">📅</div>
                <div class="info-label">申請締切</div>
                <div class="info-value <?php echo $deadline_class === 'urgent' ? 'urgent' : ''; ?>">
                    <?php echo esc_html($deadline_info); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($grant_data['grant_success_rate'] > 0): ?>
            <div class="info-card">
                <div class="info-icon">📊</div>
                <div class="info-label">採択率</div>
                <div class="info-value"><?php echo $grant_data['grant_success_rate']; ?>%</div>
            </div>
            <?php endif; ?>
            
            <?php if ($grant_data['organization']): ?>
            <div class="info-card">
                <div class="info-icon">🏢</div>
                <div class="info-label">実施機関</div>
                <div class="info-value" style="font-size: var(--text-lg);"><?php echo esc_html($grant_data['organization']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Main Content Layout -->
    <div class="content-layout">
        <!-- Main Content -->
        <div class="content-main">
            <!-- Main Content Section -->
            <section class="content-section">
                <header class="section-header">
                    <div class="section-icon">📄</div>
                    <h2 class="section-title">詳細情報</h2>
                </header>
                <div class="section-content">
                    <?php the_content(); ?>
                </div>
            </section>
            
            <!-- Detailed Information Table -->
            <section class="content-section">
                <header class="section-header">
                    <div class="section-icon">📋</div>
                    <h2 class="section-title">助成金詳細</h2>
                </header>
                <div class="section-content">
                    <table class="info-table">
                        <?php if ($grant_data['organization']): ?>
                        <tr>
                            <th>実施機関</th>
                            <td>
                                <?php echo esc_html($grant_data['organization']); ?>
                                <?php if ($grant_data['organization_type']): ?>
                                    <br><small style="color: var(--mono-mid-gray);"><?php echo $org_type_labels[$grant_data['organization_type']] ?? $grant_data['organization_type']; ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($formatted_amount): ?>
                        <tr>
                            <th>助成額</th>
                            <td><strong><?php echo esc_html($formatted_amount); ?></strong></td>
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
                            <td><?php echo esc_html($deadline_info); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($grant_data['application_method']): ?>
                        <tr>
                            <th>申請方法</th>
                            <td><?php echo $method_labels[$grant_data['application_method']] ?? esc_html($grant_data['application_method']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($difficulty !== 'normal'): ?>
                        <tr>
                            <th>申請難易度</th>
                            <td>
                                <div class="difficulty-indicator">
                                    <?php echo $difficulty_data['label']; ?>
                                    <div class="difficulty-dots">
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <div class="difficulty-dot <?php echo $i <= $difficulty_data['dots'] ? 'filled' : ''; ?>"></div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <th>閲覧数</th>
                            <td><?php echo number_format($grant_data['views_count']); ?> 回</td>
                        </tr>
                    </table>
                </div>
            </section>
            
            <?php if ($grant_data['grant_target']): ?>
            <!-- Target Details -->
            <section class="content-section">
                <header class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">対象者・対象事業</h2>
                </header>
                <div class="section-content">
                    <?php echo wp_kses_post($grant_data['grant_target']); ?>
                </div>
            </section>
            <?php endif; ?>
            
            <?php if ($grant_data['eligible_expenses']): ?>
            <!-- Eligible Expenses -->
            <section class="content-section">
                <header class="section-header">
                    <div class="section-icon">💰</div>
                    <h2 class="section-title">対象経費</h2>
                </header>
                <div class="section-content">
                    <?php echo wp_kses_post($grant_data['eligible_expenses']); ?>
                </div>
            </section>
            <?php endif; ?>
            
            <?php if ($grant_data['required_documents']): ?>
            <!-- Required Documents -->
            <section class="content-section">
                <header class="section-header">
                    <div class="section-icon">📝</div>
                    <h2 class="section-title">必要書類</h2>
                </header>
                <div class="section-content">
                    <?php echo wp_kses_post($grant_data['required_documents']); ?>
                </div>
            </section>
            <?php endif; ?>
            
            <?php if ($grant_data['contact_info']): ?>
            <!-- Contact Information -->
            <section class="content-section">
                <header class="section-header">
                    <div class="section-icon">📞</div>
                    <h2 class="section-title">お問い合わせ先</h2>
                </header>
                <div class="section-content">
                    <div style="background: var(--mono-off-white); padding: var(--space-5); border-radius: var(--radius-base); border-left: 4px solid var(--mono-black);">
                        <?php echo nl2br(esc_html($grant_data['contact_info'])); ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Action Buttons -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    🚀 アクション
                </h3>
                <div class="action-buttons">
                    <?php if ($grant_data['official_url']): ?>
                    <a href="<?php echo esc_url($grant_data['official_url']); ?>" class="btn btn-primary" target="_blank" rel="noopener">
                        🔗 公式サイトで申請
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-secondary" onclick="toggleFavorite(<?php echo $post_id; ?>)">
                        ❤️ お気に入りに追加
                    </button>
                    
                    <button class="btn btn-secondary" onclick="shareGrant()">
                        📤 この助成金をシェア
                    </button>
                    
                    <button class="btn btn-secondary" onclick="window.print()">
                        🖨️ 印刷用ページ
                    </button>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    📊 統計情報
                </h3>
                <div class="stats-grid">
                    <?php if ($grant_data['grant_success_rate'] > 0): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $grant_data['grant_success_rate']; ?>%</span>
                        <span class="stat-label">採択率</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $grant_data['grant_success_rate']; ?>%"></div>
                        </div>
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
                        <span class="stat-number"><?php echo $difficulty_data['dots']; ?>/4</span>
                        <span class="stat-label">難易度</span>
                    </div>
                </div>
            </div>
            
            <!-- Tags and Taxonomies -->
            <?php if ($taxonomies['categories'] || $taxonomies['prefectures'] || $taxonomies['tags']): ?>
            <div class="sidebar-card">
                <h3 class="sidebar-title">
                    🏷️ 関連分類
                </h3>
                
                <?php if ($taxonomies['categories'] && !is_wp_error($taxonomies['categories'])): ?>
                <div class="tags-section">
                    <h4 style="margin-bottom: var(--space-3); color: var(--mono-mid-gray); font-size: var(--text-sm);">カテゴリー</h4>
                    <div class="tags-list">
                        <?php foreach ($taxonomies['categories'] as $category): ?>
                        <a href="<?php echo get_term_link($category); ?>" class="tag">
                            🏷️ <?php echo esc_html($category->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($taxonomies['prefectures'] && !is_wp_error($taxonomies['prefectures'])): ?>
                <div class="tags-section">
                    <h4 style="margin: var(--space-4) 0 var(--space-3) 0; color: var(--mono-mid-gray); font-size: var(--text-sm);">対象地域</h4>
                    <div class="tags-list">
                        <?php foreach ($taxonomies['prefectures'] as $prefecture): ?>
                        <a href="<?php echo get_term_link($prefecture); ?>" class="tag">
                            📍 <?php echo esc_html($prefecture->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($taxonomies['municipalities'] && !is_wp_error($taxonomies['municipalities'])): ?>
                <div class="tags-section">
                    <h4 style="margin: var(--space-4) 0 var(--space-3) 0; color: var(--mono-mid-gray); font-size: var(--text-sm);">市町村</h4>
                    <div class="tags-list">
                        <?php foreach ($taxonomies['municipalities'] as $municipality): ?>
                        <a href="<?php echo get_term_link($municipality); ?>" class="tag">
                            🏘️ <?php echo esc_html($municipality->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($taxonomies['tags'] && !is_wp_error($taxonomies['tags'])): ?>
                <div class="tags-section">
                    <h4 style="margin: var(--space-4) 0 var(--space-3) 0; color: var(--mono-mid-gray); font-size: var(--text-sm);">タグ</h4>
                    <div class="tags-list">
                        <?php foreach ($taxonomies['tags'] as $tag): ?>
                        <a href="<?php echo get_term_link($tag); ?>" class="tag">
                            # <?php echo esc_html($tag->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<script>
// Enhanced functionality for stylish design
function toggleFavorite(postId) {
    const button = event.target.closest('.btn');
    
    // Visual feedback
    button.style.transform = 'scale(0.95)';
    setTimeout(() => {
        button.style.transform = '';
        button.innerHTML = '💖 お気に入り登録済み';
        button.style.background = 'var(--accent-danger)';
        button.style.color = 'white';
    }, 100);
    
    console.log('Toggle favorite for post:', postId);
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
        }).catch(err => console.log('Error sharing:', err));
    } else {
        navigator.clipboard.writeText(url).then(() => {
            // Visual feedback
            const button = event.target.closest('.btn');
            const originalText = button.innerHTML;
            button.innerHTML = '✅ URLをコピーしました！';
            button.style.background = 'var(--accent-success)';
            button.style.color = 'white';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '';
                button.style.color = '';
            }, 2000);
        }).catch(err => {
            alert('URLのコピーに失敗しました');
        });
    }
}

// Initialize page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.progress-fill').forEach((bar, index) => {
            const width = bar.style.width;
            bar.style.width = '0';
            setTimeout(() => {
                bar.style.width = width;
            }, 300 + (index * 100));
        });
    }, 500);
    
    // Add intersection observer for animations
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                }
            });
        }, {
            threshold: 0.1
        });
        
        document.querySelectorAll('.content-section, .sidebar-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            observer.observe(el);
        });
    }
    
    // Add CSS animation keyframes
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
});

// Enhanced print functionality
window.addEventListener('beforeprint', function() {
    document.body.style.background = 'white';
});

window.addEventListener('afterprint', function() {
    document.body.style.background = '';
});
</script>

<?php get_footer(); ?>