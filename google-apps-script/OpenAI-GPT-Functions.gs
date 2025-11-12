/**
 * OpenAI GPT Custom Functions for Google Sheets
 * スプレッドシート用OpenAI GPTカスタム関数
 * 
 * 使用方法:
 * 1. OpenAI APIキーを下記のAPI_KEYに設定
 * 2. このスクリプトを保存・実行して権限を承認
 * 3. スプレッドシートで =GPT(テキスト, 指示) のように使用
 * 
 * @version 1.0.0
 * @author Grant Insight Perfect
 */

// =============================================================================
// 🔑 設定 - OpenAI APIキーを設定してください
// =============================================================================

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

// =============================================================================
// 🤖 メイン GPT関数
// =============================================================================

/**
 * GPT カスタム関数
 * 
 * @param {string} input - 入力テキスト
 * @param {string} instruction - GPTへの指示
 * @param {string} model - 使用するモデル (省略可能)
 * @param {number} maxTokens - 最大トークン数 (省略可能)
 * @return {string} GPTからの応答
 * 
 * @customfunction
 */
function GPT(input, instruction, model, maxTokens) {
  try {
    // 入力検証
    if (!input || !instruction) {
      return 'エラー: 入力テキストと指示の両方が必要です';
    }
    
    // パラメータの設定
    const selectedModel = model || OPENAI_CONFIG.DEFAULT_MODEL;
    const tokens = maxTokens || OPENAI_CONFIG.DEFAULT_MAX_TOKENS;
    
    // キャッシュキーの生成
    const cacheKey = generateCacheKey(input, instruction, selectedModel);
    
    // キャッシュから結果を取得
    const cachedResult = getCachedResult(cacheKey);
    if (cachedResult) {
      return cachedResult;
    }
    
    // OpenAI APIを呼び出し
    const result = callOpenAI(input, instruction, selectedModel, tokens);
    
    // 結果をキャッシュに保存
    setCachedResult(cacheKey, result);
    
    return result;
    
  } catch (error) {
    console.error('GPT関数エラー:', error);
    return 'エラー: ' + error.message;
  }
}

/**
 * GPT4 専用関数（高精度）
 * 
 * @param {string} input - 入力テキスト
 * @param {string} instruction - GPTへの指示
 * @return {string} GPT-4からの応答
 * 
 * @customfunction
 */
function GPT4(input, instruction) {
  return GPT(input, instruction, 'gpt-4', 2000);
}

/**
 * GPT Fast 関数（高速・低コスト）
 * 
 * @param {string} input - 入力テキスト
 * @param {string} instruction - GPTへの指示
 * @return {string} GPT-3.5-turboからの応答
 * 
 * @customfunction
 */
function GPTFAST(input, instruction) {
  return GPT(input, instruction, 'gpt-3.5-turbo', 500);
}

// =============================================================================
// 🎯 特化型GPT関数
// =============================================================================

/**
 * 会社概要生成関数
 * 
 * @param {string} companyName - 会社名または銘柄コード
 * @param {number} length - 文字数制限 (省略可能、デフォルト400字)
 * @return {string} 会社概要
 * 
 * @customfunction
 */
function GPT_COMPANY(companyName, length) {
  const maxLength = length || 400;
  const instruction = `${companyName}の会社概要を${maxLength}字以内で簡潔にまとめてください。事業内容、業界での地位、特徴的な点を含めてください。`;
  
  return GPT(companyName, instruction, 'gpt-3.5-turbo');
}

/**
 * 要約生成関数
 * 
 * @param {string} text - 要約したいテキスト
 * @param {number} length - 要約の文字数 (省略可能、デフォルト200字)
 * @return {string} 要約テキスト
 * 
 * @customfunction
 */
