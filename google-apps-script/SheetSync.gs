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
    // イベントオブジェクトが存在しない場合（手動実行等）は処理しない
    if (!e) {
      console.log('onEdit called without event object (manual execution?)');
      return;
    }
    
    debugLog('onEdit triggered', e);
    
    // イベントオブジェクトの必要なプロパティをチェック
    if (!e.source || !e.range) {
      debugLog('Invalid event object:', e);
      return;
    }
    
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
    // イベントオブジェクトが存在しない場合（手動実行等）は処理しない
    if (!e) {
      console.log('onChange called without event object (manual execution?)');
      return;
    }
    
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
    // 既存のInstallable Triggersを削除
    const triggers = ScriptApp.getProjectTriggers();
    triggers.forEach(trigger => {
      ScriptApp.deleteTrigger(trigger);
    });
    
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // onChange用のInstallable Triggerのみ作成
    // （onEditはSimple Triggerなので自動で動作）
    ScriptApp.newTrigger('onChange')
      .onFormSubmit() // onChangeは廃止予定のため、代替としてonFormSubmitを使用
      .create();
    
    console.log('✅ Triggers setup completed');
    console.log('📝 Note: onEdit is a Simple Trigger and works automatically');
    
    // 設定確認
    console.log('⚙️ Configuration:', {
      WEBHOOK_URL: CONFIG.WEBHOOK_URL,
      REST_API_URL: CONFIG.REST_API_URL,
      SHEET_NAME: CONFIG.SHEET_NAME,
      SECRET_KEY: CONFIG.SECRET_KEY ? 'Set' : 'Not set',
      SPREADSHEET_ID: spreadsheet.getId(),
      SPREADSHEET_NAME: spreadsheet.getName()
    });
    
    // 動作テスト
    console.log('🧪 Running test...');
    testConnection();
    
    return {
      success: true,
      message: 'トリガー設定が完了しました。onEditは自動で動作します。'
    };
    
  } catch (error) {
    console.error('❌ Setup failed:', error);
    return {
      success: false,
      message: 'セットアップに失敗しました: ' + error.toString()
    };
  }
}

/**
 * 接続テスト
 */
