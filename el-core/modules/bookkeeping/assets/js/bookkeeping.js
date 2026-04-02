/**
 * ELS Bookkeeping Module — bookkeeping.js
 *
 * Handles all admin-side interactions for the Bookkeeping module.
 * Uses elBookkeeping.ajaxUrl / elBookkeeping.nonce (localized by PHP).
 */

/* global elBookkeeping, jQuery */
(function ($) {
    'use strict';

    if (typeof elBookkeeping === 'undefined') return;

    const ajax = elBookkeeping.ajaxUrl;
    const nonce = elBookkeeping.nonce;

    // ── Utility ────────────────────────────────────────────────────────────────

    function elBkAjax(action, data, onSuccess, onError) {
        $.post(ajax, Object.assign({ action: 'el_core_action', el_action: action, nonce }, data), function (res) {
            if (res && res.success) {
                if (onSuccess) onSuccess(res.data);
            } else {
                const msg = (res && res.data && res.data.message) ? res.data.message : 'An error occurred.';
                if (onError) onError(msg); else alert(msg);
            }
        }).fail(function () {
            const msg = 'Request failed. Please try again.';
            if (onError) onError(msg); else alert(msg);
        });
    }

    // ── Inline Transaction Editing ─────────────────────────────────────────────

    $(document).on('change', '.el-bk-inline-select', function () {
        const $el = $(this);
        elBkAjax('bk_update_transaction', {
            id: $el.data('id'),
            field: $el.data('field'),
            value: $el.val(),
        }, function () {
            // Visual confirmation — briefly highlight row
            $el.closest('tr').css('outline', '2px solid #22c55e').delay(800).queue(function () {
                $(this).css('outline', '').dequeue();
            });
        });
    });

    $(document).on('blur', '.el-bk-inline-input', function () {
        const $el = $(this);
        elBkAjax('bk_update_transaction', {
            id: $el.data('id'),
            field: $el.data('field'),
            value: $el.val(),
        });
    });

    // ── Bulk Confirm ───────────────────────────────────────────────────────────

    $(document).on('click', '.el-bk-confirm-all-btn', function () {
        const scope = $(this).data('scope');
        const label = scope === 'travel' ? 'travel suggestions' : 'all suggestions';
        if (!confirm('Confirm ' + label + '?')) return;
        elBkAjax('bk_bulk_confirm', { scope, tax_year: elBookkeeping.taxYear }, function (data) {
            alert(data.message || 'Done.');
            location.reload();
        });
    });

    // ── Rules: Add/Edit Form Toggle ────────────────────────────────────────────

    $('#el-bk-add-rule-btn').on('click', function () {
        $('#el-bk-rule-id').val('');
        $('#el-bk-rule-keyword').val('');
        $('#el-bk-rule-match-type').val('contains');
        $('#el-bk-rule-category').val('');
        $('#el-bk-rule-form').slideDown();
    });

    $(document).on('click', '.el-bk-edit-rule-btn', function () {
        const $btn = $(this);
        $('#el-bk-rule-id').val($btn.data('id'));
        $('#el-bk-rule-keyword').val($btn.data('keyword'));
        $('#el-bk-rule-match-type').val($btn.data('matchType') || $btn.data('match-type'));
        $('#el-bk-rule-category').val($btn.data('category'));
        $('#el-bk-rule-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-rule-form').offset().top - 60 }, 200);
    });

    $('#el-bk-cancel-rule-btn').on('click', function () {
        $('#el-bk-rule-form').slideUp();
    });

    $('#el-bk-save-rule-btn').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        elBkAjax('bk_save_rule', {
            id:         $('#el-bk-rule-id').val(),
            keyword:    $('#el-bk-rule-keyword').val(),
            match_type: $('#el-bk-rule-match-type').val(),
            category:   $('#el-bk-rule-category').val(),
        }, function () {
            location.reload();
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Save Rule');
        });
    });

    $(document).on('click', '.el-bk-delete-rule-btn', function () {
        if (!confirm('Delete this rule?')) return;
        const id = $(this).data('id');
        elBkAjax('bk_delete_rule', { id }, function () {
            location.reload();
        });
    });

    // ── Travel Periods: Add/Edit Form Toggle ───────────────────────────────────

    $('#el-bk-add-period-btn').on('click', function () {
        $('#el-bk-period-id').val('');
        $('#el-bk-period-label').val('');
        $('#el-bk-period-start').val('');
        $('#el-bk-period-end').val('');
        $('#el-bk-period-purpose').val('');
        $('#el-bk-period-form').slideDown();
    });

    $(document).on('click', '.el-bk-edit-period-btn', function () {
        const $btn = $(this);
        $('#el-bk-period-id').val($btn.data('id'));
        $('#el-bk-period-label').val($btn.data('label'));
        $('#el-bk-period-start').val($btn.data('start'));
        $('#el-bk-period-end').val($btn.data('end'));
        $('#el-bk-period-purpose').val($btn.data('purpose'));
        $('#el-bk-period-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-period-form').offset().top - 60 }, 200);
    });

    $('#el-bk-cancel-period-btn').on('click', function () {
        $('#el-bk-period-form').slideUp();
    });

    $('#el-bk-save-period-btn').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        elBkAjax('bk_save_travel_period', {
            id:         $('#el-bk-period-id').val(),
            label:      $('#el-bk-period-label').val(),
            start_date: $('#el-bk-period-start').val(),
            end_date:   $('#el-bk-period-end').val(),
            purpose:    $('#el-bk-period-purpose').val(),
        }, function () {
            location.reload();
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Save Period');
        });
    });

    $(document).on('click', '.el-bk-delete-period-btn', function () {
        if (!confirm('Delete this travel period? Transactions tagged to it will be untagged.')) return;
        elBkAjax('bk_delete_travel_period', { id: $(this).data('id') }, function () {
            location.reload();
        });
    });

    // ── Re-Apply Travel Rules ──────────────────────────────────────────────────

    $(document).on('click', '.el-bk-reapply-travel-btn', function () {
        if (!confirm('Re-apply travel rules to all unclassified transactions for this tax year?')) return;
        const $btn = $(this).prop('disabled', true).text('Processing…');
        elBkAjax('bk_reapply_travel_rules', { tax_year: $(this).data('taxYear') || elBookkeeping.taxYear }, function (data) {
            alert(data.message || 'Done.');
            $btn.prop('disabled', false).text('Re-Apply Travel Rules');
            location.reload();
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Re-Apply Travel Rules');
        });
    });

    // ── Contractors: Add/Edit Form Toggle ──────────────────────────────────────

    $('#el-bk-add-contractor-btn').on('click', function () {
        $('#el-bk-contractor-id').val('');
        $('#el-bk-contractor-name').val('');
        $('#el-bk-contractor-email').val('');
        $('#el-bk-contractor-address').val('');
        $('#el-bk-contractor-form').slideDown();
    });

    $(document).on('click', '.el-bk-edit-contractor-btn', function () {
        const $btn = $(this);
        $('#el-bk-contractor-id').val($btn.data('id'));
        $('#el-bk-contractor-name').val($btn.data('name'));
        $('#el-bk-contractor-email').val($btn.data('email'));
        $('#el-bk-contractor-address').val($btn.data('address'));
        $('#el-bk-contractor-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-contractor-form').offset().top - 60 }, 200);
    });

    $('#el-bk-cancel-contractor-btn').on('click', function () {
        $('#el-bk-contractor-form').slideUp();
    });

    $('#el-bk-save-contractor-btn').on('click', function () {
        const $btn = $(this).prop('disabled', true).text('Saving…');
        elBkAjax('bk_save_contractor', {
            id:      $('#el-bk-contractor-id').val(),
            name:    $('#el-bk-contractor-name').val(),
            email:   $('#el-bk-contractor-email').val(),
            address: $('#el-bk-contractor-address').val(),
        }, function () {
            location.reload();
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Save Contractor');
        });
    });

    $(document).on('click', '.el-bk-delete-contractor-btn', function () {
        if (!confirm('Delete this contractor?')) return;
        elBkAjax('bk_delete_contractor', { id: $(this).data('id') }, function () {
            location.reload();
        });
    });

    $(document).on('change', '.el-bk-assign-contractor', function () {
        const $el = $(this);
        elBkAjax('bk_assign_contractor', {
            transaction_id: $el.data('transactionId') || $el.data('transaction-id'),
            contractor_id:  $el.val(),
        }, function () {
            $el.closest('tr').css('outline', '2px solid #22c55e').delay(800).queue(function () {
                $(this).css('outline', '').dequeue();
            });
        });
    });

    // ── Receipt Actions ────────────────────────────────────────────────────────

    $('#el-bk-receipt-browse-btn').on('click', function () {
        $('#el-bk-receipt-file-input').trigger('click');
    });

    $('#el-bk-receipt-upload-zone').on('dragover', function (e) {
        e.preventDefault();
        $(this).css('background', '#eff6ff');
    }).on('dragleave drop', function (e) {
        e.preventDefault();
        $(this).css('background', '');
        if (e.type === 'drop') {
            // Phase 6: handle dropped files
        }
    });

    $(document).on('click', '.el-bk-detach-receipt-btn', function () {
        if (!confirm('Detach this receipt from its transaction?')) return;
        elBkAjax('bk_detach_receipt', { receipt_id: $(this).data('receiptId') || $(this).data('receipt-id') }, function () {
            location.reload();
        });
    });

    $(document).on('click', '.el-bk-delete-receipt-btn', function () {
        if (!confirm('Permanently delete this receipt?')) return;
        elBkAjax('bk_delete_receipt', { receipt_id: $(this).data('receiptId') || $(this).data('receipt-id') }, function () {
            location.reload();
        });
    });

    // ── AI Chat (Known Expenses) ───────────────────────────────────────────────

    $('#el-bk-chat-send-btn').on('click', function () {
        const $btn    = $(this).prop('disabled', true).text('Processing…');
        const message = $('#el-bk-chat-input').val().trim();
        if (!message) {
            $btn.prop('disabled', false).text('Process Rules');
            return;
        }

        const $log = $('#el-bk-chat-log');
        $log.find('.el-bk-chat-placeholder').hide();
        $log.append('<div class="el-bk-chat-message-user"><strong>You:</strong> ' + $('<span>').text(message).html() + '</div>');
        $('#el-bk-chat-input').val('');

        elBkAjax('bk_process_rules', { message }, function (data) {
            $log.append('<div class="el-bk-chat-message-ai"><strong>Assistant:</strong> ' + $('<span>').text(data.reply || 'Done.').html() + '</div>');
            $log.scrollTop($log[0].scrollHeight);
            $btn.prop('disabled', false).text('Process Rules');
            if (data.rules_saved) location.reload();
        }, function (msg) {
            $log.append('<div class="el-bk-chat-message-ai" style="color:red;"><strong>Error:</strong> ' + $('<span>').text(msg).html() + '</div>');
            $btn.prop('disabled', false).text('Process Rules');
        });
    });

    $('#el-bk-chat-input').on('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            $('#el-bk-chat-send-btn').trigger('click');
        }
    });

    // ── CSV Rules Import ──────────────────────────────────────────────────────

    let csvRulesFile = null;

    $('#el-bk-csv-rules-file').on('change', function () {
        csvRulesFile = this.files[0] || null;
        $('#el-bk-csv-rules-upload-btn').prop('disabled', !csvRulesFile);
    });

    $('#el-bk-csv-rules-upload-btn').on('click', function () {
        if (!csvRulesFile) return;
        const $btn = $(this).prop('disabled', true).text('Reading…');
        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);

        $.ajax({
            url: ajax,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Upload & Preview');
                if (res.success && res.data && res.data.data && res.data.data.step === 'map_columns') {
                    const cols = res.data.data.columns;
                    const $merchant = $('#el-bk-csv-merchant-col').empty();
                    const $category = $('#el-bk-csv-category-col').empty();
                    cols.forEach(function (c) {
                        $merchant.append($('<option>').val(c).text(c));
                        $category.append($('<option>').val(c).text(c));
                    });
                    autoSelectColumn($merchant, ['merchant', 'description', 'vendor', 'payee', 'name']);
                    autoSelectColumn($category, ['category', 'type', 'classification', 'class']);
                    $('#el-bk-csv-rules-mapping').slideDown();
                } else {
                    alert((res.data && res.data.message) || 'Unexpected response.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Upload & Preview');
                alert('Upload failed. Please try again.');
            }
        });
    });

    function autoSelectColumn($select, hints) {
        $select.find('option').each(function () {
            const val = $(this).val().toLowerCase();
            for (const h of hints) {
                if (val.indexOf(h) !== -1) {
                    $select.val($(this).val());
                    return false;
                }
            }
        });
    }

    $('#el-bk-csv-rules-import-btn').on('click', function () {
        if (!csvRulesFile) { alert('No file selected.'); return; }
        const $btn = $(this).prop('disabled', true).text('Importing…');
        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);
        fd.append('merchant_col', $('#el-bk-csv-merchant-col').val());
        fd.append('category_col', $('#el-bk-csv-category-col').val());

        $.ajax({
            url: ajax,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Import Rules');
                if (res.success) {
                    const msg = res.data.message || 'Import complete.';
                    $('#el-bk-csv-rules-mapping').slideUp();
                    $('#el-bk-csv-rules-result').html('<p style="color:#16a34a;font-weight:600;">' + $('<span>').text(msg).html() + '</p>').slideDown();
                    if (res.data.data && res.data.data.rules_saved > 0) {
                        setTimeout(function () { location.reload(); }, 1500);
                    }
                } else {
                    alert((res.data && res.data.message) || 'Import failed.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Import Rules');
                alert('Import failed. Please try again.');
            }
        });
    });

    $('#el-bk-csv-rules-cancel-btn').on('click', function () {
        $('#el-bk-csv-rules-mapping').slideUp();
        csvRulesFile = null;
        $('#el-bk-csv-rules-file').val('');
        $('#el-bk-csv-rules-upload-btn').prop('disabled', true);
    });

    // ── P&L Presets ────────────────────────────────────────────────────────────

    $(document).on('click', '.el-bk-preset-btn', function () {
        const preset   = $(this).data('preset');
        const year     = elBookkeeping.taxYear || new Date().getFullYear();
        const yearN    = parseInt(year, 10);
        let from, to;

        switch (preset) {
            case 'this-year': from = year + '-01-01'; to = year + '-12-31'; break;
            case 'last-year': from = (yearN - 1) + '-01-01'; to = (yearN - 1) + '-12-31'; break;
            case 'q1': from = year + '-01-01'; to = year + '-03-31'; break;
            case 'q2': from = year + '-04-01'; to = year + '-06-30'; break;
            case 'q3': from = year + '-07-01'; to = year + '-09-30'; break;
            case 'q4': from = year + '-10-01'; to = year + '-12-31'; break;
        }

        if (from) $('#el-bk-pl-from').val(from);
        if (to)   $('#el-bk-pl-to').val(to);
    });

    $(document).on('click', '.el-bk-generate-pl-btn', function () {
        // Phase 7
        alert('P&L report generation will be available in Phase 7.');
    });

}(jQuery));
