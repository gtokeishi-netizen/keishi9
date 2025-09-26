<?php
/**
 * 助成金投稿用カスタムメタボックス
 * 
 * Google Sheetsから同期される都道府県、助成金カテゴリー、対象市町村を
 * WordPressの標準的な投稿編集画面のサイドバーで管理
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

class GrantPostMetaboxes {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_grant_metaboxes'));
        add_action('save_post', array($this, 'save_grant_metadata'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_metabox_scripts'));
    }
    
    /**
     * 助成金投稿用メタボックスを追加
     */
    public function add_grant_metaboxes() {
        // 助成金投稿タイプのみに適用
        add_meta_box(
            'grant-prefecture-metabox',
            '📍 対象都道府県',
            array($this, 'render_prefecture_metabox'),
            'grant',
            'side',
            'high'
        );
        
        add_meta_box(
            'grant-municipality-metabox',
            '🏛️ 対象市町村',
            array($this, 'render_municipality_metabox'),
            'grant',
            'side',
            'high'
        );
        
        add_meta_box(
            'grant-status-metabox',
            '📋 申請・ステータス情報',
            array($this, 'render_status_metabox'),
            'grant',
            'side',
            'high'
        );
        
        add_meta_box(
            'grant-amount-metabox',
            '💰 助成金額情報',
            array($this, 'render_amount_metabox'),
            'grant',
            'normal',
            'high'
        );
        
        add_meta_box(
            'grant-organization-metabox',
            '🏢 実施組織情報',
            array($this, 'render_organization_metabox'),
            'grant',
            'normal',
            'high'
        );
        
        add_meta_box(
            'grant-sync-info-metabox',
            '🔄 Google Sheets同期情報',
            array($this, 'render_sync_info_metabox'),
            'grant',
            'side',
            'low'
        );
    }
    
    /**
     * 対象都道府県メタボックス
     */
    public function render_prefecture_metabox($post) {
        wp_nonce_field('grant_metabox_nonce', 'grant_metabox_nonce_field');
        
        $target_prefecture = get_field('target_prefecture', $post->ID);
        $prefecture_name = get_field('prefecture_name', $post->ID);
        $regional_limitation = get_field('regional_limitation', $post->ID);
        
        ?>
        <div class="grant-metabox-content">
            <p>
                <label for="target_prefecture"><strong>都道府県コード:</strong></label><br>
                <select name="target_prefecture" id="target_prefecture" style="width: 100%;">
                    <option value="">全国対象</option>
                    <?php
                    $prefectures = $this->get_prefecture_options();
                    foreach ($prefectures as $code => $name) {
                        echo '<option value="' . esc_attr($code) . '"' . selected($target_prefecture, $code, false) . '>' . esc_html($name) . '</option>';
                    }
                    ?>
                </select>
            </p>
            
            <p>
                <label for="prefecture_name"><strong>都道府県名:</strong></label><br>
                <input type="text" name="prefecture_name" id="prefecture_name" value="<?php echo esc_attr($prefecture_name); ?>" 
                       style="width: 100%;" readonly placeholder="自動生成されます">
                <small>※都道府県コードから自動生成</small>
            </p>
            
            <p>
                <label for="regional_limitation"><strong>地域制限:</strong></label><br>
                <select name="regional_limitation" id="regional_limitation" style="width: 100%;">
                    <option value="nationwide" <?php selected($regional_limitation, 'nationwide'); ?>>全国対象</option>
                    <option value="prefecture_only" <?php selected($regional_limitation, 'prefecture_only'); ?>>都道府県内限定</option>
                    <option value="municipality_only" <?php selected($regional_limitation, 'municipality_only'); ?>>市町村限定</option>
                    <option value="region_group" <?php selected($regional_limitation, 'region_group'); ?>>地域グループ限定</option>
                    <option value="specific_area" <?php selected($regional_limitation, 'specific_area'); ?>>特定地域限定</option>
                </select>
            </p>
        </div>
        
        <style>
        .grant-metabox-content p { margin-bottom: 12px; }
        .grant-metabox-content label { font-weight: 600; color: #1d2327; }
        .grant-metabox-content small { color: #646970; }
        .grant-metabox-content select, .grant-metabox-content input { margin-top: 4px; }
        </style>
        <?php
    }
    
    /**
     * 対象市町村メタボックス
     */
    public function render_municipality_metabox($post) {
        $target_municipality = get_field('target_municipality', $post->ID);
        ?>
        <div class="grant-metabox-content">
            <p>
                <label for="target_municipality"><strong>対象市町村:</strong></label><br>
                <textarea name="target_municipality" id="target_municipality" 
                          rows="4" style="width: 100%; resize: vertical;" 
                          placeholder="例：&#10;新宿区&#10;渋谷区&#10;港区"><?php echo esc_textarea($target_municipality); ?></textarea>
                <small>複数の場合は改行で区切ってください</small>
            </p>
        </div>
        <?php
    }
    
    /**
     * 申請・ステータス情報メタボックス
     */
    public function render_status_metabox($post) {
        $application_status = get_field('application_status', $post->ID);
        $application_method = get_field('application_method', $post->ID);
        $deadline = get_field('deadline', $post->ID);
        $deadline_date = get_field('deadline_date', $post->ID);
        
        ?>
        <div class="grant-metabox-content">
            <p>
                <label for="application_status"><strong>申請ステータス:</strong></label><br>
                <select name="application_status" id="application_status" style="width: 100%;">
                    <option value="open" <?php selected($application_status, 'open'); ?>>🟢 募集中</option>
                    <option value="upcoming" <?php selected($application_status, 'upcoming'); ?>>🟡 募集予定</option>
                    <option value="closed" <?php selected($application_status, 'closed'); ?>>🔴 募集終了</option>
                    <option value="suspended" <?php selected($application_status, 'suspended'); ?>>⏸️ 一時停止</option>
                </select>
            </p>
            
            <p>
                <label for="application_method"><strong>申請方法:</strong></label><br>
                <select name="application_method" id="application_method" style="width: 100%;">
                    <option value="online" <?php selected($application_method, 'online'); ?>>💻 オンライン申請</option>
                    <option value="mail" <?php selected($application_method, 'mail'); ?>>📮 郵送申請</option>
                    <option value="visit" <?php selected($application_method, 'visit'); ?>>🏢 窓口申請</option>
                    <option value="mixed" <?php selected($application_method, 'mixed'); ?>>📧 オンライン・郵送併用</option>
                </select>
            </p>
            
            <p>
                <label for="deadline"><strong>申請期限（表示用）:</strong></label><br>
                <input type="text" name="deadline" id="deadline" value="<?php echo esc_attr($deadline); ?>" 
                       style="width: 100%;" placeholder="例：令和6年3月31日">
            </p>
            
            <p>
                <label for="deadline_date"><strong>申請期限（日付）:</strong></label><br>
                <input type="date" name="deadline_date" id="deadline_date" value="<?php echo esc_attr($deadline_date); ?>" 
                       style="width: 100%;">
            </p>
        </div>
        <?php
    }
    
    /**
     * 助成金額情報メタボックス
     */
    public function render_amount_metabox($post) {
        $max_amount = get_field('max_amount', $post->ID);
        $max_amount_numeric = get_field('max_amount_numeric', $post->ID);
        $min_amount = get_field('min_amount', $post->ID);
        $subsidy_rate = get_field('subsidy_rate', $post->ID);
        
        ?>
        <div class="grant-metabox-content" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <p>
                    <label for="max_amount"><strong>最大助成額（表示用）:</strong></label><br>
                    <input type="text" name="max_amount" id="max_amount" value="<?php echo esc_attr($max_amount); ?>" 
                           style="width: 100%;" placeholder="例：300万円">
                    <small>ユーザーに表示される文字列</small>
                </p>
                
                <p>
                    <label for="max_amount_numeric"><strong>最大助成額（数値）:</strong></label><br>
                    <input type="number" name="max_amount_numeric" id="max_amount_numeric" 
                           value="<?php echo esc_attr($max_amount_numeric); ?>" 
                           style="width: 100%;" min="0" step="1000" placeholder="3000000">
                    <small>検索・ソート用（円単位）</small>
                </p>
            </div>
            
            <div>
                <p>
                    <label for="min_amount"><strong>最小助成額:</strong></label><br>
                    <input type="number" name="min_amount" id="min_amount" 
                           value="<?php echo esc_attr($min_amount); ?>" 
                           style="width: 100%;" min="0" step="1000" placeholder="0">
                    <small>円単位</small>
                </p>
                
                <p>
                    <label for="subsidy_rate"><strong>補助率:</strong></label><br>
                    <input type="text" name="subsidy_rate" id="subsidy_rate" value="<?php echo esc_attr($subsidy_rate); ?>" 
                           style="width: 100%;" placeholder="例：2/3以内">
                    <small>補助率の説明</small>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * 実施組織情報メタボックス
     */
    public function render_organization_metabox($post) {
        $organization = get_field('organization', $post->ID);
        $organization_type = get_field('organization_type', $post->ID);
        $contact_info = get_field('contact_info', $post->ID);
        $official_url = get_field('official_url', $post->ID);
        
        ?>
        <div class="grant-metabox-content" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <p>
                    <label for="organization"><strong>実施組織名:</strong></label><br>
                    <input type="text" name="organization" id="organization" value="<?php echo esc_attr($organization); ?>" 
                           style="width: 100%;" placeholder="例：経済産業省">
                </p>
                
                <p>
                    <label for="organization_type"><strong>組織タイプ:</strong></label><br>
                    <select name="organization_type" id="organization_type" style="width: 100%;">
                        <option value="national" <?php selected($organization_type, 'national'); ?>>🏛️ 国（省庁）</option>
                        <option value="prefecture" <?php selected($organization_type, 'prefecture'); ?>>🏢 都道府県</option>
                        <option value="city" <?php selected($organization_type, 'city'); ?>>🏘️ 市区町村</option>
                        <option value="public_org" <?php selected($organization_type, 'public_org'); ?>>🏛️ 公的機関</option>
                        <option value="private_org" <?php selected($organization_type, 'private_org'); ?>>🏢 民間団体</option>
                        <option value="foundation" <?php selected($organization_type, 'foundation'); ?>>🏛️ 財団法人</option>
                        <option value="jgrants" <?php selected($organization_type, 'jgrants'); ?>>💻 Jグランツ</option>
                        <option value="other" <?php selected($organization_type, 'other'); ?>>📋 その他</option>
                    </select>
                </p>
            </div>
            
            <div>
                <p>
                    <label for="official_url"><strong>公式URL:</strong></label><br>
                    <input type="url" name="official_url" id="official_url" value="<?php echo esc_attr($official_url); ?>" 
                           style="width: 100%;" placeholder="https://example.com">
                </p>
                
                <p>
                    <label for="contact_info"><strong>問い合わせ先:</strong></label><br>
                    <textarea name="contact_info" id="contact_info" rows="3" 
                              style="width: 100%; resize: vertical;" 
                              placeholder="電話番号、メールアドレス等"><?php echo esc_textarea($contact_info); ?></textarea>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Google Sheets同期情報メタボックス
     */
    public function render_sync_info_metabox($post) {
        $last_sync = get_post_meta($post->ID, '_gi_last_sync', true);
        $sync_source = get_post_meta($post->ID, '_gi_sync_source', true);
        
        ?>
        <div class="grant-metabox-content">
            <div style="padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 12px;">
                <p><strong>🔄 同期ステータス</strong></p>
                
                <?php if ($last_sync): ?>
                    <p>📅 最終同期: <?php echo esc_html(date('Y-m-d H:i:s', strtotime($last_sync))); ?></p>
                <?php else: ?>
                    <p>📅 最終同期: 未同期</p>
                <?php endif; ?>
                
                <?php if ($sync_source): ?>
                    <p>📊 同期元: <?php echo esc_html($sync_source); ?></p>
                <?php else: ?>
                    <p>📊 同期元: WordPress直接作成</p>
                <?php endif; ?>
                
                <div style="margin-top: 10px;">
                    <button type="button" class="button button-small" onclick="syncSinglePost(<?php echo $post->ID; ?>)">
                        🔄 この投稿を同期
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        function syncSinglePost(postId) {
            if (confirm('この投稿をGoogle Sheetsと同期しますか？')) {
                // AJAX呼び出しでGoogle Sheets同期を実行
                jQuery.post(ajaxurl, {
                    action: 'gi_sync_single_post',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('gi_sync_single_post'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('同期が完了しました: ' + response.data);
                        location.reload();
                    } else {
                        alert('同期に失敗しました: ' + response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * メタボックス用のスクリプトを読み込み
     */
    public function enqueue_metabox_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        global $post_type;
        if ($post_type !== 'grant') {
            return;
        }
        
        wp_enqueue_script('grant-metaboxes', get_template_directory_uri() . '/assets/js/grant-metaboxes.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('grant-metaboxes', get_template_directory_uri() . '/assets/css/admin-metaboxes.css', array(), '1.0.0');
        
        wp_localize_script('grant-metaboxes', 'grantMetaboxes', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('grant_metaboxes_nonce')
        ));
    }
    
    /**
     * メタデータの保存
     */
    public function save_grant_metadata($post_id) {
        // 自動保存やリビジョンをスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        // 助成金投稿タイプのみ対象
        if (get_post_type($post_id) !== 'grant') return;
        
        // Nonce検証
        if (!isset($_POST['grant_metabox_nonce_field']) || 
            !wp_verify_nonce($_POST['grant_metabox_nonce_field'], 'grant_metabox_nonce')) {
            return;
        }
        
        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) return;
        
        // メタデータフィールドのマッピング
        $meta_fields = array(
            // 地域情報
            'target_prefecture' => sanitize_text_field($_POST['target_prefecture'] ?? ''),
            'prefecture_name' => sanitize_text_field($_POST['prefecture_name'] ?? ''),
            'target_municipality' => sanitize_textarea_field($_POST['target_municipality'] ?? ''),
            'regional_limitation' => sanitize_text_field($_POST['regional_limitation'] ?? 'nationwide'),
            
            // ステータス情報
            'application_status' => sanitize_text_field($_POST['application_status'] ?? 'open'),
            'application_method' => sanitize_text_field($_POST['application_method'] ?? 'online'),
            'deadline' => sanitize_text_field($_POST['deadline'] ?? ''),
            'deadline_date' => sanitize_text_field($_POST['deadline_date'] ?? ''),
            
            // 金額情報
            'max_amount' => sanitize_text_field($_POST['max_amount'] ?? ''),
            'max_amount_numeric' => intval($_POST['max_amount_numeric'] ?? 0),
            'min_amount' => intval($_POST['min_amount'] ?? 0),
            'subsidy_rate' => sanitize_text_field($_POST['subsidy_rate'] ?? ''),
            
            // 組織情報
            'organization' => sanitize_text_field($_POST['organization'] ?? ''),
            'organization_type' => sanitize_text_field($_POST['organization_type'] ?? 'national'),
            'contact_info' => sanitize_textarea_field($_POST['contact_info'] ?? ''),
            'official_url' => esc_url($_POST['official_url'] ?? '')
        );
        
        // ACFフィールドとして保存
        foreach ($meta_fields as $field_key => $value) {
            if (function_exists('update_field')) {
                update_field($field_key, $value, $post_id);
            } else {
                update_post_meta($post_id, $field_key, $value);
            }
        }
        
        // 都道府県名の自動生成
        if (!empty($meta_fields['target_prefecture'])) {
            $prefecture_name = $this->get_prefecture_name_by_code($meta_fields['target_prefecture']);
            if (function_exists('update_field')) {
                update_field('prefecture_name', $prefecture_name, $post_id);
            } else {
                update_post_meta($post_id, 'prefecture_name', $prefecture_name);
            }
        }
        
        // 同期メタデータを更新
        update_post_meta($post_id, '_gi_last_modified', current_time('mysql'));
        update_post_meta($post_id, '_gi_sync_source', 'wordpress_admin');
        
        // Google Sheetsへの同期をトリガー（WordPressで編集された場合）
        if (class_exists('GoogleSheetsSync')) {
            try {
                $sheets_sync = GoogleSheetsSync::getInstance();
                $post = get_post($post_id);
                $sheets_sync->sync_post_to_sheets($post_id, $post, true);
                
                // 同期完了メタデータを更新
                update_post_meta($post_id, '_gi_last_sync', current_time('mysql'));
            } catch (Exception $e) {
                // エラーログに記録（ユーザーには表示しない）
                error_log('Google Sheets sync error from admin: ' . $e->getMessage());
            }
        }
        
        do_action('gi_post_updated_in_admin', $post_id);
    }
    
    /**
     * 都道府県選択肢を取得
     */
    private function get_prefecture_options() {
        return array(
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
            'okinawa' => '沖縄県'
        );
    }
    
    /**
     * 都道府県コードから名前を取得
     */
    private function get_prefecture_name_by_code($code) {
        $prefectures = $this->get_prefecture_options();
        return isset($prefectures[$code]) ? $prefectures[$code] : '';
    }
}

// 単一投稿同期のAJAXハンドラー
add_action('wp_ajax_gi_sync_single_post', function() {
    check_ajax_referer('gi_sync_single_post', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('権限がありません');
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    if (!$post_id || get_post_type($post_id) !== 'grant') {
        wp_send_json_error('無効な投稿IDです');
        return;
    }
    
    try {
        // Google Sheets同期を実行
        if (class_exists('GoogleSheetsSync')) {
            $sheets_sync = GoogleSheetsSync::getInstance();
            $post = get_post($post_id);
            $sheets_sync->sync_post_to_sheets($post_id, $post, true);
            
            // 同期メタデータを更新
            update_post_meta($post_id, '_gi_last_sync', current_time('mysql'));
            
            wp_send_json_success('Google Sheetsとの同期が完了しました');
        } else {
            wp_send_json_error('Google Sheets同期機能が利用できません');
        }
    } catch (Exception $e) {
        wp_send_json_error('同期中にエラーが発生しました: ' . $e->getMessage());
    }
});

// インスタンス化
function gi_init_grant_metaboxes() {
    return GrantPostMetaboxes::getInstance();
}

add_action('init', 'gi_init_grant_metaboxes');
?>