function testConnection() {
  try {
    console.log('🔌 Testing connection to WordPress...');
    
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

/**
 * 簡易セットアップ（推奨）
 * 初回設定時に実行してください
 */
function quickSetup() {
  try {
    console.log('🚀 Starting quick setup...');
    
    // Step 1: 設定確認
    console.log('📋 Step 1: Configuration check');
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    console.log('Spreadsheet ID:', spreadsheet.getId());
    console.log('Sheet Name:', CONFIG.SHEET_NAME);
    console.log('WordPress URL:', CONFIG.WORDPRESS_BASE_URL);
    
    // Step 2: フィールドバリデーション設定
    console.log('📋 Step 2: Setting up field validation');
    const validationResult = setupFieldValidation();
    if (validationResult.success) {
      console.log('✅ Field validation setup successful');
    } else {
      console.log('❌ Field validation setup failed:', validationResult.message);
    }
    
    // Step 3: 接続テスト
    console.log('📋 Step 3: Testing WordPress connection');
    const connectionResult = testConnection();
    
    // Step 4: 完了メッセージ
    console.log('🎉 Quick setup completed!');
    console.log('📝 Note: onEdit function will work automatically when you edit cells');
    console.log('🔧 To test: Edit any cell in the sheet and check the execution log');
    
    const ui = SpreadsheetApp.getUi();
    ui.alert(
      '✅ セットアップ完了', 
      'フィールドバリデーション設定が完了しました！\n\n選択肢フィールド（E, M, O, R, U, V列）の背景が青色になっています。\nセルを編集すると自動でWordPressと同期されます。', 
      ui.ButtonSet.OK
    );
    
    return {
      success: true,
      validation: validationResult,
      connection: connectionResult
    };
    
  } catch (error) {
    console.error('❌ Quick setup failed:', error);
    
    const ui = SpreadsheetApp.getUi();
    ui.alert('❌ エラー', 'セットアップ中にエラーが発生しました: ' + error.toString(), ui.ButtonSet.OK);
    
    return {
      success: false,
      error: error.toString()
    };
  }
}

// =============================================================================
// Jグランツ API連携機能  
// =============================================================================

/**
 * JグランツAPIから補助金情報をスプレッドシートに反映
 */
function importJgrantsSubsidyData() {
  const JGRANTS_API_BASE_URL = 'https://api.jgrants-portal.go.jp/exp/v1/public';
  const SHEET_NAME = 'Jグランツ補助金情報';
  
  try {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // 既存のシートがあれば削除して新規作成
    let sheet;
    try {
      sheet = spreadsheet.getSheetByName(SHEET_NAME);
      if (sheet) {
        spreadsheet.deleteSheet(sheet);
      }
    } catch (e) {
      // シートが存在しない場合は何もしない
    }
    
    // 新しいシートを作成
    sheet = spreadsheet.insertSheet(SHEET_NAME);
    
    // ヘッダー行を設定
    const headers = [
      'ID', '補助金名', 'タイトル', 'キャッチフレーズ', '詳細',
      '利用目的', '業種', '対象地域（検索）', '対象地域（詳細）',
      '従業員数', '補助率', '補助上限額', '募集開始日時',
      '募集終了日時', '事業終了期限', '事前相談有無',
      '複数回申請可否', '公募要領ファイル数', '交付要綱ファイル数',
      '申請様式ファイル数', '取得日時'
    ];
    
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    
    // ヘッダー行のスタイルを設定
    const headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#4285f4');
    headerRange.setFontColor('white');
    headerRange.setFontWeight('bold');
    
    // 全ての補助金情報を取得
    const allSubsidyData = getAllSubsidyData(JGRANTS_API_BASE_URL);
    
    if (allSubsidyData.length === 0) {
      Browser.msgBox('データが取得できませんでした。APIの状態を確認してください。');
      return;
    }
    
    // データをスプレッドシートに書き込み
    const dataRows = allSubsidyData.map(subsidy => [
      subsidy.id || '',
      subsidy.name || '',
      subsidy.title || '',
      subsidy.subsidy_catch_phrase || '',
      subsidy.detail || '',
      subsidy.use_purpose || '',
      subsidy.industry || '',
      subsidy.target_area_search || '',
      subsidy.target_area_detail || '',
      subsidy.target_number_of_employees || '',
      subsidy.subsidy_rate || '',
      subsidy.subsidy_max_limit || '',
      formatDateTime(subsidy.acceptance_start_datetime),
      formatDateTime(subsidy.acceptance_end_datetime),
      formatDateTime(subsidy.project_end_deadline),
      subsidy.request_reception_presence || '',
      subsidy.is_enable_multiple_request ? '可' : '不可',
      (subsidy.application_guidelines && subsidy.application_guidelines.length) || 0,
      (subsidy.outline_of_grant && subsidy.outline_of_grant.length) || 0,
      (subsidy.application_form && subsidy.application_form.length) || 0,
      new Date().toLocaleString('ja-JP')
    ]);
    
    // データが存在する場合のみ書き込み
    if (dataRows.length > 0) {
      sheet.getRange(2, 1, dataRows.length, headers.length).setValues(dataRows);
      
      // 列幅を自動調整
      sheet.autoResizeColumns(1, headers.length);
      
      // フィルターを追加
      sheet.getRange(1, 1, dataRows.length + 1, headers.length).createFilter();
    }
    
    // 完了メッセージ
    Browser.msgBox(
      '完了',
      `${dataRows.length}件の補助金情報を取得してスプレッドシートに反映しました。`,
      Browser.Buttons.OK
    );
    
  } catch (error) {
    console.error('エラーが発生しました:', error);
    Browser.msgBox('エラー', 'データ取得中にエラーが発生しました: ' + error.toString(), Browser.Buttons.OK);
  }
}

/**
 * 全ての補助金データを取得する関数
 */
function getAllSubsidyData(baseUrl) {
  const allData = [];
  let hasMoreData = true;
  let offset = 0;
  const limit = 100; // 一度に取得する件数
  
  while (hasMoreData) {
    try {
      console.log(`取得中... オフセット: ${offset}`);
      
      // 補助金一覧を取得（条件を最小限にして全件取得を目指す）
      const listUrl = `${baseUrl}/subsidies`;
      const params = {
        'keyword': '補助', // 最小限のキーワード
        'sort': 'created_date',
        'order': 'DESC',
        'acceptance': '0', // 募集期間外も含める
        'offset': offset.toString(),
        'limit': limit.toString()
      };
      
      const queryString = Object.keys(params)
        .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
        .join('&');
      
      const fullUrl = `${listUrl}?${queryString}`;
      
      const response = UrlFetchApp.fetch(fullUrl, {
        'method': 'GET',
        'headers': {
          'Accept': 'application/json',
          'User-Agent': 'GoogleAppsScript'
        },
        'muteHttpExceptions': true
      });
      
      if (response.getResponseCode() !== 200) {
        console.error(`API呼び出しエラー: ${response.getResponseCode()}`);
        break;
      }
      
      const data = JSON.parse(response.getContentText());
      
      if (!data.result || data.result.length === 0) {
        hasMoreData = false;
        break;
      }
      
      // 各補助金の詳細情報を取得
      for (const subsidySummary of data.result) {
        try {
          const detailData = getSubsidyDetail(baseUrl, subsidySummary.id);
          if (detailData) {
            allData.push(detailData);
          }
          
          // API制限を考慮して少し待機
          Utilities.sleep(100);
          
        } catch (detailError) {
          console.error(`詳細取得エラー (ID: ${subsidySummary.id}):`, detailError);
          // 詳細が取得できない場合は一覧情報のみ使用
          allData.push(subsidySummary);
        }
      }
      
      offset += data.result.length;
      
      // レスポンスデータが期待した件数より少ない場合は終了
      if (data.result.length < limit) {
        hasMoreData = false;
      }
      
      // API制限を考慮して待機
      Utilities.sleep(500);
      
    } catch (error) {
      console.error(`データ取得エラー (オフセット: ${offset}):`, error);
      hasMoreData = false;
    }
  }
  
  return allData;
}

/**
 * 個別の補助金詳細情報を取得する関数
 */
function getSubsidyDetail(baseUrl, subsidyId) {
  try {
    const detailUrl = `${baseUrl}/subsidies/id/${subsidyId}`;
    
    const response = UrlFetchApp.fetch(detailUrl, {
      'method': 'GET',
      'headers': {
        'Accept': 'application/json',
        'User-Agent': 'GoogleAppsScript'
      },
      'muteHttpExceptions': true
    });
    
    if (response.getResponseCode() !== 200) {
      console.error(`詳細取得エラー (ID: ${subsidyId}): ${response.getResponseCode()}`);
      return null;
    }
    
    const data = JSON.parse(response.getContentText());
    
    if (data.result && data.result.length > 0) {
      return data.result[0];
    }
    
    return null;
    
  } catch (error) {
    console.error(`詳細取得エラー (ID: ${subsidyId}):`, error);
    return null;
  }
}

/**
 * 募集中の補助金のみを取得する関数
 */
function importActiveSubsidiesOnly() {
  const JGRANTS_API_BASE_URL = 'https://api.jgrants-portal.go.jp/exp/v1/public';
  const SHEET_NAME = '募集中補助金情報';
  
  try {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // 既存のシートがあれば削除して新規作成
    let sheet;
    try {
      sheet = spreadsheet.getSheetByName(SHEET_NAME);
      if (sheet) {
        spreadsheet.deleteSheet(sheet);
      }
    } catch (e) {
      // シートが存在しない場合は何もしない
    }
    
    sheet = spreadsheet.insertSheet(SHEET_NAME);
    
    // ヘッダー行を設定
    const headers = [
      'ID', '補助金名', 'タイトル', '対象地域', '補助上限額',
      '募集開始日時', '募集終了日時', '従業員数', '取得日時'
    ];
    
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    
    // ヘッダー行のスタイルを設定
    const headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#34a853');
    headerRange.setFontColor('white');
    headerRange.setFontWeight('bold');
    
    // 募集中の補助金情報を取得
    const activeSubsidies = getActiveSubsidyData(JGRANTS_API_BASE_URL);
    
    if (activeSubsidies.length === 0) {
      Browser.msgBox('募集中の補助金データが取得できませんでした。');
      return;
    }
    
    // データをスプレッドシートに書き込み
    const dataRows = activeSubsidies.map(subsidy => [
      subsidy.id || '',
      subsidy.name || '',
      subsidy.title || '',
      subsidy.target_area_search || '',
      subsidy.subsidy_max_limit || '',
      formatDateTime(subsidy.acceptance_start_datetime),
      formatDateTime(subsidy.acceptance_end_datetime),
      subsidy.target_number_of_employees || '',
      new Date().toLocaleString('ja-JP')
    ]);
    
    if (dataRows.length > 0) {
      sheet.getRange(2, 1, dataRows.length, headers.length).setValues(dataRows);
      sheet.autoResizeColumns(1, headers.length);
      sheet.getRange(1, 1, dataRows.length + 1, headers.length).createFilter();
    }
    
    Browser.msgBox(
      '完了',
      `${dataRows.length}件の募集中補助金情報を取得しました。`,
      Browser.Buttons.OK
    );
    
  } catch (error) {
    console.error('エラーが発生しました:', error);
    Browser.msgBox('エラー', 'データ取得中にエラーが発生しました: ' + error.toString(), Browser.Buttons.OK);
  }
}

/**
 * 募集中の補助金データを取得する関数
 */
function getActiveSubsidyData(baseUrl) {
  try {
    const listUrl = `${baseUrl}/subsidies`;
    const params = {
      'keyword': '補助',
      'sort': 'acceptance_end_datetime',
      'order': 'ASC',
      'acceptance': '1' // 募集期間内のみ
    };
    
    const queryString = Object.keys(params)
      .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
      .join('&');
    
    const fullUrl = `${listUrl}?${queryString}`;
    
    const response = UrlFetchApp.fetch(fullUrl, {
      'method': 'GET',
      'headers': {
        'Accept': 'application/json',
        'User-Agent': 'GoogleAppsScript'
      },
      'muteHttpExceptions': true
    });
    
    if (response.getResponseCode() !== 200) {
      throw new Error(`API呼び出しエラー: ${response.getResponseCode()}`);
    }
    
    const data = JSON.parse(response.getContentText());
    return data.result || [];
    
  } catch (error) {
    console.error('募集中データ取得エラー:', error);
    return [];
  }
}

/**
 * キーワード検索機能
 */
function searchSubsidiesByKeyword() {
  const keyword = Browser.inputBox(
    'キーワード検索',
    '検索したいキーワードを入力してください（例：IT、デジタル、製造業）:',
    Browser.Buttons.OK_CANCEL
  );
  
  if (keyword === 'cancel' || !keyword.trim()) {
    return;
  }
  
  const JGRANTS_API_BASE_URL = 'https://api.jgrants-portal.go.jp/exp/v1/public';
  const SHEET_NAME = `検索結果_${keyword}`;
  
  try {
    const searchResults = searchSubsidyData(JGRANTS_API_BASE_URL, keyword.trim());
    
    if (searchResults.length === 0) {
      Browser.msgBox('検索結果が見つかりませんでした。');
      return;
    }
    
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // 既存の検索結果シートがあれば削除
    try {
      const existingSheet = spreadsheet.getSheetByName(SHEET_NAME);
      if (existingSheet) {
        spreadsheet.deleteSheet(existingSheet);
      }
    } catch (e) {
      // シートが存在しない場合は何もしない
    }
    
    const sheet = spreadsheet.insertSheet(SHEET_NAME);
    
    // ヘッダー行を設定
    const headers = [
      'ID', '補助金名', 'タイトル', '詳細', '利用目的',
      '業種', '対象地域', '従業員数', '補助上限額',
      '募集開始日時', '募集終了日時', '取得日時'
    ];
    
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    
    // ヘッダー行のスタイルを設定
    const headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#ff9900');
    headerRange.setFontColor('white');
    headerRange.setFontWeight('bold');
    
    // データをスプレッドシートに書き込み
    const dataRows = searchResults.map(subsidy => [
      subsidy.id || '',
      subsidy.name || '',
      subsidy.title || '',
      subsidy.detail || '',
      subsidy.use_purpose || '',
      subsidy.industry || '',
      subsidy.target_area_search || '',
      subsidy.target_number_of_employees || '',
      subsidy.subsidy_max_limit || '',
      formatDateTime(subsidy.acceptance_start_datetime),
      formatDateTime(subsidy.acceptance_end_datetime),
      new Date().toLocaleString('ja-JP')
    ]);
    
    if (dataRows.length > 0) {
      sheet.getRange(2, 1, dataRows.length, headers.length).setValues(dataRows);
      sheet.autoResizeColumns(1, headers.length);
      sheet.getRange(1, 1, dataRows.length + 1, headers.length).createFilter();
    }
    
    Browser.msgBox(
      '完了',
      `キーワード「${keyword}」で${dataRows.length}件の補助金情報が見つかりました。`,
      Browser.Buttons.OK
    );
    
  } catch (error) {
    console.error('検索エラー:', error);
    Browser.msgBox('エラー', '検索中にエラーが発生しました: ' + error.toString(), Browser.Buttons.OK);
  }
}

/**
 * キーワード検索用データ取得関数
 */
function searchSubsidyData(baseUrl, keyword) {
  try {
    const listUrl = `${baseUrl}/subsidies`;
    const params = {
      'keyword': keyword,
      'sort': 'created_date',
      'order': 'DESC',
      'acceptance': '0' // 全期間
    };
    
    const queryString = Object.keys(params)
      .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
      .join('&');
    
    const fullUrl = `${listUrl}?${queryString}`;
    
    const response = UrlFetchApp.fetch(fullUrl, {
      'method': 'GET',
      'headers': {
        'Accept': 'application/json',
        'User-Agent': 'GoogleAppsScript'
      },
      'muteHttpExceptions': true
    });
    
    if (response.getResponseCode() !== 200) {
      throw new Error(`API呼び出しエラー: ${response.getResponseCode()}`);
    }
    
    const data = JSON.parse(response.getContentText());
    const results = data.result || [];
    
    // 詳細情報を含む検索結果を取得
    const detailedResults = [];
    for (const subsidy of results) {
      try {
        const detailData = getSubsidyDetail(baseUrl, subsidy.id);
        if (detailData) {
          detailedResults.push(detailData);
        } else {
          detailedResults.push(subsidy);
        }
        Utilities.sleep(100);
      } catch (error) {
        console.error(`詳細取得エラー (ID: ${subsidy.id}):`, error);
        detailedResults.push(subsidy);
      }
    }
    
    return detailedResults;
    
  } catch (error) {
    console.error('検索データ取得エラー:', error);
    return [];
  }
}

/**
 * 日時フォーマット関数
 */
function formatDateTime(dateTimeString) {
  if (!dateTimeString) {
    return '';
  }
  
  try {
    const date = new Date(dateTimeString);
    return date.toLocaleString('ja-JP');
  } catch (error) {
    return dateTimeString;
  }
}

/**
 * JグランツからWordPressに助成金データをインポート
 * Jグランツで取得したデータを既存のWordPress連携シートに統合
 */
function importJgrantsToWordPress() {
  try {
    console.log('Starting Jグランツ to WordPress import...');
    
    // Jグランツデータを取得
    const JGRANTS_API_BASE_URL = 'https://api.jgrants-portal.go.jp/exp/v1/public';
    const jgrantsData = getActiveSubsidyData(JGRANTS_API_BASE_URL);
    
    if (jgrantsData.length === 0) {
      Browser.msgBox('Jグランツから取得できるデータがありませんでした。');
      return {
        success: false,
        message: 'No Jグランツ data available',
        imported: 0
      };
    }
    
    // WordPress連携用のシートを取得または作成
    const sheet = getOrCreateSheet();
    
    // ヘッダー行を設定（WordPress用の25列構造）
    setupHeaders(sheet);
    
    // 既存データをクリア（ヘッダー以外）
    clearExistingData(sheet);
    
    // JグランツデータをWordPress形式に変換
    const wpFormatData = convertJgrantsToWordPressFormat(jgrantsData);
    
    // データをシートに書き込み
    let importedCount = 0;
    wpFormatData.forEach((rowData, index) => {
      try {
        const rowNumber = index + 2; // ヘッダー行の下から開始
        const range = sheet.getRange(rowNumber, 1, 1, rowData.length);
        range.setValues([rowData]);
        importedCount++;
      } catch (rowError) {
        console.error(`Failed to import row ${index + 2}:`, rowError);
      }
    });
    
    console.log(`Jグランツ to WordPress import completed: ${importedCount} grants imported`);
    
    Browser.msgBox(
      '完了',
      `Jグランツから${importedCount}件の助成金をWordPress形式でインポートしました。`,
      Browser.Buttons.OK
    );
    
    return {
      success: true,
      message: `Jグランツ import completed successfully. ${importedCount} grants imported.`,
      imported: importedCount
    };
    
  } catch (error) {
    console.error('Jグランツ to WordPress import failed:', error);
    Browser.msgBox('エラー', 'Jグランツインポート中にエラーが発生しました: ' + error.toString(), Browser.Buttons.OK);
    
    return {
      success: false,
      message: `Jグランツ import failed: ${error.message}`,
      imported: 0
    };
  }
}

/**
 * JグランツデータをWordPress形式に変換
 */
function convertJgrantsToWordPressFormat(jgrantsData) {
  return jgrantsData.map(grant => [
    '', // ID (WordPressが自動生成)
    grant.name || grant.title || '無題の助成金', // タイトル
    grant.detail || '詳細情報が利用できません', // 内容  
    grant.subsidy_catch_phrase || '助成金の概要', // 抜粋
    'draft', // ステータス（一旦下書きで作成）
    '', // 作成日（WordPressが自動設定）
    '', // 更新日（WordPressが自動設定）
    grant.subsidy_max_limit || '金額要確認', // 助成金額（表示用）
    extractNumericAmount(grant.subsidy_max_limit), // 助成金額（数値）
    formatDateTime(grant.acceptance_end_datetime) || '期限要確認', // 申請期限（表示用）
    extractDateFromString(grant.acceptance_end_datetime), // 申請期限（日付）
    grant.organizer_name || '実施団体要確認', // 実施組織
    'jgrants', // 組織タイプ（Jグランツ由来を示す）
    grant.use_purpose || grant.target_area_detail || '対象要確認', // 対象者・対象事業
    'online', // 申請方法（Jグランツは基本オンライン）
    grant.contact_information || 'Jグランツサイトを確認', // 問い合わせ先
    `https://www.jgrants-portal.go.jp/grants/detail/${grant.id}`, // 公式URL
    extractPrefectureCode(grant.target_area_search), // 都道府県コード
    extractPrefectureName(grant.target_area_search), // 都道府県名
    grant.target_area_detail || '全域', // 対象市町村
    determineAreaRestriction(grant.target_area_search), // 地域制限
    determineApplicationStatus(grant.acceptance_start_datetime, grant.acceptance_end_datetime), // 申請ステータス
    '政府系助成金, Jグランツ', // カテゴリ
    extractTags(grant), // タグ
    new Date().toISOString().substring(0, 19).replace('T', ' ') // シート更新日
  ]);
}

/**
 * 文字列から数値の金額を抽出
 */
function extractNumericAmount(amountString) {
  if (!amountString) return 0;
  
  // 数字のみを抽出
  const numbers = amountString.replace(/[^\d]/g, '');
  
  if (!numbers) return 0;
  
  let amount = parseInt(numbers);
  
  // 単位を考慮した調整
  if (amountString.includes('億')) {
    amount = amount * 100000000;
  } else if (amountString.includes('千万')) {
    amount = amount * 10000000;
  } else if (amountString.includes('万')) {
    amount = amount * 10000;
  } else if (amountString.includes('千')) {
    amount = amount * 1000;
  }
  
  return amount;
}

/**
 * 日付文字列からYYYY-MM-DD形式を抽出
 */
function extractDateFromString(dateString) {
  if (!dateString) return '';
  
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    
    return date.toISOString().substring(0, 10);
  } catch (error) {
    return '';
  }
}

/**
 * 地域名から都道府県コードを推定
 */
function extractPrefectureCode(areaString) {
  if (!areaString) return 'nationwide';
  
  const prefCodes = {
    '北海道': 'hokkaido', '青森': 'aomori', '岩手': 'iwate', '宮城': 'miyagi',
    '秋田': 'akita', '山形': 'yamagata', '福島': 'fukushima', '茨城': 'ibaraki',
    '栃木': 'tochigi', '群馬': 'gunma', '埼玉': 'saitama', '千葉': 'chiba',
    '東京': 'tokyo', '神奈川': 'kanagawa', '新潟': 'niigata', '富山': 'toyama',
    '石川': 'ishikawa', '福井': 'fukui', '山梨': 'yamanashi', '長野': 'nagano',
    '岐阜': 'gifu', '静岡': 'shizuoka', '愛知': 'aichi', '三重': 'mie',
    '滋賀': 'shiga', '京都': 'kyoto', '大阪': 'osaka', '兵庫': 'hyogo',
    '奈良': 'nara', '和歌山': 'wakayama', '鳥取': 'tottori', '島根': 'shimane',
    '岡山': 'okayama', '広島': 'hiroshima', '山口': 'yamaguchi', '徳島': 'tokushima',
    '香川': 'kagawa', '愛媛': 'ehime', '高知': 'kochi', '福岡': 'fukuoka',
    '佐賀': 'saga', '長崎': 'nagasaki', '熊本': 'kumamoto', '大分': 'oita',
    '宮崎': 'miyazaki', '鹿児島': 'kagoshima', '沖縄': 'okinawa'
  };
  
  for (const [name, code] of Object.entries(prefCodes)) {
    if (areaString.includes(name)) {
      return code;
    }
  }
  
  return 'nationwide';
}

/**
 * 都道府県名を抽出
 */
function extractPrefectureName(areaString) {
  if (!areaString) return '全国';
  
  const prefectures = [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
    '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
    '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
    '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
    '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
  ];
  
  for (const pref of prefectures) {
    if (areaString.includes(pref)) {
      return pref;
    }
  }
  
  return '全国';
}

/**
 * 地域制限を決定
 */
function determineAreaRestriction(areaString) {
  if (!areaString || areaString.includes('全国')) {
    return 'nationwide';
  }
  
  if (areaString.includes('県') || areaString.includes('都') || areaString.includes('府') || areaString.includes('道')) {
    return 'prefecture';
  }
  
  if (areaString.includes('市') || areaString.includes('町') || areaString.includes('村')) {
    return 'city';
  }
  
  return 'other';
}

/**
 * 申請ステータスを決定
 */
function determineApplicationStatus(startDate, endDate) {
  const now = new Date();
  
  if (!startDate || !endDate) {
    return 'unknown';
  }
  
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (now < start) {
      return 'upcoming';
    } else if (now >= start && now <= end) {
      return 'open';
    } else {
      return 'closed';
    }
  } catch (error) {
    return 'unknown';
  }
}

