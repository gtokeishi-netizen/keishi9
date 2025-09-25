<?php
/**
 * Google Sheets Admin UI
 * 
 * スプレッドシート統合の管理画面インターフェース
 * - 接続テスト
 * - 手動同期
 * - 同期ログ表示
 * - 設定管理
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

class SheetsAdminUI {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * 管理画面メニューに追加
     */
    public function add_admin_menu() {
        // デバッグ用: 必ず設定メニューの下に追加
        add_options_page(
            'Google Sheets連携',
            'Sheets連携',
            'edit_posts', // 権限を緩和
            'grant-sheets-sync',
            array($this, 'admin_page')
        );
        
        // 助成金投稿タイプが存在する場合は、そちらにも追加
        if (post_type_exists('grant')) {
            add_submenu_page(
                'edit.php?post_type=grant',
                'Google Sheets連携',
                'Sheets連携',
                'edit_posts', // 権限を緩和
                'grant-sheets-sync-grant',
                array($this, 'admin_page')
            );
        }
    }
    
    /**
     * 管理画面用スクリプトとスタイル
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'grant-sheets-sync') === false) {
            return;
        }
        
        wp_enqueue_script(
            'gi-sheets-admin',
            get_template_directory_uri() . '/assets/js/sheets-admin.js',
            array('jquery'),
            GI_THEME_VERSION,
            true
        );
        
        wp_localize_script('gi-sheets-admin', 'giSheetsAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gi_sheets_nonce'),
            'strings' => array(
                'testing' => '接続をテスト中...',
                'syncing' => '同期中...',
                'success' => '成功',
                'error' => 'エラー',
                'confirm_sync' => '同期を実行しますか？この操作により既存のデータが上書きされる可能性があります。'
            )
        ));
        
        wp_enqueue_style(
            'gi-sheets-admin-style',
            get_template_directory_uri() . '/assets/css/sheets-admin.css',
            array(),
            GI_THEME_VERSION
        );
    }
    
    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('gi_sheets_settings', 'gi_sheets_config');
        
        add_settings_section(
            'gi_sheets_main',
            'Google Sheets設定',
            array($this, 'settings_section_callback'),
            'gi_sheets_settings'
        );
        
        add_settings_field(
            'auto_sync_enabled',
            '自動同期を有効化',
            array($this, 'auto_sync_field_callback'),
            'gi_sheets_settings',
            'gi_sheets_main'
        );
        
        add_settings_field(
            'sync_interval',
            '同期間隔（分）',
            array($this, 'sync_interval_field_callback'),
            'gi_sheets_settings',
            'gi_sheets_main'
        );
    }
    
    /**
     * 管理画面のメインページ
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Google Sheets連携設定</h1>
            
            <!-- 接続状態カード -->
            <div class="gi-sheets-card">
                <h2>接続状態</h2>
                <div id="connection-status" class="gi-status-unknown">
                    <span class="gi-status-indicator"></span>
                    <span class="gi-status-text">未確認</span>
                </div>
                <p>
                    <button type="button" id="test-connection" class="button">接続をテスト</button>
                </p>
                
                <div class="gi-connection-info">
                    <h4>スプレッドシート情報</h4>
                    <p><strong>スプレッドシートID:</strong> 1kGc1Eb4AYvURkSfdzMwipNjfe8xC6iGCM2q1sUgIfWg</p>
                    <p><strong>シート名:</strong> grant_import</p>
                    <p><strong>サービスアカウント:</strong> grant-sheets-service@grant-sheets-integration.iam.gserviceaccount.com</p>
                    <p><a href="https://docs.google.com/spreadsheets/d/1kGc1Eb4AYvURkSfdzMwipNjfe8xC6iGCM2q1sUgIfWg/edit#gid=706632810" target="_blank" class="button button-secondary">スプレッドシートを開く</a></p>
                </div>
            </div>
            
            <!-- 手動同期カード -->
            <div class="gi-sheets-card">
                <h2>手動同期</h2>
                <div class="gi-sync-controls">
                    <div class="gi-sync-option">
                        <button type="button" class="button button-primary gi-sync-btn" data-direction="both">
                            完全同期（双方向）
                        </button>
                        <p class="description">WordPressとスプレッドシートの両方向で同期します。</p>
                    </div>
                    
                    <div class="gi-sync-option">
                        <button type="button" class="button gi-sync-btn" data-direction="wp_to_sheets">
                            WordPress → Sheets
                        </button>
                        <p class="description">WordPressの投稿をスプレッドシートに反映します。</p>
                    </div>
                    
                    <div class="gi-sync-option">
                        <button type="button" class="button gi-sync-btn" data-direction="sheets_to_wp">
                            Sheets → WordPress
                        </button>
                        <p class="description">スプレッドシートの変更をWordPressに反映します。</p>
                    </div>
                </div>
                
                <div id="sync-result" style="display: none;">
                    <div class="notice">
                        <p id="sync-message"></p>
                    </div>
                </div>
            </div>
            
            <!-- スプレッドシート初期化カード -->
            <div class="gi-sheets-card">
                <h2>スプレッドシート初期化</h2>
                <div class="gi-init-controls">
                    <p class="description">
                        スプレッドシートにヘッダー行を設定し、既存の投稿データをエクスポートします。
                    </p>
                    <div class="gi-init-actions">
                        <button type="button" id="initialize-sheet" class="button button-primary">
                            スプレッドシートを初期化
                        </button>
                        <button type="button" id="export-all-posts" class="button button-secondary">
                            全投稿をエクスポート
                        </button>
                        <button type="button" id="clear-sheet" class="button button-secondary gi-danger">
                            スプレッドシートをクリア
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Webhook設定カード -->
            <div class="gi-sheets-card">
                <h2>リアルタイム同期（Webhook）</h2>
                <div class="gi-webhook-info">
                    <p>Google Apps Scriptを設定することで、スプレッドシートの変更をリアルタイムでWordPressに反映できます。</p>
                    
                    <?php
                    if (class_exists('SheetsWebhookHandler')) {
                        $webhook_handler = SheetsWebhookHandler::getInstance();
                        $webhook_url = $webhook_handler->get_webhook_url();
                        $rest_webhook_url = $webhook_handler->get_rest_webhook_url();
                        $secret = $webhook_handler->get_webhook_secret();
                    } else {
                        $webhook_url = home_url('/?gi_sheets_webhook=true');
                        $rest_webhook_url = rest_url('gi/v1/sheets-webhook');
                        $secret = 'webhook_handler_not_loaded';
                    }
                    ?>
                    
                    <div class="gi-webhook-config">
                        <h4>Google Apps Script設定値</h4>
                        <table class="form-table">
                            <tr>
                                <th>Webhook URL</th>
                                <td>
                                    <input type="text" value="<?php echo esc_attr($webhook_url); ?>" readonly style="width: 100%;">
                                    <button type="button" class="button button-small gi-copy-btn" data-copy="<?php echo esc_attr($webhook_url); ?>">コピー</button>
                                </td>
                            </tr>
                            <tr>
                                <th>REST API URL (推奨)</th>
                                <td>
                                    <input type="text" value="<?php echo esc_attr($rest_webhook_url); ?>" readonly style="width: 100%;">
                                    <button type="button" class="button button-small gi-copy-btn" data-copy="<?php echo esc_attr($rest_webhook_url); ?>">コピー</button>
                                </td>
                            </tr>
                            <tr>
                                <th>Secret Key</th>
                                <td>
                                    <input type="password" value="<?php echo esc_attr($secret); ?>" readonly style="width: 100%;">
                                    <button type="button" class="button button-small gi-copy-btn" data-copy="<?php echo esc_attr($secret); ?>">コピー</button>
                                    <button type="button" class="button button-small" onclick="this.previousElementSibling.previousElementSibling.type='text'">表示</button>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="gi-gas-setup">
                            <h4>セットアップ手順</h4>
                            <ol>
                                <li><a href="https://script.google.com" target="_blank">Google Apps Script</a> で新しいプロジェクトを作成</li>
                                <li>提供されたコード（SheetSync.gs）をコピー＆ペースト</li>
                                <li>上記の設定値をコードの CONFIG オブジェクトに設定</li>
                                <li>setupTriggers() 関数を実行してトリガーを設定</li>
                                <li>testConnection() 関数で接続をテスト</li>
                            </ol>
                            
                            <p>
                                <a href="<?php echo esc_url(get_template_directory_uri() . '/google-apps-script/SheetSync.gs'); ?>" class="button button-secondary" download>Google Apps Scriptコードをダウンロード</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 同期設定カード -->
            <div class="gi-sheets-card">
                <h2>自動同期設定</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('gi_sheets_settings');
                    do_settings_sections('gi_sheets_settings');
                    submit_button('設定を保存');
                    ?>
                </form>
            </div>
            
            <!-- 同期ログカード -->
            <div class="gi-sheets-card">
                <h2>同期ログ</h2>
                <div id="sync-log">
                    <?php $this->display_sync_log(); ?>
                </div>
                <p>
                    <button type="button" id="refresh-log" class="button button-secondary">ログを更新</button>
                    <button type="button" id="clear-log" class="button button-secondary">ログをクリア</button>
                </p>
            </div>
            
            <!-- フィールドマッピングカード -->
            <div class="gi-sheets-card">
                <h2>フィールドマッピング</h2>
                <div class="gi-mapping-info">
                    <h4>スプレッドシートの列構成</h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>列</th>
                                <th>フィールド</th>
                                <th>説明</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>A</td><td>ID</td><td>WordPress投稿ID</td></tr>
                            <tr><td>B</td><td>タイトル</td><td>投稿のタイトル</td></tr>
                            <tr><td>C</td><td>内容</td><td>投稿の本文</td></tr>
                            <tr><td>D</td><td>抜粋</td><td>投稿の抜粋</td></tr>
                            <tr><td>E</td><td>ステータス</td><td>publish / draft / private / deleted</td></tr>
                            <tr><td>F</td><td>作成日</td><td>投稿作成日時</td></tr>
                            <tr><td>G</td><td>更新日</td><td>投稿更新日時</td></tr>
                            <tr><td>H</td><td>助成金額（表示用）</td><td>ACF: max_amount</td></tr>
                            <tr><td>I</td><td>助成金額（数値）</td><td>ACF: max_amount_numeric</td></tr>
                            <tr><td>J</td><td>申請期限（表示用）</td><td>ACF: deadline</td></tr>
                            <tr><td>K</td><td>申請期限（日付）</td><td>ACF: deadline_date</td></tr>
                            <tr><td>L</td><td>実施組織</td><td>ACF: organization</td></tr>
                            <tr><td>M</td><td>組織タイプ</td><td>ACF: organization_type</td></tr>
                            <tr><td>N</td><td>対象者・対象事業</td><td>ACF: grant_target</td></tr>
                            <tr><td>O</td><td>申請方法</td><td>ACF: application_method</td></tr>
                            <tr><td>P</td><td>問い合わせ先</td><td>ACF: contact_info</td></tr>
                            <tr><td>Q</td><td>公式URL</td><td>ACF: official_url</td></tr>
                            <tr><td>R</td><td>都道府県コード</td><td>ACF: target_prefecture</td></tr>
                            <tr><td>S</td><td>都道府県名</td><td>ACF: prefecture_name</td></tr>
                            <tr><td>T</td><td>対象市町村</td><td>ACF: target_municipality</td></tr>
                            <tr><td>U</td><td>地域制限</td><td>ACF: regional_limitation</td></tr>
                            <tr><td>V</td><td>申請ステータス</td><td>ACF: application_status</td></tr>
                            <tr><td>W</td><td>カテゴリ</td><td>カンマ区切りのカテゴリ名</td></tr>
                            <tr><td>X</td><td>タグ</td><td>カンマ区切りのタグ名</td></tr>
                            <tr><td>Y</td><td>シート更新日</td><td>スプレッドシート最終更新日時</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 使用方法カード -->
            <div class="gi-sheets-card">
                <h2>スプレッドシートでの投稿管理方法</h2>
                <div class="gi-usage-guide">
                    <h4>新規投稿の作成</h4>
                    <ol>
                        <li>スプレッドシートの最下行に新しい行を追加</li>
                        <li>A列（ID）は空欄のままにする（自動的に割り当てられます）</li>
                        <li>B列以降に投稿データを入力</li>
                        <li>E列のステータスを「publish」「draft」「private」のいずれかに設定</li>
                        <li>手動同期または自動同期でWordPressに反映</li>
                    </ol>
                    
                    <h4>既存投稿の編集</h4>
                    <ol>
                        <li>該当する投稿のIDを確認</li>
                        <li>その行の内容を編集</li>
                        <li>手動同期または自動同期でWordPressに反映</li>
                    </ol>
                    
                    <h4>投稿の削除</h4>
                    <ol>
                        <li>該当する投稿のE列（ステータス）を「deleted」に変更</li>
                        <li>手動同期または自動同期でWordPressから削除</li>
                    </ol>
                    
                    <div class="notice notice-info">
                        <p><strong>注意:</strong> スプレッドシートから行を削除してもWordPressからは削除されません。ステータスを「deleted」に変更してください。</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 設定セクションのコールバック
     */
    public function settings_section_callback() {
        echo '<p>自動同期の設定を行います。</p>';
    }
    
    /**
     * 自動同期有効化フィールド
     */
    public function auto_sync_field_callback() {
        $config = get_option('gi_sheets_config', array());
        $enabled = isset($config['auto_sync_enabled']) ? $config['auto_sync_enabled'] : true;
        
        echo '<label>';
        echo '<input type="checkbox" name="gi_sheets_config[auto_sync_enabled]" value="1" ' . checked(1, $enabled, false) . '>';
        echo ' 自動同期を有効にする';
        echo '</label>';
        echo '<p class="description">無効にすると手動同期のみになります。</p>';
    }
    
    /**
     * 同期間隔フィールド
     */
    public function sync_interval_field_callback() {
        $config = get_option('gi_sheets_config', array());
        $interval = isset($config['sync_interval']) ? $config['sync_interval'] : 5;
        
        echo '<select name="gi_sheets_config[sync_interval]">';
        $intervals = array(
            1 => '1分',
            5 => '5分',
            15 => '15分',
            30 => '30分',
            60 => '1時間'
        );
        
        foreach ($intervals as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($interval, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">自動同期の実行間隔を設定します。</p>';
    }
    
    /**
     * 同期ログを表示
     */
    private function display_sync_log() {
        $logs = get_option('gi_sheets_sync_log', array());
        
        if (empty($logs)) {
            echo '<p>まだログがありません。</p>';
            return;
        }
        
        // 最新10件のログを表示
        $logs = array_slice($logs, -10);
        $logs = array_reverse($logs);
        
        echo '<div class="gi-log-container">';
        foreach ($logs as $log) {
            $class = 'gi-log-' . esc_attr($log['level']);
            $time = date('Y-m-d H:i:s', $log['timestamp']);
            echo '<div class="gi-log-entry ' . $class . '">';
            echo '<span class="gi-log-time">' . esc_html($time) . '</span>';
            echo '<span class="gi-log-message">' . esc_html($log['message']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * ログエントリを追加
     */
    public static function add_log_entry($message, $level = 'info') {
        $logs = get_option('gi_sheets_sync_log', array());
        
        $logs[] = array(
            'timestamp' => time(),
            'level' => $level,
            'message' => $message
        );
        
        // 最大100件のログを保持
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('gi_sheets_sync_log', $logs);
    }
    
    /**
     * ログをクリア
     */
    public function clear_log() {
        delete_option('gi_sheets_sync_log');
    }
}

// AJAX ハンドラーを追加
add_action('wp_ajax_gi_clear_sheets_log', function() {
    check_ajax_referer('gi_sheets_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }
    
    delete_option('gi_sheets_sync_log');
    wp_send_json_success('ログをクリアしました。');
});

// インスタンスを初期化
function gi_init_sheets_admin_ui() {
    return SheetsAdminUI::getInstance();
}

// デバッグ用: メニュー追加の確認通知
add_action('admin_notices', function() {
    if (current_user_can('edit_posts') && !isset($_GET['page'])) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Google Sheets連携:</strong> ';
        echo '設定は「<a href="' . admin_url('options-general.php?page=grant-sheets-sync') . '">設定 → Sheets連携</a>」から利用できます。';
        if (post_type_exists('grant')) {
            echo ' または「<a href="' . admin_url('edit.php?post_type=grant&page=grant-sheets-sync-grant') . '">助成金 → Sheets連携</a>」からもアクセスできます。';
        }
        echo '</p></div>';
    }
});

// 管理画面でのみ初期化 - より早いタイミングで実行
if (is_admin()) {
    // 即座に初期化を実行
    gi_init_sheets_admin_ui();
    
    // フックでも念のため登録
    add_action('init', 'gi_init_sheets_admin_ui', 1);
}