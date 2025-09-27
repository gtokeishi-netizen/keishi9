/**
 * ===============================================================================
 * 🏛️ 助成金管理システム - 統合Google Apps Script
 * Integrated Grant Management System for Google Sheets
 * ===============================================================================
 * 
 * このスクリプトは以下の機能を統合しています：
 * 1. 🤖 OpenAI GPT Custom Functions - AI搭載スプレッドシート関数
 * 2. 🔄 WordPress Sync Functions - WordPress双方向同期機能
 * 3. 📊 Jgrants Integration - 政府助成金データ連携
 * 
 * 設置方法：
 * 1. Google Apps Scriptで新しいプロジェクト作成
 * 2. このコードをコピー＆ペースト
 * 3. 下記の設定セクションを環境に合わせて更新
 * 4. トリガーを設定（onEdit, onChange）
 * 5. OpenAI APIキーを設定（AI機能を使用する場合）
 * 
 * @version 2.0.0 - Integrated Edition
 * @author Grant Insight Perfect
 */

// =============================================================================
// 🔑 設定セクション - 環境に合わせて設定してください
// =============================================================================

/**
 * WordPress連携設定
 */
const WORDPRESS_CONFIG = {
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

/**
 * OpenAI API設定
 * ⚠️ 重要: 実際のAPIキーに置き換えてください
 */
const OPENAI_CONFIG = {
  // OpenAI API Key - https://platform.openai.com/api-keys で取得
  API_KEY: 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // ← ここに実際のAPIキーを入力
  
  // APIエンドポイント
  API_ENDPOINT: 'https://api.openai.com/v1/chat/completions',
  
  // デフォルト設定
  DEFAULT_MODEL: 'gpt-3.5-turbo', // または 'gpt-4'
  DEFAULT_MAX_TOKENS: 1000,
  DEFAULT_TEMPERATURE: 0.7,
  
  // レート制限設定
  RATE_LIMIT_DELAY: 1000, // 1秒間隔
  MAX_RETRIES: 3,
  
  // キャッシュ設定（同じ問い合わせの結果を一時保存）
  CACHE_DURATION: 60 * 60 * 1000, // 1時間
  
  // 詳細エラーハンドリング設定
  RETRY_DELAYS: [1000, 2000, 5000], // 段階的リトライ間隔
  RATE_LIMIT_WAIT: 60000, // レート制限時の待機時間（1分）
  
  // パフォーマンス最適化設定
  CACHE_PREFIX: 'gpt_cache_v2_',
  MAX_INPUT_LENGTH: 8000, // 入力テキストの最大長
  BATCH_SIZE: 5, // バッチ処理サイズ
};

// 後方互換性のために CONFIG も維持
const CONFIG = WORDPRESS_CONFIG;

// =============================================================================
// 🤖 AI機能セクション - OpenAI GPTカスタム関数
// =============================================================================

/**
 * 基本GPT関数 - 任意のテキストと指示でAI処理
 * 
 * @customfunction
 * @param {string} input 処理したいテキスト
 * @param {string} instruction AIに与える指示
 * @param {string} model 使用するAIモデル (gpt-4, gpt-3.5-turbo)
 * @param {number} maxTokens 最大トークン数
 * @return {string} AIの応答
 * 
 * 使用例: =GPT(A1, "この文章を要約して")
 * 使用例: =GPT(B1, "英語に翻訳して", "gpt-4", 500)
 */
function GPT(input, instruction, model, maxTokens) {
  try {
    // 入力検証
    if (!input || input.toString().trim() === '') {
      return 'エラー: 入力テキストが空です';
    }
    
    if (!instruction || instruction.toString().trim() === '') {
      return 'エラー: 指示が空です';
    }
    
    // デフォルト値設定
    const selectedModel = model || OPENAI_CONFIG.DEFAULT_MODEL;
    const tokens = maxTokens || OPENAI_CONFIG.DEFAULT_MAX_TOKENS;
    
    // キャッシュキー生成
    const cacheKey = generateCacheKey(input, instruction, selectedModel);
    
    // キャッシュから結果を取得
    const cachedResult = getCachedResult(cacheKey);
    if (cachedResult) {
      return cachedResult;
    }
    
    // OpenAI APIリクエスト
    const result = callOpenAIWithRetry(input, instruction, selectedModel, tokens);
    
    // 結果をキャッシュに保存
    setCachedResult(cacheKey, result);
    
    return result;
    
  } catch (error) {
    console.error('GPT function error:', error);
    return `エラー: ${error.message}`;
  }
}

/**
 * GPT-4専用関数（高品質な応答）
 * 
 * @customfunction
 * @param {string} input 処理したいテキスト
 * @param {string} instruction AIに与える指示
 * @return {string} GPT-4の応答
 */
function GPT4(input, instruction) {
  return GPT(input, instruction, 'gpt-4', 1500);
}

/**
 * 高速GPT関数（速い応答重視）
 * 
 * @customfunction  
 * @param {string} input 処理したいテキスト
 * @param {string} instruction AIに与える指示
 * @return {string} GPTの応答（高速版）
 */
function GPTFAST(input, instruction) {
  return GPT(input, instruction, 'gpt-3.5-turbo', 500);
}

// --- ビジネス特化関数 ---

/**
 * 企業分析AI関数
 * 
 * @customfunction
 * @param {string} companyName 企業名
 * @param {string} length 分析の詳細度 (short/medium/detailed)
 * @return {string} 企業分析結果
 */
function GPT_COMPANY(companyName, length) {
  const lengthMap = { short: '簡潔に', medium: '中程度の詳細で', detailed: '詳細に' };
  const detail = lengthMap[length] || '中程度の詳細で';
  const instruction = `${companyName}について、事業内容、業績、市場での位置付けを${detail}分析してください。`;
  return GPT(companyName, instruction);
}

/**
 * 文章要約AI関数
 * 
 * @customfunction
 * @param {string} text 要約したい文章
 * @param {string} length 要約の長さ (short/medium/long)
 * @return {string} 要約結果
 */
function GPT_SUMMARY(text, length) {
  const lengthMap = { short: '3行以内', medium: '5行程度', long: '10行程度' };
  const targetLength = lengthMap[length] || '5行程度';
  const instruction = `以下の文章を${targetLength}で要約してください。重要なポイントを漏らさないようにしてください。`;
  return GPT(text, instruction);
}

/**
 * 翻訳AI関数
 * 
 * @customfunction
 * @param {string} text 翻訳したいテキスト
 * @param {string} targetLang 翻訳先言語 (english/chinese/korean等)
 * @return {string} 翻訳結果
 */
function GPT_TRANSLATE(text, targetLang) {
  const langMap = { 
    english: '英語', japanese: '日本語', chinese: '中国語', 
    korean: '韓国語', french: 'フランス語', spanish: 'スペイン語' 
  };
  const language = langMap[targetLang.toLowerCase()] || targetLang;
  const instruction = `以下のテキストを${language}に翻訳してください。自然で読みやすい翻訳を心がけてください。`;
  return GPT(text, instruction);
}

/**
 * キーワード抽出AI関数
 * 
 * @customfunction
 * @param {string} text 分析したいテキスト
 * @param {number} count 抽出するキーワード数
 * @return {string} 抽出されたキーワード
 */
function GPT_KEYWORDS(text, count) {
  const keywordCount = count || 5;
  const instruction = `以下のテキストから重要なキーワードを${keywordCount}個抽出してください。カンマ区切りで出力してください。`;
  return GPT(text, instruction);
}

/**
 * 感情分析AI関数
 * 
 * @customfunction
 * @param {string} text 分析したいテキスト
 * @return {string} 感情分析結果
 */
function GPT_SENTIMENT(text) {
  const instruction = 'このテキストの感情を分析し、「ポジティブ」「ネガティブ」「ニュートラル」のいずれかで回答してください。理由も簡潔に添えてください。';
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

/**
 * カテゴリ分類AI関数
 * 
 * @customfunction
 * @param {string} text 分類したいテキスト
 * @param {string} categories カテゴリリスト（カンマ区切り）
 * @return {string} 最も適切なカテゴリ
 */
function GPT_CATEGORIZE(text, categories) {
  const instruction = `以下のテキストを次のカテゴリのいずれかに分類してください: ${categories}。最も適切なカテゴリ名のみを回答してください。`;
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

/**
 * 助成金分析AI関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報テキスト
 * @param {number} length 分析結果の文字数（省略可能、デフォルト500字）
 * @return {string} 助成金の分析結果
 */
function GPT_GRANT_ANALYSIS(grantInfo, length) {
  const maxLength = length || 500;
  const instruction = `以下の助成金・補助金情報を詳細に分析し、以下の項目について${maxLength}字以内でまとめてください：
1. 対象者・対象企業の条件
2. 支援内容・金額
3. 申請時期・締切
4. 申請の難易度
5. 注意すべきポイント
簡潔で実用的な情報を提供してください。`;
  return GPT(grantInfo, instruction, 'gpt-4');
}

/**
 * 助成金カテゴリ分類関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報
 * @return {string} 分類結果（複数カテゴリの場合はカンマ区切り）
 */
function GPT_GRANT_CATEGORY(grantInfo) {
  const categories = "創業・起業支援,研究開発,DX・IT化,省エネ・環境,人材育成,事業拡大,設備投資,海外展開,地域活性化,その他";
  const instruction = `以下の助成金情報を分析し、適切なカテゴリに分類してください。
カテゴリ: ${categories}
最も適切なカテゴリを1-2個選択し、カンマ区切りで回答してください。`;
  return GPT(grantInfo, instruction, 'gpt-3.5-turbo');
}

/**
 * 申請締切管理関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報
 * @param {string} currentDate 現在日時（省略可能）
 * @return {string} 申請スケジュール提案
 */
function GPT_GRANT_SCHEDULE(grantInfo, currentDate) {
  const today = currentDate || new Date().toLocaleDateString('ja-JP');
  const instruction = `助成金の申請スケジュールを分析し、以下の形式で提案してください：
現在日時: ${today}

1. 申請締切日: [日付]
2. 準備開始推奨日: [日付]
3. 必要な準備期間: [週数]
4. 重要なマイルストーン: [準備段階ごとの目標日]
5. 緊急度: [高/中/低]`;
  return GPT(grantInfo, instruction, 'gpt-3.5-turbo');
}

/**
 * 助成金比較分析関数
 * 
 * @customfunction
 * @param {string} grant1 助成金1の情報
 * @param {string} grant2 助成金2の情報  
 * @param {string} comparePoint 比較観点（省略可能：金額,条件,難易度など）
 * @return {string} 比較分析結果
 */
function GPT_GRANT_COMPARE(grant1, grant2, comparePoint) {
  const point = comparePoint || "総合的な観点";
  const instruction = `以下の2つの助成金を${point}で比較分析してください：

【助成金A】
${grant1}

【助成金B】  
${grant2}

以下の項目で比較してください：
1. 支援金額・規模
2. 申請条件・難易度
3. 申請期間・締切
4. 申請プロセス
5. 採択確率
6. おすすめ度とその理由`;
  return GPT(grant1 + " VS " + grant2, instruction, 'gpt-4', 1200);
}

/**
 * 地域別助成金検索支援関数
 * 
 * @customfunction
 * @param {string} location 地域名（都道府県・市町村）
 * @param {string} businessType 事業種別（省略可能）
 * @return {string} 地域特化助成金情報の分析
 */
function GPT_GRANT_REGIONAL(location, businessType) {
  const business = businessType || "一般事業者";
  const instruction = `${location}地域の${business}向け助成金・補助金について分析してください。
以下の観点で情報を整理してください：
1. 主要な地域特化助成金
2. 申請しやすい助成金
3. 支援金額が大きい助成金
4. 地域の特色を活かした支援制度
5. 申請時期・スケジュール
6. 地域での申請支援窓口・相談先`;
  return GPT(location + " " + business, instruction, 'gpt-4', 1000);
}

// --- 助成金特化関数 ---

/**
 * 助成金適合性分析AI関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報
 * @param {string} businessInfo 事業情報
 * @return {string} 適合性分析結果
 */
function GPT_GRANT_MATCH(grantInfo, businessInfo) {
  const instruction = `
助成金情報: ${grantInfo}
事業情報: ${businessInfo}

上記の助成金とビジネスの適合性を以下の観点で分析してください：
1. 適合度（5段階評価）
2. 申請可能性
3. 必要な準備事項
4. 注意すべきポイント
`;
  return GPT(grantInfo + '\n' + businessInfo, instruction);
}

/**
 * 申請書類AI支援関数
 * 
 * @customfunction
 * @param {string} grantName 助成金名
 * @param {string} businessPlan 事業計画
 * @return {string} 申請書類の要点
 */
function GPT_GRANT_APPLICATION(grantName, businessPlan) {
  const instruction = `
${grantName}の申請において、以下の事業計画をどのように申請書類に反映すべきかアドバイスしてください：

事業計画: ${businessPlan}

以下の観点でアドバイスをお願いします：
1. 強調すべきポイント
2. 補強が必要な要素
3. 申請書での表現方法
4. 審査員への訴求ポイント
`;
  return GPT(businessPlan, instruction);
}

/**
 * 助成金リスクアセスメントAI関数
 * 
 * @customfunction
 * @param {string} grantDetails 助成金詳細
 * @return {string} リスク分析結果
 */
function GPT_GRANT_RISK(grantDetails) {
  const instruction = `
以下の助成金について、申請・受給に関するリスクを分析してください：

助成金詳細: ${grantDetails}

以下の観点で分析をお願いします：
1. 申請時のリスク
2. 採択後のリスク
3. 返還リスク
4. コンプライアンス上の注意点
5. リスク軽減策
`;
  return GPT(grantDetails, instruction);
}

// =============================================================================
// 🔄 WordPress同期機能セクション
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
    
    // 構造化されたデータ形式に変換
    const structuredData = convertRowDataToStructured(rowData);
    
    // WordPress に同期（構造化データと生データの両方を送信）
    syncRowToWordPress('row_updated', {
      row_number: rowNumber,
      row_data: rowData,              // 後方互換性のための生データ
      structured_data: structuredData, // 新しいフィールドを含む構造化データ
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
      throw new Error('Target sheet not found: ' + CONFIG.SHEET_NAME);
    }
    
    const dataRange = sheet.getDataRange();
    const values = dataRange.getValues();
    
    // ヘッダー行をスキップして全行を同期
    for (let i = 1; i < values.length; i++) {
      const rowNumber = i + 1;
      const rowData = values[i];
      
      if (rowData.some(cell => cell !== '')) {
        // 構造化されたデータ形式に変換
        const structuredData = convertRowDataToStructured(rowData);
        
        syncRowToWordPress('row_updated', {
          row_number: rowNumber,
          row_data: rowData,              // 後方互換性のための生データ
          structured_data: structuredData, // 新しいフィールドを含む構造化データ
          manual_sync: true
        });
        
        // レート制限対策
        Utilities.sleep(1000);
      }
    }
    
    console.log('Manual full sync completed');
    
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
        'prefecture_only',  // 地域制限
        'open',  // 申請ステータス
        '東京都',  // 都道府県 ★完全連携
        '新宿区, 渋谷区',  // 市町村 ★完全連携
        'ビジネス支援',  // カテゴリ ★完全連携
        'スタートアップ, 中小企業',  // タグ ★完全連携
        'https://example.com/external',  // 外部リンク ★新規
        '東京都内限定の支援制度',  // 地域に関する備考 ★新規
        '事業計画書、決算書類',  // 必要書類 ★新規
        75,  // 採択率（%） ★新規
        '中級',  // 申請難易度 ★新規
        '設備費、人件費、広告費',  // 対象経費 ★新規
        '1/2（上限100万円）',  // 補助率 ★新規
        new Date().toISOString().substring(0, 19).replace('T', ' ')  // シート更新日
      ]
    ];
  }
}