// =============================================================================
// フィールドバリデーション設定機能
// =============================================================================

/**
 * スプレッドシートの選択肢フィールドにプルダウンバリデーションを設定
 * WordPress管理画面から呼び出されて実行される
 */
function setupFieldValidation() {
  try {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    const sheet = spreadsheet.getSheetByName(CONFIG.SHEET_NAME);
    
    if (!sheet) {
      throw new Error('対象シートが見つかりません: ' + CONFIG.SHEET_NAME);
    }

    console.log('Setting up field validation rules...');
    
    // E列: ステータス（publish/draft/private/deleted）
    setupDropdownValidation(sheet, 'E:E', ['draft', 'publish', 'private', 'deleted']);
    
    // M列: 組織タイプ
    setupDropdownValidation(sheet, 'M:M', [
      'national',      // 国（省庁）
      'prefecture',    // 都道府県
      'city',         // 市区町村
      'public_org',   // 公的機関
      'private_org',  // 民間団体
      'foundation',   // 財団法人
      'jgrants',      // Jグランツ
      'other'         // その他
    ]);
    
    // O列: 申請方法
    setupDropdownValidation(sheet, 'O:O', [
      'online',       // オンライン申請
      'mail',         // 郵送申請
      'visit',        // 窓口申請
      'mixed'         // オンライン・郵送併用
    ]);
    
    // R列: 都道府県コード
    setupDropdownValidation(sheet, 'R:R', [
      '', 'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima',
      'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa',
      'niigata', 'toyama', 'ishikawa', 'fukui', 'yamanashi', 'nagano', 'gifu',
      'shizuoka', 'aichi', 'mie', 'shiga', 'kyoto', 'osaka', 'hyogo', 'nara',
      'wakayama', 'tottori', 'shimane', 'okayama', 'hiroshima', 'yamaguchi',
      'tokushima', 'kagawa', 'ehime', 'kochi', 'fukuoka', 'saga', 'nagasaki',
      'kumamoto', 'oita', 'miyazaki', 'kagoshima', 'okinawa'
    ]);
    
    // U列: 地域制限
    setupDropdownValidation(sheet, 'U:U', [
      'nationwide',        // 全国対象
      'prefecture_only',   // 都道府県内限定
      'municipality_only', // 市町村限定
      'region_group',      // 地域グループ限定
      'specific_area'      // 特定地域限定
    ]);
    
    // V列: 申請ステータス
    setupDropdownValidation(sheet, 'V:V', [
      'open',             // 募集中
      'upcoming',         // 募集予定
      'closed',           // 募集終了
      'suspended'         // 一時停止
    ]);
    
    console.log('Field validation setup completed successfully');
    
    // セルの背景色を設定（選択肢フィールドを識別しやすくする）
    const validationColumns = ['E', 'M', 'O', 'R', 'U', 'V'];
    validationColumns.forEach(column => {
      const range = sheet.getRange(`${column}1:${column}1000`);
      range.setBackground('#f0f8ff'); // 薄い青色で選択肢フィールドを区別
    });
    
    return {
      success: true,
      message: 'フィールドバリデーション設定が完了しました'
    };
    
  } catch (error) {
    console.error('Field validation setup failed:', error);
    return {
      success: false,
      message: 'バリデーション設定に失敗しました: ' + error.toString()
    };
  }
}

