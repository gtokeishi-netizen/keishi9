<?php
/**
 * Grant Insight Perfect - Functions File Loader (Cleanup Edition)
 * 
 * ファイル整理により不要ファイルを削除、8個に整理
 * - 重複ファイル削除（ajax-functions系の3ファイル → 1ファイル）
 * - ファイル名をわかりやすくリネーム
 * - 機能別にファイルを整理・最適化
 * 
 * @package Grant_Insight_Perfect
 * @version 8.1.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// テーマバージョン定数（ファイル整理版）
if (!defined('GI_THEME_VERSION')) {
    define('GI_THEME_VERSION', '8.1.0');
}
if (!defined('GI_THEME_PREFIX')) {
    define('GI_THEME_PREFIX', 'gi_');
}

// 機能ファイルの読み込み
$inc_dir = get_template_directory() . '/inc/';

// 論理的なディレクトリ構造でファイルを整理
$required_files = array(
    // Core foundation files
    'core/theme-foundation.php',     // テーマ設定、投稿タイプ、タクソノミー
    'core/data-processing.php',      // データ処理・ヘルパー関数
    
    // Admin interface files  
    'admin/admin-customization.php',  // 管理画面カスタマイズ
    'admin/fields-configuration.php', // ACF設定とフィールド定義
    'admin/sheets-admin-ui.php',     // Google Sheets管理UI
    'admin/post-metaboxes.php',      // 投稿編集画面メタボックス
    
    // Feature files
    'features/card-rendering.php',       // カードレンダリング・表示機能
    'features/ajax-handlers.php',        // AJAX処理
    'features/search-integration.php',   // AI機能・検索履歴
    'features/enhanced-ai-generator.php', // 高度なAI生成機能
    'features/google-sheets-sync.php',   // Google Sheets統合
    'features/sheets-webhook.php',       // Webhook処理
    'features/sheets-initializer.php'    // スプレッドシート初期化
);

// 各ファイルを安全に読み込み
foreach ($required_files as $file) {
    $file_path = $inc_dir . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // デバッグモードの場合はエラーログに記録
        if (defined('WP_DEBUG') && WP_DEBUG) {

        }
    }
}

// 統一カードレンダラーは display-functions.php に統合済み
// テンプレートファイルのチェック
$card_unified_path = get_template_directory() . '/template-parts/grant-card-unified.php';
if (file_exists($card_unified_path)) {
    require_once $card_unified_path;
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {

    }
}

// グローバルで使えるヘルパー関数
if (!function_exists('gi_render_card')) {
    function gi_render_card($post_id, $view = 'grid') {
        if (class_exists('GrantCardRenderer')) {
            $renderer = GrantCardRenderer::getInstance();
            return $renderer->render($post_id, $view);
        }
        
        // フォールバック
        return '<div class="grant-card-error">カードレンダラーが利用できません</div>';
    }
}

/**
 * テーマの最終初期化
 */
function gi_final_init() {  // ✅ 修正
    if (defined('WP_DEBUG') && WP_DEBUG) {

    }
}
add_action('wp_loaded', 'gi_final_init', 999);

// REMOVED: Duplicate Google Sheets admin menu registration
// The SheetsAdminUI class handles menu registration properly
// This duplicate registration was causing Fatal errors

// REMOVED: Duplicate Google Sheets admin page content function
// The SheetsAdminUI class provides the proper admin interface
// This duplicate function was conflicting with the class-based approach

// Excel管理機能は削除済み - 権限バイパスコードも不要



// 以下のコードはそのまま...


/**
 * クリーンアップ処理
 */
function gi_theme_cleanup() {
    // オプションの削除
    delete_option('gi_login_attempts');
    
    // モバイル最適化キャッシュのクリア
    delete_option('gi_mobile_cache');
    
    // トランジェントのクリア
    delete_transient('gi_site_stats_v2');
    
    // オブジェクトキャッシュのフラッシュ（存在する場合のみ）
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}
add_action('switch_theme', 'gi_theme_cleanup');



/**
 * スクリプトにdefer属性を追加（改善版）
 */
if (!function_exists('gi_add_defer_attribute')) {
    function gi_add_defer_attribute($tag, $handle, $src) {
        // 管理画面では処理しない
        if (is_admin()) {
            return $tag;
        }
        
        // WordPressコアスクリプトは除外
        if (strpos($src, 'wp-includes/js/') !== false) {
            return $tag;
        }
        
        // 既にdefer/asyncがある場合はスキップ
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        
        // 特定のハンドルにのみdeferを追加
        $defer_handles = array(
            'gi-main-js',
            'gi-frontend-js',
            'gi-mobile-enhanced'
        );
        
        if (in_array($handle, $defer_handles)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
}

// フィルターの重複登録を防ぐ
remove_filter('script_loader_tag', 'gi_add_defer_attribute', 10);
add_filter('script_loader_tag', 'gi_add_defer_attribute', 10, 3);

// モバイル専用テンプレート切り替えは削除（統合されました）

/**
 * モバイル用AJAX エンドポイント - さらに読み込み
 */
function gi_ajax_load_more_grants() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $page = intval($_POST['page'] ?? 1);
    $posts_per_page = 10;
    
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'post_status' => 'publish',
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        wp_send_json_error('No more posts found');
    }
    
    ob_start();
    
    while ($query->have_posts()): $query->the_post();
        echo gi_render_card(get_the_ID(), 'mobile');
    endwhile;
    
    wp_reset_postdata();
    
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'page' => $page,
        'max_pages' => $query->max_num_pages,
        'found_posts' => $query->found_posts
    ]);
}
add_action('wp_ajax_gi_load_more_grants', 'gi_ajax_load_more_grants');
add_action('wp_ajax_nopriv_gi_load_more_grants', 'gi_ajax_load_more_grants');

