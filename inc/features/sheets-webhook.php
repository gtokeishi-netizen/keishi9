<?php
/**
 * Google Sheets Webhook Handler
 * 
 * リアルタイム同期のためのWebhook処理
 * - Google Apps Scriptからのデータ受信
 * - セキュリティ検証
 * - 即座の同期処理
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

class SheetsWebhookHandler {
    
    private static $instance = null;
    private $webhook_secret;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->webhook_secret = $this->get_or_generate_webhook_secret();
        $this->add_hooks();
    }
    
    /**
     * WordPressフックの追加
     */
    private function add_hooks() {
        // Webhook エンドポイント
        add_action('init', array($this, 'handle_webhook_request'));
        
        // REST API エンドポイント
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
        
        // 管理画面にWebhook URL表示
        add_action('admin_notices', array($this, 'show_webhook_setup_notice'));
    }
    
    /**
     * Webhook シークレットを取得または生成
     */
    private function get_or_generate_webhook_secret() {
        $secret = get_option('gi_sheets_webhook_secret');
        
        if (!$secret) {
            $secret = wp_generate_password(32, false);
            update_option('gi_sheets_webhook_secret', $secret);
        }
        
        return $secret;
    }
    
    /**
     * Webhook リクエストの処理
     */
    public function handle_webhook_request() {
        // 特定のクエリパラメータをチェック
        if (!isset($_GET['gi_sheets_webhook']) || $_GET['gi_sheets_webhook'] !== 'true') {
            return;
        }
        
        // POST リクエストのみ受け付け
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            wp_die('Method Not Allowed', '', array('response' => 405));
        }
        
        // JSON データを取得
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);
        
        if (!$data) {
            http_response_code(400);
            wp_die('Invalid JSON', '', array('response' => 400));
        }
        
        // セキュリティ検証
        if (!$this->verify_webhook_security($data)) {
            http_response_code(403);
            wp_die('Forbidden', '', array('response' => 403));
        }
        
        // Webhook データを処理
        $result = $this->process_webhook_data($data);
        
        if ($result['success']) {
            http_response_code(200);
            wp_send_json_success($result['message']);
        } else {
            http_response_code(500);
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * REST API エンドポイントの登録
     */
    public function register_webhook_endpoint() {
        register_rest_route('gi/v1', '/sheets-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_webhook_handler'),
            'permission_callback' => '__return_true', // セキュリティは独自に検証
        ));
        
        // Google Apps Script用のエクスポートエンドポイント
        register_rest_route('gi/v1', '/export-grants', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_grants_handler'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * REST API Webhook ハンドラー
     */
    public function rest_webhook_handler($request) {
        $data = $request->get_json_params();
        
        if (!$data) {
            return new WP_Error('invalid_json', 'Invalid JSON data', array('status' => 400));
        }
        
        // セキュリティ検証
        if (!$this->verify_webhook_security($data)) {
            return new WP_Error('forbidden', 'Security verification failed', array('status' => 403));
        }
        
        // データを処理
        $result = $this->process_webhook_data($data);
        
        if ($result['success']) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => $result['message']
            ));
        } else {
            return new WP_Error('processing_failed', $result['message'], array('status' => 500));
        }
    }
    
    /**
     * Webhook セキュリティ検証
     */
    private function verify_webhook_security($data) {
        // 必須フィールドの確認
        if (!isset($data['timestamp']) || !isset($data['signature']) || !isset($data['payload'])) {
            return false;
        }
        
        // タイムスタンプ検証（5分以内のリクエストのみ受け付け）
        $current_time = time();
        $request_time = intval($data['timestamp']);
        
        if (abs($current_time - $request_time) > 300) { // 5分
            return false;
        }
        
        // 署名検証
        $payload_string = json_encode($data['payload']);
        $expected_signature = hash_hmac('sha256', $request_time . $payload_string, $this->webhook_secret);
        
        return hash_equals($expected_signature, $data['signature']);
    }
    
    /**
     * Webhook データの処理
     */
    private function process_webhook_data($data) {
        try {
            $payload = $data['payload'];
            
            // アクションタイプに基づいて処理分岐
            switch ($payload['action']) {
                case 'row_updated':
                    return $this->handle_row_update($payload);
                    
                case 'row_added':
                    return $this->handle_row_add($payload);
                    
                case 'row_deleted':
                    return $this->handle_row_delete($payload);
                    
                case 'bulk_update':
                    return $this->handle_bulk_update($payload);
                    
                default:
                    return array(
                        'success' => false,
                        'message' => 'Unknown action: ' . $payload['action']
                    );
            }
            
        } catch (Exception $e) {
            // ログに記録
            gi_log_error('Webhook processing failed', array(
                'error' => $e->getMessage(),
                'data' => $data
            ));
            
            return array(
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 行更新の処理
     */
    private function handle_row_update($payload) {
        if (!isset($payload['row_data']) || !isset($payload['row_number'])) {
            return array('success' => false, 'message' => 'Missing row data');
        }
        
        $row_data = $payload['row_data'];
        $post_id = intval($row_data[0]); // A列はID
        
        // 既存投稿の確認
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        // 投稿データを更新
        $updated_post = array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field($row_data[1]),
            'post_content' => wp_kses_post($row_data[2]),
            'post_excerpt' => sanitize_textarea_field($row_data[3]),
            'post_status' => sanitize_text_field($row_data[4]),
        );
        
        $result = wp_update_post($updated_post);
        
        if (is_wp_error($result)) {
            return array('success' => false, 'message' => $result->get_error_message());
        }
        
        // ACFフィールドを更新
        $this->update_acf_fields($post_id, $row_data);
        
        // カテゴリとタグを更新
        $this->update_taxonomies($post_id, $row_data);
        
        // ログ追加
        if (class_exists('SheetsAdminUI') && method_exists('SheetsAdminUI', 'add_log_entry')) {
            SheetsAdminUI::add_log_entry("投稿 ID:{$post_id} をWebhookで更新しました", 'success');
        }
        
        return array(
            'success' => true,
            'message' => "Post {$post_id} updated successfully"
        );
    }
    
    /**
     * 新規行追加の処理
     */
    private function handle_row_add($payload) {
        if (!isset($payload['row_data'])) {
            return array('success' => false, 'message' => 'Missing row data');
        }
        
        $row_data = $payload['row_data'];
        
        // 新規投稿を作成
        $new_post = array(
            'post_title' => sanitize_text_field($row_data[1]),
            'post_content' => wp_kses_post($row_data[2]),
            'post_excerpt' => sanitize_textarea_field($row_data[3]),
            'post_status' => sanitize_text_field($row_data[4]),
            'post_type' => 'grant'
        );
        
        $post_id = wp_insert_post($new_post);
        
        if (is_wp_error($post_id)) {
            return array('success' => false, 'message' => $post_id->get_error_message());
        }
        
        // ACFフィールドを設定
        $this->update_acf_fields($post_id, $row_data);
        
        // カテゴリとタグを設定
        $this->update_taxonomies($post_id, $row_data);
        
        // スプレッドシートにIDを書き戻し（非同期で実行）
        wp_schedule_single_event(time() + 10, 'gi_update_sheet_id', array($post_id, $payload['row_number']));
        
        // ログ追加
        if (class_exists('SheetsAdminUI') && method_exists('SheetsAdminUI', 'add_log_entry')) {
            SheetsAdminUI::add_log_entry("投稿 ID:{$post_id} をWebhookで作成しました", 'success');
        }
        
        return array(
            'success' => true,
            'message' => "Post {$post_id} created successfully"
        );
    }
    
    /**
     * 行削除の処理
     */
    private function handle_row_delete($payload) {
        if (!isset($payload['post_id'])) {
            return array('success' => false, 'message' => 'Missing post ID');
        }
        
        $post_id = intval($payload['post_id']);
        
        // 投稿の存在確認
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        // 投稿を削除
        $result = wp_delete_post($post_id, true);
        
        if (!$result) {
            return array('success' => false, 'message' => 'Failed to delete post');
        }
        
        // ログ追加
        if (class_exists('SheetsAdminUI') && method_exists('SheetsAdminUI', 'add_log_entry')) {
            SheetsAdminUI::add_log_entry("投稿 ID:{$post_id} をWebhookで削除しました", 'success');
        }
        
        return array(
            'success' => true,
            'message' => "Post {$post_id} deleted successfully"
        );
    }
    
    /**
     * 一括更新の処理
     */
    private function handle_bulk_update($payload) {
        if (!isset($payload['updates']) || !is_array($payload['updates'])) {
            return array('success' => false, 'message' => 'Missing updates data');
        }
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($payload['updates'] as $update) {
            try {
                switch ($update['action']) {
                    case 'update':
                        $result = $this->handle_row_update($update);
                        break;
                    case 'add':
                        $result = $this->handle_row_add($update);
                        break;
                    case 'delete':
                        $result = $this->handle_row_delete($update);
                        break;
                    default:
                        continue 2; // 次のループへ
                }
                
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                
            } catch (Exception $e) {
                $error_count++;
                gi_log_error('Bulk update item failed', array(
                    'error' => $e->getMessage(),
                    'update' => $update
                ));
            }
        }
        
        // ログ追加
        if (class_exists('SheetsAdminUI') && method_exists('SheetsAdminUI', 'add_log_entry')) {
            SheetsAdminUI::add_log_entry("一括更新完了: 成功 {$success_count}件, エラー {$error_count}件", 'info');
        }
        
        return array(
            'success' => true,
            'message' => "Bulk update completed: {$success_count} success, {$error_count} errors"
        );
    }
    
    /**
     * ACFフィールドの更新
     */
    private function update_acf_fields($post_id, $row_data) {
        $acf_mapping = array(
            7 => 'max_amount',
            8 => 'max_amount_numeric',
            9 => 'deadline',
            10 => 'deadline_date',
            11 => 'organization',
            12 => 'organization_type',
            13 => 'grant_target',
            14 => 'application_method',
            15 => 'contact_info',
            16 => 'official_url',
            17 => 'target_prefecture',
            18 => 'prefecture_name',
            19 => 'target_municipality',
            20 => 'regional_limitation',
            21 => 'application_status'
        );
        
        foreach ($acf_mapping as $col_index => $field_name) {
            if (isset($row_data[$col_index])) {
                $value = $row_data[$col_index];
                
                // 数値フィールドの処理
                if ($field_name === 'max_amount_numeric') {
                    $value = intval($value);
                }
                
                // JSON文字列の場合はデコード
                if (is_string($value) && ($decoded = json_decode($value, true)) !== null) {
                    $value = $decoded;
                }
                
                update_field($field_name, $value, $post_id);
            }
        }
    }
    
    /**
     * カテゴリとタグの更新
     */
    private function update_taxonomies($post_id, $row_data) {
        // カテゴリ（W列 = インデックス22）
        if (isset($row_data[22]) && !empty($row_data[22])) {
            $categories = array_map('trim', explode(',', $row_data[22]));
            wp_set_post_terms($post_id, $categories, 'grant_category');
        }
        
        // タグ（X列 = インデックス23）
        if (isset($row_data[23]) && !empty($row_data[23])) {
            $tags = array_map('trim', explode(',', $row_data[23]));
            wp_set_post_terms($post_id, $tags, 'grant_tag');
        }
    }
    
    /**
     * Webhook URL を取得
     */
    public function get_webhook_url() {
        return home_url('/?gi_sheets_webhook=true');
    }
    
    /**
     * REST API Webhook URL を取得
     */
    public function get_rest_webhook_url() {
        return rest_url('gi/v1/sheets-webhook');
    }
    
    /**
     * Webhook シークレットを取得
     */
    public function get_webhook_secret() {
        return $this->webhook_secret;
    }
    
    /**
     * 管理画面にWebhook設定通知を表示
     */
    public function show_webhook_setup_notice() {
        $screen = get_current_screen();
        
        // Sheets設定ページでのみ表示
        if (!$screen || strpos($screen->id, 'grant-sheets-sync') === false) {
            return;
        }
        
        $webhook_url = $this->get_webhook_url();
        $rest_webhook_url = $this->get_rest_webhook_url();
        $secret = $this->get_webhook_secret();
        
        ?>
        <div class="notice notice-info">
            <h3>Google Apps Script Webhook設定</h3>
            <p>リアルタイム同期を有効にするため、以下の情報をGoogle Apps Scriptに設定してください：</p>
            <ul>
                <li><strong>Webhook URL:</strong> <code><?php echo esc_html($webhook_url); ?></code></li>
                <li><strong>REST API URL:</strong> <code><?php echo esc_html($rest_webhook_url); ?></code></li>
                <li><strong>Secret Key:</strong> <code><?php echo esc_html($secret); ?></code></li>
            </ul>
            <p>
                <a href="#" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>'); alert('Webhook URLをコピーしました');" class="button button-secondary">Webhook URLをコピー</a>
                <a href="#" onclick="navigator.clipboard.writeText('<?php echo esc_js($secret); ?>'); alert('Secret Keyをコピーしました');" class="button button-secondary">Secret Keyをコピー</a>
            </p>
        </div>
        <?php
    }
    
    /**
     * 助成金データエクスポートハンドラー（Google Apps Script用）
     */
    public function export_grants_handler($request) {
        try {
            // 助成金投稿を取得
            $posts = get_posts(array(
                'post_type' => 'grant',
                'post_status' => array('publish', 'draft', 'private'),
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            $exported_data = array();
            
            foreach ($posts as $post) {
                $post_id = $post->ID;
                
                // 投稿データをスプレッドシート形式に変換
                $row_data = $this->convert_post_to_export_row($post_id);
                if ($row_data) {
                    $exported_data[] = $row_data;
                }
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Posts exported successfully',
                'count' => count($exported_data),
                'data' => $exported_data
            ));
            
        } catch (Exception $e) {
            return new WP_Error('export_failed', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * 投稿データをエクスポート用の行データに変換
     */
    private function convert_post_to_export_row($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return false;
        }
        
        // 都道府県名を取得
        $prefecture_code = get_field('target_prefecture', $post_id);
        $prefecture_name = '';
        if ($prefecture_code && function_exists('gi_get_prefecture_name_by_code')) {
            $prefecture_name = gi_get_prefecture_name_by_code($prefecture_code);
        }
        
        // カテゴリとタグを取得
        $categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        $tags = wp_get_post_terms($post_id, 'grant_tag', array('fields' => 'names'));
        
        // スプレッドシートの列順に合わせたデータ配列
        return array(
            $post_id,                                                    // A: ID
            $post->post_title,                                           // B: タイトル
            wp_strip_all_tags($post->post_content),                     // C: 内容
            $post->post_excerpt,                                         // D: 抜粋
            $post->post_status,                                          // E: ステータス
            $post->post_date,                                            // F: 作成日
            $post->post_modified,                                        // G: 更新日
            get_field('max_amount', $post_id) ?: '',                     // H: 助成金額（表示用）
            get_field('max_amount_numeric', $post_id) ?: 0,              // I: 助成金額（数値）
            get_field('deadline', $post_id) ?: '',                       // J: 申請期限（表示用）
            get_field('deadline_date', $post_id) ?: '',                  // K: 申請期限（日付）
            get_field('organization', $post_id) ?: '',                   // L: 実施組織
            get_field('organization_type', $post_id) ?: 'national',      // M: 組織タイプ
            get_field('grant_target', $post_id) ?: '',                   // N: 対象者・対象事業
            get_field('application_method', $post_id) ?: 'online',       // O: 申請方法
            get_field('contact_info', $post_id) ?: '',                   // P: 問い合わせ先
            get_field('official_url', $post_id) ?: '',                   // Q: 公式URL
            get_field('target_prefecture', $post_id) ?: '',              // R: 都道府県コード
            $prefecture_name,                                            // S: 都道府県名
            get_field('target_municipality', $post_id) ?: '',            // T: 対象市町村
            get_field('regional_limitation', $post_id) ?: 'nationwide',   // U: 地域制限
            get_field('application_status', $post_id) ?: 'open',         // V: 申請ステータス
            is_array($categories) ? implode(', ', $categories) : '',     // W: カテゴリ
            is_array($tags) ? implode(', ', $tags) : '',                 // X: タグ
            current_time('mysql')                                        // Y: シート更新日
        );
    }
    
    /**
     * スプレッドシートIDの書き戻しアクション
     */
    public function update_sheet_id_callback($post_id, $row_number) {
        $sheets_sync = GoogleSheetsSync::getInstance();
        
        // スプレッドシートのA列にIDを書き込み
        $range = "grant_import!A{$row_number}";
        $sheets_sync->write_sheet_data($range, array(array($post_id)));
        
        // ログ追加
        if (class_exists('SheetsAdminUI') && method_exists('SheetsAdminUI', 'add_log_entry')) {
            SheetsAdminUI::add_log_entry("投稿ID {$post_id} をスプレッドシートに書き戻しました", 'info');
        }
    }
}

// スプレッドシートID書き戻しのアクション登録
add_action('gi_update_sheet_id', array('SheetsWebhookHandler', 'update_sheet_id_callback'), 10, 2);

// インスタンスを初期化
function gi_init_sheets_webhook() {
    return SheetsWebhookHandler::getInstance();
}
add_action('init', 'gi_init_sheets_webhook', 5);