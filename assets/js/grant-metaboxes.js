/**
 * 助成金投稿メタボックス用 JavaScript (WordPress標準タクソノミー版)
 * 
 * WordPress標準タクソノミー + ACFフィールドのハイブリッド管理
 * - カテゴリー・都道府県・市町村: WordPress標準タクソノミー
 * - その他のフィールド: ACF維持
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initGrantTaxonomyMetaboxes();
    });
    
    function initGrantTaxonomyMetaboxes() {
        // ==========
        // 1. タクソノミー関連機能
        // ==========
        
        // 都道府県：全国対象チェックボックス
        $('#select_all_prefectures').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.prefecture-checkbox').prop('checked', isChecked);
        });
        
        // 都道府県：個別チェックボックス変更時
        $('.prefecture-checkbox').on('change', function() {
            const totalPrefectures = $('.prefecture-checkbox').length;
            const checkedPrefectures = $('.prefecture-checkbox:checked').length;
            $('#select_all_prefectures').prop('checked', totalPrefectures === checkedPrefectures);
        });
        
        // 市町村：検索機能
        $('#municipality_search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('.municipality-option').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
        
        // カテゴリー追加
        $('#add_grant_category').on('click', function() {
            const categoryName = $('#new_grant_category').val().trim();
            if (categoryName) {
                addNewTaxonomyTerm('grant_category', categoryName, 'category');
            }
        });
        
        // 市町村追加
        $('#add_municipality').on('click', function() {
            const municipalityName = $('#new_municipality').val().trim();
            if (municipalityName) {
                addNewTaxonomyTerm('grant_municipality', municipalityName, 'municipality');
            }
        });
        
        // ==========
        // 2. ACFフィールド関連機能（既存機能維持）
        // ==========
        
        // 助成金額（数値）変更時の表示用金額自動生成
        $('#max_amount_numeric').on('change', function() {
            updateAmountDisplay($(this).val());
        });
        
        // 申請期限（日付）変更時の表示用期限自動生成
        $('#deadline_date').on('change', function() {
            updateDeadlineDisplay($(this).val());
        });
        
        // 組織タイプ変更時のガイダンス表示
        $('#organization_type').on('change', function() {
            showOrganizationGuidance($(this).val());
        });
        
        // フィールドの変更を検知してGoogle Sheets同期を提案
        trackFieldChanges();
        
        // 初期状態設定
        checkInitialSelections();
    }
    
    /**
     * 新しいタクソノミータームを追加
     */
    function addNewTaxonomyTerm(taxonomy, termName, type) {
        $.ajax({
            url: grantMetaboxes.ajaxurl,
            type: 'POST',
            data: {
                action: 'gi_add_taxonomy_term',
                taxonomy: taxonomy,
                term_name: termName,
                nonce: grantMetaboxes.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 新しいタームをリストに追加
                    const termId = response.data.term_id;
                    const termName = response.data.name;
                    
                    let targetContainer = '';
                    let inputName = '';
                    
                    if (type === 'category') {
                        targetContainer = '#grant-category-selection';
                        inputName = 'grant_categories[]';
                        $('#new_grant_category').val('');
                    } else if (type === 'municipality') {
                        targetContainer = '#grant-municipality-selection';
                        inputName = 'grant_municipalities[]';
                        $('#new_municipality').val('');
                    }
                    
                    const newOption = `
                        <label style="display: block; margin-bottom: 6px;" class="${type === 'municipality' ? 'municipality-option' : ''}">
                            <input type="checkbox" 
                                   name="${inputName}" 
                                   value="${termId}"
                                   checked>
                            ${termName}
                            <span style="color: #666;">（0件）</span>
                        </label>
                    `;
                    
                    // 追加ボタンの直前に挿入
                    $(targetContainer + ' > div:last-child').before(newOption);
                    
                    showNotice('success', `「${termName}」を追加しました。`);
                } else {
                    showNotice('error', `追加に失敗しました: ${response.data}`);
                }
            },
            error: function() {
                showNotice('error', '通信エラーが発生しました。');
            }
        });
    }
    
    /**
     * 初期選択状態をチェック
     */
    function checkInitialSelections() {
        // 都道府県の全選択状態をチェック
        const totalPrefectures = $('.prefecture-checkbox').length;
        const checkedPrefectures = $('.prefecture-checkbox:checked').length;
        $('#select_all_prefectures').prop('checked', totalPrefectures === checkedPrefectures && totalPrefectures > 0);
    }
    
    /**
     * 通知メッセージを表示
     */
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible" style="margin: 10px 0;">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">この通知を閉じる</span>
                </button>
            </div>
        `);
        
        $('#post').prepend(notice);
        
        // 自動で5秒後に消す
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
        
        // 閉じるボタン
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut();
        });
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
        
        // 主要フィールドの変更を追跡（タクソノミー + ACFフィールド）
        const importantFields = [
            'input[name="grant_categories[]"]',      // カテゴリータクソノミー
            'input[name="grant_prefectures[]"]',     // 都道府県タクソノミー
            'input[name="grant_municipalities[]"]',  // 市町村タクソノミー
            '#application_status',                   // ACFフィールド
            '#max_amount_numeric',                   // ACFフィールド
            '#organization',                         // ACFフィールド
            '#deadline_date'                         // ACFフィールド
        ];
        
        $(document).on('change', importantFields.join(', '), function() {
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