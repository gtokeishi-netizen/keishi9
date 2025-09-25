/**
 * Google Apps Script for WordPress Sheet Sync
 * 
 * スプレッドシートの変更をWordPressにリアルタイムで同期
 * 設置方法：
 * 1. Google Apps Scriptで新しいプロジェクトを作成
 * 2. このコードをコピー＆ペースト
 * 3. 下記の設定値を更新
 * 4. トリガーを設定（onEdit, onChange）
 */

// =============================================================================
// 設定 - こちらを環境に合わせて変更してください
// =============================================================================

const CONFIG = {
  // WordPressのWebhook URL
  WEBHOOK_URL: 'https://your-domain.com/?gi_sheets_webhook=true',
  
  // REST API URL (推奨)
  REST_API_URL: 'https://your-domain.com/wp-json/gi/v1/sheets-webhook',
  
  // Webhook Secret Key (WordPressの管理画面で確認)
  SECRET_KEY: 'your_webhook_secret_key_here',
  
  // 対象のシート名
  SHEET_NAME: 'grant_import',
  
  // WordPress サイトのベースURL（REST APIのベース）
  WORDPRESS_BASE_URL: 'https://joseikin-insight.com',
  
  // デバッグモード（trueにすると詳細ログを出力）
  DEBUG_MODE: true
};

// =============================================================================
// メイン処理関数
// =============================================================================

/**
 * セル編集時のトリガー関数
 * Google Apps Scriptのトリガー設定で「編集時」に設定
 */
function onEdit(e) {
  try {
    debugLog('onEdit triggered', e);
    
    const sheet = e.source.getActiveSheet();
    
    // 対象シートかチェック
    if (sheet.getName() !== CONFIG.SHEET_NAME) {
      debugLog('Not target sheet:', sheet.getName());
      return;
    }
    
    // ヘッダー行の編集は無視
    if (e.range.getRow() === 1) {
      debugLog('Header row edited, ignoring');
      return;
    }
    
    // 編集された行のデータを取得
    const rowNumber = e.range.getRow();
    const rowData = getRowData(sheet, rowNumber);
    
    if (!rowData) {
      debugLog('No row data found');
      return;
    }
    
    // WordPress に同期
    syncRowToWordPress('row_updated', {
      row_number: rowNumber,
      row_data: rowData,
      edited_range: e.range.getA1Notation(),
      old_value: e.oldValue,
      new_value: e.value
    });
    
  } catch (error) {
    console.error('onEdit error:', error);
    logError('onEdit failed', error);
  }
}

/**
 * シート変更時のトリガー関数
 * Google Apps Scriptのトリガー設定で「変更時」に設定
 */
function onChange(e) {
  try {
    debugLog('onChange triggered', e);
    
    const sheet = SpreadsheetApp.getActiveSheet();
    
    // 対象シートかチェック
    if (sheet.getName() !== CONFIG.SHEET_NAME) {
      return;
    }
    
    // 変更タイプに応じて処理
    switch (e.changeType) {
      case 'INSERT_ROW':
        handleRowInsert(sheet, e);
        break;
      case 'REMOVE_ROW':
        handleRowDelete(sheet, e);
        break;
      case 'INSERT_COLUMN':
      case 'REMOVE_COLUMN':
        // 列の変更は特に処理しない
        break;
      default:
        debugLog('Unhandled change type:', e.changeType);
    }
    
  } catch (error) {
    console.error('onChange error:', error);
    logError('onChange failed', error);
  }
}

/**
 * 手動で全データを同期
 * 管理者が手動実行する際に使用
 */
function manualFullSync() {
  try {
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.SHEET_NAME);
    
    if (!sheet) {
      throw new Error(`Sheet ${CONFIG.SHEET_NAME} not found`);
    }
    
    const data = sheet.getDataRange().getValues();
    const headers = data.shift(); // ヘッダー行を除去
    
    const updates = [];
    
    data.forEach((row, index) => {
      if (row.some(cell => cell !== '')) { // 空行でない場合
        updates.push({
          action: 'update',
          row_number: index + 2, // ヘッダー行分を加算
          row_data: row
        });
      }
    });
    
    if (updates.length > 0) {
      syncRowToWordPress('bulk_update', {
        updates: updates
      });
    }
    
    console.log(`Manual sync completed: ${updates.length} rows`);
    
  } catch (error) {
    console.error('Manual sync error:', error);
    logError('Manual sync failed', error);
  }
}

/**
 * WordPressから投稿データをインポート
 * WordPress側から呼び出される初期化関数
 */
