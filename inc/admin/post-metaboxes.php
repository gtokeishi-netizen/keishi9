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
        
        // 注：ACFフィールドはそのまま標準のACF表示を使用
        // Google Sheets同期は自動で動作するため、手動同期メタボックスは不要
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
    
    // 削除済み：ACFフィールド用のメタボックスレンダリング関数
    // ACFは標準の表示機能を使用し、カスタムメタボックスは不要
    
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
        
        // ACFフィールドは標準のACF保存機能に任せる（自動処理される）
        
        // Google Sheetsとの同期は既存のフック機能で自動実行される
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

// Google Sheets同期は自動で動作するため、手動同期は不要

// インスタンス化
function gi_init_grant_metaboxes() {
    return GrantPostMetaboxes::getInstance();
}

add_action('init', 'gi_init_grant_metaboxes');
?>