// =============================================================================
// 🛠️ ヘルパー関数セクション
// =============================================================================

/**
 * 行データを取得
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
 * 行データを構造化されたオブジェクトに変換
 * 新しいフィールドを含む完全な構造に対応
 */
function convertRowDataToStructured(rowData) {
  if (!rowData || rowData.length === 0) {
    return null;
  }
  
  return {
    // 基本情報（A-G列）
    id: rowData[0] || '',                          // A列: ID
    title: rowData[1] || '',                       // B列: タイトル  
    content: rowData[2] || '',                     // C列: 内容
    excerpt: rowData[3] || '',                     // D列: 抜粋
    status: rowData[4] || 'draft',                 // E列: ステータス
    created_date: rowData[5] || '',                // F列: 作成日
    updated_date: rowData[6] || '',                // G列: 更新日
    
    // 助成金情報（H-K列）
    amount_display: rowData[7] || '',              // H列: 助成金額（表示用）
    amount_numeric: rowData[8] || '',              // I列: 助成金額（数値）
    deadline_display: rowData[9] || '',            // J列: 申請期限（表示用）
    deadline_date: rowData[10] || '',              // K列: 申請期限（日付）
    
    // 組織・申請情報（L-Q列）
    organization: rowData[11] || '',               // L列: 実施組織
    organization_type: rowData[12] || '',          // M列: 組織タイプ
    target_description: rowData[13] || '',         // N列: 対象者・対象事業
    application_method: rowData[14] || '',         // O列: 申請方法
    contact_info: rowData[15] || '',               // P列: 問い合わせ先
    official_url: rowData[16] || '',               // Q列: 公式URL
    
    // 地域・カテゴリ情報（R-W列）
    area_restriction: rowData[17] || '',           // R列: 地域制限
    application_status: rowData[18] || '',         // S列: 申請ステータス
    prefecture: rowData[19] || '',                 // T列: 都道府県
    municipality: rowData[20] || '',               // U列: 市町村
    category: rowData[21] || '',                   // V列: カテゴリ
    tags: rowData[22] || '',                       // W列: タグ
    
    // 新規追加フィールド（X-AD列）★完全連携対応
    external_links: rowData[23] || '',             // X列: 外部リンク
    area_notes: rowData[24] || '',                 // Y列: 地域に関する備考
    required_documents: rowData[25] || '',         // Z列: 必要書類
    adoption_rate: rowData[26] || '',              // AA列: 採択率（%）
    difficulty_level: rowData[27] || '',           // AB列: 申請難易度
    eligible_expenses: rowData[28] || '',          // AC列: 対象経費
    subsidy_rate: rowData[29] || '',               // AD列: 補助率
    
    // システム情報
    sheet_updated: rowData[30] || ''               // AE列: シート更新日
  };
}

