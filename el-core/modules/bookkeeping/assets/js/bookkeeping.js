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
        }).fail(function (xhr) {
            var msg = 'Request failed. Please try again.';
            try {
                var res = JSON.parse(xhr.responseText);
                if (res && res.data && res.data.message) msg = res.data.message;
            } catch (e) { /* ignore parse errors */ }
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
            var d = data.data || data;
            $log.append('<div class="el-bk-chat-message-ai"><strong>Assistant:</strong> ' + $('<span>').text(d.reply || data.message || 'Done.').html() + '</div>');
            $log.scrollTop($log[0].scrollHeight);
            $btn.prop('disabled', false).text('Process Rules');
            if (d.rules_saved) location.reload();
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

    // ── CSV Rules Import (3-step: columns → categories → import) ────────────

    let csvRulesFile = null;

    $('#el-bk-csv-rules-file').on('change', function () {
        csvRulesFile = this.files[0] || null;
        $('#el-bk-csv-rules-upload-btn').prop('disabled', !csvRulesFile);
    });

    // Step 1: Upload file → get column headers
    $('#el-bk-csv-rules-upload-btn').on('click', function () {
        if (!csvRulesFile) return;
        const $btn = $(this).prop('disabled', true).text('Reading…');
        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
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
                    $('#el-bk-csv-rules-catmap').slideUp();
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

    // Step 2: Columns selected → get unique CSV categories for mapping
    $('#el-bk-csv-rules-next-btn').on('click', function () {
        if (!csvRulesFile) { alert('No file selected.'); return; }
        const $btn = $(this).prop('disabled', true).text('Reading…');
        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);
        fd.append('merchant_col', $('#el-bk-csv-merchant-col').val());
        fd.append('category_col', $('#el-bk-csv-category-col').val());

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Next: Map Categories →');
                if (res.success && res.data && res.data.data && res.data.data.step === 'map_categories') {
                    const csvCats = res.data.data.csv_categories;
                    const validCats = res.data.data.valid_categories;
                    const $tbody = $('#el-bk-csv-catmap-body').empty();

                    csvCats.forEach(function (csvCat) {
                        const $select = $('<select class="el-select el-bk-catmap-select">');
                        $select.append($('<option>').val('__skip__').text('— Skip —'));
                        validCats.forEach(function (vc) {
                            const $opt = $('<option>').val(vc).text(vc);
                            if (vc.toLowerCase() === csvCat.toLowerCase()) $opt.prop('selected', true);
                            $select.append($opt);
                        });
                        // Fuzzy match: if no exact match, try partial
                        if ($select.val() === '__skip__') {
                            const csvLower = csvCat.toLowerCase();
                            validCats.forEach(function (vc) {
                                if (vc.toLowerCase().indexOf(csvLower) !== -1 || csvLower.indexOf(vc.toLowerCase()) !== -1) {
                                    $select.val(vc);
                                }
                            });
                        }
                        const $row = $('<tr>');
                        $row.append($('<td>').text(csvCat).css('font-weight', '600'));
                        $row.append($('<td>').append($select));
                        $select.attr('data-csv-cat', csvCat);
                        $tbody.append($row);
                    });

                    $('#el-bk-csv-rules-mapping').slideUp();
                    $('#el-bk-csv-rules-catmap').slideDown();
                } else {
                    alert((res.data && res.data.message) || 'Unexpected response.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Next: Map Categories →');
                alert('Request failed. Please try again.');
            }
        });
    });

    // Step 3: Category map done → import rules
    $('#el-bk-csv-rules-import-btn').on('click', function () {
        if (!csvRulesFile) { alert('No file selected.'); return; }
        const $btn = $(this).prop('disabled', true).text('Importing…');

        // Build category map from the dropdowns
        const categoryMap = {};
        $('#el-bk-csv-catmap-body .el-bk-catmap-select').each(function () {
            categoryMap[$(this).attr('data-csv-cat')] = $(this).val();
        });

        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);
        fd.append('merchant_col', $('#el-bk-csv-merchant-col').val());
        fd.append('category_col', $('#el-bk-csv-category-col').val());
        fd.append('category_map', JSON.stringify(categoryMap));

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Import Rules');
                if (res.success) {
                    const msg = res.data.message || 'Import complete.';
                    $('#el-bk-csv-rules-catmap').slideUp();
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
        $('#el-bk-csv-rules-catmap').slideUp();
        csvRulesFile = null;
        $('#el-bk-csv-rules-file').val('');
        $('#el-bk-csv-rules-upload-btn').prop('disabled', true);
    });

    $('#el-bk-csv-rules-back-btn').on('click', function () {
        $('#el-bk-csv-rules-catmap').slideUp();
        $('#el-bk-csv-rules-mapping').slideDown();
    });

    // ── CSV Transaction Upload Modal ──────────────────────────────────────────

    let csvTxnFile = null;
    let csvTxnType = 'expense';

    $(document).on('click', '.el-bk-upload-csv-btn', function () {
        csvTxnType = $(this).data('type') || 'expense';
        const label = csvTxnType === 'income' ? 'Upload Income CSV' : 'Upload Expense CSV';
        $('#el-bk-csv-modal-title').text(label);
        $('#el-bk-csv-step1').show();
        $('#el-bk-csv-step2').hide();
        $('#el-bk-csv-result').hide();
        $('#el-bk-csv-txn-file').val('');
        $('#el-bk-csv-bank-input').val('');
        csvTxnFile = null;
        $('#el-bk-csv-txn-upload-btn').prop('disabled', true);
        $('#el-bk-csv-upload-modal').fadeIn(150);
    });

    $(document).on('click', '.el-bk-csv-modal-close, .el-bk-modal-backdrop', function () {
        $('#el-bk-csv-upload-modal').fadeOut(150);
    });

    $('#el-bk-csv-txn-file').on('change', function () {
        csvTxnFile = this.files[0] || null;
        $('#el-bk-csv-txn-upload-btn').prop('disabled', !csvTxnFile);
    });

    // Step 1: Upload → get columns + bank accounts
    $('#el-bk-csv-txn-upload-btn').on('click', function () {
        if (!csvTxnFile) return;
        const bank = $('#el-bk-csv-bank-input').val().trim();
        if (!bank) { alert('Please enter a bank account name.'); return; }
        const $btn = $(this).prop('disabled', true).text('Reading…');

        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvTxnFile);
        fd.append('type', csvTxnType);
        fd.append('bank_account', bank);
        fd.append('tax_year', elBookkeeping.taxYear);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Upload & Map Columns');
                if (res.success && res.data && res.data.data && res.data.data.step === 'map_columns') {
                    const d = res.data.data;
                    const cols = d.columns;

                    // Populate bank account datalist for future use
                    const $list = $('#el-bk-csv-bank-list').empty();
                    (d.accounts || []).forEach(function (a) {
                        $list.append($('<option>').val(a));
                    });

                    const $date = $('#el-bk-csv-date-col').empty();
                    const $amt = $('#el-bk-csv-amount-col').empty();
                    const $merch = $('#el-bk-csv-merchant-txn-col').empty();
                    cols.forEach(function (c) {
                        $date.append($('<option>').val(c).text(c));
                        $amt.append($('<option>').val(c).text(c));
                        $merch.append($('<option>').val(c).text(c));
                    });
                    autoSelectColumn($date, ['date', 'posted', 'transaction date']);
                    autoSelectColumn($amt, ['amount', 'debit', 'credit', 'total']);
                    autoSelectColumn($merch, ['description', 'merchant', 'payee', 'memo', 'name']);

                    $('#el-bk-csv-step1').slideUp();
                    $('#el-bk-csv-step2').slideDown();
                } else {
                    alert((res.data && res.data.message) || 'Unexpected response.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Upload & Map Columns');
                alert('Upload failed. Please try again.');
            }
        });
    });

    // Step 2: Import with mapped columns
    $('#el-bk-csv-txn-import-btn').on('click', function () {
        if (!csvTxnFile) { alert('No file selected.'); return; }
        const $btn = $(this).prop('disabled', true).text('Importing…');

        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvTxnFile);
        fd.append('type', csvTxnType);
        fd.append('bank_account', $('#el-bk-csv-bank-input').val().trim());
        fd.append('tax_year', elBookkeeping.taxYear);
        fd.append('date_col', $('#el-bk-csv-date-col').val());
        fd.append('amount_col', $('#el-bk-csv-amount-col').val());
        fd.append('merchant_col', $('#el-bk-csv-merchant-txn-col').val());

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Import Transactions');
                if (res.success) {
                    const d = res.data.data || {};
                    const msg = res.data.message || 'Import complete.';
                    $('#el-bk-csv-step2').slideUp();
                    $('#el-bk-csv-result').html(
                        '<p style="color:#16a34a;font-weight:600;">' + $('<span>').text(msg).html() + '</p>' +
                        '<p>' + d.imported + ' imported, ' + d.classified + ' auto-classified, ' + d.skipped + ' skipped.</p>'
                    ).slideDown();
                    if (d.imported > 0) {
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                } else {
                    alert((res.data && res.data.message) || 'Import failed.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Import Transactions');
                alert('Import failed. Please try again.');
            }
        });
    });

    // ── Ledger Tab Import (Single Category CSV) ─────────────────────────────

    let ledgerFile = null;

    $('#el-bk-import-ledger-btn').on('click', function () {
        $('#el-bk-ledger-step1').show();
        $('#el-bk-ledger-step2').hide();
        $('#el-bk-ledger-status').empty();
        $('#el-bk-ledger-result').empty();
        $('#el-bk-ledger-file').val('');
        ledgerFile = null;
        $('#el-bk-ledger-modal').fadeIn(150);
    });

    $(document).on('click', '.el-bk-ledger-cancel, #el-bk-ledger-modal .el-bk-modal-backdrop', function () {
        $('#el-bk-ledger-modal').fadeOut(150);
    });

    $('#el-bk-ledger-file').on('change', function () {
        ledgerFile = this.files[0] || null;
    });

    // Step 1: Upload file → get column headers + categories
    $('#el-bk-ledger-upload-btn').on('click', function () {
        if (!ledgerFile) { alert('Please select a CSV file.'); return; }
        var $btn = $(this).prop('disabled', true).text('Reading…');
        var $status = $('#el-bk-ledger-status').html('<em>Uploading…</em>');

        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_ledger');
        fd.append('nonce', nonce);
        fd.append('csv_file', ledgerFile);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Upload & Detect Columns');
                $status.empty();
                if (res.success && res.data && res.data.data && res.data.data.step === 'map_columns') {
                    var d = res.data.data;
                    var cols = d.columns;

                    // Populate category dropdown
                    var $cat = $('#el-bk-ledger-category').empty();
                    (d.categories || []).forEach(function (c) {
                        $cat.append($('<option>').val(c).text(c));
                    });

                    // Populate column dropdowns
                    var $date = $('#el-bk-ledger-date-col').empty();
                    var $merch = $('#el-bk-ledger-merchant-col').empty();
                    var $amt = $('#el-bk-ledger-amount-col').empty();
                    cols.forEach(function (c) {
                        $date.append($('<option>').val(c).text(c));
                        $merch.append($('<option>').val(c).text(c));
                        $amt.append($('<option>').val(c).text(c));
                    });
                    autoSelectColumn($date, ['date', 'posted', 'transaction date']);
                    autoSelectColumn($merch, ['description', 'merchant', 'payee', 'memo', 'name']);
                    autoSelectColumn($amt, ['amount', 'debit', 'credit', 'total']);

                    // Populate bank account datalist
                    var $list = $('#el-bk-ledger-banks').empty();
                    (d.accounts || []).forEach(function (a) {
                        $list.append($('<option>').val(a));
                    });

                    $('#el-bk-ledger-step1').slideUp();
                    $('#el-bk-ledger-step2').slideDown();
                } else {
                    alert((res.data && res.data.message) || 'Unexpected response.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Upload & Detect Columns');
                $status.html('<span style="color:red;">Upload failed. Please try again.</span>');
            }
        });
    });

    // Step 2: Import with mapped columns + selected category
    $('#el-bk-ledger-import-btn').on('click', function () {
        if (!ledgerFile) { alert('No file selected.'); return; }
        var category = $('#el-bk-ledger-category').val();
        if (!category) { alert('Please select a category.'); return; }

        var $btn = $(this).prop('disabled', true).text('Importing…');
        var $result = $('#el-bk-ledger-result').html('<em>Importing transactions…</em>');

        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_ledger');
        fd.append('nonce', nonce);
        fd.append('csv_file', ledgerFile);
        fd.append('category', category);
        fd.append('date_col', $('#el-bk-ledger-date-col').val());
        fd.append('merchant_col', $('#el-bk-ledger-merchant-col').val());
        fd.append('amount_col', $('#el-bk-ledger-amount-col').val());
        fd.append('bank_account', $('#el-bk-ledger-bank').val().trim());

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Import Transactions');
                if (res.success) {
                    var d = res.data.data || {};
                    var msg = res.data.message || 'Import complete.';
                    $result.html(
                        '<p style="color:#16a34a;font-weight:600;">' + $('<span>').text(msg).html() + '</p>' +
                        '<p>' + (d.imported || 0) + ' imported, ' + (d.skipped || 0) + ' skipped, ' + (d.rules_saved || 0) + ' new rules.</p>'
                    );
                    if (d.imported > 0) {
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                } else {
                    $result.html('<span style="color:red;">' + $('<span>').text((res.data && res.data.message) || 'Import failed.').html() + '</span>');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Import Transactions');
                $result.html('<span style="color:red;">Import failed. Please try again.</span>');
            }
        });
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