function importGrantPosts() {
  try {
    console.log('Starting grant posts import...');
    
    // スプレッドシートを取得または作成
    const sheet = getOrCreateSheet();
    
    // ヘッダー行を設定
    setupHeaders(sheet);
    
    // WordPressからデータを要求
    const postsData = requestGrantPostsFromWordPress();
    
    if (!postsData || postsData.length === 0) {
      console.log('No posts data received from WordPress');
      return {
        success: true,
        message: 'Import completed - no posts found',
        imported: 0
      };
    }
    
    // 既存データをクリア（ヘッダー以外）
    clearExistingData(sheet);
    
    // データを書き込み
    let importedCount = 0;
    
    postsData.forEach((postData, index) => {
      try {
        const rowNumber = index + 2; // ヘッダー行の下から開始
        const range = sheet.getRange(rowNumber, 1, 1, postData.length);
        range.setValues([postData]);
        importedCount++;
      } catch (rowError) {
        console.error(`Failed to import row ${index + 2}:`, rowError);
      }
    });
    
    console.log(`Import completed: ${importedCount} posts imported`);
    
    return {
      success: true,
      message: `Import completed successfully. ${importedCount} posts imported.`,
      imported: importedCount
    };
    
  } catch (error) {
    console.error('Import grant posts failed:', error);
    logError('Import grant posts failed', error);
    
    return {
      success: false,
      message: `Import failed: ${error.message}`,
      imported: 0
    };
  }
}

/**
 * スプレッドシートを初期化（ヘッダーのみ）
 */
function initializeSheet() {
  try {
    console.log('Initializing sheet...');
    
    const sheet = getOrCreateSheet();
    setupHeaders(sheet);
    
    console.log('Sheet initialization completed');
    
    return {
      success: true,
      message: 'Sheet initialized successfully'
    };
    
  } catch (error) {
    console.error('Sheet initialization failed:', error);
    logError('Sheet initialization failed', error);
    
    return {
      success: false,
      message: `Initialization failed: ${error.message}`
    };
  }
}

// =============================================================================
// ヘルパー関数
// =============================================================================

/**
 * 指定行のデータを取得
 */
function getRowData(sheet, rowNumber) {
  try {
    const range = sheet.getRange(rowNumber, 1, 1, sheet.getLastColumn());
    const values = range.getValues()[0];
    
    // 空の行は null を返す
    if (values.every(cell => cell === '')) {
      return null;
    }
    
    return values;
    
  } catch (error) {
    console.error('getRowData error:', error);
    return null;
  }
}

/**
 * シートを取得または作成
 */
function getOrCreateSheet() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = spreadsheet.getSheetByName(CONFIG.SHEET_NAME);
  
  if (!sheet) {
    console.log(`Creating new sheet: ${CONFIG.SHEET_NAME}`);
    sheet = spreadsheet.insertSheet(CONFIG.SHEET_NAME);
  }
  
  return sheet;
}

/**
 * ヘッダー行を設定
 */
function setupHeaders(sheet) {
  const headers = [
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
  ];
  
  // ヘッダー行を設定
  const headerRange = sheet.getRange(1, 1, 1, headers.length);
  headerRange.setValues([headers]);
  
  // ヘッダー行をフォーマット
  headerRange.setFontWeight('bold');
  headerRange.setBackground('#f0f0f0');
  
  // 列幅を自動調整
  sheet.autoResizeColumns(1, headers.length);
  
  console.log('Headers setup completed');
}

/**
 * 既存データをクリア（ヘッダー以外）
 */
function clearExistingData(sheet) {
  const lastRow = sheet.getLastRow();
  
  if (lastRow > 1) {
    const dataRange = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn());
    dataRange.clearContent();
    console.log(`Cleared existing data: rows 2-${lastRow}`);
  }
}

/**
 * WordPressから投稿データを要求
 */
