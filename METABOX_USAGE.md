# 📋 助成金投稿メタボックス（シンプル版）

WordPress投稿編集画面で**カテゴリー・都道府県・市町村**を簡単に管理できます。

## 🎯 基本機能

WordPress投稿編集画面のサイドバーに以下の3つのメタボックスが表示されます：

### 1. 📂 助成金カテゴリー
- 複数のカテゴリーをチェックボックスで選択
- 新しいカテゴリーをその場で追加可能

### 2. 📍 対象都道府県  
- 「全国対象」で全て選択、または個別に選択
- 投稿数も表示されて分かりやすい

### 3. 🏛️ 対象市町村
- 検索機能で市町村を素早く見つけられる
- 新しい市町村をその場で追加可能

## 💡 使い方

1. **助成金投稿の編集画面**を開く
2. **サイドバーのメタボックス**で選択
3. **保存**すると自動でGoogle Sheetsと同期

## ⚡ 便利機能

- **新規追加**: カテゴリーや市町村をその場で作成
- **検索**: 市町村名で素早く絞り込み  
- **一括選択**: 全国対象の場合は全都道府県をワンクリック選択
- **自動同期**: 保存時にGoogle Sheetsと自動同期

## 🔗 Google Sheets連携

### 双方向同期
- **WordPress → Sheets**: 投稿保存時に自動でGoogle Sheetsに反映
- **Sheets → WordPress**: スプレッドシート変更時にWordPressに反映

### 同期メタデータ
各投稿には以下の同期情報が記録されます：
- `_gi_last_sync`: 最終同期日時
- `_gi_sync_source`: 同期元（wordpress_admin/google_sheets）
- `_gi_last_modified`: 最終変更日時

## 📱 レスポンシブ対応

モバイルデバイスでも使いやすいように最適化されています。
- タブレット・スマートフォンでの表示調整
- タッチ操作に対応したインターフェース

## 🎨 ユーザビリティ機能

### 視覚的フィードバック
- フィールド自動入力時の背景色変化
- 必須/推奨フィールドのガイダンス表示
- エラー時の明確な表示

### 地域制限に応じた表示制御
地域制限の設定に応じて、適切なガイダンスが表示されます。

```php
// 例：「市町村限定」を選択した場合
// → 「対象市町村の明記が必要です」のガイダンス表示
```

### フィールド変更の追跡
重要なフィールドが変更された場合、Google Sheets同期の提案が表示されます。

## 🔧 カスタマイズ

### メタボックスの位置変更
`add_meta_box()` の第5パラメータで位置を変更できます：

```php
// 例：サイドバー上部 → メインエリア上部に変更
add_meta_box(
    'grant-prefecture-metabox',
    '📍 対象都道府県',
    array($this, 'render_prefecture_metabox'),
    'grant',
    'normal',  // 'side' から 'normal' に変更
    'high'
);
```

### 追加フィールドの実装
新しいメタボックスを追加する場合：

```php
// 1. add_grant_metaboxes() メソッドに追加
add_meta_box(
    'grant-custom-metabox',
    '🆕 カスタムフィールド',
    array($this, 'render_custom_metabox'),
    'grant',
    'side',
    'high'
);

// 2. レンダリングメソッドを実装
public function render_custom_metabox($post) {
    // メタボックスのHTML
}

// 3. 保存処理を save_grant_metadata() に追加
$meta_fields['custom_field'] = sanitize_text_field($_POST['custom_field'] ?? '');
```

## 🚨 トラブルシューティング

### 自動同期が動作しない場合
1. Google Sheets同期設定を確認
2. WordPress ログでエラーを確認：`wp-content/debug.log`
3. 「この投稿を同期」ボタンで手動同期を実行

### フィールドが保存されない場合
1. ユーザー権限を確認（`edit_posts` 権限が必要）
2. Nonce 検証エラーがないか確認
3. PHP エラーログを確認

### スタイルが適用されない場合
1. CSS ファイルの読み込みを確認：`admin-metaboxes.css`
2. ブラウザキャッシュをクリア
3. WordPress キャッシュをクリア

## 📚 関連ファイル

### PHP ファイル
- `inc/admin/post-metaboxes.php`: メタボックスのメインクラス
- `inc/features/google-sheets-sync.php`: Google Sheets同期処理

### JavaScript/CSS ファイル
- `assets/js/grant-metaboxes.js`: メタボックス用JavaScript
- `assets/css/admin-metaboxes.css`: メタボックス用スタイル

### 設定ファイル
- `functions.php`: メタボックス機能の読み込み

## 🔄 更新履歴

### v1.0.0 (2024-09-26)
- 初回リリース
- 基本的なメタボックス機能
- Google Sheets双方向同期対応
- レスポンシブデザイン実装

---

このメタボックス機能により、Google Sheetsとの同期を保ちながら、WordPress管理画面でより直感的に助成金情報を管理できます。