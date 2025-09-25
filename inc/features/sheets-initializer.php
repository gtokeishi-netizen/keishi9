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
            // 1. ヘッダー行を設定
            $this->setup_headers();
            
            // 2. バリデーションルールを設定
            $this->setup_validation_rules();
            
            // 3. 既存の投稿データをエクスポート
            $this->export_existing_posts();
            
            // 4. フォーマット設定
            $this->setup_formatting();
            
            return array('success' => true, 'message' => 'スプレッドシートの初期化が完了しました');
            
        } catch (Exception $e) {
            gi_log_error('Sheet initialization failed', array(
                'error' => $e->getMessage()
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
            '助成金額' => '助成金の金額',
            '申請期限' => '申請締切日',
            '実施団体' => '助成金を実施する団体名',
            '応募条件' => '申請資格・条件',
            '助成金概要' => '助成金の目的・概要',
            '申請方法' => '申請手順・方法',
            '問い合わせ先' => '連絡先情報',
            '参考URL' => '公式サイトやURL',
            'カテゴリ' => 'カンマ区切りのカテゴリ名',
            'タグ' => 'カンマ区切りのタグ名',
            'シート更新日' => 'スプレッドシート更新日時（自動入力）'
        );
        
        // ヘッダー行を書き込み
        $header_values = array_keys($headers);
        $result = $this->sheets_sync->write_sheet_data(
            $this->sheets_sync->sheet_name . '!A1:R1', 
            array($header_values)
        );
        
        if (!$result) {
            throw new Exception('ヘッダー行の設定に失敗しました');
        }
        
        // 2行目に説明を追加
        $descriptions = array_values($headers);
        $this->sheets_sync->write_sheet_data(
            $this->sheets_sync->sheet_name . '!A2:R2', 
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
        
        // サンプル行を3行目に追加
        $this->sheets_sync->write_sheet_data(
            $this->sheets_sync->sheet_name . '!A3:R3', 
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
            $range = $this->sheets_sync->sheet_name . "!A{$start_row}:R{$end_row}";
            
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
            'grant_amount',
            'application_deadline',
            'grant_organization',
            'application_conditions',
            'grant_overview',
            'application_method',
            'contact_info',
            'reference_url'
        );
        
        foreach ($acf_fields as $field) {
            $value = get_field($field, $post_id);
            
            // 配列の場合はJSON文字列に変換
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            
            $row[] = (string)$value;
        }
        
        // カテゴリを追加
        $categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        $row[] = is_array($categories) && !is_wp_error($categories) ? implode(', ', $categories) : '';
        
        // タグを追加
        $tags = wp_get_post_terms($post_id, 'grant_tag', array('fields' => 'names'));
        $row[] = is_array($tags) && !is_wp_error($tags) ? implode(', ', $tags) : '';
        
        // スプレッドシート更新日
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
     * スプレッドシートをクリア
     */
    public function clear_sheet() {
        try {
            // データ範囲を取得してクリア
            $range = $this->sheets_sync->sheet_name . '!A:Z';
            $result = $this->sheets_sync->write_sheet_data($range, array(array()));
            
            if ($result) {
                return array('success' => true, 'message' => 'スプレッドシートをクリアしました');
            } else {
                throw new Exception('スプレッドシートのクリアに失敗しました');
            }
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
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
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $result = $this->initialize_sheet();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: 全投稿エクスポート
     */
    public function ajax_export_all_posts() {
        check_ajax_referer('gi_sheets_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        try {
            $this->export_existing_posts();
            wp_send_json_success('全投稿をスプレッドシートにエクスポートしました');
            
        } catch (Exception $e) {
            wp_send_json_error('エクスポートに失敗しました: ' . $e->getMessage());
        }
    }
}

// AJAX ハンドラーを追加
add_action('wp_ajax_gi_clear_sheet', function() {
    check_ajax_referer('gi_sheets_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
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