/**
 * 指定の列範囲にドロップダウンバリデーションを設定
 */
function setupDropdownValidation(sheet, columnRange, values) {
  try {
    const range = sheet.getRange(columnRange);
    const rule = SpreadsheetApp.newDataValidation()
      .requireValueInList(values, true) // true = 無効な値に対して警告を表示
      .setAllowInvalid(false)
      .setHelpText(`選択可能な値: ${values.join(', ')}`)
      .build();
    
    range.setDataValidation(rule);
    console.log(`Dropdown validation set for ${columnRange}: ${values.join(', ')}`);
    
  } catch (error) {
    console.error(`Failed to set validation for ${columnRange}:`, error);
    throw error;
  }
}

/**
 * 統合メニューシステム - カスタムメニューを追加
 */
function onOpen() {
  const ui = SpreadsheetApp.getUi();
  
  // WordPress連携メニュー
  const wordPressMenu = ui.createMenu('WordPress連携')
    .addItem('🚀 簡易セットアップ（初回推奨）', 'quickSetup')
    .addSeparator()
    .addItem('🔄 WordPressと同期', 'syncWithWordPress')
    .addItem('📤 WordPressにデータ送信', 'sendDataToWordPress')  
    .addItem('📥 WordPressからデータ受信', 'receiveDataFromWordPress')
    .addSeparator()
    .addItem('🔧 フィールドバリデーション設定', 'setupFieldValidation')
    .addItem('🛠️ 詳細設定（トリガー設定）', 'setupTriggers')
    .addItem('🧪 接続テスト', 'testConnection');
  
  // Jグランツ連携メニュー
  const jgrantsMenu = ui.createMenu('Jグランツ連携')
    .addItem('📊 Jグランツデータ取得', 'showJgrantsImportDialog')
    .addItem('🔄 WordPress形式で変換取得', 'importJgrantsToWordPressFormat')
    .addSeparator()
    .addItem('🗂️ Jグランツシート作成', 'importJgrantsSubsidyData')
    .addItem('📋 統計データ表示', 'showJgrantsStatistics');
    
  // メインメニューに追加
  ui.createMenu('🏛️ 助成金管理システム')
    .addSubMenu(wordPressMenu)
    .addSubMenu(jgrantsMenu)
    .addSeparator()
    .addItem('📚 使い方ガイド', 'showUsageGuide')
    .addItem('ℹ️ システム情報', 'showSystemInfo')
    .addToUi();
}