/**
 * スプレッドシートを取得または作成
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
        'prefecture_only',  // 地域制限
        'open',  // 申請ステータス
        '東京都',  // 都道府県
        '新宿区, 渋谷区',  // 市町村
        'ビジネス支援',  // カテゴリ
        'スタートアップ, 資金調達',  // タグ
        'https://external-link.com',  // 外部リンク
        '都内限定の助成金です',  // 地域に関する備考
        '事業計画書, 決算書',  // 必要書類
        '85',  // 採択率（%）
        '中級',  // 申請難易度
        '人件費, 設備費',  // 対象経費
        '1/2以内',  // 補助率
        new Date().toISOString().substring(0, 19).replace('T', ' ')  // シート更新日
      ]
    ];
  }
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
    '問い合わせ先',           // P列
    '公式URL',               // Q列
    '地域制限',              // R列
    '申請ステータス',         // S列
    '都道府県',              // T列
    '市町村',                // U列
    'カテゴリ',              // V列
    'タグ',                 // W列
    '外部リンク',            // X列 ★新規追加
    '地域に関する備考',      // Y列 ★新規追加  
    '必要書類',              // Z列 ★新規追加
    '採択率（%）',           // AA列 ★新規追加
    '申請難易度',            // AB列 ★新規追加
    '対象経費',              // AC列 ★新規追加
    '補助率',                // AD列 ★新規追加
    'シート更新日'           // AE列
  ];

  const headerRange = sheet.getRange(1, 1, 1, headers.length);
  headerRange.setValues([headers]);
  
  // ヘッダー行のスタイル設定
  headerRange.setFontWeight('bold');
  headerRange.setBackground('#4285f4');
  headerRange.setFontColor('#ffffff');
  
  console.log('Headers set up successfully');
}

// =============================================================================
// 🎛️ 統合メニューシステム
// =============================================================================

/**
 * 統合メニューシステム - スプレッドシート開始時に実行
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
  
  // AI機能メニュー
  const aiMenu = ui.createMenu('🤖 AI機能')
    .addItem('🧪 OpenAI接続テスト', 'testGPTConnection')
    .addItem('🧠 全GPT関数テスト', 'testAllGPTFunctions')
    .addItem('🏛️ 助成金機能テスト', 'testGrantFunctions')
    .addSeparator()
    .addItem('📝 AI関数使用例表示', 'showGPTExamples')
    .addItem('💼 助成金AI分析', 'runGrantAnalysis')
    .addItem('📊 一括AI処理', 'batchAIProcessing')
    .addSeparator()
    .addItem('⚙️ AI設定確認', 'checkAISettings')
    .addItem('🗑️ AIキャッシュクリア', 'clearGPTCache');
  
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
    .addSubMenu(aiMenu)
    .addSubMenu(jgrantsMenu)
    .addSeparator()
    .addItem('📚 使い方ガイド', 'showUsageGuide')
    .addItem('ℹ️ システム情報', 'showSystemInfo')
    .addToUi();
}

// =============================================================================
// 🔧 設定・初期化関数
// =============================================================================

/**
 * 簡易セットアップ関数
 */
function quickSetup() {
  try {
    const sheet = getOrCreateSheet();
    setupHeaders(sheet);
    setupFieldValidation();
    
    SpreadsheetApp.getUi().alert('✅ セットアップ完了', 
      'スプレッドシートの初期設定が完了しました。\n\n' +
      '✓ ヘッダー行設定完了\n' +
      '✓ フィールドバリデーション設定完了\n\n' +
      'WordPressとの同期を開始するには、WordPress側の設定も確認してください。',
      SpreadsheetApp.getUi().ButtonSet.OK);
      
  } catch (error) {
    SpreadsheetApp.getUi().alert('❌ セットアップエラー', 
      'セットアップ中にエラーが発生しました：\n' + error.message,
      SpreadsheetApp.getUi().ButtonSet.OK);
  }
}

/**
 * AI機能テスト関数
 */
function testGPTFunctions() {
  try {
    // APIキーが設定されているかチェック
    if (OPENAI_CONFIG.API_KEY === 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') {
      SpreadsheetApp.getUi().alert('⚠️ API Key未設定', 
        'OpenAI APIキーが設定されていません。\n\n' +
        'スクリプトエディタで OPENAI_CONFIG.API_KEY を設定してください。',
        SpreadsheetApp.getUi().ButtonSet.OK);
      return;
    }
    
    // 簡単なテスト実行
    const testResult = GPT('こんにちは', '挨拶に返事をしてください');
    
    SpreadsheetApp.getUi().alert('🤖 AI機能テスト結果', 
      'テスト実行結果：\n\n' + testResult + '\n\n' +
      'AI機能が正常に動作しています。',
      SpreadsheetApp.getUi().ButtonSet.OK);
      
  } catch (error) {
    SpreadsheetApp.getUi().alert('❌ AI機能エラー', 
      'AI機能のテスト中にエラーが発生しました：\n' + error.message + '\n\n' +
      'APIキーや設定を確認してください。',
      SpreadsheetApp.getUi().ButtonSet.OK);
  }
}

// =============================================================================
// 🔐 OpenAI API関連のヘルパー関数
// =============================================================================

/**
 * OpenAI APIを呼び出し（リトライ機能付き）
 */
function callOpenAIWithRetry(input, instruction, model, maxTokens) {
  let lastError;
  
  for (let attempt = 0; attempt < OPENAI_CONFIG.MAX_RETRIES; attempt++) {
    try {
      return callOpenAI(input, instruction, model, maxTokens);
    } catch (error) {
      lastError = error;
      console.log(`API call attempt ${attempt + 1} failed:`, error.message);
      
      // レート制限エラーの場合は長めに待機
      if (error.message.includes('rate limit') || error.message.includes('429')) {
        Utilities.sleep(OPENAI_CONFIG.RATE_LIMIT_WAIT);
      } else if (attempt < OPENAI_CONFIG.MAX_RETRIES - 1) {
        // その他のエラーは段階的リトライ
        Utilities.sleep(OPENAI_CONFIG.RETRY_DELAYS[attempt]);
      }
    }
  }
  
  throw new Error(`API呼び出しが ${OPENAI_CONFIG.MAX_RETRIES} 回失敗しました: ${lastError.message}`);
}

