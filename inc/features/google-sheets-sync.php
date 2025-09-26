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
        add_action('wp_ajax_gi_setup_field_validation', array($this, 'ajax_setup_field_validation'));
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
            $range = $this->get_sheet_name() . '!A:Y'; // 全データを取得（Y列まで）
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
            
            // ACFフィールドを追加 (H-V列)
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
                'official_url',            // Q: 公式URL
                'target_prefecture',       // R: 都道府県コード
                'prefecture_name',         // S: 都道府県名
                'target_municipality',     // T: 対象市町村
                'regional_limitation',     // U: 地域制限
                'application_status'       // V: 申請ステータス
            );
            
            foreach ($acf_fields as $field) {
                $value = get_field($field, $post_id);
                
                // 都道府県名の自動生成
                if ($field === 'prefecture_name' && empty($value)) {
                    $prefecture_code = get_field('target_prefecture', $post_id);
                    if ($prefecture_code && function_exists('gi_get_prefecture_name_by_code')) {
                        $value = gi_get_prefecture_name_by_code($prefecture_code);
                    }
                }
                
                $row[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
            }
            
            // カテゴリを追加 (W列)
            $categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
            $row[] = (is_array($categories) && !is_wp_error($categories)) ? implode(', ', $categories) : '';
            
            // タグを追加 (X列)
            $tags = wp_get_post_terms($post_id, 'grant_tag', array('fields' => 'names'));
            $row[] = (is_array($tags) && !is_wp_error($tags)) ? implode(', ', $tags) : '';
            
            // シート更新日を追加 (Y列)
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
                '都道府県コード (hokkaido/tokyo等)',      // R列 - 地域コード
                '都道府県名 (自動入力)',                  // S列 - 地域名
                '対象市町村',                            // T列 - 対象市町村
                '地域制限 (nationwide/prefecture_only/municipality_only/region_group/specific_area)', // U列 - 地域制限タイプ
                '申請ステータス (open/upcoming/closed/suspended)', // V列 - 募集状況
                'カテゴリ',                              // W列 - 分類カテゴリ
                'タグ',                                  // X列 - タグ
                'シート更新日 (自動入力)'                // Y列 - 最終同期日時
            );
            
            gi_log_error('Headers array created', array('count' => count($headers)));
            
            $range = $this->sheet_name . '!A1:Y1';
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
                // 既存行を更新 - 正しい列範囲（Y列まで）を使用
                $range = $this->sheet_name . '!A' . $row_number . ':Y' . $row_number;
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
            
            gi_log_error('Full bidirectional sync completed', array(
                'sheets_to_wp' => $sheets_synced,
                'wp_to_sheets' => $wp_synced
            ));
            
            return array(
                'sheets_to_wp' => $sheets_synced,
                'wp_to_sheets' => $wp_synced,
                'total_synced' => $sheets_synced + $wp_synced
            );
            
        } catch (Exception $e) {
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
        $clear_result = $this->clear_sheet_range('A:Y');
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
                $range = $sheet_name . "!A{$current_row}:Y{$end_row}";
                
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
            
            gi_log_error('Manual sync completed successfully');
            wp_send_json_success($message);
            
        } catch (Exception $e) {
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
     * フィールドバリデーション設定のAJAXハンドラー
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
            
            gi_log_error('Setting up field validation through WordPress API');
            
            // Google Apps Scriptの設定関数を呼び出すための情報を提供
            $validation_info = array(
                'spreadsheet_id' => $this->spreadsheet_id,
                'sheet_name' => $this->sheet_name,
                'field_mappings' => $this->get_field_validation_mappings(),
                'instructions' => array(
                    'step1' => 'スプレッドシートを開いてください',
                    'step2' => 'メニューから「🏛️ 助成金管理システム」→「WordPress連携」→「🔧 フィールドバリデーション設定」を選択',
                    'step3' => '設定が完了すると選択肢フィールドが青色の背景で表示されます',
                    'step4' => 'プルダウンから正しい値を選択できるようになります'
                )
            );
            
            gi_log_error('Field validation info prepared', array('mappings_count' => count($validation_info['field_mappings'])));
            
            wp_send_json_success(array(
                'message' => 'フィールドバリデーション設定の情報を準備しました。Google Apps Scriptでの設定が必要です。',
                'validation_info' => $validation_info,
                'next_steps' => $validation_info['instructions']
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
     * フィールドバリデーション用のマッピング情報を取得
     */
    private function get_field_validation_mappings() {
        return array(
            'E' => array(
                'field_name' => 'ステータス',
                'field_key' => 'post_status',
                'type' => 'select',
                'choices' => array('draft', 'publish', 'private', 'deleted'),
                'description' => 'WordPressの投稿ステータス'
            ),
            'M' => array(
                'field_name' => '組織タイプ',
                'field_key' => 'organization_type', 
                'type' => 'select',
                'choices' => array('national', 'prefecture', 'city', 'public_org', 'private_org', 'foundation', 'jgrants', 'other'),
                'description' => '実施組織の分類'
            ),
            'O' => array(
                'field_name' => '申請方法',
                'field_key' => 'application_method',
                'type' => 'select', 
                'choices' => array('online', 'mail', 'visit', 'mixed'),
                'description' => '助成金の申請方法'
            ),
            'R' => array(
                'field_name' => '都道府県コード',
                'field_key' => 'target_prefecture',
                'type' => 'select',
                'choices' => array(
                    '', 'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima',
                    'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa',
                    'niigata', 'toyama', 'ishikawa', 'fukui', 'yamanashi', 'nagano', 'gifu',
                    'shizuoka', 'aichi', 'mie', 'shiga', 'kyoto', 'osaka', 'hyogo', 'nara',
                    'wakayama', 'tottori', 'shimane', 'okayama', 'hiroshima', 'yamaguchi',
                    'tokushima', 'kagawa', 'ehime', 'kochi', 'fukuoka', 'saga', 'nagasaki',
                    'kumamoto', 'oita', 'miyazaki', 'kagoshima', 'okinawa'
                ),
                'description' => '対象都道府県のコード'
            ),
            'U' => array(
                'field_name' => '地域制限',
                'field_key' => 'regional_limitation',
                'type' => 'select',
                'choices' => array('nationwide', 'prefecture_only', 'municipality_only', 'region_group', 'specific_area'),
                'description' => '地域制限のタイプ'
            ),
            'V' => array(
                'field_name' => '申請ステータス',
                'field_key' => 'application_status',
                'type' => 'select',
                'choices' => array('open', 'upcoming', 'closed', 'suspended'),
                'description' => '現在の募集状況'
            )
        );
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