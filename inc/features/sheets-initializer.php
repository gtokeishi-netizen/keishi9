<?php
/**
 * Google Sheets Initializer
 * 
 * スプレッドシートの初期設定と自動セットアップ
 * - ヘッダー行の自動作成
 * - 初期データの投入
 * - バリデーションルールの設定
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

class SheetsInitializer {
    
    private static $instance = null;
    private $sheets_sync;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // GoogleSheetsSync インスタンスを取得
        add_action('init', array($this, 'init_after_sheets_sync'), 15);
        
        // AJAX ハンドラー
        add_action('wp_ajax_gi_initialize_sheet', array($this, 'ajax_initialize_sheet'));
        add_action('wp_ajax_gi_export_all_posts', array($this, 'ajax_export_all_posts'));
    }
    
    /**
     * Sheets同期後の初期化
     */
    public function init_after_sheets_sync() {
        $this->sheets_sync = GoogleSheetsSync::getInstance();
    }
    
    /**
     * スプレッドシートの初期化
     */
    public function initialize_sheet() {
        try {
            gi_log_error('Starting sheet initialization process');
            
            // Sheets Syncインスタンスの確認
            if (!$this->sheets_sync) {
                gi_log_error('SheetsSync instance not available, attempting to get instance');
                if (class_exists('GoogleSheetsSync')) {
                    $this->sheets_sync = GoogleSheetsSync::getInstance();
                    gi_log_error('SheetsSync instance obtained');
                } else {
                    throw new Exception('GoogleSheetsSync クラスが利用できません');
                }
            }
            
            // 1. ヘッダー行を設定
            gi_log_error('Step 1: Setting up headers');
            $this->setup_headers();
            gi_log_error('Headers setup completed');
            
            // 2. バリデーションルールを設定
            gi_log_error('Step 2: Setting up validation rules');
            $this->setup_validation_rules();
            gi_log_error('Validation rules setup completed');
            
            // 3. 既存の投稿データをエクスポート
            gi_log_error('Step 3: Exporting existing posts');
            $this->export_existing_posts();
            gi_log_error('Existing posts export completed');
            
            // 4. フォーマット設定
            gi_log_error('Step 4: Setting up formatting');
            $this->setup_formatting();
            gi_log_error('Formatting setup completed');
            
            gi_log_error('Sheet initialization completed successfully');
            return array('success' => true, 'message' => 'スプレッドシートの初期化が完了しました');
            
        } catch (Exception $e) {
            gi_log_error('Sheet initialization failed', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            
            return array('success' => false, 'message' => '初期化に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * ヘッダー行の設定
     */
    private function setup_headers() {
        $headers = array(
            'ID' => 'WordPress投稿ID（自動入力）',
            'タイトル' => '助成金名・タイトル',
            '内容' => '助成金の詳細説明',
            '抜粋' => '簡潔な概要',
            'ステータス' => 'publish/draft/private/deleted',
            '作成日' => '投稿作成日時（自動入力）',
            '更新日' => '投稿更新日時（自動入力）',
            '助成金額（表示用）' => '表示用の助成金額',
            '助成金額（数値）' => '数値での助成金額（円）',
            '申請期限（表示用）' => '表示用の申請期限',
            '申請期限（日付）' => 'YYYY-MM-DD形式の期限',
            '実施組織' => '助成金を実施する組織名',
            '組織タイプ' => 'national/prefecture/city等',
            '対象者・対象事業' => '助成対象の詳細',
            '申請方法' => 'online/mail/visit等',
            '問い合わせ先' => '連絡先情報',
            '公式URL' => '公式サイトURL',
            '都道府県コード' => 'tokyo/osaka等のコード',
            '都道府県名' => '東京都/大阪府等の表示名',
            '対象市町村' => '対象となる市町村名',
            '地域制限' => 'nationwide/prefecture_only等',
            '申請ステータス' => 'open/closed/upcoming等',
            'カテゴリ' => 'カンマ区切りのカテゴリ名',
            'タグ' => 'カンマ区切りのタグ名',
            'シート更新日' => 'スプレッドシート更新日時（自動入力）'
        );
        
        // ヘッダー行を書き込み
        $header_values = array_keys($headers);
        $sheet_name = $this->sheets_sync->get_sheet_name();
        $result = $this->sheets_sync->write_sheet_data(
            $sheet_name . '!A1:Y1', 
            array($header_values)
        );
        
        if (!$result) {
            throw new Exception('ヘッダー行の設定に失敗しました');
        }
        
        // 2行目に説明を追加
        $descriptions = array_values($headers);
        $this->sheets_sync->write_sheet_data(
            $sheet_name . '!A2:Y2', 
            array($descriptions)
        );
        
        return true;
    }
    
    /**
     * バリデーションルールの設定（Google Sheets API v4では制限あり）
     */
    private function setup_validation_rules() {
        // ステータス列（E列）にドロップダウンを設定するリクエストを作成
        // 注意: この機能はGoogle Sheets APIの範囲を超える場合があります
        
        // 代替案: サンプルデータでバリデーション値を示す
        $validation_samples = array(
            '', // ID（空欄）
            'サンプル助成金タイトル',
            'この助成金の詳細な説明をここに記載します。',
            '短い概要説明',
            'draft', // ステータス例
            '', // 作成日（空欄）
            '', // 更新日（空欄）
            '最大100万円',
            '2024-12-31',
            '◯◯財団',
            '法人格を有する非営利団体',
            '地域活性化を目的とした助成金',
            'Webサイトから申請書をダウンロード',
            'info@example.org',
            'https://example.org',
            '地域振興, 社会貢献',
            'NPO, 助成金',
            '' // シート更新日（空欄）
        );
        
        // サンプル行を3行目に追加（25列に対応）
        $validation_samples = array(
            '', // ID（空欄）
            'サンプル助成金タイトル', // タイトル
            'この助成金の詳細な説明をここに記載します。', // 内容
            '短い概要説明', // 抜粋
            'draft', // ステータス例
            '', // 作成日（空欄）
            '', // 更新日（空欄）
            '最大100万円', // 助成金額（表示用）
            '1000000', // 助成金額（数値）
            '2024年12月31日', // 申請期限（表示用）
            '2024-12-31', // 申請期限（日付）
            '◯◯財団', // 実施組織
            'foundation', // 組織タイプ
            '中小企業向け地域振興事業', // 対象者・対象事業
            'online', // 申請方法
            'info@example.org', // 問い合わせ先
            'https://example.org', // 公式URL
            'tokyo', // 都道府県コード
            '東京都', // 都道府県名
            '全域', // 対象市町村
            'prefecture', // 地域制限
            'open', // 申請ステータス
            '地域振興, 社会貢献', // カテゴリ
            'NPO, 助成金', // タグ
            '' // シート更新日（空欄）
        );
        
        $sheet_name = $this->sheets_sync->get_sheet_name();
        $this->sheets_sync->write_sheet_data(
            $sheet_name . '!A3:Y3', 
            array($validation_samples)
        );
        
        return true;
    }
    
    /**
     * 既存投稿のエクスポート
     */
    private function export_existing_posts() {
        $posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($posts)) {
            return true; // 投稿がない場合はそのまま成功
        }
        
        $rows = array();
        $start_row = 4; // 4行目から開始（ヘッダー、説明、サンプルの後）
        
        foreach ($posts as $post) {
            $row_data = $this->convert_post_to_row($post->ID);
            if ($row_data) {
                $rows[] = $row_data;
            }
        }
        
        if (!empty($rows)) {
            // 一括で書き込み
            $end_row = $start_row + count($rows) - 1;
            $sheet_name = $this->sheets_sync->get_sheet_name();
            $range = $sheet_name . "!A{$start_row}:Y{$end_row}";
            
            $result = $this->sheets_sync->write_sheet_data($range, $rows);
            
            if (!$result) {
                throw new Exception('既存投稿のエクスポートに失敗しました');
            }
        }
        
        return true;
    }
    
    /**
     * 投稿データを行データに変換
     */
    private function convert_post_to_row($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return false;
        }
        
        // 基本データ
        $row = array(
            $post_id,
            $post->post_title,
            $post->post_content,
            $post->post_excerpt,
            $post->post_status,
            $post->post_date,
            $post->post_modified,
        );
        
        // ACFフィールドを追加
        $acf_fields = array(
            'max_amount',              // H列
            'max_amount_numeric',      // I列
            'deadline',                // J列
            'deadline_date',           // K列
            'organization',            // L列
            'organization_type',       // M列
            'grant_target',            // N列
            'application_method',      // O列
            'contact_info',            // P列
            'official_url',            // Q列
            'target_prefecture',       // R列
            'prefecture_name',         // S列
            'target_municipality',     // T列
            'regional_limitation',     // U列
            'application_status'       // V列
        );
        
        foreach ($acf_fields as $field) {
            $value = get_field($field, $post_id);
            
            // 都道府県名の自動生成
            if ($field === 'prefecture_name' && empty($value)) {
                $prefecture_code = get_field('target_prefecture', $post_id);
                if ($prefecture_code) {
                    $value = $this->get_prefecture_name_by_code($prefecture_code);
                    // 値を更新して保存
                    update_field('prefecture_name', $value, $post_id);
                }
            }
            
            // 配列の場合はJSON文字列に変換
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            
            $row[] = (string)$value;
        }
        
        // カテゴリを追加（W列）
        $categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        $row[] = is_array($categories) && !is_wp_error($categories) ? implode(', ', $categories) : '';
        
        // タグを追加（X列）
        $tags = wp_get_post_terms($post_id, 'grant_tag', array('fields' => 'names'));
        $row[] = is_array($tags) && !is_wp_error($tags) ? implode(', ', $tags) : '';
        
        // スプレッドシート更新日（Y列）
        $row[] = current_time('mysql');
        
        return $row;
    }
    
    /**
     * フォーマット設定
     */
    private function setup_formatting() {
        // Google Sheets API v4では詳細なフォーマット設定は制限的
        // 基本的なフォーマットのみ設定可能
        
        // 今回は省略（将来的にはGoogle Apps Scriptで実装を推奨）
        return true;
    }
    
    /**
     * 統計情報の取得
     */
    public function get_sync_stats() {
        // WordPress側の統計
        $wp_posts_count = wp_count_posts('grant');
        
        // スプレッドシート側の統計を取得
        $sheet_data = $this->sheets_sync->read_sheet_data();
        $sheet_rows_count = is_array($sheet_data) ? count($sheet_data) - 1 : 0; // ヘッダー行を除外
        
        return array(
            'wordpress' => array(
                'publish' => $wp_posts_count->publish ?? 0,
                'draft' => $wp_posts_count->draft ?? 0,
                'private' => $wp_posts_count->private ?? 0,
                'total' => ($wp_posts_count->publish ?? 0) + ($wp_posts_count->draft ?? 0) + ($wp_posts_count->private ?? 0)
            ),
            'spreadsheet' => array(
                'total_rows' => $sheet_rows_count,
                'last_updated' => get_option('gi_sheets_last_sync', '未同期')
            ),
            'sync_status' => array(
                'auto_sync_enabled' => get_option('gi_sheets_config', array())['auto_sync_enabled'] ?? true,
                'last_sync' => get_option('gi_sheets_last_full_sync', '未実行'),
                'errors_count' => count(get_option('gi_sheets_sync_log', array()))
            )
        );
    }
    
    /**
     * AJAX: スプレッドシート初期化
     */
    public function ajax_initialize_sheet() {
        // タイムアウトとメモリ制限の拡張
        set_time_limit(300); // 5分
        ini_set('memory_limit', '256M');
        
        try {
            gi_log_error('AJAX initialize_sheet started', array(
                'user_id' => get_current_user_id(),
                'post_data' => $_POST
            ));
            
            // Nonce検証
            check_ajax_referer('gi_sheets_nonce', 'nonce');
            gi_log_error('Nonce verification passed for initialization');
            
            // 権限チェック
            if (!current_user_can('edit_posts')) {
                gi_log_error('Permission denied for initialization', array('user_id' => get_current_user_id()));
                wp_send_json_error('Permission denied');
                return;
            }
            
            gi_log_error('Starting sheet initialization');
            
            // 初期化処理を実行
            $result = $this->initialize_sheet();
            
            gi_log_error('Sheet initialization result', $result);
            
            if ($result && isset($result['success']) && $result['success']) {
                wp_send_json_success($result['message']);
            } else {
                $error_message = isset($result['message']) ? $result['message'] : '初期化に失敗しました';
                wp_send_json_error($error_message);
            }
            
        } catch (Exception $e) {
            gi_log_error('AJAX initialize_sheet exception caught', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('初期化中にエラーが発生しました: ' . $e->getMessage());
            
        } catch (Error $e) {
            gi_log_error('AJAX initialize_sheet fatal error caught', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('初期化中に致命的エラーが発生しました: ' . $e->getMessage());
            
        } catch (Throwable $e) {
            gi_log_error('AJAX initialize_sheet throwable caught', array(
                'error' => $e->getMessage(),
                'file' => method_exists($e, 'getFile') ? $e->getFile() : 'unknown',
                'line' => method_exists($e, 'getLine') ? $e->getLine() : 'unknown',
                'trace' => method_exists($e, 'getTraceAsString') ? $e->getTraceAsString() : 'no trace'
            ));
            wp_send_json_error('初期化中に予期しないエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 都道府県コードから名前を取得
     */
    private function get_prefecture_name_by_code($code) {
        $prefectures = array(
            'hokkaido' => '北海道',
            'aomori' => '青森県',
            'iwate' => '岩手県',
            'miyagi' => '宮城県',
            'akita' => '秋田県',
            'yamagata' => '山形県',
            'fukushima' => '福島県',
            'ibaraki' => '茨城県',
            'tochigi' => '栃木県',
            'gunma' => '群馬県',
            'saitama' => '埼玉県',
            'chiba' => '千葉県',
            'tokyo' => '東京都',
            'kanagawa' => '神奈川県',
            'niigata' => '新潟県',
            'toyama' => '富山県',
            'ishikawa' => '石川県',
            'fukui' => '福井県',
            'yamanashi' => '山梨県',
            'nagano' => '長野県',
            'gifu' => '岐阜県',
            'shizuoka' => '静岡県',
            'aichi' => '愛知県',
            'mie' => '三重県',
            'shiga' => '滋賀県',
            'kyoto' => '京都府',
            'osaka' => '大阪府',
            'hyogo' => '兵庫県',
            'nara' => '奈良県',
            'wakayama' => '和歌山県',
            'tottori' => '鳥取県',
            'shimane' => '島根県',
            'okayama' => '岡山県',
            'hiroshima' => '広島県',
            'yamaguchi' => '山口県',
            'tokushima' => '徳島県',
            'kagawa' => '香川県',
            'ehime' => '愛媛県',
            'kochi' => '高知県',
            'fukuoka' => '福岡県',
            'saga' => '佐賀県',
            'nagasaki' => '長崎県',
            'kumamoto' => '熊本県',
            'oita' => '大分県',
            'miyazaki' => '宮崎県',
            'kagoshima' => '鹿児島県',
            'okinawa' => '沖縄県',
        );
        
        return isset($prefectures[$code]) ? $prefectures[$code] : '';
    }
    
    /**
     * AJAX: 全投稿エクスポート
     */
    public function ajax_export_all_posts() {
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        try {
            $this->export_existing_posts();
            wp_send_json_success('全投稿をスプレッドシートにエクスポートしました');
            
        } catch (Exception $e) {
            wp_send_json_error('エクスポートに失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * スプレッドシートをクリア
     */
    public function clear_sheet() {
        try {
            gi_log_error('Starting sheet clear process');
            
            // Sheets Syncインスタンスの確認
            if (!$this->sheets_sync) {
                if (class_exists('GoogleSheetsSync')) {
                    $this->sheets_sync = GoogleSheetsSync::getInstance();
                } else {
                    throw new Exception('GoogleSheetsSync クラスが利用できません');
                }
            }
            
            // スプレッドシートのデータをクリア（ヘッダー行は残す）
            $range = 'A2:Y1000'; // 25列、1000行までクリア
            $clear_data = $this->sheets_sync->clear_sheet_range($range);
            
            if ($clear_data) {
                gi_log_error('Sheet clear completed successfully');
                return array(
                    'success' => true,
                    'message' => 'スプレッドシートのデータをクリアしました'
                );
            } else {
                throw new Exception('スプレッドシートのクリアに失敗しました');
            }
            
        } catch (Exception $e) {
            gi_log_error('Sheet clear failed', array('error' => $e->getMessage()));
            return array(
                'success' => false,
                'message' => 'クリアに失敗しました: ' . $e->getMessage()
            );
        }
    }
}

// AJAX ハンドラーを追加
add_action('wp_ajax_gi_clear_sheet', function() {
    check_ajax_referer('gi_sheets_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }
    
    $initializer = SheetsInitializer::getInstance();
    $result = $initializer->clear_sheet();
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
});

// インスタンスを初期化
function gi_init_sheets_initializer() {
    return SheetsInitializer::getInstance();
}
add_action('init', 'gi_init_sheets_initializer');