function requestGrantPostsFromWordPress() {
  try {
    console.log('Requesting grant posts data from WordPress...');
    
    // WordPress のエクスポート API エンドポイントを構築
    const baseUrl = CONFIG.REST_API_URL.replace('/sheets-webhook', '');
    const exportUrl = `${baseUrl}/export-grants`;
    
    const options = {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      }
    };
    
    const response = UrlFetchApp.fetch(exportUrl, options);
    const responseCode = response.getResponseCode();
    const responseText = response.getContentText();
    
    console.log('WordPress export response:', {
      code: responseCode,
      body: responseText.substring(0, 200) + '...'
    });
    
    if (responseCode >= 200 && responseCode < 300) {
      const data = JSON.parse(responseText);
      return data.success ? data.data : null;
    } else {
      console.error(`WordPress export failed: HTTP ${responseCode}`);
      return null;
    }
    
  } catch (error) {
    console.error('Failed to request posts from WordPress:', error);
    
    // フォールバック: 手動でのデータ入力を促すメッセージ行を作成
    return [
      [
        '',  // ID (空欄)
        'サンプル助成金',  // タイトル
        'こちらはサンプルデータです。実際のデータを入力してください。',  // 内容
        'サンプルの抜粋です',  // 抜粋
        'draft',  // ステータス
        new Date().toISOString().substring(0, 19).replace('T', ' '),  // 作成日
        new Date().toISOString().substring(0, 19).replace('T', ' '),  // 更新日
        '最大100万円',  // 助成金額（表示用）
        1000000,  // 助成金額（数値）
        '2024年12月31日',  // 申請期限（表示用）
        '2024-12-31',  // 申請期限（日付）
        '◯◯財団',  // 実施組織
        'foundation',  // 組織タイプ
        '中小企業向け',  // 対象者・対象事業
        'online',  // 申請方法
        'contact@example.com',  // 問い合わせ先
        'https://example.com',  // 公式URL
        'tokyo',  // 都道府県コード
        '東京都',  // 都道府県名
        '全域',  // 対象市町村
        'prefecture',  // 地域制限
        'open',  // 申請ステータス
        'ビジネス支援',  // カテゴリ
        'スタートアップ, 中小企業',  // タグ
        new Date().toISOString().substring(0, 19).replace('T', ' ')  // シート更新日
      ]
    ];
  }
}

/**
 * 行挿入の処理
 */
function handleRowInsert(sheet, e) {
  try {
    debugLog('Row inserted', e);
    
    // 新しい行のデータを取得
    // 少し待ってからデータを取得（Google Sheetsの処理完了を待つ）
    Utilities.sleep(1000);
    
    const insertedRows = sheet.getDataRange().getValues();
    
    // 最後の行が新規追加されたと仮定
    const lastRow = insertedRows.length;
    const rowData = insertedRows[lastRow - 1];
    
    // 空行でない場合のみ同期
    if (rowData.some(cell => cell !== '')) {
      syncRowToWordPress('row_added', {
        row_number: lastRow,
        row_data: rowData
      });
    }
    
  } catch (error) {
    console.error('handleRowInsert error:', error);
    logError('Row insert handling failed', error);
  }
}

/**
 * 行削除の処理
 */
function handleRowDelete(sheet, e) {
  try {
    debugLog('Row deleted', e);
    
    // 削除された行の情報は取得困難なため
    // WordPressでの削除は手動またはステータス変更で行う
    // ここではログのみ記録
    
    logError('Row deleted - manual cleanup may be required', {
      changeType: e.changeType,
      timestamp: new Date()
    });
    
  } catch (error) {
    console.error('handleRowDelete error:', error);
  }
}

/**
 * WordPressに同期リクエストを送信
 */
function syncRowToWordPress(action, payload) {
  try {
    const timestamp = Math.floor(Date.now() / 1000);
    const payloadString = JSON.stringify(payload);
    const signature = createSignature(timestamp, payloadString);
    
    const requestData = {
      timestamp: timestamp,
      signature: signature,
      payload: {
        action: action,
        ...payload
      }
    };
    
    debugLog('Sending to WordPress:', requestData);
    
    // REST API を優先して使用
    const url = CONFIG.REST_API_URL || CONFIG.WEBHOOK_URL;
    
    const options = {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      payload: JSON.stringify(requestData)
    };
    
    const response = UrlFetchApp.fetch(url, options);
    const responseCode = response.getResponseCode();
    const responseText = response.getContentText();
    
    debugLog('WordPress response:', {
      code: responseCode,
      body: responseText
    });
    
    if (responseCode >= 200 && responseCode < 300) {
      console.log(`Sync successful: ${action}`);
      return true;
    } else {
      throw new Error(`HTTP ${responseCode}: ${responseText}`);
    }
    
  } catch (error) {
    console.error('Sync to WordPress failed:', error);
    logError('WordPress sync failed', {
      action: action,
      error: error.toString(),
      payload: payload
    });
    return false;
  }
}

/**
 * HMAC-SHA256 署名を作成
 */
function createSignature(timestamp, payload) {
  const message = timestamp + payload;
  const signature = Utilities.computeHmacSha256Signature(message, CONFIG.SECRET_KEY);
  return signature.map(byte => {
    const hex = (byte & 0xFF).toString(16);
    return hex.length === 1 ? '0' + hex : hex;
  }).join('');
}

