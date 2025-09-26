<?php
/**
 * Debug Field Synchronization Script
 * 
 * Diagnostic tool to identify issues with Prefecture, Grant Category, and Target Municipality field sync
 * 
 * Usage: Place in WordPress root and access via browser
 * URL: /debug-field-sync.php
 */

// セキュリティとWordPress環境の読み込み
define('WP_USE_THEMES', false);
require_once('wp-load.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    die('Access denied: Administrator permission required.');
}

// Google Sheets Sync クラスのインスタンス化
if (!class_exists('GoogleSheetsSync')) {
    die('Error: GoogleSheetsSync class not found. Please ensure the plugin is active.');
}

$sheets_sync = GoogleSheetsSync::getInstance();

echo '<html><head><title>Field Sync Diagnostic</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
    .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
    .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px; }
    .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 3px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    table th { background-color: #f2f2f2; }
    code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style></head><body>';

echo '<h1>🔍 Field Synchronization Diagnostic</h1>';
echo '<p>Diagnosing: 都道府県 (Prefecture), 助成金カテゴリー (Grant Category), 対象市町村 (Target Municipality)</p>';

// 1. スプレッドシートデータの読み取りテスト
echo '<div class="section">';
echo '<h2>📊 1. スプレッドシートデータ読み取りテスト</h2>';

try {
    $sheet_data = $sheets_sync->read_sheet_data();
    
    if ($sheet_data === false) {
        echo '<div class="error">❌ スプレッドシートからデータを読み取れませんでした。</div>';
        echo '<p>可能な原因:</p>';
        echo '<ul>';
        echo '<li>Google Sheets APIの認証に問題がある</li>';
        echo '<li>スプレッドシートのアクセス権限に問題がある</li>';
        echo '<li>ネットワーク接続に問題がある</li>';
        echo '</ul>';
    } elseif (empty($sheet_data)) {
        echo '<div class="warning">⚠️ スプレッドシートは空です。</div>';
    } else {
        echo '<div class="success">✅ スプレッドシートデータを正常に読み取りました。</div>';
        echo '<p><strong>行数:</strong> ' . count($sheet_data) . '</p>';
        
        // ヘッダー行の確認
        if (!empty($sheet_data[0])) {
            echo '<h3>📝 ヘッダー行の確認</h3>';
            echo '<table>';
            echo '<tr><th>列</th><th>ヘッダー</th><th>対応フィールド</th></tr>';
            
            $headers = $sheet_data[0];
            $expected_mappings = array(
                16 => array('target_prefecture', '都道府県コード'), // R列 (0ベース)
                17 => array('prefecture_name', '都道府県名'), // S列
                18 => array('target_municipality', '対象市町村'), // T列
                21 => array('grant_category', 'カテゴリ') // W列 (0ベース)
            );
            
            foreach ($expected_mappings as $index => $mapping) {
                $column_letter = chr(65 + $index); // A=65
                $header_text = isset($headers[$index]) ? $headers[$index] : '(未定義)';
                $is_correct = strpos($header_text, $mapping[1]) !== false;
                
                echo '<tr style="' . ($is_correct ? 'background: #d4edda;' : 'background: #f8d7da;') . '">';
                echo '<td><strong>' . $column_letter . '列 (' . $index . ')</strong></td>';
                echo '<td>' . htmlspecialchars($header_text) . '</td>';
                echo '<td>' . $mapping[0] . ' (' . $mapping[1] . ') ' . ($is_correct ? '✅' : '❌') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // データ行のサンプル表示
        if (count($sheet_data) > 1) {
            echo '<h3>📊 データサンプル (最初の3行)</h3>';
            echo '<table>';
            echo '<tr><th>行</th><th>R列<br>(Prefecture Code)</th><th>S列<br>(Prefecture Name)</th><th>T列<br>(Municipality)</th><th>W列<br>(Category)</th></tr>';
            
            for ($i = 1; $i <= min(4, count($sheet_data) - 1); $i++) {
                $row = $sheet_data[$i];
                echo '<tr>';
                echo '<td>行' . ($i + 1) . '</td>';
                echo '<td>' . htmlspecialchars($row[16] ?? '') . '</td>'; // R列
                echo '<td>' . htmlspecialchars($row[17] ?? '') . '</td>'; // S列
                echo '<td>' . htmlspecialchars($row[18] ?? '') . '</td>'; // T列
                echo '<td>' . htmlspecialchars($row[21] ?? '') . '</td>'; // W列
                echo '</tr>';
            }
            echo '</table>';
        }
    }
} catch (Exception $e) {
    echo '<div class="error">❌ エラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</div>';

// 2. ACFフィールド構成の確認
echo '<div class="section">';
echo '<h2>🏗️ 2. ACFフィールド構成確認</h2>';

$test_posts = get_posts(array(
    'post_type' => 'grant',
    'posts_per_page' => 3,
    'post_status' => array('publish', 'draft')
));

if (empty($test_posts)) {
    echo '<div class="warning">⚠️ テスト用の助成金投稿が見つかりません。</div>';
} else {
    echo '<div class="success">✅ テスト用投稿を ' . count($test_posts) . ' 件見つけました。</div>';
    
    foreach ($test_posts as $post) {
        echo '<h4>投稿: ' . htmlspecialchars($post->post_title) . ' (ID: ' . $post->ID . ')</h4>';
        echo '<table>';
        echo '<tr><th>ACFフィールド</th><th>現在の値</th><th>フィールドタイプ</th></tr>';
        
        $target_fields = array(
            'target_prefecture' => '都道府県コード',
            'prefecture_name' => '都道府県名',
            'target_municipality' => '対象市町村'
        );
        
        foreach ($target_fields as $field_key => $field_label) {
            $value = get_field($field_key, $post->ID);
            $field_object = get_field_object($field_key, $post->ID);
            
            echo '<tr>';
            echo '<td><code>' . $field_key . '</code><br>(' . $field_label . ')</td>';
            echo '<td>' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : htmlspecialchars((string)$value)) . '</td>';
            echo '<td>' . ($field_object ? htmlspecialchars($field_object['type']) : '不明') . '</td>';
            echo '</tr>';
        }
        
        // カテゴリの確認
        $categories = wp_get_post_terms($post->ID, 'grant_category', array('fields' => 'names'));
        echo '<tr>';
        echo '<td><code>grant_category</code><br>(カテゴリ)</td>';
        echo '<td>' . (is_array($categories) ? implode(', ', $categories) : '(なし)') . '</td>';
        echo '<td>taxonomy</td>';
        echo '</tr>';
        
        echo '</table>';
    }
}

echo '</div>';

// 3. 同期ロジックのシミュレーション
echo '<div class="section">';
echo '<h2>⚙️ 3. 同期ロジック シミュレーション</h2>';

if ($sheet_data && count($sheet_data) > 1) {
    echo '<p>スプレッドシートデータを使用して同期処理をシミュレーションします...</p>';
    
    // ヘッダー行を除去
    $data_rows = array_slice($sheet_data, 1);
    $processed = 0;
    $errors = array();
    
    foreach (array_slice($data_rows, 0, 3) as $index => $row) { // 最初の3行のみテスト
        $row_number = $index + 2; // ヘッダー行を考慮
        
        echo '<h4>行 ' . $row_number . ' の処理シミュレーション</h4>';
        
        // 投稿IDの確認
        $post_id = intval($row[0] ?? 0);
        
        if ($post_id && get_post($post_id)) {
            echo '<div class="info">📝 既存投稿を更新: ID ' . $post_id . '</div>';
            
            // フィールド値の抽出
            $field_values = array(
                'target_prefecture' => $row[16] ?? '', // R列
                'prefecture_name' => $row[17] ?? '',   // S列
                'target_municipality' => $row[18] ?? '', // T列
            );
            
            $category_value = $row[21] ?? ''; // W列
            
            echo '<table>';
            echo '<tr><th>フィールド</th><th>スプレッドシート値</th><th>現在のWP値</th><th>ステータス</th></tr>';
            
            foreach ($field_values as $field_key => $sheet_value) {
                $current_value = get_field($field_key, $post_id);
                $is_different = (string)$current_value !== (string)$sheet_value;
                
                echo '<tr style="' . ($is_different ? 'background: #fff3cd;' : 'background: #d4edda;') . '">';
                echo '<td><code>' . $field_key . '</code></td>';
                echo '<td>' . htmlspecialchars($sheet_value) . '</td>';
                echo '<td>' . htmlspecialchars((string)$current_value) . '</td>';
                echo '<td>' . ($is_different ? '🔄 要更新' : '✅ 同期済み') . '</td>';
                echo '</tr>';
            }
            
            // カテゴリ同期チェック
            $current_categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
            $current_cats_str = is_array($current_categories) ? implode(', ', $current_categories) : '';
            $cats_different = $current_cats_str !== $category_value;
            
            echo '<tr style="' . ($cats_different ? 'background: #fff3cd;' : 'background: #d4edda;') . '">';
            echo '<td><code>grant_category</code></td>';
            echo '<td>' . htmlspecialchars($category_value) . '</td>';
            echo '<td>' . htmlspecialchars($current_cats_str) . '</td>';
            echo '<td>' . ($cats_different ? '🔄 要更新' : '✅ 同期済み') . '</td>';
            echo '</tr>';
            
            echo '</table>';
            
        } elseif (!empty($row[1])) { // タイトルがある場合
            echo '<div class="info">🆕 新規投稿を作成予定</div>';
            echo '<p><strong>タイトル:</strong> ' . htmlspecialchars($row[1] ?? '') . '</p>';
        } else {
            echo '<div class="warning">⚠️ スキップ: 投稿IDもタイトルも設定されていません</div>';
        }
        
        $processed++;
    }
    
    echo '<div class="success">✅ ' . $processed . ' 行の同期シミュレーションが完了しました。</div>';
} else {
    echo '<div class="warning">⚠️ スプレッドシートデータが不足しているため、同期シミュレーションをスキップしました。</div>';
}

echo '</div>';

// 4. 推奨される修正手順
echo '<div class="section">';
echo '<h2>🔧 4. 推奨される修正手順</h2>';

echo '<div class="info">';
echo '<h3>✅ 問題の特定方法</h3>';
echo '<ol>';
echo '<li><strong>スプレッドシート確認:</strong> Google Sheetsで対象のフィールド（R、S、T、W列）にデータが入力されていることを確認</li>';
echo '<li><strong>列の位置確認:</strong> ヘッダー行が正しく設定され、列の位置がずれていないことを確認</li>';
echo '<li><strong>手動同期実行:</strong> WordPress管理画面から手動同期を実行してエラーメッセージを確認</li>';
echo '<li><strong>ログ確認:</strong> 同期ログでエラーや警告を確認</li>';
echo '</ol>';
echo '</div>';

echo '<div class="warning">';
echo '<h3>⚠️ 一般的な問題と解決方法</h3>';
echo '<ul>';
echo '<li><strong>列の位置ずれ:</strong> スプレッドシートでヘッダー行を再設定するか、列を挿入/削除した場合は初期化が必要</li>';
echo '<li><strong>データ形式の不一致:</strong> 都道府県コードは英語（例: tokyo, osaka）、カテゴリはカンマ区切りで入力</li>';
echo '<li><strong>ACFフィールドの問題:</strong> フィールドが存在しないか、フィールドキーが一致しない</li>';
echo '<li><strong>権限の問題:</strong> GoogleシートのAPI権限が正しく設定されていない</li>';
echo '</ul>';
echo '</div>';

echo '<div class="success">';
echo '<h3>🛠️ トラブルシューティング手順</h3>';
echo '<ol>';
echo '<li><strong>スプレッドシート初期化:</strong> WordPress管理画面で「スプレッドシートを初期化」を実行</li>';
echo '<li><strong>手動同期テスト:</strong> 「Sheets → WordPress」同期を実行してエラーを確認</li>';
echo '<li><strong>個別フィールド確認:</strong> 特定の投稿でACFフィールドが正しく更新されるかテスト</li>';
echo '<li><strong>Google Apps Script確認:</strong> GASのログでリアルタイム同期が動作しているか確認</li>';
echo '</ol>';
echo '</div>';

echo '</div>';

// 5. 即座に実行可能なテスト
echo '<div class="section">';
echo '<h2>🚀 5. 即座実行テスト</h2>';

echo '<div class="info">';
echo '<h3>Google Sheets接続テスト</h3>';
try {
    $access_token = $sheets_sync->get_access_token();
    if ($access_token) {
        echo '<div class="success">✅ Google Sheets APIアクセストークンを正常に取得しました。</div>';
        
        // テスト読み取り
        $test_read = $sheets_sync->read_sheet_data($sheets_sync->get_sheet_name() . '!A1:A1');
        if ($test_read !== false) {
            echo '<div class="success">✅ スプレッドシートからの読み取りテストが成功しました。</div>';
        } else {
            echo '<div class="error">❌ スプレッドシートからの読み取りテストが失敗しました。</div>';
        }
    } else {
        echo '<div class="error">❌ Google Sheets APIアクセストークンの取得に失敗しました。</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">❌ 接続テストでエラーが発生: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

echo '<div class="info">';
echo '<h3>ACFフィールド存在確認</h3>';
$required_fields = array('target_prefecture', 'prefecture_name', 'target_municipality');
foreach ($required_fields as $field_key) {
    if (function_exists('acf_get_field')) {
        $field = acf_get_field($field_key);
        if ($field) {
            echo '<div class="success">✅ ACFフィールド <code>' . $field_key . '</code> が見つかりました。</div>';
        } else {
            echo '<div class="error">❌ ACFフィールド <code>' . $field_key . '</code> が見つかりません。</div>';
        }
    } else {
        echo '<div class="warning">⚠️ ACF関数が利用できません。フィールド確認をスキップします。</div>';
        break;
    }
}
echo '</div>';

echo '</div>';

echo '<hr>';
echo '<p><strong>診断完了:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><a href="' . admin_url('options-general.php?page=grant-sheets-sync') . '">← Google Sheets連携設定に戻る</a></p>';

echo '</body></html>';
?>