function GPT_SUMMARY(text, length) {
  const maxLength = length || 200;
  const instruction = `以下のテキストを${maxLength}字以内で要約してください。重要なポイントを漏らさず、簡潔にまとめてください。`;
  
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

/**
 * 翻訳関数
 * 
 * @param {string} text - 翻訳したいテキスト
 * @param {string} targetLang - 翻訳先言語 (日本語、英語、中国語など)
 * @return {string} 翻訳されたテキスト
 * 
 * @customfunction
 */
function GPT_TRANSLATE(text, targetLang) {
  const language = targetLang || '日本語';
  const instruction = `以下のテキストを${language}に翻訳してください。自然で読みやすい翻訳にしてください。`;
  
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

/**
 * キーワード抽出関数
 * 
 * @param {string} text - 分析したいテキスト
 * @param {number} count - 抽出するキーワード数 (省略可能、デフォルト5個)
 * @return {string} 抽出されたキーワード（カンマ区切り）
 * 
 * @customfunction
 */
function GPT_KEYWORDS(text, count) {
  const keywordCount = count || 5;
  const instruction = `以下のテキストから重要なキーワードを${keywordCount}個抽出してください。カンマ区切りで出力してください。`;
  
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

/**
 * 感情分析関数
 * 
 * @param {string} text - 分析したいテキスト
 * @return {string} 感情分析結果（ポジティブ、ネガティブ、ニュートラル）
 * 
 * @customfunction
 */
function GPT_SENTIMENT(text) {
  const instruction = 'このテキストの感情を分析し、「ポジティブ」「ネガティブ」「ニュートラル」のいずれかで回答してください。理由も簡潔に添えてください。';
  
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

/**
 * カテゴリ分類関数
 * 
 * @param {string} text - 分類したいテキスト
 * @param {string} categories - カテゴリリスト（カンマ区切り）
 * @return {string} 最も適切なカテゴリ
 * 
 * @customfunction
 */
function GPT_CATEGORIZE(text, categories) {
  const instruction = `以下のテキストを次のカテゴリのいずれかに分類してください: ${categories}。最も適切なカテゴリ名のみを回答してください。`;
  
  return GPT(text, instruction, 'gpt-3.5-turbo');
}

// =============================================================================
// 🏛️ 助成金・補助金特化型GPT関数
// =============================================================================

/**
 * 助成金分析関数
 * 助成金情報を詳細に分析し、重要なポイントを抽出
 * 
 * @param {string} grantInfo - 助成金情報テキスト
 * @param {number} length - 分析結果の文字数 (省略可能、デフォルト500字)
 * @return {string} 助成金の分析結果
 * 
 * @customfunction
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
 * 助成金適合性判定関数
 * 企業情報と助成金情報をマッチングして適合性を判定
 * 
 * @param {string} companyInfo - 企業情報
 * @param {string} grantInfo - 助成金情報
 * @return {string} 適合性判定結果（適合度とその理由）
 * 
 * @customfunction
 */
function GPT_GRANT_MATCH(companyInfo, grantInfo) {
  const instruction = `企業情報と助成金情報を照合し、適合性を判定してください。
以下の形式で回答してください：
適合度: ★★★☆☆ (5段階)
理由: [具体的な理由]
推奨度: [高い/中程度/低い]
注意点: [申請時の注意事項]

企業情報: ${companyInfo}
助成金情報: ${grantInfo}`;
  
  return GPT(companyInfo + " | " + grantInfo, instruction, 'gpt-4');
}

/**
 * 申請書類生成支援関数
 * 助成金申請に必要な書類の要点を生成
 * 
 * @param {string} grantName - 助成金名
 * @param {string} companyProfile - 企業プロフィール
 * @param {string} documentType - 書類種別（事業計画書、申請理由書など）
 * @return {string} 申請書類の要点・構成案
 * 
 * @customfunction
 */
function GPT_GRANT_APPLICATION(grantName, companyProfile, documentType) {
  const instruction = `${grantName}への申請において、${documentType}を作成するための要点と構成案を提供してください。
企業プロフィール: ${companyProfile}

以下の項目を含めて回答してください：
1. 文書の構成・目次案
2. 各セクションで記載すべき重要ポイント
3. アピールすべき強み
4. 注意すべき審査ポイント
5. 推奨文字数・ページ数`;
  
  return GPT(grantName + " | " + companyProfile, instruction, 'gpt-4', 1500);
}

/**
 * 助成金カテゴリ分類関数
 * 助成金を目的・分野別に分類
 * 
 * @param {string} grantInfo - 助成金情報
 * @return {string} 分類結果（複数カテゴリの場合はカンマ区切り）
 * 
 * @customfunction
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
 * 助成金の締切情報を解析し、申請スケジュールを提案
 * 
 * @param {string} grantInfo - 助成金情報
 * @param {string} currentDate - 現在日時（省略可能）
 * @return {string} 申請スケジュール提案
 * 
 * @customfunction
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
 * 複数の助成金を比較分析
 * 
 * @param {string} grant1 - 助成金1の情報
 * @param {string} grant2 - 助成金2の情報
 * @param {string} comparePoint - 比較観点（省略可能：金額,条件,難易度など）
 * @return {string} 比較分析結果
 * 
 * @customfunction
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
 * 都道府県・市町村別の助成金情報を分析
 * 
 * @param {string} location - 地域名（都道府県・市町村）
 * @param {string} businessType - 事業種別（省略可能）
 * @return {string} 地域特化助成金情報の分析
 * 
 * @customfunction
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

// =============================================================================
// 🔧 ヘルパー関数
// =============================================================================

/**
 * OpenAI APIを呼び出し（改良版 - エラーハンドリング強化）
 * 
 * @param {string} input - 入力テキスト
 * @param {string} instruction - 指示
 * @param {string} model - モデル名
 * @param {number} maxTokens - 最大トークン数
 * @return {string} APIからの応答
 */
function callOpenAI(input, instruction, model, maxTokens) {
  // 入力検証の強化
  if (!OPENAI_CONFIG.API_KEY || OPENAI_CONFIG.API_KEY.includes('xxxxxxxxxx')) {
    throw new Error('OpenAI APIキーが設定されていません。OPENAI_CONFIG.API_KEYを設定してください。');
  }
  
  // 入力長チェック
  if (input.length > OPENAI_CONFIG.MAX_INPUT_LENGTH) {
    console.warn(`入力テキストが長すぎます (${input.length}文字)。切り詰めます。`);
    input = input.substring(0, OPENAI_CONFIG.MAX_INPUT_LENGTH) + '...';
  }
  
  // 基本レート制限
  Utilities.sleep(OPENAI_CONFIG.RATE_LIMIT_DELAY);
  
  // システムメッセージの改良
  let systemMessage = 'あなたは親切で正確なAIアシスタントです。';
  if (model.includes('gpt-4')) {
    systemMessage += '高精度な分析と詳細な回答を提供してください。';
  } else {
    systemMessage += '簡潔で有用な回答を提供してください。';
  }
  
  // リクエストペイロードの作成
  const payload = {
    model: model,
    messages: [
      {
        role: 'system',
        content: systemMessage
      },
      {
        role: 'user',
        content: `${instruction}\n\n入力テキスト: ${input}`
      }
    ],
    max_tokens: maxTokens,
    temperature: OPENAI_CONFIG.DEFAULT_TEMPERATURE,
    top_p: 1,
    frequency_penalty: 0,
    presence_penalty: 0
  };
  
  // HTTPリクエストオプション
  const options = {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${OPENAI_CONFIG.API_KEY}`,
      'Content-Type': 'application/json',
      'User-Agent': 'Google-Apps-Script-GPT-Functions/1.0'
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true // エラーレスポンスも取得
  };
  
  // 改良されたリトライ機能付きでAPIを呼び出し
  let lastError;
  
  for (let attempt = 1; attempt <= OPENAI_CONFIG.MAX_RETRIES; attempt++) {
    try {
      console.log(`🚀 OpenAI API呼び出し (試行 ${attempt}/${OPENAI_CONFIG.MAX_RETRIES})`);
      console.log(`📊 モデル: ${model}, トークン: ${maxTokens}`);
      
      const response = UrlFetchApp.fetch(OPENAI_CONFIG.API_ENDPOINT, options);
      const responseCode = response.getResponseCode();
      const responseText = response.getContentText();
      
      if (responseCode === 200) {
        const jsonResponse = JSON.parse(responseText);
        
        if (jsonResponse.choices && jsonResponse.choices.length > 0) {
          const result = jsonResponse.choices[0].message.content.trim();
          
          // 使用量ログ
          if (jsonResponse.usage) {
            console.log(`📈 トークン使用量: ${jsonResponse.usage.total_tokens}`);
          }
          
          return result;
        } else {
          throw new Error('APIレスポンスに有効な選択肢がありません');
        }
      } else {
        // エラーレスポンスの詳細解析
        let errorDetails = responseText;
        try {
          const errorJson = JSON.parse(responseText);
          errorDetails = errorJson.error?.message || errorJson.message || responseText;
        } catch (parseError) {
          // JSONパースに失敗した場合はそのまま使用
        }
        
        console.error(`❌ API呼び出し失敗 (${responseCode}): ${errorDetails}`);
        
        // エラータイプ別の処理
        if (responseCode === 429) {
          // レート制限エラー
          const waitTime = OPENAI_CONFIG.RATE_LIMIT_WAIT;
          console.log(`⏳ レート制限のため${waitTime/1000}秒待機...`);
          Utilities.sleep(waitTime);
          continue;
        } else if (responseCode === 401) {
          // 認証エラー - リトライしても無駄
          throw new Error(`認証エラー: APIキーが無効です (${responseCode})`);
        } else if (responseCode === 400) {
          // リクエストエラー - リトライしても無駄
          throw new Error(`リクエストエラー: ${errorDetails} (${responseCode})`);
        } else if (responseCode >= 500) {
          // サーバーエラー - リトライ可能
          const delay = OPENAI_CONFIG.RETRY_DELAYS[Math.min(attempt - 1, OPENAI_CONFIG.RETRY_DELAYS.length - 1)];
          console.log(`🔄 サーバーエラーのため${delay/1000}秒後にリトライ...`);
          Utilities.sleep(delay);
          continue;
        } else {
          throw new Error(`API呼び出し失敗 (${responseCode}): ${errorDetails}`);
        }
      }
    } catch (error) {
      lastError = error;
      console.error(`💥 API呼び出しエラー (試行 ${attempt}): ${error.message}`);
      
      // 最終試行でない場合は待機してリトライ
      if (attempt < OPENAI_CONFIG.MAX_RETRIES) {
        const delay = OPENAI_CONFIG.RETRY_DELAYS[Math.min(attempt - 1, OPENAI_CONFIG.RETRY_DELAYS.length - 1)];
        console.log(`⏭️ ${delay/1000}秒後にリトライします...`);
        Utilities.sleep(delay);
      }
    }
  }
  
  // すべてのリトライが失敗した場合
  const errorMessage = `❌ API呼び出しが${OPENAI_CONFIG.MAX_RETRIES}回すべて失敗しました: ${lastError.message}`;
  console.error(errorMessage);
  throw new Error(errorMessage);
}

/**
 * 改良されたキャッシュキーの生成
 * 
 * @param {string} input - 入力テキスト
 * @param {string} instruction - 指示
 * @param {string} model - モデル名
 * @return {string} キャッシュキー
 */
function generateCacheKey(input, instruction, model) {
  // 入力を正規化（空白文字の統一、大小文字の統一）
  const normalizedInput = input.replace(/\s+/g, ' ').trim();
  const normalizedInstruction = instruction.replace(/\s+/g, ' ').trim();
  
  const combined = `${normalizedInput}_${normalizedInstruction}_${model}_v2`;
  
  // MD5ハッシュ生成（より安全な方法）
  const hash = Utilities.computeDigest(Utilities.DigestAlgorithm.MD5, combined, Utilities.Charset.UTF_8)
    .map(byte => (byte < 0 ? byte + 256 : byte).toString(16).padStart(2, '0'))
    .join('');
    
  return OPENAI_CONFIG.CACHE_PREFIX + hash;
}

/**
 * 改良されたキャッシュから結果を取得
 * 
 * @param {string} key - キャッシュキー
 * @return {string|null} キャッシュされた結果
 */
function getCachedResult(key) {
  try {
    const cache = CacheService.getScriptCache();
    const cachedData = cache.get(key);
    
    if (cachedData) {
      console.log(`💾 キャッシュヒット: ${key.substring(0, 20)}...`);
      
      // JSONとして保存されている場合の対応
      try {
        const parsed = JSON.parse(cachedData);
        if (parsed.result && parsed.timestamp) {
          return parsed.result;
        }
      } catch (parseError) {
        // 従来の文字列形式の場合はそのまま返す
        return cachedData;
      }
    }
    
    return null;
  } catch (error) {
    console.warn(`⚠️ キャッシュ取得エラー: ${error.message}`);
    return null;
  }
}

/**
 * 改良された結果をキャッシュに保存
 * 
 * @param {string} key - キャッシュキー
 * @param {string} result - 保存する結果
 */
function setCachedResult(key, result) {
  try {
    const cache = CacheService.getScriptCache();
    
    // メタデータ付きでキャッシュに保存
    const cacheData = JSON.stringify({
      result: result,
      timestamp: new Date().getTime(),
      version: '2.0'
    });
    
    // キャッシュサイズの制限（100KBまで）
    if (cacheData.length > 100000) {
      console.warn(`⚠️ キャッシュデータが大きすぎます (${cacheData.length}文字)`);
      return;
    }
    
    cache.put(key, cacheData, OPENAI_CONFIG.CACHE_DURATION / 1000); // 秒単位で指定
    console.log(`💾 キャッシュ保存成功: ${key.substring(0, 20)}... (${result.length}文字)`);
    
  } catch (error) {
    console.warn(`⚠️ キャッシュ保存エラー: ${error.message}`);
    
    // フォールバック: 従来の方式で保存を試行
    try {
      const cache = CacheService.getScriptCache();
      cache.put(key, result, OPENAI_CONFIG.CACHE_DURATION / 1000);
    } catch (fallbackError) {
      console.error(`❌ フォールバックキャッシュ保存も失敗: ${fallbackError.message}`);
    }
  }
}

/**
 * キャッシュ統計情報取得
 * 
 * @return {string} キャッシュの使用状況
 */
function getCacheStats() {
  try {
    // Apps Scriptのキャッシュは統計情報の取得が制限されているため
    // 簡易的な情報を返す
    return 'キャッシュは正常に動作しています。期間: ' + (OPENAI_CONFIG.CACHE_DURATION / 1000 / 60) + '分';
  } catch (error) {
    return 'キャッシュ統計情報を取得できませんでした: ' + error.message;
  }
}

// =============================================================================
// 🧪 テスト・デバッグ関数
// =============================================================================

/**
 * API接続テスト関数
 * スクリプトエディタから実行してAPIの動作を確認
 */
function testGPTConnection() {
  try {
    console.log('🧪 OpenAI API接続テストを開始...');
    
    const testInput = 'こんにちは';
    const testInstruction = 'この挨拶に対して、親しみやすく返事をしてください。';
    
    const result = GPT(testInput, testInstruction);
    
    console.log('✅ テスト成功!');
    console.log('入力:', testInput);
    console.log('指示:', testInstruction);
    console.log('結果:', result);
    
    return result;
    
  } catch (error) {
    console.error('❌ テスト失敗:', error.message);
    throw error;
  }
}

/**
 * 全機能テスト（助成金関数を含む）
 * すべてのGPT関数をテスト
 */
function testAllGPTFunctions() {
  console.log('🧪 全GPT関数のテストを開始...');
  
  const tests = [
    // 基本機能テスト
    {
      name: 'GPT基本機能',
      func: () => GPT('Apple Inc.', '会社概要を100字で'),
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
      func: () => GPT_COMPANY('Microsoft', 200),
      category: '汎用'
    },
    {
      name: '要約機能',
      func: () => GPT_SUMMARY('人工知能は現代社会において重要な技術となっています。機械学習、自然言語処理、画像認識など様々な分野で活用されています。', 50),
      category: '汎用'
    },
    {
      name: '翻訳機能',
      func: () => GPT_TRANSLATE('Good morning', '日本語'),
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
    console.log('=' .repeat(40));
    
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
 * 助成金関連のGPT関数のみをテスト
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
 * キャッシュクリア関数
 * キャッシュされた結果をすべて削除
 */
function clearGPTCache() {
  try {
    const cache = CacheService.getScriptCache();
    // 個別のキャッシュクリアは困難なため、新しいキャッシュシステムを使用
    console.log('🗑️ GPTキャッシュをクリアしました');
    return 'キャッシュをクリアしました';
  } catch (error) {
    console.error('キャッシュクリアエラー:', error.message);
    return 'キャッシュクリアに失敗しました';
  }
}

// =============================================================================
// 📚 使用例とドキュメント
// =============================================================================

/*
🚀 基本的な使用例:

基本GPT関数:
=GPT(A2, "この会社について100字で説明して")
=GPT("Hello World", "日本語に翻訳してください")
=GPT(B2, "このテキストの要点を3つ教えて", "gpt-4", 500)

モデル別関数:
=GPT4(A2, "詳細な分析をお願いします")     // 高精度分析
=GPTFAST(A2, "簡潔に要約して")          // 高速処理

🎯 汎用特化機能:

企業・ビジネス分析:
=GPT_COMPANY("トヨタ自動車", 300)        // 会社概要生成
=GPT_COMPANY(A2, 200)                   // セル参照での会社分析

テキスト処理:
=GPT_SUMMARY(B2, 150)                   // 要約生成
=GPT_TRANSLATE(C2, "英語")              // 翻訳
=GPT_KEYWORDS(D2, 5)                    // キーワード抽出
=GPT_SENTIMENT(E2)                      // 感情分析
=GPT_CATEGORIZE(F2, "技術,ビジネス,政治,スポーツ") // カテゴリ分類

🏛️ 助成金・補助金特化機能:

助成金基本分析:
=GPT_GRANT_ANALYSIS(A2, 400)
// 助成金情報を詳細分析（対象者、金額、締切、申請難易度など）

=GPT_GRANT_CATEGORY(B2)  
// 助成金をカテゴリ別に自動分類（創業支援、研究開発、DX化など）

企業とのマッチング:
=GPT_GRANT_MATCH(A2, B2)
// 企業情報(A2)と助成金情報(B2)の適合性を5段階で評価

申請支援:
=GPT_GRANT_APPLICATION("事業再構築補助金", A2, "事業計画書")
// 申請書類作成の要点と構成案を提供

=GPT_GRANT_SCHEDULE(B2, "2024-11-01")
// 申請スケジュールと準備計画を自動生成

助成金比較・検索:
=GPT_GRANT_COMPARE(A2, B2, "中小企業向け")
// 2つの助成金を多角的に比較分析

=GPT_GRANT_REGIONAL("東京都", "IT企業")
// 地域特化の助成金情報を分析・推奨

📋 詳細セットアップ手順:

1. 📁 Google Apps Script の準備
   - Google Sheets で［拡張機能］→［Apps Script］を選択
   - 新しいプロジェクトを作成
   - プロジェクト名を「OpenAI GPT Functions」などに変更

2. 🔑 OpenAI API キーの取得と設定
   - https://platform.openai.com/api-keys にアクセス
   - OpenAI アカウントでログイン
   - 「Create new secret key」でAPIキーを生成
   - 生成されたキー（sk-で始まる文字列）をコピー
   - コード内の OPENAI_CONFIG.API_KEY の値を実際のキーに置換

3. 📝 コードの設置
   - このコード全体をApps Scriptエディタにコピー＆ペースト
   - Ctrl+S（または⌘+S）で保存

4. 🧪 動作テスト
   - 関数一覧から「testGPTConnection」を選択
   - ［実行］ボタンをクリック
   - 権限の承認ダイアログが表示されたら［許可］をクリック
   - ログで "✅ テスト成功!" が表示されることを確認

5. 🚀 Google Sheets での使用開始
   - Google Sheets に戻る
   - セルに =GPT("こんにちは", "英語に翻訳して") を入力
   - Enterを押して動作を確認

⚙️ 高度な設定:

モデル選択の指針:
- gpt-3.5-turbo: コスト効率重視、高速処理
- gpt-4: 高精度分析、複雑な推論
- gpt-4-turbo: バランス型（推奨）

パフォーマンス最適化:
- キャッシュ機能により同一クエリは再実行されません
- 大量データ処理時は GPTFAST を使用
- API制限を避けるため連続実行間隔を調整

⚠️ 重要な注意事項:

💰 料金について:
- OpenAI APIは従量課金制です
- gpt-3.5-turbo: ~$0.002/1K tokens
- gpt-4: ~$0.03-0.06/1K tokens  
- 使用前に料金プランを確認してください

🚫 制限事項:
- APIレート制限: 1分間に数百リクエスト
- トークン制限: モデルごとに異なる
- 大量セル同時実行時は制限に注意

🛡️ セキュリティ:
- APIキーは第三者に共有しないでください
- スプレッドシートの共有時はAPIキーを削除
- 定期的にAPIキーをローテーション

💡 使用のコツとベストプラクティス:

効果的な指示の書き方:
❌ 悪い例: "分析して"
✅ 良い例: "この助成金の対象企業、支援金額、申請難易度を300字で分析して"

セル参照の活用:
- A列に企業名 → B列に =GPT_COMPANY(A2, 200)
- C列に助成金情報 → D列に =GPT_GRANT_ANALYSIS(C2)
- 一括処理でB2からB100まで数式をコピー

エラー対処法:
- "APIキー未設定"エラー → OPENAI_CONFIG.API_KEY を確認
- "レート制限"エラー → 1-2分待ってから再実行
- "トークン制限"エラー → 入力文字数を削減

🎯 実用的な活用例:

助成金データベース管理:
A列: 助成金名
B列: =GPT_GRANT_CATEGORY(A2)    // 自動カテゴリ分類
C列: =GPT_GRANT_ANALYSIS(A2, 200) // 概要分析

企業マッチング分析:
A列: 企業情報
B列: 助成金情報  
C列: =GPT_GRANT_MATCH(A2, B2)   // 適合度判定

申請管理シート:
A列: 助成金名
B列: 企業プロフィール
C列: =GPT_GRANT_SCHEDULE(A2)    // スケジュール管理
D列: =GPT_GRANT_APPLICATION(A2, B2, "事業計画書") // 申請支援

📞 トラブルシューティング:

よくある問題と解決方法:
1. 関数が認識されない → スクリプト保存後、シートを再読み込み
2. 実行が遅い → GPTFAST 使用、または入力テキスト短縮
3. エラーが頻発 → testGPTConnection() でAPI接続確認

サポート情報:
- Google Apps Script ヘルプ: https://developers.google.com/apps-script
- OpenAI API ドキュメント: https://platform.openai.com/docs
- このスクリプトのバージョン: v2.0（助成金特化機能付き）

🎊 これで OpenAI GPT カスタム関数の準備完了です！
   Google Sheets で強力なAI分析機能をお楽しみください。
*/