/**
 * デバッグログ出力
 */
function debugLog(message, data = null) {
  if (CONFIG.DEBUG_MODE) {
    console.log(`[DEBUG] ${message}`, data || '');
  }
}

/**
 * エラーログを記録
 */
function logError(message, error) {
  const errorData = {
    message: message,
    error: error.toString(),
    timestamp: new Date(),
    stack: error.stack || 'No stack trace'
  };
  
  console.error('[ERROR]', errorData);
  
  // エラーログをシートに記録（オプション）
  recordErrorToSheet(errorData);
}

/**
 * エラーをシートに記録
 */
function recordErrorToSheet(errorData) {
  try {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    let errorSheet = spreadsheet.getSheetByName('Error_Log');
    
    // エラーログシートが存在しない場合は作成
    if (!errorSheet) {
      errorSheet = spreadsheet.insertSheet('Error_Log');
      errorSheet.getRange(1, 1, 1, 4).setValues([
        ['Timestamp', 'Message', 'Error', 'Stack']
      ]);
    }
    
    // エラーデータを追加
    errorSheet.appendRow([
      errorData.timestamp,
      errorData.message,
      errorData.error,
      errorData.stack
    ]);
    
    // 100行を超えたら古いログを削除
    if (errorSheet.getLastRow() > 101) { // ヘッダー + 100行
      errorSheet.deleteRows(2, errorSheet.getLastRow() - 101);
    }
    
  } catch (err) {
    console.error('Failed to record error to sheet:', err);
  }
}

// =============================================================================
// セットアップ関数
// =============================================================================

/**
 * 初期セットアップ
 * 一度だけ実行してトリガーを設定
 */
function setupTriggers() {
  try {
    // 既存のトリガーを削除
    const triggers = ScriptApp.getProjectTriggers();
    triggers.forEach(trigger => {
      ScriptApp.deleteTrigger(trigger);
    });
    
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // 編集時トリガー
    ScriptApp.newTrigger('onEdit')
      .onEdit()
      .create();
    
    // 変更時トリガー
    ScriptApp.newTrigger('onChange')
      .onChange()
      .create();
    
    console.log('Triggers setup completed');
    
    // 設定確認
    console.log('Configuration:', {
      WEBHOOK_URL: CONFIG.WEBHOOK_URL,
      REST_API_URL: CONFIG.REST_API_URL,
      SHEET_NAME: CONFIG.SHEET_NAME,
      SECRET_KEY: CONFIG.SECRET_KEY ? 'Set' : 'Not set'
    });
    
  } catch (error) {
    console.error('Setup failed:', error);
    throw error;
  }
}

/**
 * 接続テスト
 */
function testConnection() {
  try {
    console.log('Testing connection to WordPress...');
    
    const testPayload = {
      action: 'test',
      message: 'Connection test from Google Apps Script',
      timestamp: new Date()
    };
    
    const result = syncRowToWordPress('test', testPayload);
    
    if (result) {
      console.log('✅ Connection test successful');
    } else {
      console.log('❌ Connection test failed');
    }
    
    return result;
    
  } catch (error) {
    console.error('Connection test error:', error);
    return false;
  }
}

// =============================================================================
// 実行方法の説明
// =============================================================================

/*
セットアップ手順：

1. Google Apps Scriptで新しいプロジェクトを作成
2. このコードをコピー＆ペーストして保存
3. CONFIG オブジェクトの値を環境に合わせて変更：
   - WEBHOOK_URL または REST_API_URL を設定
   - SECRET_KEY を WordPress管理画面から取得して設定
   - SHEET_NAME を確認
4. setupTriggers() 関数を一度実行してトリガーを設定
5. testConnection() 関数で接続をテスト
6. importGrantPosts() 関数でWordPressからデータをインポート

利用可能な関数：
- setupTriggers() : 初期セットアップとトリガー設定
- testConnection() : WordPress接続テスト
- importGrantPosts() : WordPressから投稿データをインポート
- initializeSheet() : スプレッドシートの初期化（ヘッダーのみ）
- manualFullSync() : 手動での全データ同期

注意事項：
- Google Apps Scriptの実行権限が必要です
- スプレッドシートの共有設定でサービスアカウントに編集権限を付与してください
- トリガーは自動的に設定されますが、手動で確認することをお勧めします

使用方法：
- スプレッドシートを編集すると自動的にWordPressに同期されます
- manualFullSync() 関数で手動で全データを同期できます
- Error_Log シートでエラーを確認できます
- importGrantPosts() でWordPressからの初回データインポートが可能です
*/