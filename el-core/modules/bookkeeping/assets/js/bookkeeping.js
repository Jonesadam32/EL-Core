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

    function autoSelectColumn($select, hints) {
        $select.find('option').each(function () {
            var val = $(this).val().toLowerCase();
            for (var i = 0; i < hints.length; i++) {
                if (val.indexOf(hints[i]) !== -1) {
                    $select.val($(this).val());
                    return false;
                }
            }
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
            const $row = $el.closest('tr');
            if ($el.data('field') === 'category' && $el.val()) {
                $row.removeClass('el-bk-row--suggested el-bk-row--rejected')
                    .addClass('el-bk-row--classified');
                $row.attr('data-status', 'classified')
                    .attr('data-category', $el.val().toLowerCase());
                if (!$row.find('.el-bk-lock-badge').length) {
                    $el.after('<span class="el-bk-lock-badge" title="Locked — won\u2019t change on Re-Classify">🔒</span>');
                }
                if (!$row.find('.el-bk-reject-btn').length) {
                    $row.find('.el-bk-col-actions').html(
                        '<button class="el-bk-reject-btn" data-id="' + $el.data('id') + '" title="Reject — clear category and mark rejected">✕</button>'
                    );
                }
            }
            $row.css('outline', '2px solid #22c55e').delay(800).queue(function () {
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

    // ── Re-Classify Expenses ─────────────────────────────────────────────────

    $('#el-bk-reclassify-btn').on('click', function () {
        if (!confirm('Re-run rules on unclassified/suggested expenses?\n\nLocked (🔒) transactions will NOT be changed.')) return;
        var $btn = $(this).prop('disabled', true).text('Re-classifying…');
        elBkAjax('bk_reclassify', { tax_year: elBookkeeping.taxYear }, function (data) {
            var d = data.data || data;
            alert(d.message || 'Done.');
            $btn.prop('disabled', false).text('Re-Classify Expenses');
            if (d.reclassified > 0) location.reload();
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Re-Classify Expenses');
        });
    });

    // ── Reject Suggestion ─────────────────────────────────────────────────────

    $(document).on('click', '.el-bk-reject-btn', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        var $row = $btn.closest('tr');
        var merchant = $row.find('td').eq(4).text().trim();
        if (!confirm('Reject classification for "' + merchant + '"?\n\nThis clears the category and marks it rejected.')) return;

        elBkAjax('bk_update_transaction', { id: id, field: 'category', value: '' }, function () {
            elBkAjax('bk_update_transaction', { id: id, field: 'status', value: 'rejected' }, function () {
                $row.removeClass('el-bk-row--classified el-bk-row--suggested')
                    .addClass('el-bk-row--rejected');
                $row.attr('data-status', 'rejected').attr('data-category', '');
                $row.find('.el-bk-inline-select[data-field="category"]').val('');
                $row.find('.el-bk-lock-badge').remove();
                $btn.remove();
                $row.css('outline', '2px solid #ef4444').delay(800).queue(function () {
                    $(this).css('outline', '').dequeue();
                });
            });
        });
    });

    // ── Expense Table Filtering ───────────────────────────────────────────────

    function filterExpenseTable() {
        var search   = ($('#el-bk-exp-search').val() || '').toLowerCase();
        var cat      = $('#el-bk-exp-cat-filter').val() || '';
        var bank     = $('#el-bk-exp-bank-filter').val() || '';
        var status   = $('#el-bk-exp-status-filter').val() || '';
        var expType  = $('#el-bk-exp-type-filter').val() || '';
        var dateFrom = $('#el-bk-exp-from').val() || '';
        var dateTo   = $('#el-bk-exp-to').val() || '';

        var visible = 0;
        var total   = 0;
        var visibleAmount = 0;

        $('.el-bk-transactions-table tbody .el-bk-transaction-row').each(function () {
            var $row = $(this);
            total++;

            var rowMerchant = $row.attr('data-merchant') || '';
            var rowBusiness = $row.attr('data-business') || '';
            var rowComments = $row.attr('data-comments') || '';
            var rowCategory = $row.attr('data-category') || '';
            var rowBank     = $row.attr('data-bank') || '';
            var rowDate     = $row.attr('data-date') || '';
            var rowStatus   = $row.attr('data-status') || '';
            var rowExpType  = $row.attr('data-expense-type') || '';

            var show = true;

            if (search) {
                var haystack = rowMerchant + ' ' + rowBusiness + ' ' + rowComments + ' ' + rowCategory;
                if (haystack.indexOf(search) === -1) show = false;
            }

            if (show && cat) {
                if (cat === '__unclassified__') {
                    if (rowCategory !== '') show = false;
                } else if (rowCategory !== cat.toLowerCase()) {
                    show = false;
                }
            }

            if (show && bank && rowBank !== bank) show = false;

            if (show && status) {
                var effectiveStatus = rowStatus || 'unclassified';
                if (effectiveStatus !== status) show = false;
            }

            if (show && expType && rowExpType !== expType) show = false;

            if (show && dateFrom && rowDate < dateFrom) show = false;
            if (show && dateTo && rowDate > dateTo) show = false;

            if (show) {
                $row.show();
                visible++;
                var amountText = $row.find('.el-bk-amount').first().text().replace(/[^0-9.\-]/g, '');
                visibleAmount += parseFloat(amountText) || 0;
            } else {
                $row.hide();
            }
        });

        var $count = $('#el-bk-exp-filter-count');
        var isFiltered = search || cat || bank || status || expType;
        if (isFiltered) {
            $count.html('Showing <strong>' + visible + '</strong> of ' + total + ' &mdash; $' + visibleAmount.toFixed(2));
        } else {
            $count.html('');
        }
    }

    $('#el-bk-exp-search').on('input', filterExpenseTable);
    $('#el-bk-exp-cat-filter, #el-bk-exp-bank-filter, #el-bk-exp-status-filter, #el-bk-exp-type-filter').on('change', filterExpenseTable);
    $('#el-bk-exp-from, #el-bk-exp-to').on('change', filterExpenseTable);

    $('#el-bk-exp-clear-filters').on('click', function () {
        $('#el-bk-exp-search').val('');
        $('#el-bk-exp-cat-filter').val('');
        $('#el-bk-exp-bank-filter').val('');
        $('#el-bk-exp-status-filter').val('');
        $('#el-bk-exp-type-filter').val('');
        var year = elBookkeeping.taxYear || new Date().getFullYear();
        $('#el-bk-exp-from').val(year + '-01-01');
        $('#el-bk-exp-to').val(year + '-12-31');
        filterExpenseTable();
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

    // ── Rules: Filter, Search, Bulk Delete ──────────────────────────────────────

    function filterRulesTable() {
        var cat = ($('#el-bk-rules-filter-cat').val() || '').toLowerCase();
        var search = ($('#el-bk-rules-search').val() || '').toLowerCase();
        var visible = 0;

        $('#el-bk-rules-tbody tr').each(function () {
            var $row = $(this);
            var rowCat = ($row.attr('data-category') || '').toLowerCase();
            var rowKey = ($row.attr('data-keyword') || '').toLowerCase();
            var matchCat = !cat || rowCat === cat;
            var matchSearch = !search || rowKey.indexOf(search) !== -1;
            if (matchCat && matchSearch) {
                $row.show();
                visible++;
            } else {
                $row.hide();
                $row.find('.el-bk-rule-check').prop('checked', false);
            }
        });

        var total = $('#el-bk-rules-tbody tr').length;
        var $count = $('#el-bk-rules-visible-count');
        if (cat || search) {
            $count.text('Showing ' + visible + ' of ' + total);
        } else {
            $count.text('');
        }

        updateBulkDeleteBtn();
    }

    $('#el-bk-rules-filter-cat').on('change', filterRulesTable);
    $('#el-bk-rules-search').on('input', filterRulesTable);

    // Select all visible
    $('#el-bk-rules-select-all').on('change', function () {
        var checked = $(this).prop('checked');
        $('#el-bk-rules-tbody tr:visible .el-bk-rule-check').prop('checked', checked);
        updateBulkDeleteBtn();
    });

    $(document).on('change', '.el-bk-rule-check', function () {
        updateBulkDeleteBtn();
    });

    function updateBulkDeleteBtn() {
        var count = $('#el-bk-rules-tbody .el-bk-rule-check:checked').length;
        var $btn = $('#el-bk-bulk-delete-btn');
        if (count > 0) {
            $btn.show().text('Delete Selected (' + count + ')');
        } else {
            $btn.hide();
        }
    }

    $('#el-bk-bulk-delete-btn').on('click', function () {
        var ids = [];
        $('#el-bk-rules-tbody .el-bk-rule-check:checked').each(function () {
            ids.push($(this).val());
        });
        if (!ids.length) return;
        if (!confirm('Delete ' + ids.length + ' selected rule(s)? This cannot be undone.')) return;

        var $btn = $(this).prop('disabled', true).text('Deleting…');
        elBkAjax('bk_bulk_delete_rules', { ids: ids.join(',') }, function (data) {
            alert((data.data || data).message || 'Deleted.');
            location.reload();
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Delete Selected');
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

    // ── Receipt Upload ─────────────────────────────────────────────────────────

    var RECEIPT_MAX_BYTES = 10 * 1024 * 1024;
    var RECEIPT_EXTS      = ['jpg', 'jpeg', 'png', 'pdf'];

    /**
     * Upload a single File object to bk_upload_receipt.
     * Calls onDone(errorMsg, data) when complete.
     */
    function uploadReceiptFile(file, onDone) {
        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_upload_receipt');
        fd.append('nonce', nonce);
        fd.append('receipt_file', file);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                if (res && res.success) {
                    onDone(null, res.data.data || {});
                } else {
                    onDone((res && res.data && res.data.message) || 'Upload failed.');
                }
            },
            error: function () {
                onDone('Network error — please try again.');
            }
        });
    }

    /**
     * Append one receipt card to the review queue.
     * data = the object returned by handle_upload_receipt on the PHP side.
     */
    function addToReviewQueue(data, filename) {
        var $queue = $('#el-bk-receipt-review-queue');

        var thumbHtml;
        if (data.is_image && data.file_url) {
            thumbHtml = '<img src="' + $('<span>').text(data.file_url).html() + '" alt="receipt">';
        } else {
            thumbHtml = '<div class="el-bk-receipt-review-placeholder">📄</div>';
        }

        var fields = '';
        if (data.merchant) {
            fields += '<div class="el-bk-review-field"><span>Merchant</span><strong>' + $('<span>').text(data.merchant).html() + '</strong></div>';
        }
        if (data.date) {
            fields += '<div class="el-bk-review-field"><span>Date</span><strong>' + $('<span>').text(data.date).html() + '</strong></div>';
        }
        if (data.amount) {
            fields += '<div class="el-bk-review-field"><span>Amount</span><strong>$' + $('<span>').text(data.amount).html() + '</strong></div>';
        }
        if (data.category) {
            fields += '<div class="el-bk-review-field"><span>Category</span><strong>' + $('<span>').text(data.category).html() + '</strong></div>';
        }

        if (!fields) {
            fields = '<div class="el-bk-review-field-empty">No data extracted</div>';
        }

        var badge = data.ai_extracted
            ? '<span class="el-bk-review-badge el-bk-review-badge--ai">✓ AI extracted</span>'
            : '<span class="el-bk-review-badge el-bk-review-badge--manual">Saved (no AI extraction)</span>';

        var $card = $('<div class="el-bk-review-card" data-receipt-id="' + data.id + '">').html(
            '<div class="el-bk-review-card-thumb">' + thumbHtml + '</div>' +
            '<div class="el-bk-review-card-body">' +
                '<div class="el-bk-review-card-filename">' + $('<span>').text(filename).html() + '</div>' +
                fields +
                badge +
            '</div>' +
            '<button class="el-bk-review-dismiss" title="Dismiss">✕</button>'
        );

        $queue.append($card);
    }

    /**
     * Validate and upload an array / FileList of files.
     * Shows inline status in #el-bk-receipt-upload-status.
     */
    function processReceiptUploads(files) {
        if (!files || !files.length) return;

        var $zone   = $('#el-bk-receipt-upload-zone');
        var $status = $('#el-bk-receipt-upload-status');
        var fileArr = Array.from(files);
        var total   = fileArr.length;
        var done    = 0;
        var uploaded = 0;
        var errors   = 0;

        $zone.addClass('el-bk-upload-zone--uploading');
        $status.text('Uploading 1 of ' + total + '…');

        fileArr.forEach(function (file) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();

            // Client-side validation
            if (RECEIPT_EXTS.indexOf(ext) === -1) {
                $status.append(' | Skipped "' + $('<span>').text(file.name).html() + '": unsupported type.');
                done++;
                errors++;
                if (done === total) finishUploads();
                return;
            }
            if (file.size > RECEIPT_MAX_BYTES) {
                $status.append(' | Skipped "' + $('<span>').text(file.name).html() + '": exceeds 10 MB.');
                done++;
                errors++;
                if (done === total) finishUploads();
                return;
            }

            uploadReceiptFile(file, function (err, data) {
                done++;
                if (err) {
                    errors++;
                    $status.append(' | Error: ' + $('<span>').text(err).html());
                } else {
                    uploaded++;
                    addToReviewQueue(data, file.name);
                    if (done < total) {
                        $status.text('Uploading ' + (done + 1) + ' of ' + total + '…');
                    }
                }
                if (done === total) finishUploads();
            });
        });

        function finishUploads() {
            $zone.removeClass('el-bk-upload-zone--uploading');
            $('#el-bk-receipt-file-input').val('');

            if (errors === 0) {
                $status.text(uploaded + (uploaded === 1 ? ' receipt' : ' receipts') + ' uploaded. Reload to see in the table.');
            } else {
                $status.prepend(uploaded + ' uploaded, ' + errors + ' skipped. ');
            }
        }
    }

    // Browse button
    $('#el-bk-receipt-browse-btn').on('click', function () {
        $('#el-bk-receipt-file-input').trigger('click');
    });

    // File picker change
    $('#el-bk-receipt-file-input').on('change', function () {
        processReceiptUploads(this.files);
    });

    // Drag and drop
    $('#el-bk-receipt-upload-zone')
        .on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('el-bk-upload-zone--drag');
        })
        .on('dragleave', function (e) {
            e.preventDefault();
            $(this).removeClass('el-bk-upload-zone--drag');
        })
        .on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('el-bk-upload-zone--drag');
            var files = e.originalEvent.dataTransfer.files;
            processReceiptUploads(files);
        });

    // Dismiss a review queue card
    $(document).on('click', '.el-bk-review-dismiss', function () {
        $(this).closest('.el-bk-review-card').fadeOut(200, function () { $(this).remove(); });
    });

    // Detach receipt
    $(document).on('click', '.el-bk-detach-receipt-btn', function () {
        if (!confirm('Detach this receipt from its transaction?')) return;
        var id = $(this).data('receiptId') || $(this).data('receipt-id');
        elBkAjax('bk_detach_receipt', { receipt_id: id }, function () {
            location.reload();
        });
    });

    // Delete receipt
    $(document).on('click', '.el-bk-delete-receipt-btn', function () {
        if (!confirm('Permanently delete this receipt? This cannot be undone.')) return;
        var id = $(this).data('receiptId') || $(this).data('receipt-id');
        elBkAjax('bk_delete_receipt', { receipt_id: id }, function () {
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

    // ── CSV Rules Import (single-category: pick category → upload → map desc col → import) ──

    let csvRulesFile = null;

    $('#el-bk-csv-rules-file').on('change', function () {
        csvRulesFile = this.files[0] || null;
        $('#el-bk-csv-rules-upload-btn').prop('disabled', !csvRulesFile || !$('#el-bk-csv-rules-category').val());
    });

    $('#el-bk-csv-rules-category').on('change', function () {
        $('#el-bk-csv-rules-upload-btn').prop('disabled', !csvRulesFile || !$(this).val());
    });

    // Step 1: Upload file → get column headers
    $('#el-bk-csv-rules-upload-btn').on('click', function () {
        if (!csvRulesFile) return;
        var category = $('#el-bk-csv-rules-category').val();
        if (!category) { alert('Please select a category first.'); return; }

        var $btn = $(this).prop('disabled', true).text('Reading…');
        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Upload & Detect Columns');
                if (res.success && res.data && res.data.data && res.data.data.step === 'map_columns') {
                    var cols = res.data.data.columns;
                    var $desc = $('#el-bk-csv-rules-desc-col').empty();
                    cols.forEach(function (c) {
                        $desc.append($('<option>').val(c).text(c));
                    });
                    autoSelectColumn($desc, ['description', 'merchant', 'vendor', 'payee', 'name', 'memo']);

                    $('#el-bk-csv-rules-cat-label').text('All descriptions will be saved as rules for: ' + category);
                    $('#el-bk-csv-rules-step1').slideUp();
                    $('#el-bk-csv-rules-step2').slideDown();
                } else {
                    alert((res.data && res.data.message) || 'Unexpected response.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Upload & Detect Columns');
                alert('Upload failed. Please try again.');
            }
        });
    });

    // Step 2: Import — send file + category + description column
    $('#el-bk-csv-rules-import-btn').on('click', function () {
        if (!csvRulesFile) { alert('No file selected.'); return; }
        var category = $('#el-bk-csv-rules-category').val();
        if (!category) { alert('No category selected.'); return; }

        var $btn = $(this).prop('disabled', true).text('Importing…');
        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_rules_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvRulesFile);
        fd.append('merchant_col', $('#el-bk-csv-rules-desc-col').val());
        fd.append('single_category', category);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Import as Rules');
                if (res.success) {
                    var msg = res.data.message || 'Import complete.';
                    $('#el-bk-csv-rules-step2').slideUp();
                    $('#el-bk-csv-rules-result').html('<p style="color:#16a34a;font-weight:600;">' + $('<span>').text(msg).html() + '</p>').slideDown();
                    var d = res.data.data || {};
                    if (d.rules_saved > 0) {
                        setTimeout(function () { location.reload(); }, 1500);
                    }
                } else {
                    alert((res.data && res.data.message) || 'Import failed.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Import as Rules');
                alert('Import failed. Please try again.');
            }
        });
    });

    $('#el-bk-csv-rules-cancel-btn').on('click', function () {
        $('#el-bk-csv-rules-step2').slideUp();
        $('#el-bk-csv-rules-step1').slideDown();
        $('#el-bk-csv-rules-result').slideUp();
    });

    // ── Bank Statement Upload Modal (multi-file, auto-sort income/expense) ───

    var csvTxnFiles = [];

    $(document).on('click', '.el-bk-upload-csv-btn', function () {
        $('#el-bk-csv-step1').show();
        $('#el-bk-csv-step2').hide();
        $('#el-bk-csv-result').hide();
        $('#el-bk-csv-progress').hide().empty();
        $('#el-bk-csv-txn-file').val('');
        $('#el-bk-csv-bank-input').val('');
        csvTxnFiles = [];
        $('#el-bk-csv-txn-upload-btn').prop('disabled', true);
        $('#el-bk-csv-upload-modal').fadeIn(150);
    });

    $(document).on('click', '.el-bk-csv-modal-close', function () {
        $('#el-bk-csv-upload-modal').fadeOut(150);
    });

    // Don't close modal when clicking backdrop during import
    $(document).on('click', '#el-bk-csv-upload-modal .el-bk-modal-backdrop', function () {
        if ($('#el-bk-csv-progress').is(':visible')) return;
        $('#el-bk-csv-upload-modal').fadeOut(150);
    });

    $('#el-bk-csv-txn-file').on('change', function () {
        csvTxnFiles = this.files ? Array.from(this.files) : [];
        $('#el-bk-csv-txn-upload-btn').prop('disabled', csvTxnFiles.length === 0);
    });

    // Step 1: Upload first file to get column headers for mapping
    $('#el-bk-csv-txn-upload-btn').on('click', function () {
        if (!csvTxnFiles.length) return;
        var bank = $('#el-bk-csv-bank-input').val().trim();
        if (!bank) { alert('Please enter a bank account name.'); return; }
        var $btn = $(this).prop('disabled', true).text('Reading…');

        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_import_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', csvTxnFiles[0]);
        fd.append('bank_account', bank);

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Upload & Map Columns');
                if (res.success && res.data && res.data.data && res.data.data.step === 'map_columns') {
                    var d = res.data.data;
                    var cols = d.columns;

                    var $date = $('#el-bk-csv-date-col').empty();
                    var $amt = $('#el-bk-csv-amount-col').empty();
                    var $merch = $('#el-bk-csv-merchant-txn-col').empty();
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

    // Step 2: Import all files with the mapped columns
    $('#el-bk-csv-txn-import-btn').on('click', function () {
        if (!csvTxnFiles.length) { alert('No files selected.'); return; }
        var $btn = $(this).prop('disabled', true).text('Importing…');
        var $progress = $('#el-bk-csv-progress').show();
        $('#el-bk-csv-step2').slideUp();

        var bank = $('#el-bk-csv-bank-input').val().trim();
        var dateCol = $('#el-bk-csv-date-col').val();
        var amountCol = $('#el-bk-csv-amount-col').val();
        var merchantCol = $('#el-bk-csv-merchant-txn-col').val();

        var totals = { income: 0, expense: 0, classified: 0, skipped: 0 };
        var fileIdx = 0;

        function importNext() {
            if (fileIdx >= csvTxnFiles.length) {
                $progress.hide();
                var totalImported = totals.income + totals.expense;
                $('#el-bk-csv-result').html(
                    '<p style="color:#16a34a;font-weight:600;">All ' + csvTxnFiles.length + ' file(s) imported successfully.</p>' +
                    '<p><strong>' + totals.income + '</strong> income, <strong>' + totals.expense + '</strong> expenses (' + totals.classified + ' auto-classified), ' + totals.skipped + ' skipped.</p>'
                ).slideDown();
                $btn.prop('disabled', false).text('Import All Files');
                if (totalImported > 0) {
                    setTimeout(function () { location.reload(); }, 2500);
                }
                return;
            }

            var file = csvTxnFiles[fileIdx];
            var fileNum = fileIdx + 1;
            $progress.html('<em>Importing file ' + fileNum + ' of ' + csvTxnFiles.length + ': ' + $('<span>').text(file.name).html() + '…</em>');

            var fd = new FormData();
            fd.append('action', 'el_core_action');
            fd.append('el_action', 'bk_import_csv');
            fd.append('nonce', nonce);
            fd.append('csv_file', file);
            fd.append('bank_account', bank);
            fd.append('date_col', dateCol);
            fd.append('amount_col', amountCol);
            fd.append('merchant_col', merchantCol);

            $.ajax({
                url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
                success: function (res) {
                    if (res.success) {
                        var d = res.data.data || {};
                        totals.income     += (d.income_imported || 0);
                        totals.expense    += (d.expense_imported || 0);
                        totals.classified += (d.classified || 0);
                        totals.skipped    += (d.skipped || 0);
                    }
                    fileIdx++;
                    importNext();
                },
                error: function () {
                    $progress.append('<br><span style="color:#dc2626;">Failed to import ' + $('<span>').text(file.name).html() + '. Continuing…</span>');
                    fileIdx++;
                    importNext();
                }
            });
        }

        importNext();
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
