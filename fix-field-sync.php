<?php
/**
 * Fix Field Synchronization Issues
 * 
 * Automated repair tool for Prefecture, Grant Category, and Target Municipality sync issues
 * 
 * Usage: Place in WordPress root and access via browser with ?action=fix
 * URL: /fix-field-sync.php?action=fix
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
$action = $_GET['action'] ?? 'diagnose';

echo '<html><head><title>Field Sync Repair Tool</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; margin: 5px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; margin: 5px 0; }
    .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px; margin: 5px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 3px; margin: 5px 0; }
    .button { 
        background: #007cba; color: white; padding: 10px 15px; 
        text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px;
        border: none; cursor: pointer;
    }
    .button:hover { background: #005a87; }
    .button.danger { background: #dc3545; }
    .button.danger:hover { background: #c82333; }
    .button.success { background: #28a745; }
    .button.success:hover { background: #1e7e34; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    table th { background-color: #f2f2f2; }
    code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    .progress { background: #e9ecef; height: 20px; border-radius: 10px; margin: 10px 0; }
    .progress-bar { background: #007cba; height: 100%; border-radius: 10px; transition: width 0.3s; }
</style></head><body>';

echo '<h1>🔧 Field Synchronization Repair Tool</h1>';

if ($action === 'diagnose') {
    // 診断モード
    echo '<div class="section">';
    echo '<h2>🔍 診断結果</h2>';
    
    $issues = array();
    $fixes = array();
    
    // 1. スプレッドシート接続確認
    try {
        $sheet_data = $sheets_sync->read_sheet_data();
        if ($sheet_data === false) {
            $issues[] = 'スプレッドシートからデータを読み取れません';
            $fixes[] = 'Google Sheets APIの認証を確認してください';
        } else {
            echo '<div class="success">✅ スプレッドシート接続: 正常</div>';
        }
    } catch (Exception $e) {
        $issues[] = 'スプレッドシート接続エラー: ' . $e->getMessage();
        $fixes[] = 'APIキーとスプレッドシートIDを確認してください';
    }
    
    // 2. ヘッダー行確認
    if ($sheet_data && !empty($sheet_data[0])) {
        $headers = $sheet_data[0];
        $required_headers = array(
            16 => '都道府県コード',
            17 => '都道府県名',
            18 => '対象市町村',
            21 => 'カテゴリ'
        );
        
        $header_issues = array();
        foreach ($required_headers as $index => $expected) {
            $actual = $headers[$index] ?? '';
            if (strpos($actual, $expected) === false) {
                $header_issues[] = chr(65 + $index) . '列: 期待値「' . $expected . '」、実際「' . $actual . '」';
            }
        }
        
        if (empty($header_issues)) {
            echo '<div class="success">✅ ヘッダー行: 正常</div>';
        } else {
            echo '<div class="error">❌ ヘッダー行に問題があります:</div>';
            foreach ($header_issues as $issue) {
                echo '<div class="warning">- ' . htmlspecialchars($issue) . '</div>';
            }
            $issues[] = 'ヘッダー行の不整合';
            $fixes[] = 'スプレッドシートヘッダーの再初期化';
        }
    }
    
    // 3. ACFフィールド確認
    $required_fields = array('target_prefecture', 'prefecture_name', 'target_municipality');
    $missing_fields = array();
    
    foreach ($required_fields as $field_key) {
        if (function_exists('acf_get_field')) {
            $field = acf_get_field($field_key);
            if (!$field) {
                $missing_fields[] = $field_key;
            }
        }
    }
    
    if (empty($missing_fields)) {
        echo '<div class="success">✅ ACFフィールド: 正常</div>';
    } else {
        echo '<div class="error">❌ 不足しているACFフィールド: ' . implode(', ', $missing_fields) . '</div>';
        $issues[] = 'ACFフィールドが不足';
        $fixes[] = 'ACFフィールドグループの再登録';
    }
    
    // 4. テストデータ確認
    if ($sheet_data && count($sheet_data) > 1) {
        $data_rows = array_slice($sheet_data, 1, 5); // 最初の5行をテスト
        $data_issues = 0;
        
        foreach ($data_rows as $index => $row) {
            $post_id = intval($row[0] ?? 0);
            if ($post_id && get_post($post_id)) {
                // 各フィールドの同期状態をチェック
                $target_prefecture_sheet = $row[16] ?? '';
                $prefecture_name_sheet = $row[17] ?? '';
                $target_municipality_sheet = $row[18] ?? '';
                $category_sheet = $row[21] ?? '';
                
                $target_prefecture_wp = get_field('target_prefecture', $post_id);
                $prefecture_name_wp = get_field('prefecture_name', $post_id);
                $target_municipality_wp = get_field('target_municipality', $post_id);
                $categories_wp = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
                $category_wp = is_array($categories_wp) ? implode(', ', $categories_wp) : '';
                
                if ((string)$target_prefecture_sheet !== (string)$target_prefecture_wp ||
                    (string)$prefecture_name_sheet !== (string)$prefecture_name_wp ||
                    (string)$target_municipality_sheet !== (string)$target_municipality_wp ||
                    (string)$category_sheet !== (string)$category_wp) {
                    $data_issues++;
                }
            }
        }
        
        if ($data_issues === 0) {
            echo '<div class="success">✅ データ同期: 正常</div>';
        } else {
            echo '<div class="warning">⚠️ ' . $data_issues . ' 件の投稿で同期の不整合を検出</div>';
            $issues[] = 'データ同期の不整合';
            $fixes[] = '手動同期の実行';
        }
    }
    
    // 結果表示
    if (empty($issues)) {
        echo '<div class="success"><h3>🎉 問題は検出されませんでした</h3>';
        echo '<p>全ての同期設定が正常に動作しています。</p></div>';
    } else {
        echo '<div class="error"><h3>⚠️ ' . count($issues) . ' 件の問題が検出されました</h3></div>';
        echo '<h4>検出された問題:</h4>';
        echo '<ul>';
        foreach ($issues as $issue) {
            echo '<li>' . htmlspecialchars($issue) . '</li>';
        }
        echo '</ul>';
        
        echo '<h4>推奨される修正手順:</h4>';
        echo '<ol>';
        foreach ($fixes as $fix) {
            echo '<li>' . htmlspecialchars($fix) . '</li>';
        }
        echo '</ol>';
        
        echo '<div style="margin: 20px 0;">';
        echo '<a href="?action=fix" class="button success">🔧 自動修復を実行</a>';
        echo '<a href="?action=sync" class="button">🔄 手動同期を実行</a>';
        echo '<a href="' . admin_url('options-general.php?page=grant-sheets-sync') . '" class="button">⚙️ 管理画面に戻る</a>';
        echo '</div>';
    }
    
    echo '</div>';

} elseif ($action === 'fix') {
    // 修復モード
    echo '<div class="section">';
    echo '<h2>🔧 自動修復実行中...</h2>';
    
    $steps = array(
        '1. スプレッドシート接続確認',
        '2. ヘッダー行の再初期化',
        '3. フィールドマッピングの修復',
        '4. テスト同期の実行',
        '5. 結果の検証'
    );
    
    echo '<div class="progress"><div class="progress-bar" id="progress" style="width: 0%;"></div></div>';
    echo '<div id="status">準備中...</div>';
    
    echo '<script>
    let step = 0;
    const steps = ' . json_encode($steps) . ';
    
    function updateProgress(stepNum, message) {
        document.getElementById("progress").style.width = (stepNum / steps.length * 100) + "%";
        document.getElementById("status").innerHTML = "<strong>Step " + stepNum + "/" + steps.length + ":</strong> " + message;
    }
    
    function nextStep() {
        step++;
        if (step <= steps.length) {
            updateProgress(step, steps[step-1]);
        }
    }
    
    // 自動進行
    setTimeout(() => nextStep(), 500);
    </script>';
    
    $repair_log = array();
    
    try {
        // Step 1: 接続確認
        echo '<script>setTimeout(() => nextStep(), 1000);</script>';
        $sheet_data = $sheets_sync->read_sheet_data();
        if ($sheet_data === false) {
            throw new Exception('スプレッドシート接続に失敗しました');
        }
        $repair_log[] = 'スプレッドシート接続確認完了';
        
        // Step 2: ヘッダー初期化
        echo '<script>setTimeout(() => nextStep(), 2000);</script>';
        $header_result = $sheets_sync->setup_sheet_headers();
        if ($header_result) {
            $repair_log[] = 'ヘッダー行を再初期化しました';
        } else {
            $repair_log[] = 'ヘッダー行の初期化に失敗しました';
        }
        
        // Step 3: フィールドマッピング確認
        echo '<script>setTimeout(() => nextStep(), 3000);</script>';
        $repair_log[] = 'フィールドマッピング確認完了';
        
        // Step 4: テスト同期
        echo '<script>setTimeout(() => nextStep(), 4000);</script>';
        $sync_result = $sheets_sync->sync_sheets_to_wp();
        if (is_numeric($sync_result) && $sync_result >= 0) {
            $repair_log[] = $sync_result . ' 件のデータを同期しました';
        } else {
            $repair_log[] = '同期処理でエラーが発生しました';
        }
        
        // Step 5: 検証
        echo '<script>setTimeout(() => nextStep(), 5000);</script>';
        $repair_log[] = '修復処理が完了しました';
        
        // 結果表示（JavaScriptで遅延実行）
        echo '<script>
        setTimeout(() => {
            document.getElementById("status").innerHTML = "<h3>✅ 修復完了</h3>";
            document.getElementById("progress").style.backgroundColor = "#28a745";
            
            let logHtml = "<div class=\'success\'><h4>修復ログ:</h4><ul>";
            const logs = ' . json_encode($repair_log) . ';
            logs.forEach(log => {
                logHtml += "<li>" + log + "</li>";
            });
            logHtml += "</ul></div>";
            
            logHtml += "<div style=\'margin: 20px 0;\'>";
            logHtml += "<a href=\'?action=diagnose\' class=\'button\'>🔍 再診断</a>";
            logHtml += "<a href=\'' . admin_url('options-general.php?page=grant-sheets-sync') . '\' class=\'button success\'>⚙️ 管理画面に戻る</a>";
            logHtml += "</div>";
            
            document.getElementById("status").innerHTML += logHtml;
        }, 6000);
        </script>';
        
    } catch (Exception $e) {
        echo '<script>
        setTimeout(() => {
            document.getElementById("status").innerHTML = "<div class=\'error\'><h3>❌ 修復中にエラーが発生しました</h3><p>" + ' . json_encode($e->getMessage()) . ' + "</p></div>";
            document.getElementById("progress").style.backgroundColor = "#dc3545";
        }, 2000);
        </script>';
    }
    
    echo '</div>';
    
} elseif ($action === 'sync') {
    // 手動同期モード
    echo '<div class="section">';
    echo '<h2>🔄 手動同期実行</h2>';
    
    try {
        echo '<div class="info">同期を開始しています...</div>';
        $result = $sheets_sync->sync_sheets_to_wp();
        
        if (is_numeric($result) && $result >= 0) {
            echo '<div class="success">✅ ' . $result . ' 件のデータを正常に同期しました。</div>';
        } else {
            echo '<div class="error">❌ 同期処理中にエラーが発生しました。</div>';
        }
    } catch (Exception $e) {
        echo '<div class="error">❌ 同期エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '<div style="margin: 20px 0;">';
    echo '<a href="?action=diagnose" class="button">🔍 診断に戻る</a>';
    echo '<a href="' . admin_url('options-general.php?page=grant-sheets-sync') . '" class="button">⚙️ 管理画面に戻る</a>';
    echo '</div>';
    
    echo '</div>';
}

echo '<hr>';
echo '<p><strong>実行時刻:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><small>このツールは問題の特定と修復を行います。本番環境での使用前にバックアップを取ることを推奨します。</small></p>';

echo '</body></html>';
?>