/**
 * 助成金投稿メタボックス用 JavaScript
 * 
 * Google Sheets連携対応の投稿編集画面機能
 * - 都道府県名の自動生成
 * - フィールド間の連動
 * - リアルタイム同期
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initGrantMetaboxes();
    });
    
    function initGrantMetaboxes() {
        // 都道府県コード変更時の都道府県名自動生成
        $('#target_prefecture').on('change', function() {
            updatePrefectureName($(this).val());
        });
        
        // 助成金額（数値）変更時の表示用金額自動生成
        $('#max_amount_numeric').on('change', function() {
            updateAmountDisplay($(this).val());
        });
        
        // 申請期限（日付）変更時の表示用期限自動生成
        $('#deadline_date').on('change', function() {
            updateDeadlineDisplay($(this).val());
        });
        
        // 地域制限の変更時に都道府県・市町村フィールドの表示制御
        $('#regional_limitation').on('change', function() {
            toggleLocationFields($(this).val());
        });
        
        // 組織タイプ変更時のガイダンス表示
        $('#organization_type').on('change', function() {
            showOrganizationGuidance($(this).val());
        });
        
        // 初期表示時の設定
        toggleLocationFields($('#regional_limitation').val());
        
        // フィールドの変更を検知してGoogle Sheets同期を提案
        trackFieldChanges();
    }
    
    /**
     * 都道府県名の自動生成
     */
    function updatePrefectureName(prefectureCode) {
        const prefectureNames = {
            '': '',
            'hokkaido': '北海道',
            'aomori': '青森県',
            'iwate': '岩手県',
            'miyagi': '宮城県',
            'akita': '秋田県',
            'yamagata': '山形県',
            'fukushima': '福島県',
            'ibaraki': '茨城県',
            'tochigi': '栃木県',
            'gunma': '群馬県',
            'saitama': '埼玉県',
            'chiba': '千葉県',
            'tokyo': '東京都',
            'kanagawa': '神奈川県',
            'niigata': '新潟県',
            'toyama': '富山県',
            'ishikawa': '石川県',
            'fukui': '福井県',
            'yamanashi': '山梨県',
            'nagano': '長野県',
            'gifu': '岐阜県',
            'shizuoka': '静岡県',
            'aichi': '愛知県',
            'mie': '三重県',
            'shiga': '滋賀県',
            'kyoto': '京都府',
            'osaka': '大阪府',
            'hyogo': '兵庫県',
            'nara': '奈良県',
            'wakayama': '和歌山県',
            'tottori': '鳥取県',
            'shimane': '島根県',
            'okayama': '岡山県',
            'hiroshima': '広島県',
            'yamaguchi': '山口県',
            'tokushima': '徳島県',
            'kagawa': '香川県',
            'ehime': '愛媛県',
            'kochi': '高知県',
            'fukuoka': '福岡県',
            'saga': '佐賀県',
            'nagasaki': '長崎県',
            'kumamoto': '熊本県',
            'oita': '大分県',
            'miyazaki': '宮崎県',
            'kagoshima': '鹿児島県',
            'okinawa': '沖縄県'
        };
        
        const prefectureName = prefectureNames[prefectureCode] || '';
        $('#prefecture_name').val(prefectureName);
        
        // 視覚的フィードバック
        if (prefectureName) {
            $('#prefecture_name').css('background-color', '#f0fff0');
            setTimeout(function() {
                $('#prefecture_name').css('background-color', '');
            }, 1000);
        }
    }
    
    /**
     * 助成金額表示用の自動生成
     */
    function updateAmountDisplay(numericAmount) {
        if (!numericAmount || numericAmount == 0) {
            $('#max_amount').attr('placeholder', '例：300万円');
            return;
        }
        
        const amount = parseInt(numericAmount);
        let displayAmount = '';
        
        if (amount >= 100000000) { // 1億円以上
            displayAmount = (amount / 100000000) + '億円';
        } else if (amount >= 10000) { // 1万円以上
            displayAmount = (amount / 10000) + '万円';
        } else {
            displayAmount = amount.toLocaleString() + '円';
        }
        
        // 既存値が空の場合のみ自動設定
        if (!$('#max_amount').val()) {
            $('#max_amount').val(displayAmount);
            
            // 視覚的フィードバック
            $('#max_amount').css('background-color', '#f0fff0');
            setTimeout(function() {
                $('#max_amount').css('background-color', '');
            }, 1000);
        } else {
            // プレースホルダーとして提案
            $('#max_amount').attr('placeholder', '提案: ' + displayAmount);
        }
    }
    
    /**
     * 申請期限表示用の自動生成
     */
    function updateDeadlineDisplay(dateValue) {
        if (!dateValue) {
            $('#deadline').attr('placeholder', '例：令和6年3月31日');
            return;
        }
        
        try {
            const date = new Date(dateValue);
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const day = date.getDate();
            
            // 令和年計算（2019年が令和1年）
            const reiwaYear = year - 2018;
            const displayDate = `令和${reiwaYear}年${month}月${day}日`;
            
            // 既存値が空の場合のみ自動設定
            if (!$('#deadline').val()) {
                $('#deadline').val(displayDate);
                
                // 視覚的フィードバック
                $('#deadline').css('background-color', '#f0fff0');
                setTimeout(function() {
                    $('#deadline').css('background-color', '');
                }, 1000);
            } else {
                // プレースホルダーとして提案
                $('#deadline').attr('placeholder', '提案: ' + displayDate);
            }
        } catch (e) {
            console.log('日付変換エラー:', e);
        }
    }
    
    /**
     * 地域制限に応じたフィールド表示制御
     */
    function toggleLocationFields(limitation) {
        const $prefectureBox = $('#grant-prefecture-metabox');
        const $municipalityBox = $('#grant-municipality-metabox');
        
        // 制限タイプに応じた表示制御
        switch (limitation) {
            case 'nationwide':
                showFieldGuidance($prefectureBox, '全国対象のため都道府県選択は任意です', 'info');
                showFieldGuidance($municipalityBox, '全国対象のため市町村指定は任意です', 'info');
                break;
                
            case 'prefecture_only':
                showFieldGuidance($prefectureBox, '都道府県内限定のため必須入力です', 'warning');
                showFieldGuidance($municipalityBox, '都道府県内全域対象のため市町村指定は任意です', 'info');
                break;
                
            case 'municipality_only':
                showFieldGuidance($prefectureBox, '市町村限定のため都道府県選択必須です', 'warning');
                showFieldGuidance($municipalityBox, '対象市町村の明記が必要です', 'warning');
                break;
                
            case 'region_group':
                showFieldGuidance($prefectureBox, '地域グループ限定です', 'info');
                showFieldGuidance($municipalityBox, '対象地域を詳しく記載してください', 'info');
                break;
                
            case 'specific_area':
                showFieldGuidance($prefectureBox, '特定地域限定です', 'info');
                showFieldGuidance($municipalityBox, '対象となる特定地域を明記してください', 'warning');
                break;
        }
    }
    
    /**
     * フィールドガイダンスの表示
     */
    function showFieldGuidance($metabox, message, type) {
        // 既存のガイダンスを削除
        $metabox.find('.field-guidance').remove();
        
        const typeClass = type === 'warning' ? 'notice-warning' : 'notice-info';
        const icon = type === 'warning' ? '⚠️' : 'ℹ️';
        
        const $guidance = $('<div class="field-guidance notice ' + typeClass + ' inline" style="margin: 10px 0; padding: 8px;"><p>' + icon + ' ' + message + '</p></div>');
        
        $metabox.find('.inside').prepend($guidance);
        
        // 3秒後にフェードアウト
        setTimeout(function() {
            $guidance.fadeOut();
        }, 3000);
    }
    
    /**
     * 組織タイプに応じたガイダンス表示
     */
    function showOrganizationGuidance(organizationType) {
        const $orgField = $('#organization');
        
        const suggestions = {
            'national': '例：経済産業省、厚生労働省、文部科学省',
            'prefecture': '例：東京都、大阪府、北海道',
            'city': '例：新宿区、大阪市、横浜市',
            'public_org': '例：日本政策金融公庫、中小企業基盤整備機構',
            'private_org': '例：○○協会、○○財団',
            'foundation': '例：○○財団、公益財団法人○○',
            'jgrants': '例：Jグランツ経由での申請',
            'other': '例：その他の実施組織'
        };
        
        const suggestion = suggestions[organizationType];
        if (suggestion) {
            $orgField.attr('placeholder', suggestion);
        }
    }
    
    /**
     * フィールド変更の追跡とGoogle Sheets同期提案
     */
    function trackFieldChanges() {
        let hasChanges = false;
        
        // 主要フィールドの変更を追跡
        const importantFields = [
            '#target_prefecture', '#target_municipality', 
            '#application_status', '#max_amount_numeric',
            '#organization', '#deadline_date'
        ];
        
        $(importantFields.join(', ')).on('change', function() {
            if (!hasChanges) {
                hasChanges = true;
                showSyncReminder();
            }
        });
    }
    
    /**
     * 同期リマインダーの表示
     */
    function showSyncReminder() {
        // 既存のリマインダーがある場合は削除
        $('#sync-reminder').remove();
        
        const $reminder = $(`
            <div id="sync-reminder" class="notice notice-info" style="margin: 15px 0; padding: 10px;">
                <p>
                    <strong>🔄 Google Sheets同期</strong><br>
                    重要なフィールドが変更されました。投稿を保存した後、Google Sheetsと同期することをお勧めします。
                </p>
                <button type="button" class="button button-secondary button-small" onclick="dismissSyncReminder()">
                    理解しました
                </button>
            </div>
        `);
        
        // 同期情報メタボックスの上に表示
        $('#grant-sync-info-metabox').before($reminder);
    }
    
    // グローバル関数として同期リマインダー非表示を定義
    window.dismissSyncReminder = function() {
        $('#sync-reminder').fadeOut();
    };
    
    /**
     * エラーハンドリング付きのAJAX関数
     */
    function safeAjaxCall(data, successCallback, errorCallback) {
        $.ajax({
            url: grantMetaboxes.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (successCallback) successCallback(response.data);
                } else {
                    if (errorCallback) errorCallback(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                if (errorCallback) errorCallback('通信エラーが発生しました: ' + error);
            }
        });
    }
    
    /**
     * フィールドバリデーション
     */
    function validateFields() {
        let isValid = true;
        const errors = [];
        
        // 必須フィールドのチェック
        if (!$('#organization').val().trim()) {
            errors.push('実施組織名は必須です');
            isValid = false;
        }
        
        // 数値フィールドのチェック
        const maxAmount = $('#max_amount_numeric').val();
        if (maxAmount && (isNaN(maxAmount) || parseInt(maxAmount) < 0)) {
            errors.push('最大助成額（数値）は正の数値で入力してください');
            isValid = false;
        }
        
        // URLフィールドのチェック
        const officialUrl = $('#official_url').val();
        if (officialUrl && !isValidUrl(officialUrl)) {
            errors.push('公式URLが正しい形式ではありません');
            isValid = false;
        }
        
        // エラー表示
        if (!isValid) {
            showValidationErrors(errors);
        }
        
        return isValid;
    }
    
    /**
     * URL形式チェック
     */
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    /**
     * バリデーションエラーの表示
     */
    function showValidationErrors(errors) {
        $('#validation-errors').remove();
        
        const $errorDiv = $('<div id="validation-errors" class="notice notice-error"><ul></ul></div>');
        
        errors.forEach(function(error) {
            $errorDiv.find('ul').append('<li>' + error + '</li>');
        });
        
        $('#post').prepend($errorDiv);
        
        // トップまでスクロール
        $('html, body').animate({scrollTop: 0}, 500);
    }
    
    // フォーム送信前のバリデーション
    $('#post').on('submit', function(e) {
        if (!validateFields()) {
            e.preventDefault();
            return false;
        }
    });
    
})(jQuery);