/**
 * テーマのアクティベーションチェック
 */
function gi_theme_activation_check() {
    // PHP バージョンチェック
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Grant Insight テーマはPHP 7.4以上が必要です。現在のバージョン: ' . PHP_VERSION;
            echo '</p></div>';
        });
    }
    
    // WordPress バージョンチェック
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            echo 'Grant Insight テーマはWordPress 5.8以上を推奨します。';
            echo '</p></div>';
        });
    }
    
    // 必須プラグインチェック（ACFなど）
    if (!class_exists('ACF') && is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>';
            echo 'Grant Insight テーマの全機能を利用するには、Advanced Custom Fields (ACF) プラグインのインストールを推奨します。';
            echo '</p></div>';
        });
    }
}
add_action('after_setup_theme', 'gi_theme_activation_check');

/**
 * エラーハンドリング用のグローバル関数
 */
if (!function_exists('gi_log_error')) {
    function gi_log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[Grant Insight Error] ' . $message;
            if (!empty($context)) {
                $log_message .= ' | Context: ' . print_r($context, true);
            }
            error_log($log_message);
        }
    }
}

/**
 * 外部連携機能関連Cronタスクの無効化（削除後の安全確保）
 */
add_action('init', function() {
    // 削除された外部連携機能関連のCronフックを無効化
    $external_cron_hooks = array(
        'giji_auto_import_hook',        // J-Grants (削除済み)
        'gi_excel_auto_export_hook'     // Excel (削除済み)
        // gi_sheets_sync_cron は現在アクティブなので削除しない
    );
    
    foreach ($external_cron_hooks as $hook) {
        // スケジュールされたイベントを全てクリア
        wp_clear_scheduled_hook($hook);
    }
});

/**
 * テーマ設定のデフォルト値を取得
 */
if (!function_exists('gi_get_theme_option')) {
    function gi_get_theme_option($option_name, $default = null) {
        $theme_options = get_option('gi_theme_options', array());
        
        if (isset($theme_options[$option_name])) {
            return $theme_options[$option_name];
        }
        
        return $default;
    }
}

/**
 * テーマ設定を保存
 */
if (!function_exists('gi_update_theme_option')) {
    function gi_update_theme_option($option_name, $value) {
        $theme_options = get_option('gi_theme_options', array());
        $theme_options[$option_name] = $value;
        
        return update_option('gi_theme_options', $theme_options);
    }
}



/**
 * テーマのバージョンアップグレード処理
 */
function gi_theme_version_upgrade() {
    $current_version = get_option('gi_installed_version', '0.0.0');
    
    if (version_compare($current_version, GI_THEME_VERSION, '<')) {
        // バージョンアップグレード処理
        
        // 6.2.0 -> 6.2.1 のアップグレード
        if (version_compare($current_version, '6.2.1', '<')) {
            // キャッシュのクリア
            gi_theme_cleanup();
        }
        
        // 6.2.1 -> 6.2.2 のアップグレード
        if (version_compare($current_version, '6.2.2', '<')) {
            // 新しいメタフィールドの追加など
            flush_rewrite_rules();
        }
        
        // バージョン更新
        update_option('gi_installed_version', GI_THEME_VERSION);
        
        // アップグレード完了通知
        if (is_admin()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo 'Grant Insight テーマが v' . GI_THEME_VERSION . ' にアップグレードされました。';
                echo '</p></div>';
            });
        }
    }
}
add_action('init', 'gi_theme_version_upgrade');

/**
 * データベーステーブル作成
 */
function gi_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // AI検索履歴テーブル
    $search_history_table = $wpdb->prefix . 'gi_search_history';
    $sql1 = "CREATE TABLE IF NOT EXISTS $search_history_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        search_query text NOT NULL,
        search_filter varchar(50) DEFAULT NULL,
        results_count int(11) DEFAULT 0,
        clicked_results text DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    

    
    // ユーザー設定テーブル
    $user_preferences_table = $wpdb->prefix . 'gi_user_preferences';
    $sql4 = "CREATE TABLE IF NOT EXISTS $user_preferences_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        preference_key varchar(100) NOT NULL,
        preference_value text DEFAULT NULL,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_preference (user_id, preference_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql4);
    
    // バージョン管理
    update_option('gi_db_version', '1.0.0');
}

// テーマ有効化時にテーブル作成
add_action('after_switch_theme', 'gi_create_database_tables');

// 既存のインストールでもテーブル作成を確認
add_action('init', function() {
    $db_version = get_option('gi_db_version', '0');
    if (version_compare($db_version, '1.0.0', '<')) {
        gi_create_database_tables();
    }
});

// 検索履歴関数は inc/ai-functions.php に移動





/**
 * AJAXハンドラーの登録確認
 */