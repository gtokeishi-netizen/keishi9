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
        // 自動同期が有効な場合のみフックを追加
        if ($this->is_auto_sync_enabled()) {
            // 投稿の保存・更新時にスプレッドシートを更新
            add_action('save_post_grant', array($this, 'sync_post_to_sheets'), 10, 3);
            
            // 投稿の削除時にスプレッドシートからも削除
            add_action('before_delete_post', array($this, 'delete_post_from_sheets'));
            
            // 投稿ステータス変更時の同期
            add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        }
        
        // 定期的な双方向同期（設定に応じて）
        if ($this->is_scheduled_sync_enabled()) {
            add_action('gi_sheets_sync_cron', array($this, 'full_bidirectional_sync'));
            
            // Cronスケジュールの設定
            if (!wp_next_scheduled('gi_sheets_sync_cron')) {
                $interval = $this->get_sync_interval();
                wp_schedule_event(time(), $interval, 'gi_sheets_sync_cron');
            }
        } else {
            // 自動同期が無効の場合はCronを削除
            wp_clear_scheduled_hook('gi_sheets_sync_cron');
        }
        
        // AJAX ハンドラー（常に有効）
        add_action('wp_ajax_gi_manual_sheets_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_gi_test_sheets_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_gi_setup_field_validation', array($this, 'ajax_setup_field_validation'));
        add_action('wp_ajax_gi_test_specific_fields', array($this, 'ajax_test_specific_fields'));
        add_action('wp_ajax_gi_update_sync_settings', array($this, 'ajax_update_sync_settings'));
        add_action('wp_ajax_gi_get_sync_settings', array($this, 'ajax_get_sync_settings'));
    }
    
    /**
     * Google Sheets APIアクセストークンを取得
     */
    private function get_access_token() {
        gi_log_error('Getting access token', array(
            'has_existing_token' => !empty($this->access_token),
            'token_expires_at' => $this->token_expires_at,
            'current_time' => time(),
            'token_still_valid' => ($this->token_expires_at && time() < ($this->token_expires_at - 300))
        ));
        
        // 既存のトークンが有効な場合はそれを使用
        if ($this->access_token && $this->token_expires_at && time() < ($this->token_expires_at - 300)) {
            gi_log_error('Using existing valid token');
            return $this->access_token;
        }
        
        gi_log_error('Generating new access token');
        
        // JWTを作成
        $jwt = $this->create_jwt();
        if (!$jwt) {
            gi_log_error('JWT creation failed');
            return false;
        }
        
        gi_log_error('JWT created successfully', array('jwt_length' => strlen($jwt)));
        
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
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        gi_log_error('Token request response', array(
            'response_code' => $response_code,
            'body' => $body
        ));
        
        $token_data = json_decode($body, true);
        
        if (!isset($token_data['access_token'])) {
            gi_log_error('Invalid Token Response', array(
                'response_code' => $response_code,
                'response' => $body,
                'parsed_data' => $token_data
            ));
            return false;
        }
        
        // トークンを保存
        $this->access_token = $token_data['access_token'];
        $this->token_expires_at = time() + ($token_data['expires_in'] - 300); // 5分早めに期限切れとする
        
        update_option('gi_sheets_access_token', $this->access_token);
        update_option('gi_sheets_token_expires', $this->token_expires_at);
        
        gi_log_error('New access token obtained and saved', array(
            'expires_at' => $this->token_expires_at,
            'expires_in' => $token_data['expires_in']
        ));
        
        return $this->access_token;
    }
    
    /**
     * シート名を取得
     */
    public function get_sheet_name() {
        return $this->sheet_name;
    }
    
    /**
     * スプレッドシートIDを取得
     */
    public function get_spreadsheet_id() {
        return $this->spreadsheet_id;
    }
    
    /**
     * JWT（JSON Web Token）を作成
     */
    private function create_jwt() {
        try {
            gi_log_error('Creating JWT', array(
                'client_email' => $this->service_account_key['client_email'],
                'has_private_key' => !empty($this->service_account_key['private_key'])
            ));
            
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
            
            gi_log_error('JWT payload created', array(
                'iss' => $this->service_account_key['client_email'],
                'scope' => self::AUTH_SCOPE,
                'now' => $now,
                'exp' => $now + 3600
            ));
            
            $base64_header = $this->base64url_encode($header);
            $base64_payload = $this->base64url_encode($payload);
            
            $signature_input = $base64_header . '.' . $base64_payload;
            
            // 秘密鍵で署名
            $private_key = $this->service_account_key['private_key'];
            
            if (empty($private_key)) {
                gi_log_error('Private key is empty');
                return false;
            }
            
            // OpenSSL署名の実行
            $sign_result = openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
            
            if (!$sign_result) {
                gi_log_error('OpenSSL signing failed', array(
                    'openssl_error' => openssl_error_string(),
                    'private_key_length' => strlen($private_key)
                ));
                return false;
            }
            
            gi_log_error('JWT signing successful', array(
                'signature_length' => strlen($signature)
            ));
            
            $base64_signature = $this->base64url_encode($signature);
            
            $final_jwt = $signature_input . '.' . $base64_signature;
            
            gi_log_error('JWT created successfully', array(
                'jwt_length' => strlen($final_jwt)
            ));
            
            return $final_jwt;
            
        } catch (Exception $e) {
            gi_log_error('JWT creation failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            return false;
        }
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
        gi_log_error('Starting read_sheet_data', array('requested_range' => $range));
        
        $access_token = $this->get_access_token();
        if (!$access_token) {
            gi_log_error('read_sheet_data: No access token available');
            return false;
        }
        
        if (!$range) {
            $range = $this->get_sheet_name() . '!A:AE'; // 全データを取得（AE列まで）31列対応
        }
        
        gi_log_error('Reading from sheets', array(
            'range' => $range,
            'spreadsheet_id' => $this->spreadsheet_id
        ));
        
        $url = self::SHEETS_API_URL . $this->spreadsheet_id . '/values/' . urlencode($range);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            gi_log_error('Sheets Read Request Failed', array(
                'error' => $response->get_error_message(),
                'url' => $url
            ));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        gi_log_error('Sheets read response', array(
            'response_code' => $response_code,
            'body_length' => strlen($body)
        ));
        
        if ($response_code !== 200) {
            gi_log_error('Sheets Read Failed - Bad Response Code', array(
                'response_code' => $response_code,
                'response_body' => $body
            ));
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            gi_log_error('JSON decode failed', array(
                'json_error' => json_last_error_msg(),
                'response_body' => $body
            ));
            return false;
        }
        
        $values = isset($data['values']) ? $data['values'] : array();
        
        gi_log_error('Read sheet data completed', array(
            'rows_count' => count($values),
            'first_row_columns' => !empty($values) ? count($values[0]) : 0
        ));
        
        return $values;
    }
    
    /**
     * スプレッドシートにデータを書き込み
     */
    public function write_sheet_data($range, $values, $input_option = 'RAW') {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            gi_log_error('Write Sheet Data: No access token available');
            return false;
        }
        
        gi_log_error('Writing to sheets', array(
            'range' => $range,
            'values_count' => count($values),
            'spreadsheet_id' => $this->spreadsheet_id,
            'sheet_name' => $this->sheet_name
        ));
        
        $url = self::SHEETS_API_URL . $this->spreadsheet_id . '/values/' . urlencode($range) . '?valueInputOption=' . $input_option;
        
        $request_body = array(
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values
        );
        
        gi_log_error('Sheets API request details', array(
            'url' => $url,
            'request_body' => $request_body
        ));
        
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
            gi_log_error('Sheets Write Request Failed', array(
                'error' => $response->get_error_message(),
                'range' => $range,
                'url' => $url
            ));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        gi_log_error('Sheets write response', array(
            'response_code' => $response_code,
            'response_body' => $response_body,
            'range' => $range
        ));
        
        if ($response_code < 200 || $response_code >= 300) {
            gi_log_error('Sheets Write Failed - Bad Response Code', array(
                'response_code' => $response_code,
                'response_body' => $response_body,
                'range' => $range
            ));
            return false;
        }
        
        gi_log_error('Sheets write successful', array('range' => $range));
        return true;
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
        try {
            gi_log_error('Converting post to sheet row', array('post_id' => $post_id));
            
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'grant') {
                gi_log_error('Invalid post for conversion', array('post_id' => $post_id, 'post_type' => $post ? $post->post_type : 'null'));
                return false;
            }
            
            // 基本データ (A-G列)
            $row = array(
                $post_id, // A: ID
                $post->post_title, // B: タイトル
                wp_strip_all_tags($post->post_content), // C: 内容（HTMLタグを除去）
                $post->post_excerpt, // D: 抜粋
                $post->post_status, // E: ステータス
                $post->post_date, // F: 作成日
                $post->post_modified, // G: 更新日
            );
            
            // ACFフィールドを追加 (H-Q列)
            $acf_fields = array(
                'max_amount',              // H: 助成金額（表示用）
                'max_amount_numeric',      // I: 助成金額（数値）
                'deadline',                // J: 申請期限（表示用）
                'deadline_date',           // K: 申請期限（日付）
                'organization',            // L: 実施組織
                'organization_type',       // M: 組織タイプ
                'grant_target',            // N: 対象者・対象事業
                'application_method',      // O: 申請方法
                'contact_info',            // P: 問い合わせ先
                'official_url'             // Q: 公式URL
            );
            
            foreach ($acf_fields as $field) {
                $value = get_field($field, $post_id);
                $row[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
            }
            
            // R列: 地域制限 (ACFフィールド)
            $regional_limitation = get_field('regional_limitation', $post_id);
            $row[] = (string)$regional_limitation;
            
            // S列: 申請ステータス (ACFフィールド)
            $application_status = get_field('application_status', $post_id);
            $row[] = (string)$application_status;
            
            // T列: 都道府県 (タクソノミー) ★完全連携
            $prefectures = wp_get_post_terms($post_id, 'grant_prefecture', array('fields' => 'names'));
            $row[] = (is_array($prefectures) && !is_wp_error($prefectures)) ? implode(', ', $prefectures) : '';
            
            // U列: 市町村 (タクソノミー) ★完全連携
            $municipalities = wp_get_post_terms($post_id, 'grant_municipality', array('fields' => 'names'));
            $row[] = (is_array($municipalities) && !is_wp_error($municipalities)) ? implode(', ', $municipalities) : '';
            
            // V列: カテゴリ (タクソノミー) ★完全連携
            $categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
            $row[] = (is_array($categories) && !is_wp_error($categories)) ? implode(', ', $categories) : '';
            
            // W列: タグ (タクソノミー) ★完全連携
            $tags = wp_get_post_terms($post_id, 'grant_tag', array('fields' => 'names'));
            $row[] = (is_array($tags) && !is_wp_error($tags)) ? implode(', ', $tags) : '';
            
            // 新規フィールド (X-AD列) ★31列対応
            $new_acf_fields = array(
                'external_link',           // X: 外部リンク
                'region_notes',            // Y: 地域に関する備考
                'required_documents',      // Z: 必要書類
                'adoption_rate',           // AA: 採択率（%）
                'application_difficulty',  // AB: 申請難易度
                'target_expenses',         // AC: 対象経費
                'subsidy_rate'             // AD: 補助率
            );
            
            foreach ($new_acf_fields as $field) {
                $value = get_field($field, $post_id);
                $row[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
            }
            
            // AE列: シート更新日
            $row[] = current_time('mysql');
            
            gi_log_error('Post converted to sheet row successfully', array('post_id' => $post_id, 'columns' => count($row)));
            
            return $row;
            
        } catch (Exception $e) {
            gi_log_error('convert_post_to_sheet_row failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return false;
        }
    }
    
    /**
     * スプレッドシートのヘッダー行を設定
     */
    public function setup_sheet_headers() {
        try {
            gi_log_error('Setting up sheet headers');
            
            $headers = array(
                'ID (自動入力)',                          // A列 - WordPress投稿ID
                'タイトル',                               // B列 - 助成金名
                '内容・詳細',                            // C列 - 助成金の詳細説明
                '抜粋・概要',                            // D列 - 簡単な概要
                'ステータス (draft/publish/private)',     // E列 - 投稿ステータス
                '作成日 (自動入力)',                      // F列 - WordPress作成日
                '更新日 (自動入力)',                      // G列 - WordPress更新日
                '助成金額 (例: 300万円)',                 // H列 - 表示用金額
                '助成金額数値 (例: 3000000)',             // I列 - ソート用数値
                '申請期限 (例: 令和6年3月31日)',          // J列 - 表示用期限
                '申請期限日付 (YYYY-MM-DD)',             // K列 - ソート用日付
                '実施組織名',                            // L列 - 実施する組織名
                '組織タイプ (national/prefecture/city/public_org/private_org/other)', // M列 - 組織分類
                '対象者・対象事業',                      // N列 - 助成対象の詳細
                '申請方法 (online/mail/visit/mixed)',     // O列 - 申請方法
                '問い合わせ先',                          // P列 - 連絡先情報
                '公式URL',                               // Q列 - 公式サイトURL
                '地域制限 (nationwide/prefecture_only/municipality_only/region_group/specific_area)', // R列 - 地域制限タイプ
                '申請ステータス (open/upcoming/closed/suspended)', // S列 - 募集状況
                '都道府県 (例: 東京都)',                  // T列 - 都道府県名 ★完全連携
                '市町村 (例: 新宿区,渋谷区)',            // U列 - 市町村名 ★完全連携
                'カテゴリ (例: ビジネス支援,IT関連)',     // V列 - 分類カテゴリ ★完全連携
                'タグ (例: スタートアップ,中小企業)',     // W列 - タグ ★完全連携
                '外部リンク',                            // X列 - 参考リンク
                '地域に関する備考',                      // Y列 - 地域制限の詳細
                '必要書類',                              // Z列 - 申請に必要な書類
                '採択率（%）',                          // AA列 - 採択率の数値
                '申請難易度 (easy/normal/hard/very_hard)', // AB列 - 難易度評価
                '対象経費',                              // AC列 - 補助対象経費の詳細
                '補助率 (例: 2/3, 50%)',                // AD列 - 補助率・補助割合
                'シート更新日 (自動入力)'                // AE列 - 最終同期日時
            );
            
            gi_log_error('Headers array created', array('count' => count($headers)));
            
            $range = $this->sheet_name . '!A1:AE1';
            gi_log_error('Writing headers to range', array('range' => $range));
            
            $result = $this->write_sheet_data($range, array($headers));
            
            gi_log_error('Headers setup result', array('success' => $result));
            
            return $result;
            
        } catch (Exception $e) {
            gi_log_error('setup_sheet_headers failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return false;
        }
    }
    
    /**
     * 投稿保存時のスプレッドシート同期
     */
    public function sync_post_to_sheets($post_id, $post, $update) {
        try {
            gi_log_error('sync_post_to_sheets started', array('post_id' => $post_id, 'post_type' => $post->post_type));
            
            // 自動保存やリビジョンを除外
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                gi_log_error('Skipping autosave', array('post_id' => $post_id));
                return;
            }
            
            if (wp_is_post_revision($post_id)) {
                gi_log_error('Skipping revision', array('post_id' => $post_id));
                return;
            }
            
            // 助成金投稿のみ対象
            if ($post->post_type !== 'grant') {
                gi_log_error('Skipping non-grant post', array('post_id' => $post_id, 'post_type' => $post->post_type));
                return;
            }
            
            // 投稿データを変換
            gi_log_error('Converting post to sheet row', array('post_id' => $post_id));
            $row_data = $this->convert_post_to_sheet_row($post_id);
            if (!$row_data) {
                throw new Exception('Failed to convert post data to sheet row');
            }
            
            gi_log_error('Row data converted', array('post_id' => $post_id, 'columns' => count($row_data)));
            
            // スプレッドシートで該当行を検索
            gi_log_error('Reading sheet data to find existing row');
            $sheet_data = $this->read_sheet_data();
            
            if ($sheet_data === false) {
                throw new Exception('Failed to read sheet data');
            }
            
            gi_log_error('Sheet data read', array('rows' => count($sheet_data)));
            
            $row_number = $this->find_post_row_in_sheet($post_id, $sheet_data);
            gi_log_error('Row search result', array('post_id' => $post_id, 'row_number' => $row_number));
            
            if ($row_number) {
                // 既存行を更新 - 31列対応（AE列まで）
                $range = $this->sheet_name . '!A' . $row_number . ':AE' . $row_number;
                gi_log_error('Updating existing row', array('post_id' => $post_id, 'range' => $range));
                $success = $this->write_sheet_data($range, array($row_data));
            } else {
                // 新しい行を追加
                gi_log_error('Appending new row', array('post_id' => $post_id));
                $success = $this->append_sheet_data($row_data);
            }
            
            if ($success) {
                gi_log_error('Post synced to sheets successfully', array('post_id' => $post_id));
            } else {
                throw new Exception('Failed to write data to sheets');
            }
            
        } catch (Exception $e) {
            gi_log_error('sync_post_to_sheets failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            throw $e; // Re-throw to propagate the error up
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
        try {
            gi_log_error('Starting sync_sheets_to_wp');
            
            $sheet_data = $this->read_sheet_data();
            if (empty($sheet_data)) {
                gi_log_error('No sheet data found');
                return 0;
            }
            
            gi_log_error('Sheet data retrieved', array('row_count' => count($sheet_data)));
            
            $headers = array_shift($sheet_data); // ヘッダー行を除去
            $synced_count = 0;
            $new_post_ids_to_update = array(); // 新規作成された投稿のIDと行番号を記録
        
        foreach ($sheet_data as $row_index => $row) {
            if (empty($row) || count($row) < 5) {
                continue; // 不完全な行をスキップ
            }
            
            $original_post_id = intval($row[0]); // 元のpost_id（空の場合は0）
            $post_id = $original_post_id;
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
            
            $was_new_post = false; // 新規投稿かどうかのフラグ
            
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
                gi_log_error('Updated existing post', array('post_id' => $post_id, 'title' => $title));
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
                $was_new_post = true;
                
                if ($post_id && !is_wp_error($post_id)) {
                    // 新規投稿が作成されたので、後でスプレッドシートのA列を更新する必要がある
                    $sheet_row_number = $row_index + 2; // ヘッダー行を考慮して+2（配列は0ベース、Sheetsは1ベース+ヘッダー）
                    $new_post_ids_to_update[$sheet_row_number] = $post_id;
                    gi_log_error('Created new post, will update spreadsheet', array(
                        'post_id' => $post_id, 
                        'title' => $title, 
                        'sheet_row' => $sheet_row_number
                    ));
                }
            }
            
            if ($post_id && !is_wp_error($post_id)) {
                // ACFフィールドを更新（タクソノミー化されたフィールドは除外）
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
                    'regional_limitation' => isset($row[17]) ? $row[17] : 'nationwide', // 新R列
                    'application_status' => isset($row[18]) ? $row[18] : 'open', // 新S列
                );
                
                // ACFフィールドの同期ログ
                gi_log_error('Syncing ACF fields', array(
                    'post_id' => $post_id,
                    'row_index' => $row_index,
                    'acf_fields_count' => count($acf_fields),
                    'row_length' => count($row)
                ));
                
                // ACFフィールドを更新
                foreach ($acf_fields as $field => $value) {
                    $update_result = update_field($field, $value, $post_id);
                }
                
                // タクソノミーデータの同期（都道府県・市町村・カテゴリー）
                
                // ★完全連携: スプレッドシートからタクソノミーデータを同期
                
                // 都道府県を設定（T列のデータから） ★完全連携
                if (isset($row[19]) && !empty($row[19])) {
                    $prefectures = array_map('trim', explode(',', $row[19]));
                    $prefecture_result = wp_set_post_terms($post_id, $prefectures, 'grant_prefecture');
                    
                    gi_log_error('Prefecture sync result', array(
                        'post_id' => $post_id,
                        'raw_prefecture_data' => $row[19],
                        'prefectures_array' => $prefectures,
                        'set_terms_result' => $prefecture_result
                    ));
                }
                
                // 市町村を設定（U列のデータから） ★完全連携
                if (isset($row[20]) && !empty($row[20])) {
                    $municipalities = array_map('trim', explode(',', $row[20]));
                    $municipality_result = wp_set_post_terms($post_id, $municipalities, 'grant_municipality');
                    
                    gi_log_error('Municipality sync result', array(
                        'post_id' => $post_id,
                        'raw_municipality_data' => $row[20],
                        'municipalities_array' => $municipalities,
                        'set_terms_result' => $municipality_result
                    ));
                }
                
                // カテゴリを設定（V列のデータから） ★完全連携
                if (isset($row[21]) && !empty($row[21])) {
                    $categories = array_map('trim', explode(',', $row[21]));
                    $category_result = wp_set_post_terms($post_id, $categories, 'grant_category');
                    
                    gi_log_error('Category sync result', array(
                        'post_id' => $post_id,
                        'raw_category_data' => $row[21],
                        'categories_array' => $categories,
                        'set_terms_result' => $category_result
                    ));
                }
                
                // タグを設定（W列のデータから） ★完全連携
                if (isset($row[22]) && !empty($row[22])) {
                    $tags = array_map('trim', explode(',', $row[22]));
                    wp_set_post_terms($post_id, $tags, 'grant_tag');
                }
                
                // 新規ACFフィールドの同期 (X-AD列) ★31列対応
                $new_acf_fields = array(
                    'external_link' => isset($row[23]) ? $row[23] : '',           // X列: 外部リンク
                    'region_notes' => isset($row[24]) ? $row[24] : '',            // Y列: 地域に関する備考
                    'required_documents' => isset($row[25]) ? $row[25] : '',      // Z列: 必要書類
                    'adoption_rate' => isset($row[26]) ? floatval($row[26]) : 0,  // AA列: 採択率（%）
                    'application_difficulty' => isset($row[27]) ? $row[27] : 'normal', // AB列: 申請難易度
                    'target_expenses' => isset($row[28]) ? $row[28] : '',         // AC列: 対象経費
                    'subsidy_rate' => isset($row[29]) ? $row[29] : '',            // AD列: 補助率
                );
                
                // 新規ACFフィールドを更新
                foreach ($new_acf_fields as $field => $value) {
                    $update_result = update_field($field, $value, $post_id);
                    gi_log_error('New ACF field updated', array(
                        'post_id' => $post_id,
                        'field' => $field,
                        'value' => $value,
                        'update_result' => $update_result
                    ));
                }
                
                $synced_count++;
            }
        }
        
        // 新規作成された投稿のIDをスプレッドシートに書き戻し
        if (!empty($new_post_ids_to_update)) {
            gi_log_error('Updating spreadsheet with new post IDs', array('count' => count($new_post_ids_to_update)));
            
            foreach ($new_post_ids_to_update as $sheet_row => $new_post_id) {
                try {
                    // A列（post_id列）のみを更新
                    $range = $this->get_sheet_name() . '!A' . $sheet_row;
                    $success = $this->write_sheet_data($range, array(array($new_post_id)));
                    
                    if ($success) {
                        gi_log_error('Updated post ID in spreadsheet', array(
                            'post_id' => $new_post_id, 
                            'row' => $sheet_row, 
                            'range' => $range
                        ));
                    } else {
                        gi_log_error('Failed to update post ID in spreadsheet', array(
                            'post_id' => $new_post_id, 
                            'row' => $sheet_row
                        ));
                    }
                } catch (Exception $e) {
                    gi_log_error('Exception while updating post ID in spreadsheet', array(
                        'post_id' => $new_post_id,
                        'row' => $sheet_row,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        gi_log_error('sync_sheets_to_wp completed', array(
            'synced_count' => $synced_count,
            'new_posts_updated' => count($new_post_ids_to_update)
        ));
        return $synced_count;
        
        } catch (Exception $e) {
            gi_log_error('sync_sheets_to_wp failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            throw $e;
        }
    }
    
    /**
     * 完全双方向同期
     */
    public function full_bidirectional_sync() {
        gi_log_error('Starting full bidirectional sync');
        
        try {
            // Step 1: スプレッドシートからWordPressに同期（既存データの更新）
            gi_log_error('Step 1: Sheets to WordPress sync');
            $sheets_synced = 0;
            try {
                $sheets_synced = $this->sync_sheets_to_wp();
                gi_log_error('Sheets to WP sync completed', array('sheets_synced' => $sheets_synced));
            } catch (Exception $e) {
                gi_log_error('Sheets to WP sync failed, continuing with WP to Sheets', array(
                    'error' => $e->getMessage()
                ));
                // スプレッドシート→WordPress同期が失敗しても、WordPress→スプレッドシート同期は実行
            }
            
            // Step 2: WordPressからスプレッドシートに同期（新規データの追加）
            gi_log_error('Step 2: WordPress to Sheets sync');
            $wp_synced = $this->sync_all_posts_to_sheets();
            
            $result = array(
                'sheets_to_wp' => $sheets_synced,
                'wp_to_sheets' => $wp_synced,
                'total_synced' => $sheets_synced + $wp_synced
            );
            
            // 同期結果をログに記録
            $this->log_sync_result('scheduled', 'success', 
                "双方向同期完了: Sheets→WP({$sheets_synced}件), WP→Sheets({$wp_synced}件)");
            
            gi_log_error('Full bidirectional sync completed', $result);
            
            return $result;
            
        } catch (Exception $e) {
            // 同期失敗をログに記録
            $this->log_sync_result('scheduled', 'failed', $e->getMessage());
            
            gi_log_error('Full bidirectional sync failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            throw $e;
        }
    }
    
    /**
     * 全投稿をスプレッドシートに同期
     */
    public function sync_all_posts_to_sheets() {
        gi_log_error('Starting sync_all_posts_to_sheets');
        
        // 全件取得（バッチ処理で分割して同期）
        $posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1
        ));
        
        gi_log_error('Found posts to sync', array('count' => count($posts)));
        
        if (empty($posts)) {
            gi_log_error('No posts found to sync');
            return 0;
        }
        
        // まず既存データをクリア
        gi_log_error('Clearing existing sheet data');
        $clear_result = $this->clear_sheet_range('A:AE'); // 31列対応
        gi_log_error('Clear result', array('success' => $clear_result));
        
        // ヘッダーを設定
        gi_log_error('Setting up sheet headers');
        $header_result = $this->setup_sheet_headers();
        gi_log_error('Header setup result', array('success' => $header_result));
        
        if (!$header_result) {
            throw new Exception('ヘッダーの設定に失敗しました');
        }
        
        // バッチサイズを設定（Google Sheets APIの制限を考慮）
        $batch_size = 100; // 一度に100件まで
        $total_synced = 0;
        $all_data = array();
        
        // 全データを準備
        foreach ($posts as $post) {
            try {
                gi_log_error('Preparing post data', array('post_id' => $post->ID, 'title' => $post->post_title));
                $row_data = $this->convert_post_to_sheet_row($post->ID);
                if ($row_data) {
                    $all_data[] = $row_data;
                }
            } catch (Exception $e) {
                gi_log_error('Failed to prepare individual post', array(
                    'post_id' => $post->ID,
                    'error' => $e->getMessage()
                ));
                // 個別の投稿の失敗では全体を停止させない
                continue;
            }
        }
        
        gi_log_error('Prepared all data', array('total_posts' => count($all_data)));
        
        // バッチごとに分割して書き込み
        if (!empty($all_data)) {
            $batches = array_chunk($all_data, $batch_size);
            $sheet_name = $this->get_sheet_name();
            $current_row = 2; // ヘッダー行の次から
            
            foreach ($batches as $batch_index => $batch_data) {
                gi_log_error('Processing batch', array(
                    'batch_index' => $batch_index + 1,
                    'batch_size' => count($batch_data),
                    'start_row' => $current_row
                ));
                
                $end_row = $current_row + count($batch_data) - 1;
                $range = $sheet_name . "!A{$current_row}:AE{$end_row}"; // 31列対応
                
                $result = $this->write_sheet_data($range, $batch_data);
                
                if ($result) {
                    $total_synced += count($batch_data);
                    $current_row = $end_row + 1;
                    gi_log_error('Batch write successful', array(
                        'batch_synced' => count($batch_data),
                        'total_synced' => $total_synced
                    ));
                } else {
                    gi_log_error('Batch write failed', array('batch_index' => $batch_index + 1));
                    throw new Exception("バッチ " . ($batch_index + 1) . " の書き込みに失敗しました");
                }
                
                // API制限を考慮して少し待機
                if (count($batches) > 1 && $batch_index < count($batches) - 1) {
                    sleep(1);
                }
            }
            
            gi_log_error('All batches completed', array('total_synced' => $total_synced));
            return $total_synced;
        }
        
        gi_log_error('No data to sync');
        return 0;
    }
    
    /**
     * 手動同期のAJAXハンドラー
     */
    public function ajax_manual_sync() {
        // タイムアウトとメモリ制限の拡張
        set_time_limit(300); // 5分
        ini_set('memory_limit', '256M');
        
        // 全体をtry-catchでラップして500エラーを防ぐ
        try {
            // デバッグ: AJAXリクエストが到達したことをログに記録
            gi_log_error('AJAX manual sync request received', array(
                'user_id' => get_current_user_id(),
                'post_data' => $_POST,
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN'
            ));
            
            // Nonce検証
            try {
                check_ajax_referer('gi_sheets_nonce', 'nonce');
                gi_log_error('Nonce verification passed');
            } catch (Exception $e) {
                gi_log_error('Nonce verification failed', array('error' => $e->getMessage()));
                wp_send_json_error('Nonce verification failed: ' . $e->getMessage());
                return;
            }
            
            // 権限チェック
            if (!current_user_can('edit_posts')) {
                gi_log_error('Permission denied', array('user_id' => get_current_user_id()));
                wp_send_json_error('Permission denied');
                return;
            }
            
            gi_log_error('Permission check passed');
            
            // 同期方向を取得
            $sync_direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'both';
            gi_log_error('Sync direction determined', array('direction' => $sync_direction));
            
            // 同期処理を実行
            gi_log_error('Manual sync started', array('direction' => $sync_direction));
            
            switch ($sync_direction) {
                case 'wp_to_sheets':
                    gi_log_error('Starting WP to Sheets sync');
                    $this->sync_all_posts_to_sheets();
                    $message = 'WordPressからスプレッドシートへの同期が完了しました。';
                    break;
                
                case 'sheets_to_wp':
                    gi_log_error('Starting Sheets to WP sync');
                    $synced = $this->sync_sheets_to_wp();
                    $message = "スプレッドシートからWordPressへ {$synced} 件同期しました。";
                    break;
                
                case 'both':
                default:
                    gi_log_error('Starting bidirectional sync');
                    $result = $this->full_bidirectional_sync();
                    $message = "双方向同期が完了しました。Sheets→WP: {$result['sheets_to_wp']}件、WP→Sheets: {$result['wp_to_sheets']}件";
                    break;
            }
            
            // 同期結果をログに記録
            $this->log_sync_result('manual', 'success', $message);
            
            gi_log_error('Manual sync completed successfully');
            wp_send_json_success($message);
            
        } catch (Exception $e) {
            // 同期失敗をログに記録
            $this->log_sync_result('manual', 'failed', $e->getMessage());
            
            gi_log_error('Manual sync exception caught', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('同期に失敗しました: ' . $e->getMessage());
            
        } catch (Error $e) {
            gi_log_error('Manual sync fatal error caught', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('同期中に致命的エラーが発生しました: ' . $e->getMessage());
            
        } catch (Throwable $e) {
            // PHP 7+ のすべてのエラーをキャッチ
            gi_log_error('Manual sync throwable caught', array(
                'error' => $e->getMessage(),
                'file' => method_exists($e, 'getFile') ? $e->getFile() : 'unknown',
                'line' => method_exists($e, 'getLine') ? $e->getLine() : 'unknown',
                'trace' => method_exists($e, 'getTraceAsString') ? $e->getTraceAsString() : 'no trace'
            ));
            wp_send_json_error('予期しないエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 接続テストのAJAXハンドラー
     */
    public function ajax_test_connection() {
        // デバッグ: 接続テストリクエストが到達
        gi_log_error('AJAX test connection request received', array(
            'user_id' => get_current_user_id(),
            'post_data' => $_POST
        ));
        
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            gi_log_error('Permission denied for connection test', array('user_id' => get_current_user_id()));
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
     * スプレッドシートの範囲をクリア
     */
    public function clear_sheet_range($range) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            gi_log_error('Failed to get access token for clear operation');
            return false;
        }
        
        $url = self::SHEETS_API_URL . $this->spreadsheet_id . '/values/' . urlencode($this->sheet_name . '!' . $range) . ':clear';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => '{}',
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            gi_log_error('Clear Sheet Range Request Failed', array(
                'error' => $response->get_error_message(),
                'range' => $range
            ));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            gi_log_error('Clear Sheet Range Failed', array(
                'response_code' => $response_code,
                'response_body' => wp_remote_retrieve_body($response),
                'range' => $range
            ));
            return false;
        }
        
        gi_log_error('Sheet range cleared successfully', array('range' => $range));
        return true;
    }
    
    /**
     * 📋 フィールドバリデーション設定のAJAXハンドラー (31列完全対応)
     */
    public function ajax_setup_field_validation() {
        // タイムアウトとメモリ制限の拡張
        set_time_limit(300); // 5分
        ini_set('memory_limit', '256M');
        
        try {
            gi_log_error('AJAX field validation setup request received', array(
                'user_id' => get_current_user_id(),
                'post_data' => $_POST
            ));
            
            // Nonce検証
            check_ajax_referer('gi_sheets_nonce', 'nonce');
            
            // 権限チェック
            if (!current_user_can('edit_posts')) {
                gi_log_error('Permission denied for field validation setup', array('user_id' => get_current_user_id()));
                wp_send_json_error('権限がありません');
                return;
            }
            
            gi_log_error('Setting up comprehensive field validation for 31-column structure');
            
            // 完全な31列フィールドマッピング情報を取得
            $field_mappings = $this->get_field_validation_mappings();
            
            // バリデーション統計情報を準備
            $validation_stats = array(
                'total_fields' => count($field_mappings),
                'field_types' => array(),
                'validation_fields' => array(),
                'taxonomy_fields' => array(),
                'readonly_fields' => array()
            );
            
            foreach ($field_mappings as $column => $field) {
                $type = $field['type'];
                $validation_stats['field_types'][$type] = ($validation_stats['field_types'][$type] ?? 0) + 1;
                
                if ($type === 'select' || $type === 'number') {
                    $validation_stats['validation_fields'][] = $column . '(' . $field['field_name'] . ')';
                }
                
                if ($type === 'taxonomy') {
                    $validation_stats['taxonomy_fields'][] = $column . '(' . $field['field_name'] . ')';
                }
                
                if ($type === 'readonly') {
                    $validation_stats['readonly_fields'][] = $column . '(' . $field['field_name'] . ')';
                }
            }
            
            // Google Apps Scriptでの設定情報
            $validation_info = array(
                'spreadsheet_id' => $this->spreadsheet_id,
                'sheet_name' => $this->sheet_name,
                'field_mappings' => $field_mappings,
                'validation_stats' => $validation_stats,
                'column_range' => 'A:AE', // 31列対応
                'setup_instructions' => array(
                    'step1' => '🔗 スプレッドシートを開く',
                    'step2' => '📋 メニューから「🏛️ 助成金管理システム」→「WordPress連携」→「🔧 フィールドバリデーション設定」を選択',
                    'step3' => '✅ 31列全体のバリデーション設定が自動実行されます',
                    'step4' => '🎨 設定完了後、選択肢フィールド（E, M, O, R, S, AB列）が青色背景で表示',
                    'step5' => '🔢 数値フィールド（I, AA列）に範囲制限が適用',
                    'step6' => '🔒 読み取り専用フィールド（A, F, G, AE列）がグレー表示',
                    'step7' => '🌐 URL フィールド（Q, X列）にリンク検証が追加'
                ),
                'validation_features' => array(
                    'dropdown_validation' => 'プルダウンメニューによる入力制限',
                    'number_validation' => '数値範囲の制限（採択率: 0-100%等）',
                    'url_validation' => 'URL形式の検証',
                    'date_validation' => '日付形式の検証',
                    'required_validation' => '必須項目の設定',
                    'readonly_protection' => '自動入力フィールドの保護'
                )
            );
            
            gi_log_error('Comprehensive field validation info prepared', array(
                'total_mappings' => count($field_mappings),
                'validation_fields' => count($validation_stats['validation_fields']),
                'taxonomy_fields' => count($validation_stats['taxonomy_fields']),
                'readonly_fields' => count($validation_stats['readonly_fields'])
            ));
            
            wp_send_json_success(array(
                'message' => '📋 31列完全対応フィールドバリデーション設定情報を準備しました',
                'validation_info' => $validation_info,
                'setup_guide' => $validation_info['setup_instructions'],
                'features' => $validation_info['validation_features'],
                'statistics' => $validation_stats
            ));
            
        } catch (Exception $e) {
            gi_log_error('Field validation setup failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('フィールドバリデーション設定に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 📋 フィールドマッピング & バリデーション設定 (31列完全対応)
     */
    private function get_field_validation_mappings() {
        return array(
            // 基本情報フィールド
            'A' => array(
                'field_name' => 'ID (自動入力)',
                'field_key' => 'post_id',
                'type' => 'readonly',
                'description' => 'WordPress投稿ID（自動生成・編集不可）'
            ),
            'B' => array(
                'field_name' => 'タイトル',
                'field_key' => 'post_title',
                'type' => 'text',
                'required' => true,
                'description' => '助成金名・制度名'
            ),
            'C' => array(
                'field_name' => '内容・詳細',
                'field_key' => 'post_content',
                'type' => 'textarea',
                'description' => '助成金の詳細説明・概要'
            ),
            'D' => array(
                'field_name' => '抜粋・概要',
                'field_key' => 'post_excerpt',
                'type' => 'text',
                'description' => '簡潔な概要文'
            ),
            'E' => array(
                'field_name' => 'ステータス',
                'field_key' => 'post_status',
                'type' => 'select',
                'choices' => array('draft', 'publish', 'private', 'deleted'),
                'description' => 'WordPressの投稿ステータス'
            ),
            'F' => array(
                'field_name' => '作成日 (自動入力)',
                'field_key' => 'post_date',
                'type' => 'readonly',
                'description' => 'WordPress作成日時（自動記録）'
            ),
            'G' => array(
                'field_name' => '更新日 (自動入力)',
                'field_key' => 'post_modified',
                'type' => 'readonly',
                'description' => 'WordPress更新日時（自動記録）'
            ),
            
            // 助成金詳細フィールド (H-Q)
            'H' => array(
                'field_name' => '助成金額 (表示用)',
                'field_key' => 'max_amount',
                'type' => 'text',
                'description' => '助成金額の表示用テキスト（例：300万円）'
            ),
            'I' => array(
                'field_name' => '助成金額数値',
                'field_key' => 'max_amount_numeric',
                'type' => 'number',
                'validation' => array('min' => 0, 'max' => 999999999),
                'description' => 'ソート・計算用の数値（例：3000000）'
            ),
            'J' => array(
                'field_name' => '申請期限 (表示用)',
                'field_key' => 'deadline',
                'type' => 'text',
                'description' => '申請期限の表示用テキスト（例：令和6年3月31日）'
            ),
            'K' => array(
                'field_name' => '申請期限日付',
                'field_key' => 'deadline_date',
                'type' => 'date',
                'format' => 'YYYY-MM-DD',
                'description' => 'ソート・検索用の日付形式'
            ),
            'L' => array(
                'field_name' => '実施組織名',
                'field_key' => 'organization',
                'type' => 'text',
                'description' => '助成金を実施する組織・機関名'
            ),
            'M' => array(
                'field_name' => '組織タイプ',
                'field_key' => 'organization_type', 
                'type' => 'select',
                'choices' => array('national', 'prefecture', 'city', 'public_org', 'private_org', 'foundation', 'jgrants', 'other'),
                'description' => '実施組織の分類'
            ),
            'N' => array(
                'field_name' => '対象者・対象事業',
                'field_key' => 'grant_target',
                'type' => 'textarea',
                'description' => '助成対象となる事業・対象者の詳細'
            ),
            'O' => array(
                'field_name' => '申請方法',
                'field_key' => 'application_method',
                'type' => 'select', 
                'choices' => array('online', 'mail', 'visit', 'mixed'),
                'description' => '助成金の申請方法'
            ),
            'P' => array(
                'field_name' => '問い合わせ先',
                'field_key' => 'contact_info',
                'type' => 'textarea',
                'description' => '連絡先情報・問い合わせ窓口'
            ),
            'Q' => array(
                'field_name' => '公式URL',
                'field_key' => 'official_url',
                'type' => 'url',
                'description' => '公式サイト・詳細ページのURL'
            ),
            
            // 地域・ステータス情報 (R-S)
            'R' => array(
                'field_name' => '地域制限',
                'field_key' => 'regional_limitation',
                'type' => 'select',
                'choices' => array('nationwide', 'prefecture_only', 'municipality_only', 'region_group', 'specific_area'),
                'description' => '地域制限のタイプ'
            ),
            'S' => array(
                'field_name' => '申請ステータス',
                'field_key' => 'application_status',
                'type' => 'select',
                'choices' => array('open', 'upcoming', 'closed', 'suspended'),
                'description' => '現在の募集状況'
            ),
            
            // タクソノミー情報 (T-W) ★完全連携
            'T' => array(
                'field_name' => '都道府県',
                'field_key' => 'grant_prefecture',
                'type' => 'taxonomy',
                'taxonomy_name' => 'grant_prefecture',
                'description' => '対象都道府県（タクソノミー連携）'
            ),
            'U' => array(
                'field_name' => '市町村',
                'field_key' => 'grant_municipality',
                'type' => 'taxonomy',
                'taxonomy_name' => 'grant_municipality',
                'description' => '対象市町村（タクソノミー連携）'
            ),
            'V' => array(
                'field_name' => 'カテゴリ',
                'field_key' => 'grant_category',
                'type' => 'taxonomy',
                'taxonomy_name' => 'grant_category',
                'description' => '助成金カテゴリ（タクソノミー連携）'
            ),
            'W' => array(
                'field_name' => 'タグ',
                'field_key' => 'grant_tag',
                'type' => 'taxonomy',
                'taxonomy_name' => 'grant_tag',
                'description' => '助成金タグ（タクソノミー連携）'
            ),
            
            // ★ 新規拡張フィールド (X-AD) - 31列対応
            'X' => array(
                'field_name' => '外部リンク',
                'field_key' => 'external_link',
                'type' => 'url',
                'description' => '参考リンク・関連情報URL'
            ),
            'Y' => array(
                'field_name' => '地域に関する備考',
                'field_key' => 'region_notes',
                'type' => 'textarea',
                'description' => '地域制限の詳細説明・備考'
            ),
            'Z' => array(
                'field_name' => '必要書類',
                'field_key' => 'required_documents',
                'type' => 'textarea',
                'description' => '申請に必要な書類一覧'
            ),
            'AA' => array(
                'field_name' => '採択率（%）',
                'field_key' => 'adoption_rate',
                'type' => 'number',
                'validation' => array('min' => 0, 'max' => 100, 'step' => 0.1),
                'description' => '採択率の数値（0-100%）'
            ),
            'AB' => array(
                'field_name' => '申請難易度',
                'field_key' => 'application_difficulty',
                'type' => 'select',
                'choices' => array('easy', 'normal', 'hard', 'very_hard'),
                'description' => '申請の難易度レベル（簡単〜非常に困難）'
            ),
            'AC' => array(
                'field_name' => '対象経費',
                'field_key' => 'target_expenses',
                'type' => 'textarea',
                'description' => '補助対象となる経費の詳細'
            ),
            'AD' => array(
                'field_name' => '補助率',
                'field_key' => 'subsidy_rate',
                'type' => 'text',
                'description' => '補助率・補助割合（例：2/3、50%）'
            ),
            
            // システム情報 (AE)
            'AE' => array(
                'field_name' => 'シート更新日 (自動入力)',
                'field_key' => 'sheet_updated_at',
                'type' => 'readonly',
                'description' => '最終同期日時（自動記録）'
            )
        );
    }
    
    /**
     * 特定フィールドのテストAJAXハンドラー
     */
    public function ajax_test_specific_fields() {
        try {
            gi_log_error('AJAX specific field test request received', array(
                'user_id' => get_current_user_id(),
                'post_data' => $_POST
            ));
            
            // Nonce検証
            check_ajax_referer('gi_sheets_nonce', 'nonce');
            
            // 権限チェック
            if (!current_user_can('edit_posts')) {
                gi_log_error('Permission denied for specific field test', array('user_id' => get_current_user_id()));
                wp_send_json_error('権限がありません');
                return;
            }
            
            $results = $this->test_specific_field_sync();
            
            if (isset($results['error'])) {
                wp_send_json_error($results['error']);
            } else {
                wp_send_json_success($results);
            }
            
        } catch (Exception $e) {
            gi_log_error('Specific field test failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('フィールドテストに失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 📊 31列対応フィールド同期状態テスト
     */
    public function test_specific_field_sync() {
        $sheet_data = $this->read_sheet_data();
        
        if ($sheet_data === false) {
            return array('error' => 'スプレッドシートデータの読み取りに失敗しました');
        }
        
        if (empty($sheet_data) || count($sheet_data) < 2) {
            return array('error' => 'データ行が見つかりません');
        }
        
        // ヘッダー行を除去
        $headers = array_shift($sheet_data);
        
        // フィールドマッピング情報を取得
        $field_mappings = $this->get_field_validation_mappings();
        
        $results = array(
            'total_rows' => count($sheet_data),
            'total_columns' => count($headers),
            'headers' => $headers,
            'field_mappings_count' => count($field_mappings),
            'test_results' => array(),
            'field_analysis' => array()
        );
        
        // 重要フィールドのテスト対象を31列対応で定義
        $critical_test_fields = array(
            // 基本情報
            'post_title' => 1,              // B列: タイトル
            'post_status' => 4,             // E列: ステータス
            
            // ACFフィールド
            'max_amount' => 7,              // H列: 助成金額
            'organization_type' => 12,       // M列: 組織タイプ
            'application_method' => 14,      // O列: 申請方法
            'regional_limitation' => 17,     // R列: 地域制限
            'application_status' => 18,      // S列: 申請ステータス
            
            // 新規フィールド (31列対応)
            'external_link' => 23,          // X列: 外部リンク
            'region_notes' => 24,           // Y列: 地域に関する備考
            'required_documents' => 25,      // Z列: 必要書類
            'adoption_rate' => 26,          // AA列: 採択率
            'application_difficulty' => 27,  // AB列: 申請難易度
            'target_expenses' => 28,        // AC列: 対象経費
            'subsidy_rate' => 29,           // AD列: 補助率
        );
        
        // タクソノミーフィールドのテスト
        $taxonomy_fields = array(
            'grant_prefecture' => 19,       // T列: 都道府県
            'grant_municipality' => 20,     // U列: 市町村
            'grant_category' => 21,         // V列: カテゴリ
            'grant_tag' => 22,              // W列: タグ
        );
        
        // 最初の3行をテスト（処理時間を考慮）
        foreach (array_slice($sheet_data, 0, 3) as $index => $row) {
            $post_id = intval($row[0] ?? 0);
            
            if (!$post_id || !get_post($post_id)) {
                continue;
            }
            
            $row_result = array(
                'post_id' => $post_id,
                'post_title' => get_the_title($post_id),
                'sheet_row' => $index + 2,
                'acf_fields' => array(),
                'taxonomy_fields' => array(),
                'sync_status' => array()
            );
            
            // ACFフィールドの同期状態をテスト
            foreach ($critical_test_fields as $field_key => $column_index) {
                $sheet_value = $row[$column_index] ?? '';
                
                if ($field_key === 'post_title') {
                    $wp_value = get_the_title($post_id);
                } elseif ($field_key === 'post_status') {
                    $wp_value = get_post_status($post_id);
                } else {
                    $wp_value = get_field($field_key, $post_id);
                }
                
                $column_letter = $this->number_to_column($column_index + 1);
                
                $row_result['acf_fields'][$field_key] = array(
                    'column' => $column_letter,
                    'column_index' => $column_index,
                    'sheet_value' => $sheet_value,
                    'wp_value' => $wp_value,
                    'matches' => (string)$sheet_value === (string)$wp_value,
                    'sheet_empty' => empty($sheet_value),
                    'wp_empty' => empty($wp_value),
                    'field_type' => $field_mappings[$column_letter]['type'] ?? 'unknown'
                );
            }
            
            // タクソノミーフィールドの同期状態をテスト
            foreach ($taxonomy_fields as $taxonomy => $column_index) {
                $sheet_value = $row[$column_index] ?? '';
                $wp_terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
                $wp_value = is_array($wp_terms) && !is_wp_error($wp_terms) ? implode(', ', $wp_terms) : '';
                
                $column_letter = $this->number_to_column($column_index + 1);
                
                $row_result['taxonomy_fields'][$taxonomy] = array(
                    'column' => $column_letter,
                    'column_index' => $column_index,
                    'sheet_value' => $sheet_value,
                    'wp_value' => $wp_value,
                    'matches' => $sheet_value === $wp_value,
                    'sheet_empty' => empty($sheet_value),
                    'wp_empty' => empty($wp_value),
                    'terms_count' => is_array($wp_terms) ? count($wp_terms) : 0
                );
            }
            
            // 同期状態の統計
            $total_tested = count($row_result['acf_fields']) + count($row_result['taxonomy_fields']);
            $matched_fields = 0;
            
            foreach ($row_result['acf_fields'] as $field_data) {
                if ($field_data['matches']) $matched_fields++;
            }
            
            foreach ($row_result['taxonomy_fields'] as $field_data) {
                if ($field_data['matches']) $matched_fields++;
            }
            
            $row_result['sync_status'] = array(
                'total_tested' => $total_tested,
                'matched_fields' => $matched_fields,
                'sync_rate' => $total_tested > 0 ? round(($matched_fields / $total_tested) * 100, 2) : 0,
                'has_issues' => $matched_fields < $total_tested
            );
            
            $results['test_results'][] = $row_result;
        }
        
        // フィールド分析統計を追加
        $results['field_analysis'] = array(
            'tested_acf_fields' => count($critical_test_fields),
            'tested_taxonomy_fields' => count($taxonomy_fields),
            'total_columns_available' => 31, // AE列まで
            'coverage_percentage' => round(((count($critical_test_fields) + count($taxonomy_fields)) / 31) * 100, 2)
        );
        
        return $results;
    }
    
    /**
     * 数値を列文字に変換（1=A, 2=B, ..., 27=AA, 28=AB, etc.）
     */
    private function number_to_column($number) {
        $column = '';
        while ($number > 0) {
            $number--;
            $column = chr(65 + ($number % 26)) . $column;
            $number = intval($number / 26);
        }
        return $column;
    }
    
    /**
     * 自動同期設定の確認
     */
    private function is_auto_sync_enabled() {
        return get_option('gi_sheets_auto_sync_enabled', true); // デフォルト: 有効
    }
    
    /**
     * スケジュール同期設定の確認
     */
    private function is_scheduled_sync_enabled() {
        return get_option('gi_sheets_scheduled_sync_enabled', true); // デフォルト: 有効
    }
    
    /**
     * 同期間隔の取得
     */
    private function get_sync_interval() {
        return get_option('gi_sheets_sync_interval', 'every_5_minutes'); // デフォルト: 5分間隔
    }
    
    /**
     * 同期設定の更新AJAXハンドラー
     */
    public function ajax_update_sync_settings() {
        try {
            gi_log_error('AJAX sync settings update request received', array(
                'user_id' => get_current_user_id(),
                'post_data' => $_POST
            ));
            
            // Nonce検証
            check_ajax_referer('gi_sheets_nonce', 'nonce');
            
            // 権限チェック
            if (!current_user_can('manage_options')) {
                gi_log_error('Permission denied for sync settings update', array('user_id' => get_current_user_id()));
                wp_send_json_error('設定を変更する権限がありません');
                return;
            }
            
            // 設定値の取得とサニタイズ
            $auto_sync_enabled = isset($_POST['auto_sync_enabled']) ? (bool)$_POST['auto_sync_enabled'] : false;
            $scheduled_sync_enabled = isset($_POST['scheduled_sync_enabled']) ? (bool)$_POST['scheduled_sync_enabled'] : false;
            $sync_interval = isset($_POST['sync_interval']) ? sanitize_text_field($_POST['sync_interval']) : 'every_5_minutes';
            
            // 有効な間隔かチェック
            $valid_intervals = array('every_5_minutes', 'every_15_minutes', 'hourly', 'twicedaily', 'daily');
            if (!in_array($sync_interval, $valid_intervals)) {
                $sync_interval = 'every_5_minutes';
            }
            
            // 設定を保存
            update_option('gi_sheets_auto_sync_enabled', $auto_sync_enabled);
            update_option('gi_sheets_scheduled_sync_enabled', $scheduled_sync_enabled);
            update_option('gi_sheets_sync_interval', $sync_interval);
            
            // Cronスケジュールの更新
            wp_clear_scheduled_hook('gi_sheets_sync_cron');
            if ($scheduled_sync_enabled) {
                wp_schedule_event(time(), $sync_interval, 'gi_sheets_sync_cron');
            }
            
            gi_log_error('Sync settings updated successfully', array(
                'auto_sync_enabled' => $auto_sync_enabled,
                'scheduled_sync_enabled' => $scheduled_sync_enabled,
                'sync_interval' => $sync_interval
            ));
            
            wp_send_json_success(array(
                'message' => '同期設定が正常に保存されました',
                'settings' => array(
                    'auto_sync_enabled' => $auto_sync_enabled,
                    'scheduled_sync_enabled' => $scheduled_sync_enabled,
                    'sync_interval' => $sync_interval,
                    'next_scheduled' => $scheduled_sync_enabled ? wp_next_scheduled('gi_sheets_sync_cron') : false
                )
            ));
            
        } catch (Exception $e) {
            gi_log_error('Sync settings update failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('設定の保存に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 同期設定の取得AJAXハンドラー
     */
    public function ajax_get_sync_settings() {
        try {
            gi_log_error('AJAX sync settings get request received', array(
                'user_id' => get_current_user_id()
            ));
            
            // Nonce検証
            check_ajax_referer('gi_sheets_nonce', 'nonce');
            
            // 権限チェック
            if (!current_user_can('edit_posts')) {
                gi_log_error('Permission denied for sync settings get', array('user_id' => get_current_user_id()));
                wp_send_json_error('設定を取得する権限がありません');
                return;
            }
            
            $settings = array(
                'auto_sync_enabled' => $this->is_auto_sync_enabled(),
                'scheduled_sync_enabled' => $this->is_scheduled_sync_enabled(),
                'sync_interval' => $this->get_sync_interval(),
                'next_scheduled' => wp_next_scheduled('gi_sheets_sync_cron'),
                'field_mappings' => $this->get_field_validation_mappings(),
                'sync_status' => array(
                    'last_sync' => get_option('gi_sheets_last_sync_time', '未実行'),
                    'last_sync_result' => get_option('gi_sheets_last_sync_result', 'unknown'),
                    'sync_count_today' => get_option('gi_sheets_sync_count_' . date('Y-m-d'), 0)
                )
            );
            
            gi_log_error('Sync settings retrieved successfully', array(
                'auto_sync' => $settings['auto_sync_enabled'],
                'scheduled_sync' => $settings['scheduled_sync_enabled'],
                'interval' => $settings['sync_interval']
            ));
            
            wp_send_json_success($settings);
            
        } catch (Exception $e) {
            gi_log_error('Sync settings get failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('設定の取得に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * Cronスケジュールに追加間隔を定義
     */
    public static function add_cron_schedules($schedules) {
        $schedules['every_5_minutes'] = array(
            'interval' => 300, // 5分 = 300秒
            'display' => '5分間隔'
        );
        $schedules['every_15_minutes'] = array(
            'interval' => 900, // 15分 = 900秒
            'display' => '15分間隔'
        );
        return $schedules;
    }
    
    /**
     * 同期結果をログに記録
     */
    private function log_sync_result($sync_type, $result, $message = '') {
        try {
            // 同期時刻を記録
            update_option('gi_sheets_last_sync_time', current_time('mysql'));
            update_option('gi_sheets_last_sync_result', $result);
            
            // 日次カウント
            $today = date('Y-m-d');
            $count_key = 'gi_sheets_sync_count_' . $today;
            $current_count = get_option($count_key, 0);
            update_option($count_key, $current_count + 1);
            
            // 詳細ログ
            gi_log_error('Sync result logged', array(
                'type' => $sync_type,
                'result' => $result,
                'message' => $message,
                'timestamp' => current_time('mysql'),
                'daily_count' => $current_count + 1
            ));
            
            // 古いログの清理（30日以上前）
            $cleanup_date = date('Y-m-d', strtotime('-30 days'));
            $old_count_key = 'gi_sheets_sync_count_' . $cleanup_date;
            delete_option($old_count_key);
            
        } catch (Exception $e) {
            gi_log_error('Failed to log sync result', array(
                'error' => $e->getMessage()
            ));
        }
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