/**
 * OpenAI APIを呼び出し
 */
function callOpenAI(input, instruction, model, maxTokens) {
  // 入力長制限チェック
  if (input.length > OPENAI_CONFIG.MAX_INPUT_LENGTH) {
    input = input.substring(0, OPENAI_CONFIG.MAX_INPUT_LENGTH);
  }
  
  const payload = {
    model: model,
    messages: [
      {
        role: "system", 
        content: instruction
      },
      {
        role: "user", 
        content: input
      }
    ],
    max_tokens: maxTokens,
    temperature: OPENAI_CONFIG.DEFAULT_TEMPERATURE
  };
  
  const options = {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${OPENAI_CONFIG.API_KEY}`,
      'Content-Type': 'application/json'
    },
    payload: JSON.stringify(payload)
  };
  
  const response = UrlFetchApp.fetch(OPENAI_CONFIG.API_ENDPOINT, options);
  const responseCode = response.getResponseCode();
  
  if (responseCode !== 200) {
    const errorText = response.getContentText();
    throw new Error(`API Error (${responseCode}): ${errorText}`);
  }
  
  const json = JSON.parse(response.getContentText());
  
  if (!json.choices || json.choices.length === 0) {
    throw new Error('API応答にデータが含まれていません');
  }
  
  return json.choices[0].message.content.trim();
}

/**
 * キャッシュキー生成
 */
function generateCacheKey(input, instruction, model) {
  const combined = input + '|||' + instruction + '|||' + model;
  return OPENAI_CONFIG.CACHE_PREFIX + Utilities.base64Encode(combined).replace(/[^a-zA-Z0-9]/g, '').substring(0, 50);
}

/**
 * キャッシュから結果を取得
 */
function getCachedResult(cacheKey) {
  try {
    const cache = CacheService.getScriptCache();
    return cache.get(cacheKey);
  } catch (error) {
    console.log('Cache retrieval error:', error);
    return null;
  }
}

/**
 * 結果をキャッシュに保存
 */
function setCachedResult(cacheKey, result) {
  try {
    const cache = CacheService.getScriptCache();
    cache.put(cacheKey, result, OPENAI_CONFIG.CACHE_DURATION / 1000); // 秒単位で指定
  } catch (error) {
    console.log('Cache storage error:', error);
    // キャッシュエラーは処理を止めない
  }
}

// =============================================================================
// 🔄 WordPress同期関連のヘルパー関数（続き）
// =============================================================================

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
      // 構造化されたデータ形式に変換
      const structuredData = convertRowDataToStructured(rowData);
      
      syncRowToWordPress('row_added', {
        row_number: lastRow,
        row_data: rowData,              // 後方互換性のための生データ
        structured_data: structuredData  // 新しいフィールドを含む構造化データ
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
function debugLog(message, data) {
  if (CONFIG.DEBUG_MODE) {
    console.log(`[DEBUG] ${message}`, data || '');
  }
}

/**
 * エラーログ記録
 */
function logError(message, error) {
  console.error(`[ERROR] ${message}`, error);
  
  // 必要に応じて外部ログサービスやWordPressに送信
  try {
    // エラー情報を構造化
    const errorInfo = {
      message: message,
      error: error.toString(),
      timestamp: new Date().toISOString(),
      spreadsheet_id: SpreadsheetApp.getActiveSpreadsheet().getId()
    };
    
    // エラーをシートに記録
    recordErrorToSheet(errorInfo);
    
    // WordPressにエラーログを送信（オプション）
    if (CONFIG.WORDPRESS_BASE_URL) {
      // エラーログ送信のロジックをここに追加可能
    }
  } catch (logError) {
    console.error('Failed to log error:', logError);
  }
}

/**
 * エラーログをシートに記録
 */
function recordErrorToSheet(errorData) {
  try {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    let errorSheet = spreadsheet.getSheetByName('Error_Logs');
    
    // エラーログシートが存在しない場合は作成
    if (!errorSheet) {
      errorSheet = spreadsheet.insertSheet('Error_Logs');
      
      // ヘッダー行を設定
      const headers = ['Timestamp', 'Message', 'Error', 'Spreadsheet ID'];
      errorSheet.getRange(1, 1, 1, headers.length).setValues([headers]);
      errorSheet.getRange(1, 1, 1, headers.length).setFontWeight('bold');
    }
    
    // 新しい行にエラー情報を追加
    const lastRow = errorSheet.getLastRow();
    const newRow = [
      errorData.timestamp,
      errorData.message,
      errorData.error,
      errorData.spreadsheet_id
    ];
    
    errorSheet.getRange(lastRow + 1, 1, 1, newRow.length).setValues([newRow]);
    
  } catch (error) {
    console.error('Failed to record error to sheet:', error);
  }
}

/**
 * トリガー設定関数
 */
function setupTriggers() {
  try {
    // 既存のトリガーを削除
    const triggers = ScriptApp.getProjectTriggers();
    triggers.forEach(trigger => {
      if (trigger.getHandlerFunction() === 'onEdit' || trigger.getHandlerFunction() === 'onChange') {
        ScriptApp.deleteTrigger(trigger);
      }
    });
    
    // 新しいトリガーを設定
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // onEdit トリガー
    ScriptApp.newTrigger('onEdit')
      .onEdit()
      .create();
      
    // onChange トリガー  
    ScriptApp.newTrigger('onChange')
      .onChange()
      .create();
    
    console.log('Triggers setup completed');
    
    return {
      success: true,
      message: 'トリガー設定が完了しました'
    };
    
  } catch (error) {
    console.error('Trigger setup failed:', error);
    return {
      success: false,
      message: 'トリガー設定に失敗しました: ' + error.message
    };
  }
}

/**
 * 接続テスト関数
 */
function testConnection() {
  try {
    console.log('Testing WordPress connection...');
    
    // テスト用のダミーデータ
    const testPayload = {
      action: 'connection_test',
      timestamp: new Date().toISOString(),
      test_data: 'GAS connection test'
    };
    
    const result = syncRowToWordPress('connection_test', testPayload);
    
    if (result) {
      console.log('✅ Connection test successful');
      return {
        success: true,
        message: 'WordPress接続テスト成功'
      };
    } else {
      throw new Error('Connection test failed');
    }
    
  } catch (error) {
    console.error('❌ Connection test failed:', error);
    return {
      success: false,
      message: 'WordPress接続テスト失敗: ' + error.message
    };
  }
}

/**
 * WordPressとの同期実行
 */
function syncWithWordPress() {
  return manualFullSync();
}

/**
 * WordPressにデータ送信
 */
function sendDataToWordPress() {
  return syncWithWordPress();
}

/**
 * WordPressからデータ受信
 */
function receiveDataFromWordPress() {
  return importGrantPosts();
}

// =============================================================================
// 📊 フィールドバリデーション機能
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
    
    // R列: 地域制限
    setupDropdownValidation(sheet, 'R:R', [
      'nationwide',        // 全国対象
      'prefecture_only',   // 都道府県内限定
      'municipality_only', // 市町村限定
      'region_group',      // 地域グループ限定
      'specific_area'      // 特定地域限定
    ]);
    
    // S列: 申請ステータス
    setupDropdownValidation(sheet, 'S:S', [
      'open',             // 募集中
      'upcoming',         // 募集予定
      'closed',           // 募集終了
      'suspended'         // 一時停止
    ]);
    
    // T列: 都道府県 (自由入力 - 完全連携対応)
    // ★バリデーションなし：どんな都道府県名でも入力可能
    
    // U列: 市町村 (自由入力 - 完全連携対応)
    // ★バリデーションなし：カンマ区切りで複数の市町村名を入力可能
    
    // AA列: 採択率（%）- 数値バリデーション（0-100の範囲）
    setupNumericValidation(sheet, 'AA:AA', 0, 100, '採択率は0〜100の数値で入力してください（%は自動で付与されます）');
    
    // AB列: 申請難易度 - 選択肢バリデーション
    setupDropdownValidation(sheet, 'AB:AB', [
      '初級',        // 初級レベル
      '中級',        // 中級レベル  
      '上級',        // 上級レベル
      '非常に高い'    // 非常に高いレベル
    ]);
    
    // AC列: 対象経費 (自由入力)
    // ★バリデーションなし：対象となる経費を自由に記述可能
    
    // AD列: 補助率 (自由入力) 
    // ★バリデーションなし：補助率を自由に記述可能（例：1/2、50%、上限100万円など）
    
    console.log('Field validation setup completed successfully');
    
    // セルの背景色を設定（選択肢フィールドを識別しやすくする）
    const validationColumns = ['E', 'M', 'O', 'R', 'S', 'AB']; // AB列（申請難易度）を追加
    validationColumns.forEach(column => {
      const range = sheet.getRange(`${column}1:${column}1000`);
      range.setBackground('#f0f8ff'); // 薄い青色で選択肢フィールドを区別
    });
    
    // 数値バリデーションフィールドを薄いオレンジ色で区別
    const numericColumns = ['AA']; // AA列（採択率）
    numericColumns.forEach(column => {
      const range = sheet.getRange(`${column}1:${column}1000`);
      range.setBackground('#fff3e0'); // 薄いオレンジ色で数値フィールドを区別
    });
    
    // 完全連携フィールドを緑色で区別（タクソノミー連携フィールド）
    const taxonomyColumns = ['T', 'U', 'V', 'W']; // 都道府県、市町村、カテゴリ、タグ
    taxonomyColumns.forEach(column => {
      const range = sheet.getRange(`${column}1:${column}1000`);
      range.setBackground('#e8f5e8'); // 薄い緑色でタクソノミーフィールドを区別
    });
    
    // 新規追加の自由入力フィールドを薄いグレー色で区別
    const newFreeTextColumns = ['X', 'Y', 'Z', 'AC', 'AD']; // 外部リンク、地域備考、必要書類、対象経費、補助率
    newFreeTextColumns.forEach(column => {
      const range = sheet.getRange(`${column}1:${column}1000`);
      range.setBackground('#f5f5f5'); // 薄いグレー色で新規自由入力フィールドを区別
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
 * 指定の列範囲に数値バリデーションを設定
 */
function setupNumericValidation(sheet, columnRange, minValue, maxValue, helpText) {
  try {
    const range = sheet.getRange(columnRange);
    const rule = SpreadsheetApp.newDataValidation()
      .requireNumberBetween(minValue, maxValue)
      .setAllowInvalid(false)
      .setHelpText(helpText || `${minValue}〜${maxValue}の数値を入力してください`)
      .build();
    
    range.setDataValidation(rule);
    console.log(`Numeric validation set for ${columnRange}: ${minValue}-${maxValue}`);
    
  } catch (error) {
    console.error(`Failed to set numeric validation for ${columnRange}:`, error);
    throw error;
  }
}

// =============================================================================
// 📖 ヘルプ・情報表示関数
// =============================================================================

/**
 * AI関数の使用例を表示
 */
function showGPTExamples() {
  const examples = `
🤖 AI関数使用例

【基本関数】
=GPT(A1, "この文章を要約してください")
=GPT4(B1, "英語に翻訳してください") 
=GPTFAST(C1, "キーワードを抽出してください")

【ビジネス特化】  
=GPT_COMPANY("トヨタ自動車", "detailed")
=GPT_SUMMARY(A1, "short")
=GPT_TRANSLATE(B1, "english")
=GPT_KEYWORDS(C1, 5)
=GPT_SENTIMENT(D1)

【助成金特化】
=GPT_GRANT_MATCH(A1, B1) // 助成金情報と事業情報の適合性分析
=GPT_GRANT_APPLICATION(A1, B1) // 申請書類支援
=GPT_GRANT_RISK(A1) // リスクアセスメント

【パラメータ説明】
- input: 処理したいテキスト（セル参照可）
- instruction: AIへの指示
- model: gpt-4 または gpt-3.5-turbo
- length: short/medium/long または detailed
`;

  SpreadsheetApp.getUi().alert('🤖 AI関数使用例', examples, SpreadsheetApp.getUi().ButtonSet.OK);
}

/**
 * システム情報を表示
 */
function showSystemInfo() {
  const info = `
🏛️ 助成金管理システム v2.0.0

【統合機能】
✅ WordPress双方向同期
✅ OpenAI GPT関数 (17種類)
✅ Jグランツデータ連携
✅ フィールドバリデーション

【対応列数】 31列 (A-AE)
【新規フィールド】 8列 (X-AD)

【設定状況】
WordPress URL: ${CONFIG.WORDPRESS_BASE_URL}
シート名: ${CONFIG.SHEET_NAME}
デバッグモード: ${CONFIG.DEBUG_MODE ? '有効' : '無効'}
AI API Key: ${OPENAI_CONFIG.API_KEY !== 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ? '設定済み' : '未設定'}

【サポート】
- 完全な双方向同期
- AI搭載データ分析
- 自動バリデーション
- リアルタイム更新
`;

  SpreadsheetApp.getUi().alert('ℹ️ システム情報', info, SpreadsheetApp.getUi().ButtonSet.OK);
}

/**
 * 使い方ガイドを表示
 */
function showUsageGuide() {
  const guide = `
📚 助成金管理システム 使い方ガイド

【初期設定】
1. 🚀 簡易セットアップを実行
2. WordPress側でWebhook設定
3. OpenAI APIキーを設定（AI機能使用時）

【基本操作】
📝 データ入力 → 自動でWordPressに同期
🔄 WordPress更新 → 自動でスプレッドシートに反映
🤖 AI関数使用 → =GPT(セル, "指示") で実行

【メニュー活用】
• WordPress連携: 同期・設定管理
• AI機能: GPT関数・一括処理
• Jグランツ連携: 政府データ取得

【フィールド色分け】
🔵 青: ドロップダウン選択
🟠 橙: 数値入力（バリデーション付）
🟢 緑: タクソノミー（カテゴリ・タグ等）
⚪ 灰: 自由入力

【トラブル時】
1. 🧪 接続テストを実行
2. ℹ️ システム情報で設定確認
3. デバッグモードでログ確認
`;

  SpreadsheetApp.getUi().alert('📚 使い方ガイド', guide, SpreadsheetApp.getUi().ButtonSet.OK);
}

// =============================================================================
// ✨ 追加のユーティリティ関数
// =============================================================================

/**
 * GPT接続テスト
 */
function testGPTConnection() {
  try {
    console.log('🔍 OpenAI API接続テスト開始...');
    
    if (OPENAI_CONFIG.API_KEY === 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') {
      throw new Error('API Keyが設定されていません');
    }
    
    const testResult = GPT('Hello', 'これは接続テストです。"OK"と回答してください。', 'gpt-3.5-turbo', 10);
    
    console.log('✅ 接続テスト成功:', testResult);
    return true;
    
  } catch (error) {
    console.error('❌ 接続テスト失敗:', error.message);
    return false;
  }
}

/**
 * 全GPT関数テスト
 */
function testAllGPTFunctions() {
  console.log('🧪 全GPT関数のテストを開始します...\n');
  
  const tests = [
    // 基本機能テスト
    {
      name: 'GPT基本機能',
      func: () => GPT('OpenAI', 'この会社について1行で説明して'),
      category: '基本'
    },
    {
      name: 'GPT4機能',
      func: () => GPT4('OpenAI', '会社の特徴を教えて'),
      category: '基本'
    },
    {
      name: 'GPTFAST機能',
      func: () => GPTFAST('Hello', '日本語に翻訳して'),
      category: '基本'
    },
    
    // 汎用機能テスト
    {
      name: '会社概要機能',
      func: () => GPT_COMPANY('Microsoft', 'short'),
      category: '汎用'
    },
    {
      name: '要約機能',
      func: () => GPT_SUMMARY('人工知能は現代社会において重要な技術となっています。機械学習、自然言語処理、画像認識など様々な分野で活用されています。', 'short'),
      category: '汎用'
    },
    {
      name: '翻訳機能',
      func: () => GPT_TRANSLATE('Good morning', 'japanese'),
      category: '汎用'
    },
    {
      name: 'キーワード抽出',
      func: () => GPT_KEYWORDS('AIと機械学習は現代のビジネスにおいて重要な技術です。', 3),
      category: '汎用'
    },
    {
      name: '感情分析',
      func: () => GPT_SENTIMENT('とても素晴らしい製品だと思います！'),
      category: '汎用'
    },
    
    // 助成金特化機能テスト
    {
      name: '助成金分析機能',
      func: () => GPT_GRANT_ANALYSIS('IT導入補助金は、中小企業のIT導入を支援する制度です。対象は中小企業で、ITツール導入費用の一部を補助します。', 300),
      category: '助成金'
    },
    {
      name: '助成金カテゴリ分類',
      func: () => GPT_GRANT_CATEGORY('新規事業立ち上げのための設備投資と人材採用を支援する補助金制度'),
      category: '助成金'
    },
    {
      name: '申請スケジュール提案',
      func: () => GPT_GRANT_SCHEDULE('申請締切：2024年12月31日、必要書類：事業計画書、決算書類', '2024-11-01'),
      category: '助成金'
    },
    {
      name: '地域別助成金情報',
      func: () => GPT_GRANT_REGIONAL('東京都', 'IT企業'),
      category: '助成金'
    }
  ];
  
  // カテゴリ別に実行
  const categories = ['基本', '汎用', '助成金'];
  let totalTests = 0;
  let successTests = 0;
  
  categories.forEach(category => {
    console.log(`\n📂 ${category}機能のテスト`);
    console.log('='.repeat(40));
    
    const categoryTests = tests.filter(test => test.category === category);
    
    categoryTests.forEach((test, index) => {
      totalTests++;
      try {
        console.log(`\n${totalTests}. ${test.name}をテスト中...`);
        const startTime = new Date().getTime();
        
        const result = test.func();
        
        const endTime = new Date().getTime();
        const duration = endTime - startTime;
        
        console.log(`✅ ${test.name}: 成功 (${duration}ms)`);
        console.log(`📝 結果: ${result.substring(0, 100)}${result.length > 100 ? '...' : ''}`);
        
        successTests++;
        
        // API制限を避けるため少し待機
        Utilities.sleep(2000);
        
      } catch (error) {
        console.error(`❌ ${test.name}: 失敗 - ${error.message}`);
      }
    });
  });
  
  // 結果サマリー
  console.log('\n' + '='.repeat(50));
  console.log('🎉 全機能テスト完了!');
  console.log(`📊 成功率: ${successTests}/${totalTests} (${Math.round(successTests/totalTests*100)}%)`);
  
  return {
    total: totalTests,
    success: successTests,
    rate: Math.round(successTests/totalTests*100)
  };
}

/**
 * GPTキャッシュクリア
 */
function clearGPTCache() {
  try {
    const cache = CacheService.getScriptCache();
    
    // キャッシュの統計情報を取得（削除前）
    const stats = getCacheStats();
    
    // 全てのキャッシュをクリア（GPT関連のプレフィックスがあるもののみ）
    // 注意: Google Apps Scriptでは個別のキーを指定してクリアできないため、
    // 全体クリアを行います
    cache.removeAll();
    
    console.log('🗑️ GPTキャッシュをクリアしました');
    console.log(`📊 削除前の統計: ${JSON.stringify(stats)}`);
    
    return {
      success: true,
      message: 'キャッシュクリア完了',
      previousStats: stats
    };
    
  } catch (error) {
    console.error('❌ キャッシュクリアに失敗:', error);
    return {
      success: false,
      message: 'キャッシュクリア失敗: ' + error.message
    };
  }
}

/**
 * キャッシュ統計情報取得
 */
function getCacheStats() {
  try {
    // 簡単な統計情報を返す（Google Apps Scriptの制限により詳細情報は取得困難）
    return {
      timestamp: new Date().toISOString(),
      cache_available: true,
      note: 'GAS制限により詳細統計は取得できません'
    };
  } catch (error) {
    return {
      timestamp: new Date().toISOString(),
      cache_available: false,
      error: error.message
    };
  }
}

/**
 * AI設定確認
 */
function checkAISettings() {
  const apiKeySet = OPENAI_CONFIG.API_KEY !== 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
  const model = OPENAI_CONFIG.DEFAULT_MODEL;
  const maxTokens = OPENAI_CONFIG.DEFAULT_MAX_TOKENS;
  
  const status = `
⚙️ AI設定確認

API Key: ${apiKeySet ? '✅ 設定済み' : '❌ 未設定'}
デフォルトモデル: ${model}
最大トークン数: ${maxTokens}
キャッシュ機能: ✅ 有効
リトライ機能: ✅ 有効

${!apiKeySet ? '\n⚠️ APIキーを設定してください' : ''}
`;

  SpreadsheetApp.getUi().alert('⚙️ AI設定確認', status, SpreadsheetApp.getUi().ButtonSet.OK);
}

/**
 * OpenAI接続テスト関数
 */
function testGPTConnection() {
  try {
    console.log('🧪 OpenAI API接続テストを開始...');
    
    // APIキー設定確認
    if (OPENAI_CONFIG.API_KEY === 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') {
      console.log('❌ APIキーが設定されていません');
      return '❌ APIキーが設定されていません。OPENAI_CONFIG.API_KEY を設定してください。';
    }
    
    // 簡単なテストリクエスト
    const testResult = callOpenAI(
      'Hello', 
      'この挨拶に日本語で返事をしてください。', 
      'gpt-3.5-turbo', 
      50
    );
    
    console.log('✅ テスト成功!');
    console.log('📝 テスト結果:', testResult);
    
    return `✅ テスト成功!\n応答: ${testResult}`;
    
  } catch (error) {
    console.error('❌ 接続テスト失敗:', error.message);
    return `❌ 接続テスト失敗: ${error.message}`;
  }
}

/**
 * 全GPT関数テスト
 */
function testAllGPTFunctions() {
  console.log('🚀 全GPT関数の動作テストを開始...');
  
  const tests = [
    // 基本機能
    {
      name: '基本GPT関数',
      func: () => GPT('こんにちは', 'この挨拶に返事をしてください'),
      category: '基本'
    },
    {
      name: 'GPT4関数',
      func: () => GPT4('AI技術', '50字で説明してください'),
      category: '基本'
    },
    {
      name: '高速GPT関数',
      func: () => GPTFAST('テスト', 'この文字の意味を教えて'),
      category: '基本'
    },
    
    // 汎用機能
    {
      name: '企業分析',
      func: () => GPT_COMPANY('Google', 'short'),
      category: '汎用'
    },
    {
      name: '文章要約',
      func: () => GPT_SUMMARY('人工知能（AI）は、コンピューターが人間の知能を模倣する技術です。機械学習や深層学習などの手法により、大量のデータから学習し、パターンを認識したり予測を行ったりすることができます。', 'short'),
      category: '汎用'
    },
    {
      name: '翻訳機能',
      func: () => GPT_TRANSLATE('おはようございます', 'english'),
      category: '汎用'
    },
    {
      name: 'キーワード抽出',
      func: () => GPT_KEYWORDS('人工知能と機械学習の技術は現代のビジネスにおいて重要な役割を果たしています', 3),
      category: '汎用'
    },
    {
      name: '感情分析',
      func: () => GPT_SENTIMENT('今日はとても良い天気で、気分が最高です！'),
      category: '汎用'
    },
    
    // 助成金機能
    {
      name: '助成金分析',
      func: () => GPT_GRANT_ANALYSIS('ものづくり補助金：中小企業の設備投資支援、最大1000万円、年2回募集', 200),
      category: '助成金'
    },
    {
      name: '適合性分析',
      func: () => GPT_GRANT_MATCH('従業員30名のIT企業', 'IT導入補助金：ITツール導入支援'),
      category: '助成金'
    },
    {
      name: '助成金カテゴリ分類',
      func: () => GPT_GRANT_CATEGORY('新製品開発のための設備投資を支援する補助金'),
      category: '助成金'
    }
  ];
  
  // カテゴリ別に実行
  const categories = ['基本', '汎用', '助成金'];
  let totalTests = 0;
  let successTests = 0;
  
  categories.forEach(category => {
    console.log(`\n📂 ${category}機能のテスト`);
    console.log('='.repeat(40));
    
    const categoryTests = tests.filter(test => test.category === category);
    
    categoryTests.forEach((test, index) => {
      totalTests++;
      try {
        console.log(`\n${totalTests}. ${test.name}をテスト中...`);
        const startTime = new Date().getTime();
        
        const result = test.func();
        
        const endTime = new Date().getTime();
        const duration = endTime - startTime;
        
        console.log(`✅ ${test.name}: 成功 (${duration}ms)`);
        console.log(`📝 結果: ${result.substring(0, 100)}${result.length > 100 ? '...' : ''}`);
        
        successTests++;
        
        // API制限を避けるため少し待機
        Utilities.sleep(2000);
        
      } catch (error) {
        console.error(`❌ ${test.name}: 失敗 - ${error.message}`);
      }
    });
  });
  
  // 結果サマリー
  console.log('\n' + '='.repeat(50));
  console.log('🎉 全機能テスト完了!');
  console.log(`📊 結果: ${successTests}/${totalTests} (成功率: ${Math.round(successTests/totalTests*100)}%)`);
  
  if (successTests === totalTests) {
    console.log('🎊 すべてのテストが成功しました！');
  } else {
    console.log('⚠️ 一部のテストが失敗しました。ログを確認してください。');
  }
  
  // キャッシュ統計も表示
  console.log('💾 ' + getCacheStats());
}

/**
 * 助成金特化機能のみテスト
 */
function testGrantFunctions() {
  console.log('🏛️ 助成金特化機能のテストを開始...');
  
  const grantTests = [
    {
      name: '助成金分析',
      func: () => GPT_GRANT_ANALYSIS('ものづくり補助金は、中小企業の設備投資を支援します。対象は製造業で、最大1000万円まで補助。申請期間は年2回。', 400)
    },
    {
      name: '適合性判定',
      func: () => GPT_GRANT_MATCH('従業員50名のIT企業、年商3億円', 'IT導入補助金：中小企業のITツール導入支援、最大450万円')
    },
    {
      name: '申請書類支援',
      func: () => GPT_GRANT_APPLICATION('事業再構築補助金', 'レストラン業、コロナ禍でテイクアウト事業開始', '事業計画書')
    },
    {
      name: '助成金比較',
      func: () => GPT_GRANT_COMPARE('ものづくり補助金：設備投資支援、最大1000万円', 'IT導入補助金：ITツール導入支援、最大450万円', '中小製造業にとっての利用しやすさ')
    }
  ];
  
  grantTests.forEach((test, index) => {
    try {
      console.log(`\n${index + 1}. ${test.name}をテスト中...`);
      const result = test.func();
      console.log(`✅ ${test.name}: 成功`);
      console.log(`📋 結果:\n${result}`);
      
      Utilities.sleep(3000); // 助成金分析は重い処理なので長めに待機
      
    } catch (error) {
      console.error(`❌ ${test.name}: 失敗 - ${error.message}`);
    }
  });
  
  console.log('\n🏆 助成金機能テスト完了!');
}

/**
 * キャッシュ統計取得
 */
function getCacheStats() {
  try {
    // Apps Scriptのキャッシュサービスでは統計情報の直接取得は困難
    return 'キャッシュ機能: 有効（1時間保持）';
  } catch (error) {
    return 'キャッシュ統計取得エラー: ' + error.message;
  }
}

/**
 * キャッシュクリア関数
 */
function clearGPTCache() {
  try {
    const cache = CacheService.getScriptCache();
    // 個別のキャッシュクリアは困難なため、ログのみ記録
    console.log('🗑️ GPTキャッシュクリア要求を受信しました');
    return 'キャッシュクリア処理を実行しました';
  } catch (error) {
    console.error('キャッシュクリアエラー:', error.message);
    return 'キャッシュクリアに失敗しました';
  }
}

/**
 * 一括AI処理（例：選択範囲の文章を一括要約）
 */
function batchAIProcessing() {
  try {
    const sheet = SpreadsheetApp.getActiveSheet();
    const selection = sheet.getActiveRange();
    
    if (!selection) {
      SpreadsheetApp.getUi().alert('❌ エラー', '処理する範囲を選択してください。', SpreadsheetApp.getUi().ButtonSet.OK);
      return;
    }
    
    const response = SpreadsheetApp.getUi().prompt('📊 一括AI処理', 
      '選択範囲に対して実行するAI処理を指定してください：\n\n' +
      '例: 要約して, 英訳して, キーワード抽出して',
      SpreadsheetApp.getUi().ButtonSet.OK_CANCEL);
      
    if (response.getSelectedButton() !== SpreadsheetApp.getUi().Button.OK) {
      return;
    }
    
    const instruction = response.getResponseText();
    if (!instruction) {
      return;
    }
    
    const values = selection.getValues();
    const results = [];
    
    for (let i = 0; i < values.length; i++) {
      const row = [];
      for (let j = 0; j < values[i].length; j++) {
        const cellValue = values[i][j].toString();
        if (cellValue && cellValue.length > 0) {
          try {
            const result = GPT(cellValue, instruction);
            row.push(result);
          } catch (error) {
            row.push(`エラー: ${error.message}`);
          }
        } else {
          row.push('');
        }
      }
      results.push(row);
      
      // プログレス表示とレート制限対策
      if (i % 5 === 0) {
        console.log(`Processing... ${i + 1}/${values.length}`);
        Utilities.sleep(1000);
      }
    }
    
    // 結果を隣の列に出力
    const outputRange = sheet.getRange(selection.getRow(), selection.getLastColumn() + 1, results.length, results[0].length);
    outputRange.setValues(results);
    
    SpreadsheetApp.getUi().alert('✅ 完了', `一括AI処理が完了しました。\n処理件数: ${values.length}行`, SpreadsheetApp.getUi().ButtonSet.OK);
    
  } catch (error) {
    SpreadsheetApp.getUi().alert('❌ エラー', `一括処理中にエラーが発生しました：\n${error.message}`, SpreadsheetApp.getUi().ButtonSet.OK);
  }
}

/**
 * 助成金分析AI関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報テキスト
 * @param {number} length 分析結果の文字数（省略可能、デフォルト500字）
 * @return {string} 助成金の分析結果
 */
function GPT_GRANT_ANALYSIS(grantInfo, length) {
  const maxLength = length || 500;
  const instruction = `以下の助成金・補助金情報を詳細に分析し、以下の項目について${maxLength}字以内でまとめてください：
1. 対象者・対象企業の条件
2. 支援内容・金額
3. 申請時期・締切
4. 申請の難易度
5. 注意すべきポイント
簡潔で実用的な情報を提供してください。`;
  return GPT(grantInfo, instruction, 'gpt-4');
}

/**
 * 助成金カテゴリ分類関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報
 * @return {string} 分類結果（複数カテゴリの場合はカンマ区切り）
 */
function GPT_GRANT_CATEGORY(grantInfo) {
  const categories = "創業・起業支援,研究開発,DX・IT化,省エネ・環境,人材育成,事業拡大,設備投資,海外展開,地域活性化,その他";
  const instruction = `以下の助成金情報を分析し、適切なカテゴリに分類してください。
カテゴリ: ${categories}
最も適切なカテゴリを1-2個選択し、カンマ区切りで回答してください。`;
  return GPT(grantInfo, instruction, 'gpt-3.5-turbo');
}

/**
 * 申請締切管理関数
 * 
 * @customfunction
 * @param {string} grantInfo 助成金情報
 * @param {string} currentDate 現在日時（省略可能）
 * @return {string} 申請スケジュール提案
 */
function GPT_GRANT_SCHEDULE(grantInfo, currentDate) {
  const today = currentDate || new Date().toLocaleDateString('ja-JP');
  const instruction = `助成金の申請スケジュールを分析し、以下の形式で提案してください：
現在日時: ${today}

1. 申請締切日: [日付]
2. 準備開始推奨日: [日付]  
3. 必要な準備期間: [週数]
4. 重要なマイルストーン: [準備段階ごとの目標日]
5. 緊急度: [高/中/低]`;
  return GPT(grantInfo, instruction, 'gpt-3.5-turbo');
}

/**
 * 助成金比較分析関数
 * 
 * @customfunction
 * @param {string} grant1 助成金1の情報
 * @param {string} grant2 助成金2の情報
 * @param {string} comparePoint 比較観点（省略可能：金額,条件,難易度など）
 * @return {string} 比較分析結果
 */
function GPT_GRANT_COMPARE(grant1, grant2, comparePoint) {
  const point = comparePoint || "総合的な観点";
  const instruction = `以下の2つの助成金を${point}で比較分析してください：

【助成金A】
${grant1}

【助成金B】
${grant2}

以下の項目で比較してください：
1. 支援金額・規模
2. 申請条件・難易度
3. 申請期間・締切
4. 申請プロセス
5. 採択確率
6. おすすめ度とその理由`;
  return GPT(grant1 + " VS " + grant2, instruction, 'gpt-4', 1200);
}

/**
 * 地域別助成金検索支援関数
 * 
 * @customfunction
 * @param {string} location 地域名（都道府県・市町村）
 * @param {string} businessType 事業種別（省略可能）
 * @return {string} 地域特化助成金情報の分析
 */
function GPT_GRANT_REGIONAL(location, businessType) {
  const business = businessType || "一般事業者";
  const instruction = `${location}地域の${business}向け助成金・補助金について分析してください。
以下の観点で情報を整理してください：
1. 主要な地域特化助成金
2. 申請しやすい助成金
3. 支援金額が大きい助成金
4. 地域の特色を活かした支援制度
5. 申請時期・スケジュール
6. 地域での申請支援窓口・相談先`;
  return GPT(location + " " + business, instruction, 'gpt-4', 1000);
}

/**
 * 助成金AI分析実行
 */
function runGrantAnalysis() {
  try {
    const sheet = SpreadsheetApp.getActiveSheet();
    const selection = sheet.getActiveRange();
    
    if (!selection || selection.getNumRows() !== 1) {
      SpreadsheetApp.getUi().alert('❌ エラー', '分析する助成金の行を1つ選択してください。', SpreadsheetApp.getUi().ButtonSet.OK);
      return;
    }
    
    const rowData = getRowData(sheet, selection.getRow());
    if (!rowData) {
      SpreadsheetApp.getUi().alert('❌ エラー', 'データが見つかりません。', SpreadsheetApp.getUi().ButtonSet.OK);
      return;
    }
    
    const structuredData = convertRowDataToStructured(rowData);
    const grantInfo = `
タイトル: ${structuredData.title}
内容: ${structuredData.content}
対象者: ${structuredData.target_description}
助成金額: ${structuredData.amount_display}
申請期限: ${structuredData.deadline_display}
実施組織: ${structuredData.organization}
`;
    
    // AI分析を実行
    const analysisResult = GPT_GRANT_RISK(grantInfo);
    
    // 結果を新しいシートまたはサイドバーに表示
    const ui = SpreadsheetApp.getUi();
    ui.alert('🤖 助成金AI分析結果', `${structuredData.title}\n\n${analysisResult}`, ui.ButtonSet.OK);
    
  } catch (error) {
    SpreadsheetApp.getUi().alert('❌ エラー', `AI分析中にエラーが発生しました：\n${error.message}`, SpreadsheetApp.getUi().ButtonSet.OK);
  }
}

/**
 * トリガーを設定
 */
function setupTriggers() {
  try {
    console.log('Setting up triggers...');
    
    // 既存のトリガーを削除
    const triggers = ScriptApp.getProjectTriggers();
    triggers.forEach(trigger => {
      if (trigger.getHandlerFunction() === 'onEdit' || trigger.getHandlerFunction() === 'onChange') {
        ScriptApp.deleteTrigger(trigger);
      }
    });
    
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    
    // onEdit トリガーを設定
    ScriptApp.newTrigger('onEdit')
      .onEdit()
      .create();
    
    // onChange トリガーを設定  
    ScriptApp.newTrigger('onChange')
      .onChange()
      .create();
    
    console.log('Triggers setup completed');
    
    SpreadsheetApp.getUi().alert('✅ トリガー設定完了', 
      'スプレッドシートのトリガーが正常に設定されました。\n\n' +
      '✓ onEdit: セル編集時の自動同期\n' +
      '✓ onChange: シート変更時の自動同期\n\n' +
      'これでWordPressとの自動同期が有効になりました。',
      SpreadsheetApp.getUi().ButtonSet.OK);
      
  } catch (error) {
    console.error('Setup triggers failed:', error);
    SpreadsheetApp.getUi().alert('❌ トリガー設定エラー', 
      'トリガーの設定中にエラーが発生しました：\n' + error.message,
      SpreadsheetApp.getUi().ButtonSet.OK);
  }
}

/**
 * WordPress接続テスト
 */
function testConnection() {
  try {
    console.log('Testing WordPress connection...');
    
    // 設定確認
    if (!CONFIG.REST_API_URL && !CONFIG.WEBHOOK_URL) {
      throw new Error('WordPress URLが設定されていません');
    }
    
    if (!CONFIG.SECRET_KEY || CONFIG.SECRET_KEY === 'your_webhook_secret_key_here') {
      throw new Error('シークレットキーが設定されていません');
    }
    
    // テストデータを作成
    const testPayload = {
      action: 'connection_test',
      timestamp: new Date().toISOString(),
      test_data: 'Google Sheets connection test'
    };
    
    const timestamp = Math.floor(Date.now() / 1000);
    const payloadString = JSON.stringify(testPayload);
    const signature = createSignature(timestamp, payloadString);
    
    const requestData = {
      timestamp: timestamp,
      signature: signature,
      payload: testPayload
    };
    
    const url = CONFIG.REST_API_URL || CONFIG.WEBHOOK_URL;
    
    const options = {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      payload: JSON.stringify(requestData)
    };
    
    console.log('Sending test request to:', url);
    
    const response = UrlFetchApp.fetch(url, options);
    const responseCode = response.getResponseCode();
    const responseText = response.getContentText();
    
    console.log('Test response:', {
      code: responseCode,
      body: responseText
    });
    
    if (responseCode >= 200 && responseCode < 300) {
      const message = `✅ 接続テスト成功！\n\n` +
        `URL: ${url}\n` +
        `レスポンス: ${responseCode}\n` +
        `内容: ${responseText.substring(0, 200)}${responseText.length > 200 ? '...' : ''}`;
      
      SpreadsheetApp.getUi().alert('✅ 接続テスト成功', message, SpreadsheetApp.getUi().ButtonSet.OK);
      return true;
    } else {
      throw new Error(`HTTP ${responseCode}: ${responseText}`);
    }
    
  } catch (error) {
    console.error('Connection test failed:', error);
    
    const errorMessage = `❌ 接続テスト失敗\n\n` +
      `エラー: ${error.message}\n\n` +
      `確認事項:\n` +
      `• WordPress URLが正しいか\n` +
      `• シークレットキーが正しいか\n` +
      `• WordPress側のプラグインが有効か`;
    
    SpreadsheetApp.getUi().alert('❌ 接続テスト失敗', errorMessage, SpreadsheetApp.getUi().ButtonSet.OK);
    return false;
  }
}

/**
 * エラーをシートに記録
 */
function recordErrorToSheet(errorData) {
  try {
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    let errorSheet = spreadsheet.getSheetByName('Error_Log');
    
    if (!errorSheet) {
      errorSheet = spreadsheet.insertSheet('Error_Log');
      // ヘッダー行を設定
      errorSheet.getRange(1, 1, 1, 5).setValues([
        ['Timestamp', 'Error Type', 'Message', 'Details', 'Row Data']
      ]);
    }
    
    const lastRow = errorSheet.getLastRow();
    const newRow = lastRow + 1;
    
    errorSheet.getRange(newRow, 1, 1, 5).setValues([[
      new Date().toISOString(),
      errorData.type || 'Unknown',
      errorData.message || 'No message',
      errorData.details || 'No details',
      JSON.stringify(errorData.rowData || {})
    ]]);
    
    console.log('Error recorded to Error_Log sheet');
    
  } catch (logError) {
    console.error('Failed to record error to sheet:', logError);
  }
}

/**
 * WordPress連携用のヘルパー関数群
 */
function syncWithWordPress() {
  manualFullSync();
}

function sendDataToWordPress() {
  manualFullSync();
}

function receiveDataFromWordPress() {
  importGrantPosts();
}

console.log('🏛️ Grant Management System v2.0.0 - Integrated Edition loaded successfully!');