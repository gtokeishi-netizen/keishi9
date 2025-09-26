<?php
/**
 * 助成金投稿用カスタムメタボックス (WordPress標準タクソノミー版)
 * 
 * Google Sheetsから同期される都道府県、助成金カテゴリー、対象市町村を
 * WordPress標準のタクソノミー機能として投稿編集画面のサイドバーで管理
 * ACFフィールドはそのまま維持し、特定の3項目のみタクソノミー化
 * 
 * @package Grant_Insight_Perfect
 * @version 2.0.0
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
     * 都道府県・カテゴリー・市町村はWordPress標準タクソノミー使用
     * その他のフィールドはACFを維持
     */
    public function add_grant_metaboxes() {
        // WordPress標準のタクソノミーメタボックスを置き換え
        // デフォルトのタクソノミーメタボックスを非表示にして、カスタム版を表示
        remove_meta_box('grant_categorydiv', 'grant', 'side');
        remove_meta_box('grant_prefecturediv', 'grant', 'side');
        remove_meta_box('grant_municipalitydiv', 'grant', 'side');
        
        // カスタムタクソノミーメタボックス
        add_meta_box(
            'grant-category-metabox',
            '📂 助成金カテゴリー',
            array($this, 'render_category_metabox'),
            'grant',
            'side',
            'high'
        );
        
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
        
        // ACFフィールド用メタボックス（既存フィールドを維持）
        add_meta_box(
            'grant-status-metabox',
            '📋 申請・ステータス情報（ACF）',
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
     * 助成金カテゴリーメタボックス（WordPress標準タクソノミー）
     */
    public function render_category_metabox($post) {
        wp_nonce_field('grant_taxonomy_nonce', 'grant_taxonomy_nonce_field');
        
        $categories = get_terms(array(
            'taxonomy' => 'grant_category',
            'hide_empty' => false
        ));
        
        $post_categories = wp_get_post_terms($post->ID, 'grant_category', array('fields' => 'ids'));
        
        ?>
        <div class="grant-metabox-content">
            <div id="grant-category-selection">
                <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" 
                                   name="grant_categories[]" 
                                   value="<?php echo esc_attr($category->term_id); ?>"
                                   <?php checked(in_array($category->term_id, $post_categories)); ?>>
                            <?php echo esc_html($category->name); ?>
                            <span style="color: #666;">（<?php echo $category->count; ?>件）</span>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666;">カテゴリーがありません。まず<a href="<?php echo admin_url('edit-tags.php?taxonomy=grant_category&post_type=grant'); ?>" target="_blank">カテゴリー管理</a>で作成してください。</p>
                <?php endif; ?>
                
                <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">
                    <input type="text" id="new_grant_category" placeholder="新しいカテゴリー名" style="width: 70%;">
                    <button type="button" id="add_grant_category" class="button button-small">追加</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 対象都道府県メタボックス（WordPress標準タクソノミー）
     */
    public function render_prefecture_metabox($post) {
        $prefectures = get_terms(array(
            'taxonomy' => 'grant_prefecture',
            'hide_empty' => false,
            'orderby' => 'name'
        ));
        
        $post_prefectures = wp_get_post_terms($post->ID, 'grant_prefecture', array('fields' => 'ids'));
        
        ?>
        <div class="grant-metabox-content">
            <div id="grant-prefecture-selection" style="max-height: 300px; overflow-y: auto;">
                <p>
                    <label>
                        <input type="checkbox" id="select_all_prefectures"> 
                        <strong>全国対象（全て選択）</strong>
                    </label>
                </p>
                <div style="border-top: 1px solid #ddd; padding-top: 8px; margin-top: 8px;">
                    <?php if (!empty($prefectures) && !is_wp_error($prefectures)): ?>
                        <?php foreach ($prefectures as $prefecture): ?>
                            <label style="display: block; margin-bottom: 6px;">
                                <input type="checkbox" 
                                       name="grant_prefectures[]" 
                                       value="<?php echo esc_attr($prefecture->term_id); ?>"
                                       class="prefecture-checkbox"
                                       <?php checked(in_array($prefecture->term_id, $post_prefectures)); ?>>
                                <?php echo esc_html($prefecture->name); ?>
                                <span style="color: #666;">（<?php echo $prefecture->count; ?>件）</span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666;">都道府県データがありません。<a href="<?php echo admin_url('admin.php?page=gi-prefecture-debug'); ?>" target="_blank">都道府県デバッグ</a>で初期化してください。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 対象市町村メタボックス（WordPress標準タクソノミー）
     */
    public function render_municipality_metabox($post) {
        $municipalities = get_terms(array(
            'taxonomy' => 'grant_municipality',
            'hide_empty' => false,
            'orderby' => 'name'
        ));
        
        $post_municipalities = wp_get_post_terms($post->ID, 'grant_municipality', array('fields' => 'ids'));
        
        ?>
        <div class="grant-metabox-content">
            <div style="margin-bottom: 10px;">
                <input type="text" id="municipality_search" placeholder="市町村を検索..." style="width: 100%;">
            </div>
            
            <div id="grant-municipality-selection" style="max-height: 250px; overflow-y: auto;">
                <?php if (!empty($municipalities) && !is_wp_error($municipalities)): ?>
                    <?php foreach ($municipalities as $municipality): ?>
                        <label style="display: block; margin-bottom: 6px;" class="municipality-option">
                            <input type="checkbox" 
                                   name="grant_municipalities[]" 
                                   value="<?php echo esc_attr($municipality->term_id); ?>"
                                   <?php checked(in_array($municipality->term_id, $post_municipalities)); ?>>
                            <?php echo esc_html($municipality->name); ?>
                            <span style="color: #666;">（<?php echo $municipality->count; ?>件）</span>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666;">市町村データがありません。</p>
                <?php endif; ?>
                
                <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">
                    <input type="text" id="new_municipality" placeholder="新しい市町村名" style="width: 70%;">
                    <button type="button" id="add_municipality" class="button button-small">追加</button>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        例：新宿区、渋谷区、札幌市、福岡市
                    </small>
                </div>
            </div>
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
     * メタデータとタクソノミーの保存
     */
    public function save_grant_metadata($post_id) {
        // 自動保存やリビジョンをスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        // 助成金投稿タイプのみ対象
        if (get_post_type($post_id) !== 'grant') return;
        
        // Nonce検証（タクソノミー用）
        if (!isset($_POST['grant_taxonomy_nonce_field']) || 
            !wp_verify_nonce($_POST['grant_taxonomy_nonce_field'], 'grant_taxonomy_nonce')) {
            return;
        }
        
        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) return;
        
        // ==========
        // 1. タクソノミーの保存（WordPress標準）
        // ==========
        
        // 助成金カテゴリーの保存
        if (isset($_POST['grant_categories'])) {
            $categories = array_map('intval', $_POST['grant_categories']);
            wp_set_post_terms($post_id, $categories, 'grant_category');
        } else {
            // チェックボックスが一つも選択されていない場合は空にする
            wp_set_post_terms($post_id, array(), 'grant_category');
        }
        
        // 都道府県の保存
        if (isset($_POST['grant_prefectures'])) {
            $prefectures = array_map('intval', $_POST['grant_prefectures']);
            wp_set_post_terms($post_id, $prefectures, 'grant_prefecture');
        } else {
            wp_set_post_terms($post_id, array(), 'grant_prefecture');
        }
        
        // 市町村の保存
        if (isset($_POST['grant_municipalities'])) {
            $municipalities = array_map('intval', $_POST['grant_municipalities']);
            wp_set_post_terms($post_id, $municipalities, 'grant_municipality');
        } else {
            wp_set_post_terms($post_id, array(), 'grant_municipality');
        }
        
        // ==========
        // 2. ACFフィールドの保存（既存フィールドを維持）
        // ==========
        
        // ACFフィールドのマッピング（タクソノミー化されていないフィールドのみ）
        $acf_fields = array(
            // ステータス情報（ACF維持）
            'application_status' => sanitize_text_field($_POST['application_status'] ?? 'open'),
            'application_method' => sanitize_text_field($_POST['application_method'] ?? 'online'),
            'deadline' => sanitize_text_field($_POST['deadline'] ?? ''),
            'deadline_date' => sanitize_text_field($_POST['deadline_date'] ?? ''),
            
            // 金額情報（ACF維持）
            'max_amount' => sanitize_text_field($_POST['max_amount'] ?? ''),
            'max_amount_numeric' => intval($_POST['max_amount_numeric'] ?? 0),
            'min_amount' => intval($_POST['min_amount'] ?? 0),
            'subsidy_rate' => sanitize_text_field($_POST['subsidy_rate'] ?? ''),
            
            // 組織情報（ACF維持）
            'organization' => sanitize_text_field($_POST['organization'] ?? ''),
            'organization_type' => sanitize_text_field($_POST['organization_type'] ?? 'national'),
            'contact_info' => sanitize_textarea_field($_POST['contact_info'] ?? ''),
            'official_url' => esc_url($_POST['official_url'] ?? ''),
            
            // 地域制限（ACF維持）
            'regional_limitation' => sanitize_text_field($_POST['regional_limitation'] ?? 'nationwide')
        );
        
        // ACFフィールドとして保存
        foreach ($acf_fields as $field_key => $value) {
            if (function_exists('update_field')) {
                update_field($field_key, $value, $post_id);
            } else {
                update_post_meta($post_id, $field_key, $value);
            }
        }
        
        // ==========
        // 3. 同期メタデータとGoogle Sheets連携
        // ==========
        
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
     * タクソノミーのタームデータをJSONで取得するヘルパー
     */
    private function get_taxonomy_terms_json($taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ));
        
        if (is_wp_error($terms)) {
            return '[]';
        }
        
        $term_data = array();
        foreach ($terms as $term) {
            $term_data[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count
            );
        }
        
        return json_encode($term_data);
    }
}

// タクソノミータームを追加するAJAXハンドラー
add_action('wp_ajax_gi_add_taxonomy_term', function() {
    check_ajax_referer('grant_metaboxes_nonce', 'nonce');
    
    if (!current_user_can('manage_categories')) {
        wp_send_json_error('権限がありません');
        return;
    }
    
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    $term_name = sanitize_text_field($_POST['term_name']);
    
    // 許可されたタクソノミーのみ
    $allowed_taxonomies = array('grant_category', 'grant_municipality', 'grant_prefecture');
    if (!in_array($taxonomy, $allowed_taxonomies)) {
        wp_send_json_error('無効なタクソノミーです');
        return;
    }
    
    if (empty($term_name)) {
        wp_send_json_error('タerm名が入力されていません');
        return;
    }
    
    // タームが既に存在するかチェック
    $existing_term = term_exists($term_name, $taxonomy);
    if ($existing_term) {
        wp_send_json_error('このタームは既に存在します');
        return;
    }
    
    // タームを作成
    $result = wp_insert_term($term_name, $taxonomy);
    
    if (is_wp_error($result)) {
        wp_send_json_error('タームの作成に失敗しました: ' . $result->get_error_message());
        return;
    }
    
    wp_send_json_success(array(
        'term_id' => $result['term_id'],
        'name' => $term_name,
        'taxonomy' => $taxonomy
    ));
});

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