/**
 * 使い方ガイドを表示
 */
function showUsageGuide() {
  const ui = SpreadsheetApp.getUi();
  
  const guide = `
🏛️ 助成金管理システム 使い方ガイド

【WordPress連携】
• 🔄 WordPressと同期: 双方向で最新データに同期
• 📤 WordPressにデータ送信: スプレッドシート → WordPress
• 📥 WordPressからデータ受信: WordPress → スプレッドシート

【Jグランツ連携】  
• 📊 Jグランツデータ取得: 最新の政府系助成金を取得
• 🔄 WordPress形式で変換取得: WordPress用に自動変換

【選択肢フィールド（背景が青色）】
• E列: ステータス (draft/publish/private/deleted)
• M列: 組織タイプ (national/prefecture/city等)
• O列: 申請方法 (online/mail/visit/mixed)
• R列: 都道府県コード (tokyo/osaka等)
• U列: 地域制限 (nationwide/prefecture_only等)
• V列: 申請ステータス (open/closed/upcoming/suspended)

【初回設定】
1. 🛠️ 初期設定（トリガー設定）を実行
2. 🔧 フィールドバリデーション設定を実行
3. 🧪 接続テストで確認

【サポート】
問題が発生した場合は、システム管理者にお問い合わせください。
  `;
  
  ui.alert('使い方ガイド', guide, ui.ButtonSet.OK);
}

