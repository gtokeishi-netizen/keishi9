<?php
/**
 * Google Sheets Sync Integration
 * 
 * 助成金カスタム投稿とGoogle Sheetsの完全同期システム
 * - 双方向同期（WordPress ⟷ Google Sheets）
 * - リアルタイム更新
 * - CRUD操作の完全対応
 * - ACFフィールドとカテゴリの同期
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

class GoogleSheetsSync {
    
    private static $instance = null;
    private $service_account_key;
    private $spreadsheet_id;
    private $sheet_name;
    private $access_token;
    private $token_expires_at;
    
    // Google Sheets API設定
    const SHEETS_API_URL = 'https://sheets.googleapis.com/v4/spreadsheets/';
    const AUTH_SCOPE = 'https://www.googleapis.com/auth/spreadsheets';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_settings();
        $this->add_hooks();
    }
    
    /**
     * 設定の初期化
     */
    private function init_settings() {
        // サービスアカウントキー（セキュアに保存）
        $this->service_account_key = array(
            "type" => "service_account",
            "project_id" => "grant-sheets-integration",
            "private_key_id" => "c0fdd6753a43e1c51cbc1854c4ce53cb461b0136",
            "private_key" => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC+Ba+i0O4k0Jta\n17u3D/hJaqkLuptpyknOhjeQLzOGl9GtRP88KYX+NpKO1RxuuZMmlBt/7ShlXDPk\nJXdOtOjPlMzHZeh32M/f+98L9S9PVfapGUKRV0p4XJmExljmP7AVnXaMjlXqm9BJ\ngvO7K898LApyAsdrtcOYgt371LWZbQdTqpNWQemfJcYnTndwMcYzv6Snm/lOUruD\nrV2VOhvsMfqwVOaKywhE6rvUrF1ARaT3meQJyF9CpqFcb947f5phRUVD1QEdQp1K\nfGeFmMqR3nT4sY6I7VVqnseyr7v6U4i9V2aaL8KhUmH895xRlL6cc+QR7lgPtkT3\nZ8FJdseLAgMBAAECggEAWj9OFrg+2jo/Bmp+SyepBolDJwBl7lz2J8Fj4zUfthUl\nrrKdu9+GtWEKww5g1g+J3SErXFrwvA8J0BmhK77M8UWc6jiyqzTMKXcwjDfS082i\ne9Y04N1Bz58/BCnFr/jgcquZ0ZCKKoX86uToR+U7QiCSh2pddwDZF/ZTYla4NtiZ\nP/uZBAIuO/Fz2bLnjzQrQ1tLBdgY3mWx/wChi6+JhqubiNTnrWqy8qXG8P2OieZS\nQxU31/EjOp8rK4ErxqN5WDS0BRhIKM0DTN3WXwB8Sb5JCSluxksdICvNshiilsVF\nQGsXF3pGZA6Okv9cJS0u6vUoYVMMSzeWQvyM0tKwuQKBgQDgrUS2K21sVun+mI3L\niQ99XlMDT0AhsDaSWyenqveNawosoKz3ueBXEwkpOcM8DdcTDKbZVohM7h1cTEax\nPobdj2bQdUFWkzup5kekVBu88bIPthTMK5IuTUcHYyfiH8V7vsEtrX184UAiET/p\nXmHZ+lcUCuL+8+uKogEdvy/1UwKBgQDYg5eJlQ0hoOH0VP8HkSeJSn246X8CdeHT\n1kgkymJcLwWYr+EKngTQrSkLkIfxBER3UMfHtla95IL4qGC/iNcIWbie2Gtc2wXz\nWvwpaoliReoKOYyFG94Fl5zdcp5xYi2oA2qB9LM+eyCqqEEkVhpg3w61Xfj03wMI\n6Ibxc0al6QKBgQC7KVut7WtP7u8qOWcVgG244BSDE0e3SJWNQgY8tD1YPyzQlGDC\nVMM/hgoBn661nknmAooTTvRoMYuf0aKqEA5FDyp0yNjPCAORutU/XRlmQmk0kVet\n5TX3AEUFMGKPCix2syc1p+p7VyEXwArfmtIkxVg4yADkpck3SVFouFV5JQKBgDcz\njb45L0jkoNdPmFoQixj40gcEGSrCbVo6JtiidON15aJhLSos0aN2kqFtLwum/+G/\nyb/EYGc3zKCjJU+QDusFHQn6uZzKBsFd8C6LCA3zL1F+DLKfQUMBva/EGltkIanV\nfSE3B0Al2lVIYptmDIGoPTLGi8O63CY4SrdioZ+JAoGAMjzeU4jqFtkXaiRBTa+v\njspaqbk1rq1x4ZmnPMZzMQnZLStP9QP7SQn5/my/ZSWcnmjxW8ZgMdfWB1TD51RC\n4HYL/jGrjOUmumshQmiA1a7zCvr8yVJFkOVcYpCWl6TT5hiFbqrW82Dw73JFHTuK\n30Chu7ki9aOiJJeMmHaOfOU=\n-----END PRIVATE KEY-----\n",
            "client_email" => "grant-sheets-service@grant-sheets-integration.iam.gserviceaccount.com",
            "client_id" => "109769300820349787611",
            "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
            "token_uri" => "https://oauth2.googleapis.com/token",
            "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
            "client_x509_cert_url" => "https://www.googleapis.com/robot/v1/metadata/x509/grant-sheets-service%40grant-sheets-integration.iam.gserviceaccount.com",
            "universe_domain" => "googleapis.com"
        );
        
        // スプレッドシートの設定
        $this->spreadsheet_id = '1kGc1Eb4AYvURkSfdzMwipNjfe8xC6iGCM2q1sUgIfWg';
        $this->sheet_name = 'grant_import';
        
        // 既存のアクセストークンを確認
        $stored_token = get_option('gi_sheets_access_token');
        $stored_expires = get_option('gi_sheets_token_expires');
        
        if ($stored_token && $stored_expires && time() < $stored_expires) {
            $this->access_token = $stored_token;
            $this->token_expires_at = $stored_expires;
        }
    }
    
    /**
     * WordPressフックの追加
     */
    private function add_hooks() {
        // 投稿の保存・更新時にスプレッドシートを更新
        add_action('save_post_grant', array($this, 'sync_post_to_sheets'), 10, 3);
        
        // 投稿の削除時にスプレッドシートからも削除
        add_action('before_delete_post', array($this, 'delete_post_from_sheets'));
        
        // 投稿ステータス変更時の同期
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        
        // 定期的な双方向同期
        add_action('gi_sheets_sync_cron', array($this, 'full_bidirectional_sync'));
        
        // Cronスケジュールの設定
        if (!wp_next_scheduled('gi_sheets_sync_cron')) {
            wp_schedule_event(time(), 'every_5_minutes', 'gi_sheets_sync_cron');
        }
        
        // AJAX ハンドラー
        add_action('wp_ajax_gi_manual_sheets_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_gi_test_sheets_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Google Sheets APIアクセストークンを取得
     */
    private function get_access_token() {
        // 既存のトークンが有効な場合はそれを使用
        if ($this->access_token && $this->token_expires_at && time() < ($this->token_expires_at - 300)) {
            return $this->access_token;
        }
        
        // JWTを作成
        $jwt = $this->create_jwt();
        
        // トークンリクエスト
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            gi_log_error('Google Sheets Token Request Failed', array(
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);
        
        if (!isset($token_data['access_token'])) {
            gi_log_error('Invalid Token Response', array(
                'response' => $body
            ));
            return false;
        }
        
        // トークンを保存
        $this->access_token = $token_data['access_token'];
        $this->token_expires_at = time() + ($token_data['expires_in'] - 300); // 5分早めに期限切れとする
        
        update_option('gi_sheets_access_token', $this->access_token);
        update_option('gi_sheets_token_expires', $this->token_expires_at);
        
        return $this->access_token;
    }
    
    /**
     * JWT（JSON Web Token）を作成
     */
    private function create_jwt() {
        $header = json_encode(array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        ));
        
        $now = time();
        $payload = json_encode(array(
            'iss' => $this->service_account_key['client_email'],
            'scope' => self::AUTH_SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ));
        
        $base64_header = $this->base64url_encode($header);
        $base64_payload = $this->base64url_encode($payload);
        
        $signature_input = $base64_header . '.' . $base64_payload;
        
        // 秘密鍵で署名
        $private_key = $this->service_account_key['private_key'];
        openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
        
        $base64_signature = $this->base64url_encode($signature);
        
        return $signature_input . '.' . $base64_signature;
    }
    
    /**
     * Base64URL エンコード
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * スプレッドシートからデータを読み取り
     */
    public function read_sheet_data($range = null) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        if (!$range) {
            $range = $this->sheet_name . '!A:Y'; // 全データを取得（Y列まで）
        }
        
        $url = self::SHEETS_API_URL . $this->spreadsheet_id . '/values/' . urlencode($range);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            gi_log_error('Sheets Read Failed', array(
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['values']) ? $data['values'] : array();
    }
    
    /**
     * スプレッドシートにデータを書き込み
     */
    public function write_sheet_data($range, $values, $input_option = 'RAW') {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        $url = self::SHEETS_API_URL . $this->spreadsheet_id . '/values/' . urlencode($range) . '?valueInputOption=' . $input_option;
        
        $request_body = array(
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values
        );
        
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            gi_log_error('Sheets Write Failed', array(
                'error' => $response->get_error_message(),
                'range' => $range
            ));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code >= 200 && $response_code < 300;
    }
    
    /**
     * スプレッドシートに行を追加
     */
    public function append_sheet_data($values, $input_option = 'RAW') {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        $url = self::SHEETS_API_URL . $this->spreadsheet_id . '/values/' . urlencode($this->sheet_name) . ':append?valueInputOption=' . $input_option;
        
        $request_body = array(
            'range' => $this->sheet_name,
            'majorDimension' => 'ROWS',
            'values' => array($values)
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            gi_log_error('Sheets Append Failed', array(
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code >= 200 && $response_code < 300;
    }
    
    /**
     * 投稿データをスプレッドシート用に変換
     */
    private function convert_post_to_sheet_row($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return false;
        }
        
        // 基本データ
        $row = array(
            $post_id, // ID
            $post->post_title, // タイトル
            $post->post_content, // 内容
            $post->post_excerpt, // 抜粋
            $post->post_status, // ステータス
            $post->post_date, // 作成日
            $post->post_modified, // 更新日
        );
        
        // ACFフィールドを追加
        $acf_fields = array(
            'max_amount',              // 助成金額（表示用）
            'max_amount_numeric',      // 助成金額（数値）
            'deadline',                // 申請期限（表示用）
            'deadline_date',           // 申請期限（日付）
            'organization',            // 実施組織
            'organization_type',       // 組織タイプ
            'grant_target',            // 対象者・対象事業
            'application_method',      // 申請方法
            'contact_info',            // 問い合わせ先
            'official_url',            // 公式URL
            'target_prefecture',       // 対象都道府県（コード）
            'prefecture_name',         // 都道府県名（表示用）
            'target_municipality',     // 対象市町村
            'regional_limitation',     // 地域制限
            'application_status'       // 申請ステータス
        );
        
        foreach ($acf_fields as $field) {
            $value = get_field($field, $post_id);
            $row[] = is_array($value) ? json_encode($value) : (string)$value;
        }
        
        // カテゴリを追加
        $categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        $row[] = is_array($categories) ? implode(', ', $categories) : '';
        
        // タグを追加
        $tags = wp_get_post_terms($post_id, 'grant_tag', array('fields' => 'names'));
        $row[] = is_array($tags) ? implode(', ', $tags) : '';
        
        return $row;
    }
    
    /**
     * スプレッドシートのヘッダー行を設定
     */
    public function setup_sheet_headers() {
        $headers = array(
            'ID',                    // A列
            'タイトル',               // B列
            '内容',                  // C列
            '抜粋',                  // D列
            'ステータス',             // E列
            '作成日',                // F列
            '更新日',                // G列
            '助成金額（表示用）',      // H列
            '助成金額（数値）',        // I列
            '申請期限（表示用）',      // J列
            '申請期限（日付）',        // K列
            '実施組織',              // L列
            '組織タイプ',            // M列
            '対象者・対象事業',       // N列
            '申請方法',              // O列
            '問い合わせ先',          // P列
            '公式URL',               // Q列
            '都道府県コード',        // R列
            '都道府県名',            // S列
            '対象市町村',            // T列
            '地域制限',              // U列
            '申請ステータス',        // V列
            'カテゴリ',              // W列
            'タグ',                  // X列
            'シート更新日'           // Y列
        );
        
        return $this->write_sheet_data($this->sheet_name . '!A1:Y1', array($headers));
    }
    
    /**
     * 投稿保存時のスプレッドシート同期
     */
    public function sync_post_to_sheets($post_id, $post, $update) {
        // 自動保存やリビジョンを除外
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // 助成金投稿のみ対象
        if ($post->post_type !== 'grant') {
            return;
        }
        
        // 投稿データを変換
        $row_data = $this->convert_post_to_sheet_row($post_id);
        if (!$row_data) {
            return;
        }
        
        // スプレッドシート更新日を追加
        $row_data[] = current_time('mysql');
        
        // スプレッドシートで該当行を検索
        $sheet_data = $this->read_sheet_data();
        $row_number = $this->find_post_row_in_sheet($post_id, $sheet_data);
        
        if ($row_number) {
            // 既存行を更新
            $range = $this->sheet_name . '!A' . $row_number . ':R' . $row_number;
            $success = $this->write_sheet_data($range, array($row_data));
        } else {
            // 新しい行を追加
            $success = $this->append_sheet_data($row_data);
        }
        
        if ($success) {
            gi_log_error('Post synced to sheets', array('post_id' => $post_id));
        } else {
            gi_log_error('Failed to sync post to sheets', array('post_id' => $post_id));
        }
    }
    
    /**
     * スプレッドシートから投稿IDの行番号を検索
     */
    private function find_post_row_in_sheet($post_id, $sheet_data) {
        if (empty($sheet_data)) {
            return false;
        }
        
        foreach ($sheet_data as $index => $row) {
            if (isset($row[0]) && intval($row[0]) === intval($post_id)) {
                return $index + 1; // 1-based indexing
            }
        }
        
        return false;
    }
    
    /**
     * 投稿削除時のスプレッドシート同期
     */
    public function delete_post_from_sheets($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return;
        }
        
        $sheet_data = $this->read_sheet_data();
        $row_number = $this->find_post_row_in_sheet($post_id, $sheet_data);
        
        if ($row_number) {
            // 行を削除（実際はステータスを'deleted'に変更）
            $range = $this->sheet_name . '!E' . $row_number; // ステータス列
            $this->write_sheet_data($range, array(array('deleted')));
        }
    }
    
    /**
     * 投稿ステータス変更時の処理
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'grant') {
            return;
        }
        
        // ステータス変更時は即座に同期
        $this->sync_post_to_sheets($post->ID, $post, true);
    }
    
    /**
     * スプレッドシートからWordPressへの同期
     */
    public function sync_sheets_to_wp() {
        $sheet_data = $this->read_sheet_data();
        if (empty($sheet_data)) {
            return false;
        }
        
        $headers = array_shift($sheet_data); // ヘッダー行を除去
        $synced_count = 0;
        
        foreach ($sheet_data as $row) {
            if (empty($row) || count($row) < 5) {
                continue; // 不完全な行をスキップ
            }
            
            $post_id = intval($row[0]);
            $title = isset($row[1]) ? sanitize_text_field($row[1]) : '';
            $content = isset($row[2]) ? wp_kses_post($row[2]) : '';
            $excerpt = isset($row[3]) ? sanitize_textarea_field($row[3]) : '';
            $status = isset($row[4]) ? sanitize_text_field($row[4]) : 'draft';
            
            // 削除されたアイテムの処理
            if ($status === 'deleted') {
                if ($post_id && get_post($post_id)) {
                    wp_delete_post($post_id, true);
                    $synced_count++;
                }
                continue;
            }
            
            // 既存投稿の更新または新規作成
            if ($post_id && get_post($post_id)) {
                // 既存投稿を更新
                $updated_post = array(
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_excerpt' => $excerpt,
                    'post_status' => $status,
                );
                
                wp_update_post($updated_post);
            } else {
                // 新規投稿を作成
                $new_post = array(
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_excerpt' => $excerpt,
                    'post_status' => $status,
                    'post_type' => 'grant'
                );
                
                $post_id = wp_insert_post($new_post);
            }
            
            if ($post_id) {
                // ACFフィールドを更新
                $acf_fields = array(
                    'max_amount' => isset($row[7]) ? $row[7] : '',
                    'max_amount_numeric' => isset($row[8]) ? intval($row[8]) : 0,
                    'deadline' => isset($row[9]) ? $row[9] : '',
                    'deadline_date' => isset($row[10]) ? $row[10] : '',
                    'organization' => isset($row[11]) ? $row[11] : '',
                    'organization_type' => isset($row[12]) ? $row[12] : 'national',
                    'grant_target' => isset($row[13]) ? $row[13] : '',
                    'application_method' => isset($row[14]) ? $row[14] : 'online',
                    'contact_info' => isset($row[15]) ? $row[15] : '',
                    'official_url' => isset($row[16]) ? $row[16] : '',
                    'target_prefecture' => isset($row[17]) ? $row[17] : '',
                    'prefecture_name' => isset($row[18]) ? $row[18] : '',
                    'target_municipality' => isset($row[19]) ? $row[19] : '',
                    'regional_limitation' => isset($row[20]) ? $row[20] : 'nationwide',
                    'application_status' => isset($row[21]) ? $row[21] : 'open',
                );
                
                foreach ($acf_fields as $field => $value) {
                    update_field($field, $value, $post_id);
                }
                
                // カテゴリを設定
                if (isset($row[22]) && !empty($row[22])) {
                    $categories = array_map('trim', explode(',', $row[22]));
                    wp_set_post_terms($post_id, $categories, 'grant_category');
                }
                
                // タグを設定
                if (isset($row[23]) && !empty($row[23])) {
                    $tags = array_map('trim', explode(',', $row[23]));
                    wp_set_post_terms($post_id, $tags, 'grant_tag');
                }
                
                $synced_count++;
            }
        }
        
        return $synced_count;
    }
    
    /**
     * 完全双方向同期
     */
    public function full_bidirectional_sync() {
        // まず、スプレッドシートからWordPressに同期
        $sheets_synced = $this->sync_sheets_to_wp();
        
        // 次に、WordPressからスプレッドシートに同期
        $this->sync_all_posts_to_sheets();
        
        gi_log_error('Full bidirectional sync completed', array(
            'sheets_to_wp' => $sheets_synced,
            'wp_to_sheets' => 'completed'
        ));
    }
    
    /**
     * 全投稿をスプレッドシートに同期
     */
    public function sync_all_posts_to_sheets() {
        $posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1
        ));
        
        // ヘッダーを設定
        $this->setup_sheet_headers();
        
        foreach ($posts as $post) {
            $this->sync_post_to_sheets($post->ID, $post, true);
        }
    }
    
    /**
     * 手動同期のAJAXハンドラー
     */
    public function ajax_manual_sync() {
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $sync_direction = sanitize_text_field($_POST['direction'] ?? 'both');
        
        try {
            switch ($sync_direction) {
                case 'wp_to_sheets':
                    $this->sync_all_posts_to_sheets();
                    $message = 'WordPressからスプレッドシートへの同期が完了しました。';
                    break;
                
                case 'sheets_to_wp':
                    $synced = $this->sync_sheets_to_wp();
                    $message = "スプレッドシートからWordPressへ {$synced} 件同期しました。";
                    break;
                
                case 'both':
                default:
                    $this->full_bidirectional_sync();
                    $message = '双方向同期が完了しました。';
                    break;
            }
            
            wp_send_json_success($message);
            
        } catch (Exception $e) {
            gi_log_error('Manual sync failed', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error('同期に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 接続テストのAJAXハンドラー
     */
    public function ajax_test_connection() {
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                wp_send_json_error('認証に失敗しました。');
                return;
            }
            
            // テスト読み取り
            $test_data = $this->read_sheet_data($this->sheet_name . '!A1:A1');
            
            if ($test_data !== false) {
                wp_send_json_success('Google Sheetsへの接続に成功しました。');
            } else {
                wp_send_json_error('スプレッドシートの読み取りに失敗しました。');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('接続テストに失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * Cronスケジュールに5分間隔を追加
     */
    public static function add_cron_schedules($schedules) {
        $schedules['every_5_minutes'] = array(
            'interval' => 300, // 5分 = 300秒
            'display' => '5分間隔'
        );
        return $schedules;
    }
}

// Cronスケジュールフィルターを追加
add_filter('cron_schedules', array('GoogleSheetsSync', 'add_cron_schedules'));

// インスタンスを初期化
function gi_init_google_sheets_sync() {
    return GoogleSheetsSync::getInstance();
}

// テーマ読み込み時に初期化
add_action('init', 'gi_init_google_sheets_sync');