/**
 * システム情報を表示
 */
function showSystemInfo() {
  const ui = SpreadsheetApp.getUi();
  
  const info = `
🏛️ 助成金管理システム v2.0

【接続情報】
WordPress URL: ${CONFIG.WORDPRESS_BASE_URL}
Webhook URL: ${CONFIG.REST_API_URL}
対象シート: ${CONFIG.SHEET_NAME}
Secret Key: ${CONFIG.SECRET_KEY ? '設定済み' : '未設定'}

【機能】
✅ WordPress双方向同期
✅ Jグランツ API連携
✅ フィールドバリデーション
✅ リアルタイム更新
✅ 自動データ変換

【最終更新】
2024年12月 - 選択肢フィールドプルダウン対応版
  `;
  
  ui.alert('システム情報', info, ui.ButtonSet.OK);
}

/**
 * タグを抽出して生成
 */
function extractTags(grant) {
  const tags = ['Jグランツ'];
  
  if (grant.industry) {
    tags.push(grant.industry);
  }
  
  if (grant.use_purpose) {
    tags.push(grant.use_purpose);
  }
  
  if (grant.subsidy_rate && grant.subsidy_rate.includes('100%')) {
    tags.push('全額補助');
  }
  
  if (grant.target_number_of_employees) {
    tags.push('従業員数制限あり');
  }
  
  return tags.join(', ');
}

/**
 * メニューに機能を追加（統合版）
 */
function onOpen() {
  try {
    const ui = SpreadsheetApp.getUi();
    ui.createMenu('🚀 助成金統合管理')
      .addSubMenu(ui.createMenu('📊 WordPress連携')
        .addItem('🔄 WordPressからデータインポート', 'importGrantPosts')
        .addItem('⚙️ スプレッドシート初期化', 'initializeSheet')  
        .addItem('📤 WordPressに全データ同期', 'manualFullSync')
        .addItem('🔗 WordPress接続テスト', 'testConnection'))
      .addSubMenu(ui.createMenu('🏛️ Jグランツ連携')
        .addItem('📋 全補助金情報を取得', 'importJgrantsSubsidyData')
        .addItem('🔥 募集中の補助金のみ取得', 'importActiveSubsidiesOnly')
        .addItem('🔍 キーワード検索', 'searchSubsidiesByKeyword')
        .addItem('🔄 JグランツからWordPressに統合', 'importJgrantsToWordPress'))
      .addSubMenu(ui.createMenu('⚙️ システム管理')
        .addItem('🔧 トリガー設定', 'setupTriggers')
        .addItem('ℹ️ ヘルプ', 'showHelp'))
      .addToUi();
    
    console.log('統合メニューが正常に作成されました');
  } catch (error) {
    console.error('メニュー作成エラー:', error);
    Browser.msgBox('エラー', 'メニューの作成に失敗しました: ' + error.toString(), Browser.Buttons.OK);
  }
}

/**
 * ヘルプ機能（統合版）
 */
function showHelp() {
  const helpText = `
【助成金統合管理システムの使い方】

📊 WordPress連携機能
🔄 WordPressからデータインポート: サイトの助成金データをスプレッドシートにインポート
⚙️ スプレッドシート初期化: WordPress連携用のヘッダーとサンプルデータを設定
📤 WordPressに全データ同期: スプレッドシートの内容をWordPressに一括同期
🔗 WordPress接続テスト: WordPress連携の接続状況を確認

🏛️ Jグランツ連携機能
📋 全補助金情報を取得: JグランツAPIから全ての補助金データを取得
🔥 募集中の補助金のみ取得: 現在募集中の補助金のみを取得
🔍 キーワード検索: 特定キーワードで補助金を検索
🔄 JグランツからWordPressに統合: JグランツデータをWordPress形式で統合

⚙️ システム管理
🔧 トリガー設定: 自動同期のためのトリガーを設定
ℹ️ ヘルプ: この説明を表示

【推奨ワークフロー】
1. 最初にWordPress連携の接続テストを実行
2. スプレッドシート初期化でWordPress連携用の構造を作成
3. WordPressからデータインポートで既存データを取得
4. 必要に応じてJグランツからデータを追加取得・統合
5. トリガー設定で自動同期を有効化

※初回実行時は時間がかかる場合があります
※API制限により、大量データ取得時は段階的に実行されます
  `;
  
  Browser.msgBox('助成金統合管理システム ヘルプ', helpText, Browser.Buttons.OK);
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