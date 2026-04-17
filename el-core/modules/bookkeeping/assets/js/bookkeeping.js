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

    function debounce(fn, delay) {
        var timer;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, delay);
        };
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

    // Expand category placeholder to full option list on first user interaction
    $(document).on('focus click', '.el-bk-cat-placeholder', function () {
        var $el = $(this);
        if ($el.hasClass('el-bk-cat-expanded')) return;

        var currentVal = $el.attr('data-current') || '';
        var id         = $el.attr('data-id');

        var $template = $('#el-bk-cat-select-template').clone();
        $template
            .removeAttr('id')
            .removeAttr('style')
            .removeAttr('aria-hidden')
            .addClass('el-bk-inline-select el-bk-cat-expanded')
            .removeClass('el-bk-cat-placeholder')
            .attr('data-field', 'category')
            .attr('data-id', id)
            .val(currentVal);

        $el.replaceWith($template);
        $template.focus();
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

    // ── Re-Classify Range ────────────────────────────────────────────────────

    $('#el-bk-reclassify-range-btn').on('click', function () {
        $('#el-bk-reclassify-range-result').hide().text('');
        $('#el-bk-reclassify-range-modal').show();
    });

    $(document).on('click', '.el-bk-reclassify-range-close', function () {
        $('#el-bk-reclassify-range-modal').hide();
    });

    $('#el-bk-reclassify-range-confirm-btn').on('click', function () {
        var dateFrom = $('#el-bk-reclassify-range-from').val();
        var dateTo   = $('#el-bk-reclassify-range-to').val();
        if (!dateFrom || !dateTo) { alert('Please enter both From and To dates.'); return; }
        if (dateTo < dateFrom) { alert('To date must be on or after From date.'); return; }

        var $btn = $(this).prop('disabled', true).text('Re-classifying…');

        elBkAjax('bk_reclassify', { tax_year: elBookkeeping.taxYear, date_from: dateFrom, date_to: dateTo }, function (data) {
            var d = data.data || data;
            var msg = d.message || 'Done.';

            var $result = $('#el-bk-reclassify-range-result');
            $result.css({ background: '#f0fdf4', border: '1px solid #86efac', color: '#166534' })
                   .text(msg).show();
            $btn.prop('disabled', false).text('Re-Classify Range');

            if ((d.reclassified || 0) > 0) {
                setTimeout(function () { location.reload(); }, 1200);
            }
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('Re-Classify Range');
        });
    });

    // ── Lock / Unlock Period ─────────────────────────────────────────────────

    $('#el-bk-lock-period-btn').on('click', function () {
        $('#el-bk-lock-result').hide().text('');
        $('#el-bk-lock-period-modal').show();
    });

    $(document).on('click', '.el-bk-lock-period-close', function () {
        $('#el-bk-lock-period-modal').hide();
    });

    $('#el-bk-lock-confirm-btn').on('click', function () {
        var dateFrom = $('#el-bk-lock-from').val();
        var dateTo   = $('#el-bk-lock-to').val();
        if (!dateFrom || !dateTo) { alert('Please enter both From and To dates.'); return; }
        if (dateTo < dateFrom) { alert('To date must be on or after From date.'); return; }

        var $btn = $(this).prop('disabled', true).text('Locking\u2026');

        elBkAjax('bk_lock_period', { date_from: dateFrom, date_to: dateTo }, function (data) {
            var d = data.data || data;
            var msg = d.message || ('Locked ' + (d.locked || 0) + ' transactions.');

            $('.el-bk-transaction-row').each(function () {
                var $row    = $(this);
                var rowDate = $row.data('date');
                var status  = $row.data('status');
                if (status === 'split') return;
                if (rowDate >= dateFrom && rowDate <= dateTo) {
                    $row.removeClass('el-bk-row--suggested el-bk-row--rejected')
                        .addClass('el-bk-row--classified');
                    $row.attr('data-status', 'classified');
                    var $catCell = $row.find('.el-bk-inline-select[data-field="category"]');
                    if ($catCell.length && !$row.find('.el-bk-lock-badge').length) {
                        $catCell.after('<span class="el-bk-lock-badge" title="Locked \u2014 won\u2019t change on Re-Classify">\uD83D\uDD12</span>');
                    }
                }
            });

            var $result = $('#el-bk-lock-result');
            $result.css({ background: '#f0fdf4', border: '1px solid #86efac', color: '#166534' })
                   .text(msg).show();
            $btn.prop('disabled', false).text('\uD83D\uDD12 Lock Period');
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('\uD83D\uDD12 Lock Period');
        });
    });

    $('#el-bk-unlock-confirm-btn').on('click', function () {
        var dateFrom = $('#el-bk-lock-from').val();
        var dateTo   = $('#el-bk-lock-to').val();
        if (!dateFrom || !dateTo) { alert('Please enter both From and To dates.'); return; }
        if (dateTo < dateFrom) { alert('To date must be on or after From date.'); return; }

        var $btn = $(this).prop('disabled', true).text('Unlocking\u2026');

        elBkAjax('bk_unlock_period', { date_from: dateFrom, date_to: dateTo }, function (data) {
            var d = data.data || data;
            var msg = d.message || ('Unlocked ' + (d.unlocked || 0) + ' transactions.');

            $('.el-bk-transaction-row').each(function () {
                var $row    = $(this);
                var rowDate = $row.data('date');
                var status  = $row.data('status');
                if (status !== 'classified') return;
                if (rowDate >= dateFrom && rowDate <= dateTo) {
                    var category = $row.attr('data-category') || '';
                    var newStatus = category ? 'suggested' : '';
                    $row.removeClass('el-bk-row--classified')
                        .toggleClass('el-bk-row--suggested', !!category);
                    $row.attr('data-status', newStatus);
                    $row.find('.el-bk-lock-badge').remove();
                    if (category && !$row.find('.el-bk-reject-btn').length) {
                        $row.find('.el-bk-col-actions .el-bk-split-btn').after(
                            ' <button class="el-bk-reject-btn" data-id="' + $row.data('id') + '" title="Reject \u2014 clear category and mark rejected">\u2715</button>'
                        );
                    }
                }
            });

            var $result = $('#el-bk-lock-result');
            $result.css({ background: '#fefce8', border: '1px solid #fde047', color: '#713f12' })
                   .text(msg).show();
            $btn.prop('disabled', false).text('\uD83D\uDD13 Unlock Period');
        }, function (msg) {
            alert(msg);
            $btn.prop('disabled', false).text('\uD83D\uDD13 Unlock Period');
        });
    });

    // ── Row-level Lock Toggle ─────────────────────────────────────────────────

    $(document).on('click', '.el-bk-row-lock-btn', function () {
        var $btn    = $(this);
        var id      = $btn.data('id');
        var locked  = $btn.data('locked') == '1';
        var $row    = $btn.closest('tr');
        var category = $row.attr('data-category') || '';

        if (locked) {
            // Unlock: restore to suggested (if categorised) or unclassified
            var newStatus = category ? 'suggested' : '';
            elBkAjax('bk_update_transaction', { id: id, field: 'status', value: newStatus }, function () {
                $row.removeClass('el-bk-row--classified')
                    .toggleClass('el-bk-row--suggested', !!category);
                $row.attr('data-status', newStatus);
                $row.find('.el-bk-lock-badge').remove();
                $btn.data('locked', '0')
                    .attr('title', 'Lock this transaction')
                    .css('opacity', '0.35')
                    .text('\uD83D\uDD13');
                // Restore reject button if categorised
                if (category && !$row.find('.el-bk-reject-btn').length) {
                    $btn.after(' <button class="el-bk-reject-btn" data-id="' + id + '" title="Reject \u2014 clear category and mark rejected">\u2715</button>');
                }
            });
        } else {
            // Lock: set to classified
            elBkAjax('bk_update_transaction', { id: id, field: 'status', value: 'classified' }, function () {
                $row.removeClass('el-bk-row--suggested el-bk-row--rejected')
                    .addClass('el-bk-row--classified');
                $row.attr('data-status', 'classified');
                var $catCell = $row.find('.el-bk-inline-select[data-field="category"]');
                if ($catCell.length && !$row.find('.el-bk-lock-badge').length) {
                    $catCell.after('<span class="el-bk-lock-badge" title="Locked \u2014 won\u2019t change on Re-Classify">\uD83D\uDD12</span>');
                }
                $btn.data('locked', '1')
                    .attr('title', 'Unlock this transaction')
                    .css('opacity', '1')
                    .text('\uD83D\uDD12');
            });
        }
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

        // Always update the tfoot total to match visible rows
        var $expTotalCell = $('#el-bk-exp-total-cell');
        if ($expTotalCell.length) {
            $expTotalCell.html('<strong>$' + visibleAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</strong>');
            $('#el-bk-exp-total-label').html(isFiltered ? '<strong>Total (filtered)</strong>' : '<strong>Total</strong>');
        }
    }

    $('#el-bk-exp-search').on('input', debounce(filterExpenseTable, 300));
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

    // Initialise expense total from DOM on page load (catches inline edits)
    if ($('#el-bk-exp-total-cell').length) { filterExpenseTable(); }

    // ── Make Rule Popover ─────────────────────────────────────────────────────

    var $makeRulePopover = $('#el-bk-make-rule-popover');
    var makeRuleConflictTimer = null;

    function closeMakeRulePopover() {
        $makeRulePopover.hide();
        $('#el-bk-make-rule-conflict').hide().empty();
    }

    function checkMakeRuleConflict(keyword) {
        clearTimeout(makeRuleConflictTimer);
        if (!keyword) { $('#el-bk-make-rule-conflict').hide().empty(); return; }
        makeRuleConflictTimer = setTimeout(function () {
            elBkAjax('bk_check_rule_conflict', { keyword: keyword }, function (data) {
                var $warn = $('#el-bk-make-rule-conflict');
                if (data.conflicts && data.conflicts.length > 0) {
                    var lines = data.conflicts.map(function (c) {
                        return '\u2022 \u201c' + c.keyword + '\u201d \u2192 ' + c.category;
                    });
                    $warn.html('<strong>⚠ This will replace an existing rule:</strong><br>' + lines.join('<br>')).show();
                } else {
                    $warn.hide().empty();
                }
            });
        }, 300);
    }

    $(document).on('click', '.el-bk-make-rule-btn', function (e) {
        e.stopPropagation();
        var $btn    = $(this);
        var merchant = $btn.attr('data-merchant') || '';
        var category = $btn.attr('data-category') || '';
        var txnId    = $btn.attr('data-id') || '';

        $('#el-bk-make-rule-keyword').val(merchant);
        $('#el-bk-make-rule-category').val(category);
        $('#el-bk-make-rule-txn-id').val(txnId);
        $('#el-bk-make-rule-conflict').hide().empty();

        // Position near the button, correcting for scroll (fixed positioning is viewport-relative)
        var offset    = $btn.offset();
        var scrollTop = $(window).scrollTop();
        var popW      = 360;
        var left      = offset.left - $(window).scrollLeft();
        if ( left + popW + 20 > $(window).width() ) {
            left = $(window).width() - popW - 20;
        }
        $makeRulePopover.css({
            top:  offset.top - scrollTop + $btn.outerHeight() + 6,
            left: Math.max(10, left),
        }).show();

        checkMakeRuleConflict(merchant);
    });

    $('#el-bk-make-rule-keyword').on('input', function () {
        checkMakeRuleConflict($(this).val().trim());
    });

    $('#el-bk-make-rule-close, #el-bk-make-rule-cancel').on('click', closeMakeRulePopover);

    $(document).on('click', function (e) {
        if ($makeRulePopover.is(':visible') && !$(e.target).closest('#el-bk-make-rule-popover, .el-bk-make-rule-btn').length) {
            closeMakeRulePopover();
        }
    });

    $('#el-bk-make-rule-save').on('click', function () {
        var keyword  = $('#el-bk-make-rule-keyword').val().trim();
        var category = $('#el-bk-make-rule-category').val();
        var txnId    = $('#el-bk-make-rule-txn-id').val();
        var $btn     = $(this).prop('disabled', true).text('Saving…');

        if (!keyword || !category) {
            alert('Keyword and category are both required.');
            $btn.prop('disabled', false).text('Save Rule');
            return;
        }

        elBkAjax('bk_quick_save_rule', {
            keyword:        keyword,
            category:       category,
            match_type:     'contains',
            transaction_id: txnId,
        }, function (data) {
            $btn.prop('disabled', false).text('Save Rule');
            closeMakeRulePopover();

            // Update the row in the table immediately
            if (txnId) {
                var $row = $('.el-bk-transaction-row[data-id="' + txnId + '"]');
                $row.find('select.el-bk-inline-select[data-field="category"]').val(category);
                $row.attr('data-category', category.toLowerCase());
                $row.attr('data-status', 'classified');
                $row.removeClass('el-bk-row--suggested el-bk-row--rejected').addClass('el-bk-row--classified');
                $row.find('.el-bk-make-rule-btn').attr('data-category', category);
            }

            var msg = 'Rule saved.';
            if (data.replaced && data.replaced.length > 0) {
                var old = data.replaced.map(function(r){ return '\u201c' + r.category + '\u201d'; }).join(', ');
                msg = 'Rule saved. Replaced previous rule: ' + old + '.';
            }
            alert(msg);
        }, function (errMsg) {
            $btn.prop('disabled', false).text('Save Rule');
            alert('Error: ' + errMsg);
        });
    });

    // ── Expense Date Column Sort ──────────────────────────────────────────────

    var expDateSortDesc = true; // starts newest-first (matches server ORDER BY date DESC)

    $('#el-bk-exp-date-th').on('click', function () {
        expDateSortDesc = !expDateSortDesc;
        $('#el-bk-exp-date-sort-icon').text(expDateSortDesc ? '▼' : '▲');
        var $tbody = $('.el-bk-transactions-table tbody');
        var $rows = $tbody.find('.el-bk-transaction-row').toArray();
        $rows.sort(function (a, b) {
            var da = $(a).attr('data-date') || '';
            var db = $(b).attr('data-date') || '';
            return expDateSortDesc ? db.localeCompare(da) : da.localeCompare(db);
        });
        $tbody.append($rows);
    });

    // ── Income Table Filtering ────────────────────────────────────────────────

    function filterIncomeTable() {
        var dateFrom = $('#el-bk-inc-from').val() || '';
        var dateTo   = $('#el-bk-inc-to').val()   || '';
        var search   = ($('#el-bk-inc-search').val() || '').toLowerCase().trim();
        var catFilter = $('#el-bk-inc-cat-filter').val() || '';

        var visible = 0;
        var visibleAmount = 0;
        var total = 0;

        $('#el-bk-inc-table tbody .el-bk-transaction-row').each(function () {
            var $row    = $(this);
            var rowDate = $row.attr('data-date') || '';
            var rowMerchant = ($row.attr('data-merchant') || $row.find('td').eq(4).text()).toLowerCase();
            var rowCatVal   = $row.find('.el-bk-inline-select[data-field="category"]').val() || '';
            var show    = true;
            total++;

            if (show && dateFrom && rowDate < dateFrom) show = false;
            if (show && dateTo   && rowDate > dateTo)   show = false;
            if (show && search   && rowMerchant.indexOf(search) === -1) show = false;
            if (show && catFilter) {
                if (catFilter === '__unclassified__') {
                    if (rowCatVal) show = false;
                } else {
                    if (rowCatVal !== catFilter) show = false;
                }
            }

            if (show) {
                $row.show();
                visible++;
                var amt = parseFloat($row.find('.el-bk-amount').text().replace(/[$,]/g, '')) || 0;
                visibleAmount += amt;
            } else {
                $row.hide();
            }
        });

        var isFiltered = (dateFrom || dateTo || search || catFilter);
        var $count = $('#el-bk-inc-filter-count');
        if (isFiltered && visible < total) {
            $count.html('Showing <strong>' + visible + '</strong> of ' + total + ' &mdash; $' + visibleAmount.toFixed(2));
        } else {
            $count.html('');
        }

        // Always update the tfoot total to match visible rows
        var $incTotalCell = $('#el-bk-inc-total-cell');
        if ($incTotalCell.length) {
            $incTotalCell.html('<strong>$' + visibleAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</strong>');
            $('#el-bk-inc-total-label').html(isFiltered ? '<strong>Total (filtered)</strong>' : '<strong>Total</strong>');
        }
    }

    $('#el-bk-inc-filter-btn').on('click', filterIncomeTable);
    $('#el-bk-inc-from, #el-bk-inc-to').on('change', filterIncomeTable);
    $('#el-bk-inc-search').on('input', filterIncomeTable);
    $('#el-bk-inc-cat-filter').on('change', filterIncomeTable);

    $('#el-bk-inc-clear-filters').on('click', function () {
        $('#el-bk-inc-search').val('');
        $('#el-bk-inc-cat-filter').val('');
        $('#el-bk-inc-from').val('');
        $('#el-bk-inc-to').val('');
        filterIncomeTable();
    });

    // Initialise income total from DOM on page load (catches inline edits)
    if ($('#el-bk-inc-total-cell').length) { filterIncomeTable(); }

    // ── Income Table Sorting ──────────────────────────────────────────────────

    var incSortCol  = 'date'; // active sort column: 'date' | 'merchant' | 'category'
    var incSortDesc = true;   // true = descending (newest / Z→A)

    function sortIncomeTable(col) {
        if (incSortCol === col) {
            incSortDesc = !incSortDesc;
        } else {
            incSortCol  = col;
            incSortDesc = col === 'date'; // dates default newest-first; text defaults A→Z
        }

        // Update sort icons — clear all, then set the active one
        $('#el-bk-inc-date-sort-icon, #el-bk-inc-merchant-sort-icon, #el-bk-inc-cat-sort-icon').text('');
        var icon = incSortDesc ? '▼' : '▲';
        if (col === 'date')     $('#el-bk-inc-date-sort-icon').text(icon);
        if (col === 'merchant') $('#el-bk-inc-merchant-sort-icon').text(icon);
        if (col === 'category') $('#el-bk-inc-cat-sort-icon').text(icon);

        var $tbody = $('#el-bk-inc-table tbody');
        var $rows  = $tbody.find('.el-bk-transaction-row').toArray();

        $rows.sort(function (a, b) {
            var va = $(a).attr('data-' + col) || '';
            var vb = $(b).attr('data-' + col) || '';
            return incSortDesc ? vb.localeCompare(va) : va.localeCompare(vb);
        });

        $tbody.append($rows);
    }

    $('#el-bk-inc-date-th').on('click',     function () { sortIncomeTable('date'); });
    $('#el-bk-inc-merchant-th').on('click', function () { sortIncomeTable('merchant'); });
    $('#el-bk-inc-cat-th').on('click',      function () { sortIncomeTable('category'); });

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
        if (data.location) {
            fields += '<div class="el-bk-review-field"><span>Location</span><strong>' + $('<span>').text(data.location).html() + '</strong></div>';
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

    // Inline receipt field edit (e.g. location)
    $(document).on('blur', '.el-bk-receipt-inline-input', function () {
        var $el = $(this);
        elBkAjax('bk_update_receipt', {
            id:    $el.data('receiptId') || $el.data('receipt-id'),
            field: $el.data('field'),
            value: $el.val(),
        }, function () {
            $el.closest('td').css('outline', '2px solid #22c55e').delay(600).queue(function () {
                $(this).css('outline', '').dequeue();
            });
        });
    });

    // Receipt Edit row — toggle
    $(document).on('click', '.el-bk-edit-receipt-btn', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr.el-bk-receipt-row');
        var $existing = $row.next('tr.el-bk-receipt-edit-row');

        if ($existing.length) {
            $existing.remove();
            $btn.text('Edit');
            return;
        }

        // Close any other open edit rows
        $('.el-bk-receipt-edit-row').remove();
        $('.el-bk-edit-receipt-btn').text('Edit');

        var id       = $row.data('receiptId') || $row.data('receipt-id');
        var merchant = $row.data('merchant') || '';
        var date     = $row.data('date')     || '';
        var amount   = $row.data('amount')   || '';
        var category = $row.data('category') || '';
        var location = $row.data('location') || '';
        var notes    = $row.data('notes')    || '';

        var colspan  = $row.find('td').length;

        // Clone the hidden category select and set its value
        var $catSelect = $('#el-bk-receipt-category-template').clone()
            .removeAttr('id')
            .removeAttr('style')
            .addClass('el-select el-bk-edit-receipt-category');
        $catSelect.val(category);

        var $editRow = $('<tr class="el-bk-receipt-edit-row"><td colspan="' + colspan + '"></td></tr>');
        var $inner   = $('<div class="el-bk-receipt-edit-form"></div>');

        $inner.append(
            '<div class="el-bk-receipt-edit-grid">' +
                '<div class="el-bk-form-row">' +
                    '<label>Merchant</label>' +
                    '<input type="text" class="el-input el-bk-edit-receipt-merchant" value="' + $('<span>').text(merchant).html() + '" placeholder="Merchant name">' +
                '</div>' +
                '<div class="el-bk-form-row">' +
                    '<label>Date</label>' +
                    '<input type="date" class="el-input el-bk-edit-receipt-date" value="' + $('<span>').text(date).html() + '">' +
                '</div>' +
                '<div class="el-bk-form-row">' +
                    '<label>Amount</label>' +
                    '<input type="text" class="el-input el-bk-edit-receipt-amount" value="' + $('<span>').text(amount).html() + '" placeholder="0.00">' +
                '</div>' +
                '<div class="el-bk-form-row">' +
                    '<label>Location</label>' +
                    '<input type="text" class="el-input el-bk-edit-receipt-location" value="' + $('<span>').text(location).html() + '" placeholder="City, ST">' +
                '</div>' +
            '</div>'
        );

        // Category row (full width)
        var $catRow = $('<div class="el-bk-form-row el-bk-receipt-edit-cat-row"><label>Category</label></div>');
        $catRow.append($catSelect);
        $inner.append($catRow);

        // Notes row (full width)
        $inner.append(
            '<div class="el-bk-form-row">' +
                '<label>Notes</label>' +
                '<textarea class="el-textarea el-bk-edit-receipt-notes" rows="2" placeholder="Optional notes">' + $('<span>').text(notes).html() + '</textarea>' +
            '</div>'
        );

        // Action buttons
        var $actions = $(
            '<div class="el-bk-receipt-edit-actions">' +
                '<button class="el-btn el-btn-primary el-btn-sm el-bk-edit-receipt-save-btn">Save Changes</button>' +
                '<button class="el-btn el-btn-outline el-btn-sm el-bk-edit-receipt-cancel-btn">Cancel</button>' +
            '</div>'
        );
        $inner.append($actions);

        $editRow.find('td').append($inner);
        $row.after($editRow);
        $btn.text('Close');
    });

    // Receipt Edit row — cancel
    $(document).on('click', '.el-bk-edit-receipt-cancel-btn', function () {
        var $editRow = $(this).closest('tr.el-bk-receipt-edit-row');
        var $dataRow = $editRow.prev('tr.el-bk-receipt-row');
        $dataRow.find('.el-bk-edit-receipt-btn').text('Edit');
        $editRow.remove();
    });

    // Receipt Edit row — save
    $(document).on('click', '.el-bk-edit-receipt-save-btn', function () {
        var $btn     = $(this).prop('disabled', true).text('Saving…');
        var $editRow = $btn.closest('tr.el-bk-receipt-edit-row');
        var $dataRow = $editRow.prev('tr.el-bk-receipt-row');
        var id       = $dataRow.data('receiptId') || $dataRow.data('receipt-id');

        var merchant = $editRow.find('.el-bk-edit-receipt-merchant').val();
        var date     = $editRow.find('.el-bk-edit-receipt-date').val();
        var amount   = $editRow.find('.el-bk-edit-receipt-amount').val();
        var category = $editRow.find('.el-bk-edit-receipt-category').val();
        var location = $editRow.find('.el-bk-edit-receipt-location').val();
        var notes    = $editRow.find('.el-bk-edit-receipt-notes').val();

        elBkAjax('bk_save_receipt_edits', {
            id: id, merchant: merchant, date: date, amount: amount,
            category: category, location: location, notes: notes
        }, function () {
            // Update data attributes so re-opening edit shows fresh values
            $dataRow.data('merchant', merchant).data('date', date)
                    .data('amount', amount).data('category', category)
                    .data('location', location).data('notes', notes);

            // Update visible cells
            $dataRow.find('.el-bk-receipt-cell-merchant').text(merchant || '—');
            $dataRow.find('.el-bk-receipt-cell-date').text(date || '—');
            var amtClean = parseFloat(String(amount).replace(/[$,\s]/g, ''));
            $dataRow.find('.el-bk-receipt-cell-amount').text(isNaN(amtClean) ? '—' : '$' + amtClean.toFixed(2));
            $dataRow.find('.el-bk-receipt-cell-category').text(category || '—');
            $dataRow.find('.el-bk-receipt-inline-input[data-field="location"]').val(location);

            $dataRow.find('.el-bk-edit-receipt-btn').text('Edit');
            $editRow.remove();
        }, function (msg) {
            $btn.prop('disabled', false).text('Save Changes');
            alert('Error: ' + msg);
        });
    });

    // ── Receipt Auto-Match (Feature E) ────────────────────────────────────────

    // Find Match — toggle match candidate panel
    $(document).on('click', '.el-bk-find-match-btn', function () {
        var $btn     = $(this);
        var $row     = $btn.closest('tr.el-bk-receipt-row');
        var $existing = $row.next('tr.el-bk-receipt-match-row');

        if ($existing.length) {
            $existing.remove();
            $btn.text('Find Match');
            return;
        }

        // Close any open edit rows, other match rows, and expense receipt panels
        $('.el-bk-receipt-edit-row').remove();
        $('.el-bk-edit-receipt-btn').text('Edit');
        $('.el-bk-receipt-match-row').remove();
        $('.el-bk-find-match-btn').text('Find Match');
        $('.el-bk-expense-receipt-row').remove();

        var id      = $row.data('receiptId') || $row.data('receipt-id');
        var colspan = $row.find('td').length;

        $btn.prop('disabled', true).text('Asking AI…');

        elBkAjax('bk_suggest_receipt_matches', { receipt_id: id, tax_year: elBookkeeping.taxYear || 0 }, function (candidates) {
            $btn.prop('disabled', false).text('Close');

            // EL_AJAX_Handler::success($array, $msg) wraps as { data: $array, message: $msg }
            var matches = Array.isArray(candidates) ? candidates : (candidates.data || []);
            var serverMsg = (candidates && candidates.message) ? candidates.message : '';

            var $matchRow = $('<tr class="el-bk-receipt-match-row"><td colspan="' + colspan + '"></td></tr>');
            var $inner    = $('<div class="el-bk-receipt-match-panel"></div>');

            if (!matches || matches.length === 0) {
                var emptyMsg = serverMsg || 'AI found no matching transactions for this receipt. Check that the receipt has a merchant name and date, then verify the expense exists in the Expenses tab.';
                $inner.append(
                    '<p class="el-bk-receipt-match-empty">' + $('<span>').text(emptyMsg).html() + '</p>'
                );
            } else {
                var $table = $(
                    '<table class="el-bk-receipt-match-table widefat">' +
                        '<thead><tr>' +
                            '<th>Merchant</th>' +
                            '<th>Date</th>' +
                            '<th>Amount</th>' +
                            '<th>Category</th>' +
                            '<th>Confidence</th>' +
                            '<th>AI Reasoning</th>' +
                            '<th></th>' +
                        '</tr></thead>' +
                        '<tbody></tbody>' +
                    '</table>'
                );

                matches.forEach(function (c) {
                    var conf = (c.confidence || 'low').toLowerCase();
                    $table.find('tbody').append(
                        $('<tr>').append(
                            $('<td>').text(c.merchant || '—'),
                            $('<td>').text(c.date || '—'),
                            $('<td>').text('$' + c.amount),
                            $('<td>').text(c.category || '—'),
                            $('<td>').append(
                                $('<span class="el-bk-conf-badge el-bk-conf-' + conf + '">').text(conf)
                            ),
                            $('<td class="el-bk-match-reason">').text(c.reason || ''),
                            $('<td>').append(
                                $('<button class="el-btn el-btn-primary el-btn-sm el-bk-attach-match-btn">')
                                    .text('Attach')
                                    .attr('data-receipt-id', id)
                                    .attr('data-transaction-id', c.id)
                            )
                        )
                    );
                });

                $inner.append($table);
            }

            // Always show Pick Manually fallback — lets user browse all unattached expenses
            $inner.append(
                $('<div class="el-bk-pick-manually-section">').append(
                    $('<button class="el-btn el-btn-outline el-btn-sm el-bk-pick-manually-btn">')
                        .text('Pick Manually')
                        .attr('data-receipt-id', id)
                )
            );

            $matchRow.find('td').append($inner);
            $row.after($matchRow);

        }, function (msg) {
            $btn.prop('disabled', false).text('Find Match');
            alert('Error: ' + msg);
        });
    });

    // Attach Match — user selects a candidate
    $(document).on('click', '.el-bk-attach-match-btn', function () {
        var $btn           = $(this).prop('disabled', true).text('Attaching…');
        var receiptId      = $btn.data('receiptId')     || $btn.data('receipt-id');
        var transactionId  = $btn.data('transactionId') || $btn.data('transaction-id');

        elBkAjax('bk_attach_receipt', {
            receipt_id:     receiptId,
            transaction_id: transactionId,
        }, function () {
            location.reload();
        }, function (msg) {
            $btn.prop('disabled', false).text('Attach');
            alert('Error: ' + msg);
        });
    });

    // Pick Manually — toggle searchable expense list in match panel
    $(document).on('click', '.el-bk-pick-manually-btn', function () {
        var $btn     = $(this);
        var $section = $btn.closest('.el-bk-pick-manually-section');
        var $wrap    = $section.find('.el-bk-manual-pick-wrap');

        if ($wrap.length) {
            $wrap.remove();
            $btn.text('Pick Manually');
            return;
        }

        $btn.text('Hide Manual Pick');

        var receiptId = $btn.data('receiptId') || $btn.data('receipt-id');
        var expenses  = (typeof elBkManualExpenses !== 'undefined') ? elBkManualExpenses : [];

        var $newWrap = $('<div class="el-bk-manual-pick-wrap">');
        $newWrap.append(
            '<div class="el-bk-manual-pick-search">' +
                '<input type="text" class="el-input el-bk-manual-pick-filter" placeholder="Search by merchant or date…">' +
            '</div>'
        );

        if (!expenses.length) {
            $newWrap.append('<p class="el-bk-receipt-match-empty">No unattached expense transactions found for this tax year.</p>');
        } else {
            var $t = $(
                '<table class="el-bk-receipt-match-table el-bk-manual-pick-table widefat">' +
                    '<thead><tr>' +
                        '<th>Merchant</th>' +
                        '<th>Date</th>' +
                        '<th>Amount</th>' +
                        '<th>Category</th>' +
                        '<th></th>' +
                    '</tr></thead>' +
                    '<tbody></tbody>' +
                '</table>'
            );

            expenses.forEach(function (e) {
                $t.find('tbody').append(
                    $('<tr class="el-bk-manual-pick-row">').append(
                        $('<td class="el-bk-manual-pick-merchant">').text(e.merchant || '—'),
                        $('<td>').text(e.date || '—'),
                        $('<td>').text('$' + parseFloat(e.amount || 0).toFixed(2)),
                        $('<td>').text(e.category || '—'),
                        $('<td>').append(
                            $('<button class="el-btn el-btn-primary el-btn-sm el-bk-attach-match-btn">')
                                .text('Attach')
                                .attr('data-receipt-id', receiptId)
                                .attr('data-transaction-id', e.id)
                        )
                    )
                );
            });

            $newWrap.append($t);
        }

        $section.append($newWrap);
    });

    // Pick Manually — live search filter
    $(document).on('input', '.el-bk-manual-pick-filter', function () {
        var term = $(this).val().toLowerCase();
        $(this).closest('.el-bk-manual-pick-wrap').find('.el-bk-manual-pick-row').each(function () {
            var merchant = $(this).find('.el-bk-manual-pick-merchant').text().toLowerCase();
            var date     = $(this).find('td').eq(1).text().toLowerCase();
            $(this).toggle(!term || merchant.indexOf(term) !== -1 || date.indexOf(term) !== -1);
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

    // ── P&L Filter Button ──────────────────────────────────────────────────────

    $(document).on('click', '#el-bk-pl-filter-btn', function () {
        var from = $('#el-bk-pl-from').val();
        var to   = $('#el-bk-pl-to').val();
        var view = $('.el-btn-toggle--active[data-view]').data('view') || 'business';
        var url  = new URL(window.location.href);
        url.searchParams.set('pl_from', from);
        url.searchParams.set('pl_to', to);
        url.searchParams.set('pl_view', view);
        window.location.href = url.toString();
    });

    // ── P&L View Toggle ────────────────────────────────────────────────────────

    $(document).on('click', '.el-btn-toggle[data-view]', function () {
        var view = $(this).data('view');
        var url  = new URL(window.location.href);
        url.searchParams.set('pl_view', view);
        window.location.href = url.toString();
    });

    $(document).on('click', '.el-bk-generate-pl-btn', function () {
        // Phase 7
        alert('P&L report generation will be available in Phase 7.');
    });

    // ── Manual Receipt Entry Form ──────────────────────────────────────────────

    function clearManualReceiptForm() {
        $('#el-bk-manual-title').val('');
        $('#el-bk-manual-date').val(new Date().toISOString().slice(0, 10));
        $('#el-bk-manual-vendor').val('');
        $('#el-bk-manual-amount').val('');
        $('#el-bk-manual-category').val('');
        $('#el-bk-manual-location').val('');
        $('#el-bk-manual-notes').val('');
        $('#el-bk-manual-image').val('');
    }

    function submitManualReceipt(clearAfter) {
        var $saveBtn     = $('#el-bk-manual-receipt-save-btn');
        var $addAnother  = $('#el-bk-manual-receipt-add-another-btn');
        var $status      = $('#el-bk-manual-receipt-status');

        var fd = new FormData();
        fd.append('action',   'el_core_action');
        fd.append('el_action', 'bk_save_receipt_manual');
        fd.append('nonce',    nonce);
        fd.append('title',    $('#el-bk-manual-title').val());
        fd.append('date',     $('#el-bk-manual-date').val());
        fd.append('vendor',   $('#el-bk-manual-vendor').val());
        fd.append('amount',   $('#el-bk-manual-amount').val());
        fd.append('category', $('#el-bk-manual-category').val());
        fd.append('location', $('#el-bk-manual-location').val());
        fd.append('notes',    $('#el-bk-manual-notes').val());

        var imageFile = $('#el-bk-manual-image')[0].files[0];
        if (imageFile) {
            fd.append('receipt_image', imageFile);
        }

        $saveBtn.prop('disabled', true).text('Saving…');
        $addAnother.prop('disabled', true);
        $status.text('');

        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $saveBtn.prop('disabled', false).text('Save Receipt');
                $addAnother.prop('disabled', false);

                if (res && res.success) {
                    var d = res.data.data || {};
                    var filename = $('#el-bk-manual-vendor').val() || $('#el-bk-manual-title').val() || 'Manual entry';
                    addToReviewQueue(Object.assign({ ai_extracted: false }, d), filename);
                    $status.html('<span style="color:#16a34a;font-weight:600;">✓ Receipt saved.</span>');
                    if (clearAfter) {
                        clearManualReceiptForm();
                        $status.text('');
                    }
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Save failed.';
                    $status.html('<span style="color:#dc2626;">' + $('<span>').text(msg).html() + '</span>');
                }
            },
            error: function () {
                $saveBtn.prop('disabled', false).text('Save Receipt');
                $addAnother.prop('disabled', false);
                $status.html('<span style="color:#dc2626;">Request failed. Please try again.</span>');
            }
        });
    }

    $('#el-bk-manual-receipt-save-btn').on('click', function () {
        submitManualReceipt(false);
    });

    $('#el-bk-manual-receipt-add-another-btn').on('click', function () {
        submitManualReceipt(true);
    });

    // ── Expense Tab: Receipt Badge Panel (GAP 2) ──────────────────────────────

    // Click 📎 badge to toggle receipt detail panel below the expense row
    $(document).on('click', '.el-bk-receipt-badge-btn', function () {
        var $btn      = $(this);
        var $row      = $btn.closest('tr.el-bk-transaction-row');
        var $existing = $row.next('tr.el-bk-expense-receipt-row');

        if ($existing.length) {
            $existing.remove();
            return;
        }

        // Close all open panels
        $('.el-bk-receipt-edit-row').remove();
        $('.el-bk-edit-receipt-btn').text('Edit');
        $('.el-bk-receipt-match-row').remove();
        $('.el-bk-find-match-btn').not(':disabled').text('Find Match');
        $('.el-bk-expense-receipt-row').remove();

        var receiptId = parseInt($btn.data('receiptId') || $btn.data('receipt-id'), 10);
        var colspan   = $row.find('td').length;
        var receipt   = (typeof elBkReceiptMap !== 'undefined') ? (elBkReceiptMap[receiptId] || null) : null;

        var $panel = $('<div class="el-bk-expense-receipt-panel">');

        if (!receipt) {
            $panel.append('<p class="el-bk-receipt-match-empty">Receipt data not available. Please reload the page.</p>');
        } else {
            var thumbHtml = '';
            var ft = (receipt.file_type || '').toLowerCase();
            if (receipt.file_url && (ft === 'jpg' || ft === 'jpeg' || ft === 'png')) {
                thumbHtml = '<div class="el-bk-expense-receipt-thumb-wrap">' +
                    '<a href="' + $('<span>').text(receipt.file_url).html() + '" target="_blank" rel="noopener">' +
                    '<img src="' + $('<span>').text(receipt.file_url).html() + '" alt="receipt"></a></div>';
            } else if (receipt.file_url) {
                thumbHtml = '<div class="el-bk-expense-receipt-thumb-wrap">' +
                    '<a href="' + $('<span>').text(receipt.file_url).html() + '" target="_blank" rel="noopener">📄 View PDF</a></div>';
            }

            var meta = '<div class="el-bk-expense-receipt-meta">';
            if (receipt.ai_extracted_merchant) meta += '<div><strong>Merchant:</strong> ' + $('<span>').text(receipt.ai_extracted_merchant).html() + '</div>';
            if (receipt.ai_extracted_date)     meta += '<div><strong>Date:</strong> '     + $('<span>').text(receipt.ai_extracted_date).html()     + '</div>';
            if (receipt.ai_extracted_amount)   meta += '<div><strong>Amount:</strong> $'  + $('<span>').text(parseFloat(receipt.ai_extracted_amount).toFixed(2)).html() + '</div>';
            if (receipt.ai_extracted_category) meta += '<div><strong>Category:</strong> ' + $('<span>').text(receipt.ai_extracted_category).html() + '</div>';
            if (receipt.location)              meta += '<div><strong>Location:</strong> ' + $('<span>').text(receipt.location).html()               + '</div>';
            meta += '</div>';

            $panel.append('<div class="el-bk-expense-receipt-body">' + thumbHtml + meta + '</div>');
            $panel.append(
                '<div class="el-bk-expense-receipt-actions">' +
                    '<button class="el-btn el-btn-outline el-btn-sm el-bk-expense-detach-btn" data-receipt-id="' + receiptId + '">Detach Receipt</button>' +
                '</div>'
            );
        }

        var $newRow = $('<tr class="el-bk-expense-receipt-row"><td colspan="' + colspan + '"></td></tr>');
        $newRow.find('td').append($panel);
        $row.after($newRow);
    });

    // ── Clients / 1099-NEC ────────────────────────────────────────────────────

    function elBkRebuildPatternHidden() {
        var patterns = [];
        $('#el-bk-client-pattern-tags .el-bk-pattern-tag').each(function () {
            var v = $(this).attr('data-value');
            if (v) patterns.push(v);
        });
        $('#el-bk-client-bank-patterns').val(patterns.join("|"));
    }

    function elBkAddPatternTag(value) {
        value = $.trim(value);
        if (!value) return;
        // Prevent duplicate
        var exists = false;
        $('#el-bk-client-pattern-tags .el-bk-pattern-tag').each(function () {
            if ($(this).attr('data-value') === value) { exists = true; }
        });
        if (exists) return;

        var $tag = $('<span class="el-bk-pattern-tag">')
            .text(value)
            .attr('data-value', value)
            .append(' <button type="button" class="el-bk-pattern-tag-remove" aria-label="Remove">&times;</button>');
        $('#el-bk-client-pattern-tags').append($tag);
        elBkRebuildPatternHidden();
    }

    function elBkResetClientForm() {
        $('#el-bk-client-id').val('');
        $('#el-bk-client-name').val('');
        $('#el-bk-client-short-name').val('');
        $('#el-bk-client-ein').val('');
        $('#el-bk-client-contact-name').val('');
        $('#el-bk-client-contact-email').val('');
        $('#el-bk-client-contact-phone').val('');
        $('#el-bk-client-address').val('');
        $('#el-bk-client-contract-type').val('');
        $('#el-bk-client-status').val('active');
        $('#el-bk-client-notes').val('');
        $('#el-bk-client-pattern-input').val('');
        $('#el-bk-client-pattern-tags').empty();
        $('#el-bk-client-bank-patterns').val('');
        $('#el-bk-client-form-title').text('Add Client');
    }

    // Open form for new client
    $('#el-bk-add-client-btn').on('click', function () {
        elBkResetClientForm();
        $('#el-bk-client-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-client-form').offset().top - 60 }, 200);
    });

    // Open form for edit
    $(document).on('click', '.el-bk-edit-client-btn', function () {
        var $btn = $(this);
        elBkResetClientForm();
        $('#el-bk-client-id').val($btn.data('id'));
        $('#el-bk-client-name').val($btn.data('client-name'));
        $('#el-bk-client-short-name').val($btn.data('short-name'));
        $('#el-bk-client-ein').val($btn.data('ein'));
        $('#el-bk-client-contact-name').val($btn.data('contact-name'));
        $('#el-bk-client-contact-email').val($btn.data('contact-email'));
        $('#el-bk-client-contact-phone').val($btn.data('contact-phone'));
        $('#el-bk-client-address').val($btn.data('address'));
        $('#el-bk-client-contract-type').val($btn.data('contract-type'));
        $('#el-bk-client-status').val($btn.data('status'));
        $('#el-bk-client-notes').val($btn.data('notes'));
        // Rebuild pattern tags
        var raw = $btn.attr('data-bank-patterns') || '[]';
        var lines = [];
        try { lines = JSON.parse(raw); } catch(e) { lines = []; }
        if (!Array.isArray(lines)) lines = [];
        lines.forEach(elBkAddPatternTag);
        $('#el-bk-client-form-title').text('Edit Client');
        $('#el-bk-client-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-client-form').offset().top - 60 }, 200);
    });

    // Cancel form
    $('#el-bk-cancel-client-btn').on('click', function () {
        $('#el-bk-client-form').slideUp();
    });

    // Add pattern tag via button
    $('#el-bk-client-add-pattern-btn').on('click', function () {
        var val = $('#el-bk-client-pattern-input').val();
        elBkAddPatternTag(val);
        $('#el-bk-client-pattern-input').val('').focus();
    });

    // Add pattern tag via Enter key
    $('#el-bk-client-pattern-input').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var val = $(this).val();
            elBkAddPatternTag(val);
            $(this).val('');
        }
    });

    // Remove pattern tag
    $(document).on('click', '.el-bk-pattern-tag-remove', function (e) {
        e.stopPropagation();
        $(this).parent().remove();
        elBkRebuildPatternHidden();
    });

    // Save client
    $('#el-bk-save-client-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Saving…');
        elBkAjax('bk_save_client', {
            id:            $('#el-bk-client-id').val(),
            client_name:   $('#el-bk-client-name').val(),
            short_name:    $('#el-bk-client-short-name').val(),
            ein:           $('#el-bk-client-ein').val(),
            contact_name:  $('#el-bk-client-contact-name').val(),
            contact_email: $('#el-bk-client-contact-email').val(),
            contact_phone: $('#el-bk-client-contact-phone').val(),
            address:       $('#el-bk-client-address').val(),
            contract_type: $('#el-bk-client-contract-type').val(),
            status:        $('#el-bk-client-status').val(),
            bank_patterns: $('#el-bk-client-bank-patterns').val(),
            notes:         $('#el-bk-client-notes').val(),
        }, function () {
            location.reload();
        }, function (msg) {
            $btn.prop('disabled', false).text('Save Client');
            alert('Error: ' + msg);
        });
    });

    // Delete client
    $(document).on('click', '.el-bk-delete-client-btn', function () {
        var name = $(this).data('name') || 'this client';
        if (!confirm('Delete ' + name + '? This cannot be undone.')) return;
        var $btn = $(this).prop('disabled', true).text('Deleting…');
        elBkAjax('bk_delete_client', { id: $btn.data('id') }, function () {
            $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
        }, function (msg) {
            $btn.prop('disabled', false).text('Delete');
            alert('Error: ' + msg);
        });
    });

    // Client search filter
    $('#el-bk-client-search').on('input', function () {
        var q = $(this).val().toLowerCase();
        $('#el-bk-clients-table tbody tr.el-bk-client-row').each(function () {
            var name = ($(this).data('name') || '').toLowerCase();
            $(this).toggle(q === '' || name.indexOf(q) !== -1);
        });
    });

    // Status filter
    $('#el-bk-client-status-filter').on('change', function () {
        var status = $(this).val();
        $('#el-bk-clients-table tbody tr.el-bk-client-row').each(function () {
            var rowStatus = $(this).data('status') || '';
            $(this).toggle(status === '' || rowStatus === status);
        });
    });

    // Detach receipt from expense tab panel
    $(document).on('click', '.el-bk-expense-detach-btn', function () {
        if (!confirm('Detach this receipt from the transaction?')) return;
        var $btn      = $(this).prop('disabled', true).text('Detaching…');
        var receiptId = parseInt($btn.data('receiptId') || $btn.data('receipt-id'), 10);
        var $panelRow = $btn.closest('tr.el-bk-expense-receipt-row');
        var $txnRow   = $panelRow.prev('tr.el-bk-transaction-row');

        elBkAjax('bk_detach_receipt', { receipt_id: receiptId }, function () {
            $panelRow.remove();
            $txnRow.find('.el-bk-receipt-badge-btn').replaceWith('<span class="el-bk-muted">—</span>');
            $txnRow.attr('data-receipt-id', '0');
            if (typeof elBkReceiptMap !== 'undefined') {
                delete elBkReceiptMap[receiptId];
            }
        }, function (msg) {
            $btn.prop('disabled', false).text('Detach Receipt');
            alert('Error: ' + msg);
        });
    });

    // ── 1099-NEC Records ──────────────────────────────────────────────────────

    function elBkResetNecForm() {
        $('#el-bk-nec-id').val('');
        $('#el-bk-nec-doc-attachment-id').val('');
        $('#el-bk-nec-form4852-attachment-id').val('');
        $('#el-bk-nec-supporting-doc-attachment-id').val('');
        $('#el-bk-nec-supporting-doc-title').val('');
        $('#el-bk-nec-client-id').val('');
        $('#el-bk-nec-tax-year').val(new Date().getFullYear());
        $('input[name="el-bk-nec-doc-status"][value="received"]').prop('checked', true);
        $('#el-bk-nec-box1-amount').val('');
        $('#el-bk-nec-date-received').val('');
        $('#el-bk-nec-doc-file').val('');
        $('#el-bk-nec-doc-current').hide().empty();
        $('#el-bk-nec-form4852-file').val('');
        $('#el-bk-nec-form4852-current').hide().empty();
        $('#el-bk-nec-supporting-doc-file').val('');
        $('#el-bk-nec-supporting-doc-current').hide().empty();
        $('#el-bk-nec-substitute-docs').val('');
        $('#el-bk-nec-reconciliation-status').val('pending');
        $('#el-bk-nec-notes').val('');
        $('#el-bk-nec-calculate-result').text('');
        $('#el-bk-nec-form-title').text('Add 1099-NEC Record');
        elBkNecUpdateConditionalFields();
    }

    function elBkNecUpdateConditionalFields() {
        var status = $('input[name="el-bk-nec-doc-status"]:checked').val();
        if (status === 'received') {
            $('#el-bk-nec-date-row, #el-bk-nec-doc-row').show();
            $('#el-bk-nec-substitute-row').hide();
        } else {
            $('#el-bk-nec-date-row, #el-bk-nec-doc-row').hide();
            $('#el-bk-nec-substitute-row').show();
        }
    }

    // Document status radio → show/hide conditional fields
    $(document).on('change', 'input[name="el-bk-nec-doc-status"]', function () {
        elBkNecUpdateConditionalFields();
    });

    // Open 1099 form for a specific client (from "+ 1099" row button)
    $(document).on('click', '.el-bk-add-nec-btn', function () {
        var $btn = $(this);
        elBkResetNecForm();
        $('#el-bk-nec-client-id').val($btn.attr('data-client-id'));
        $('#el-bk-nec-form-title').text('Add 1099-NEC: ' + ($btn.attr('data-client-name') || ''));
        $('#el-bk-nec-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-nec-form').offset().top - 60 }, 200);
    });

    // Open 1099 form for editing existing record
    $(document).on('click', '.el-bk-edit-nec-btn', function () {
        var $btn = $(this);
        elBkResetNecForm();
        $('#el-bk-nec-id').val($btn.attr('data-id'));
        $('#el-bk-nec-doc-attachment-id').val($btn.attr('data-document-attachment-id') || '');
        $('#el-bk-nec-form4852-attachment-id').val($btn.attr('data-form-4852-attachment-id') || '');
        $('#el-bk-nec-supporting-doc-attachment-id').val($btn.attr('data-supporting-doc-attachment-id') || '');
        $('#el-bk-nec-supporting-doc-title').val($btn.attr('data-supporting-doc-title') || '');
        $('#el-bk-nec-client-id').val($btn.attr('data-client-id'));
        $('#el-bk-nec-tax-year').val($btn.attr('data-tax-year'));
        $('input[name="el-bk-nec-doc-status"][value="' + $btn.attr('data-document-status') + '"]').prop('checked', true);
        $('#el-bk-nec-box1-amount').val($btn.attr('data-box1-amount'));
        $('#el-bk-nec-date-received').val($btn.attr('data-date-received') || '');
        $('#el-bk-nec-substitute-docs').val($btn.attr('data-substitute-docs') || '');
        $('#el-bk-nec-reconciliation-status').val($btn.attr('data-reconciliation-status') || 'pending');
        $('#el-bk-nec-notes').val($btn.attr('data-notes') || '');
        elBkNecUpdateConditionalFields();
        var docUrl = $btn.attr('data-doc-url') || '';
        if (docUrl) {
            $('#el-bk-nec-doc-current')
                .html('Current doc: <a href="' + docUrl + '" target="_blank">View</a> — upload a new file to replace')
                .show();
        }
        var form4852Url = $btn.attr('data-form4852-url') || '';
        if (form4852Url) {
            $('#el-bk-nec-form4852-current')
                .html('Current Form 4852: <a href="' + form4852Url + '" target="_blank">View</a> — upload a new file to replace')
                .show();
        }
        var supportingUrl = $btn.attr('data-supporting-url') || '';
        if (supportingUrl) {
            var supportingTitle = $btn.attr('data-supporting-doc-title') || 'Supporting Doc';
            $('#el-bk-nec-supporting-doc-current')
                .html('Current: <a href="' + supportingUrl + '" target="_blank">' + $('<span>').text(supportingTitle).html() + '</a> — upload a new file to replace')
                .show();
        }
        $('#el-bk-nec-form-title').text('Edit 1099-NEC Record');
        $('#el-bk-nec-form').slideDown();
        $('html, body').animate({ scrollTop: $('#el-bk-nec-form').offset().top - 60 }, 200);
    });

    // Cancel
    $('#el-bk-cancel-nec-btn').on('click', function () {
        $('#el-bk-nec-form').slideUp();
    });

    // Calculate from Deposits
    $('#el-bk-nec-calculate-btn').on('click', function () {
        var clientId = $('#el-bk-nec-client-id').val();
        var taxYear  = $('#el-bk-nec-tax-year').val();
        if (!clientId || !taxYear) {
            alert('Please select a client and enter a tax year first.');
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Calculating…');
        elBkAjax('bk_calculate_1099_from_deposits', { client_id: clientId, tax_year: taxYear }, function (res) {
            $btn.prop('disabled', false).text('Calculate from Deposits');
            var total = (res && typeof res.total !== 'undefined') ? parseFloat(res.total) : 0;
            $('#el-bk-nec-box1-amount').val(total.toFixed(2));
            $('#el-bk-nec-calculate-result').text('Matched deposits: $' + total.toFixed(2));
        }, function (msg) {
            $btn.prop('disabled', false).text('Calculate from Deposits');
            alert('Error: ' + msg);
        });
    });

    // Save 1099-NEC record (FormData to support file upload)
    $('#el-bk-save-nec-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Saving…');
        var fd = new FormData();
        fd.append('action',                  'el_core_action');
        fd.append('el_action',               'bk_save_1099');
        fd.append('nonce',                   nonce);
        fd.append('id',                      $('#el-bk-nec-id').val());
        fd.append('document_attachment_id',  $('#el-bk-nec-doc-attachment-id').val());
        fd.append('form_4852_attachment_id', $('#el-bk-nec-form4852-attachment-id').val());
        fd.append('supporting_doc_attachment_id', $('#el-bk-nec-supporting-doc-attachment-id').val());
        fd.append('supporting_doc_title',    $('#el-bk-nec-supporting-doc-title').val());
        fd.append('client_id',               $('#el-bk-nec-client-id').val());
        fd.append('tax_year',                $('#el-bk-nec-tax-year').val());
        fd.append('document_status',         $('input[name="el-bk-nec-doc-status"]:checked').val());
        fd.append('box1_amount',             $('#el-bk-nec-box1-amount').val());
        fd.append('date_received',           $('#el-bk-nec-date-received').val());
        fd.append('substitute_docs',         $('#el-bk-nec-substitute-docs').val());
        fd.append('reconciliation_status',   $('#el-bk-nec-reconciliation-status').val());
        fd.append('notes',                   $('#el-bk-nec-notes').val());
        var fileInput = document.getElementById('el-bk-nec-doc-file');
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
            fd.append('nec_doc_file', fileInput.files[0]);
        }
        var file4852 = document.getElementById('el-bk-nec-form4852-file');
        if (file4852 && file4852.files && file4852.files.length > 0) {
            fd.append('form_4852_file', file4852.files[0]);
        }
        var fileSupportingDoc = document.getElementById('el-bk-nec-supporting-doc-file');
        if (fileSupportingDoc && fileSupportingDoc.files && fileSupportingDoc.files.length > 0) {
            fd.append('supporting_doc_file', fileSupportingDoc.files[0]);
        }
        $.ajax({
            url: ajax, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Save 1099-NEC Record');
                if (res && res.success) {
                    location.reload();
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Unknown error';
                    alert('Error: ' + msg);
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).text('Save 1099-NEC Record');
                var msg = 'Request failed. Please try again.';
                try {
                    var errRes = JSON.parse(xhr.responseText);
                    if (errRes && errRes.data && errRes.data.message) msg = errRes.data.message;
                } catch (e) {}
                alert('Error: ' + msg);
            }
        });
    });

    // Delete 1099-NEC record
    $(document).on('click', '.el-bk-delete-nec-btn', function () {
        var client = $(this).attr('data-client') || 'this client';
        var year   = $(this).attr('data-year')   || '';
        if (!confirm('Delete the ' + year + ' 1099-NEC for ' + client + '? This cannot be undone.')) return;
        var $btn = $(this).prop('disabled', true).text('Deleting…');
        elBkAjax('bk_delete_1099', { id: $btn.attr('data-id') }, function () {
            $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
        }, function (msg) {
            $btn.prop('disabled', false).text('Delete');
            alert('Error: ' + msg);
        });
    });

    // ── Income Tab — Client Assignment (Phase A.4) ──────────────────────────────

    // Assign client to income transaction
    $(document).on('change', '.el-bk-assign-client-select', function () {
        var $select        = $(this);
        var transactionId  = $select.attr('data-transaction-id');
        var clientId       = $select.val();
        if (!clientId) return;

        elBkAjax('bk_assign_client_to_transaction', {
            transaction_id: transactionId,
            client_id: clientId
        }, function (res) {
            var $cell = $select.parent();
            $cell.html(
                '<span class="el-bk-client-badge">' + res.client_name +
                ' <button class="el-bk-unassign-client" data-transaction-id="' + transactionId + '">\u00d7</button></span>'
            );
            refreshIncomeSummaryWidget();
        }, function (msg) {
            alert('Error: ' + msg);
            $select.val('');
        });
    });

    // Unassign client from income transaction
    $(document).on('click', '.el-bk-unassign-client', function () {
        var transactionId = $(this).attr('data-transaction-id');
        elBkAjax('bk_unassign_client', { transaction_id: transactionId }, function () {
            location.reload();
        });
    });

    // Clear all income for the current tax year
    $(document).on('click', '.el-bk-clear-income-btn', function () {
        var taxYear = $(this).attr('data-tax-year');
        if (!confirm('Delete ALL income transactions for ' + taxYear + '?\n\nThis cannot be undone. Your expenses will not be affected.\n\nClick OK to continue, then re-import your bank statements.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Clearing…');
        elBkAjax('bk_clear_income', { tax_year: taxYear }, function (res) {
            alert(res.message || 'Income cleared. Re-import your bank statements.');
            location.reload();
        }, function (msg) {
            alert('Error: ' + msg);
            $btn.prop('disabled', false).text('Clear All Income');
        });
    });

    // Clear all expenses for the current tax year
    $(document).on('click', '.el-bk-clear-expenses-btn', function () {
        var taxYear = $(this).attr('data-tax-year');
        if (!confirm('Delete ALL expense transactions for ' + taxYear + '?\n\nThis cannot be undone. Your income will not be affected.\n\nClick OK to continue, then re-import your expense statements.')) {
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Clearing…');
        elBkAjax('bk_clear_expenses', { tax_year: taxYear }, function (res) {
            alert(res.message || 'Expenses cleared. Re-import your expense statements.');
            location.reload();
        }, function (msg) {
            alert('Error: ' + msg);
            $btn.prop('disabled', false).text('Clear All Expenses');
        });
    });

    // ── Split Transaction ──────────────────────────────────────────────────────

    var splitTxnId     = 0;
    var splitTxnAmount = 0;

    function escapeHtmlSplit(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function addSplitPiece(amount, category) {
        var $pieces = $('#el-bk-split-pieces');
        var idx     = $pieces.children().length;
        var catHtml = $('#el-bk-split-cat-template').html();
        var $row    = $(
            '<div class="el-bk-split-piece-input" style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">' +
                '<label style="flex:0 0 60px;font-size:12px;color:#666;">$</label>' +
                '<input type="number" class="el-bk-split-amount-input" min="0.01" step="0.01" placeholder="0.00" style="width:110px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;">' +
                '<select class="el-bk-split-cat-select" style="flex:1;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;">' + catHtml + '</select>' +
                '<button type="button" class="el-bk-split-remove-piece" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:18px;line-height:1;padding:0 4px;" title="Remove">×</button>' +
            '</div>'
        );
        if (amount) $row.find('.el-bk-split-amount-input').val(amount);
        if (category) $row.find('.el-bk-split-cat-select').val(category);
        $pieces.append($row);
        updateSplitTally();
    }

    function updateSplitTally() {
        var total = 0;
        $('#el-bk-split-pieces .el-bk-split-amount-input').each(function () {
            var v = parseFloat($(this).val()) || 0;
            total += v;
        });
        total = Math.round(total * 100) / 100;
        var orig    = Math.round(splitTxnAmount * 100) / 100;
        var diff    = Math.round((orig - total) * 100) / 100;
        var $tally  = $('#el-bk-split-tally');
        var $btn    = $('#el-bk-split-confirm-btn');
        var match   = Math.abs(diff) < 0.02;

        if (match) {
            $tally.html('<span style="color:#16a34a;font-weight:600;">✓ Fully allocated: $' + total.toFixed(2) + '</span>');
            $btn.prop('disabled', false);
        } else if (diff > 0) {
            $tally.html('<span style="color:#b45309;">Remaining: $' + diff.toFixed(2) + ' of $' + orig.toFixed(2) + ' to allocate</span>');
            $btn.prop('disabled', true);
        } else {
            $tally.html('<span style="color:#dc2626;">Over by $' + Math.abs(diff).toFixed(2) + ' — reduce piece amounts</span>');
            $btn.prop('disabled', true);
        }
    }

    function openSplitModal($btn) {
        splitTxnId     = parseInt($btn.data('id'), 10);
        splitTxnAmount = parseFloat($btn.data('amount'));
        var merchant   = $btn.data('merchant') || '';
        var date       = $btn.data('date') || '';

        $('#el-bk-split-modal-title').text('Split Expense');
        $('#el-bk-split-info').html(
            '<strong>' + escapeHtmlSplit(merchant) + '</strong>' +
            ' &nbsp;·&nbsp; ' + escapeHtmlSplit(date) +
            ' &nbsp;·&nbsp; <strong>$' + splitTxnAmount.toFixed(2) + '</strong>'
        );
        $('#el-bk-split-pieces').empty();
        addSplitPiece('', '');
        addSplitPiece('', '');
        updateSplitTally();
        $('#el-bk-split-modal').fadeIn(150);
    }

    $(document).on('click', '.el-bk-split-btn', function () {
        openSplitModal($(this));
    });

    $(document).on('click', '.el-bk-split-modal-close', function () {
        $('#el-bk-split-modal').fadeOut(150);
    });

    $(document).on('click', '#el-bk-split-add-piece', function () {
        addSplitPiece('', '');
    });

    $(document).on('click', '.el-bk-split-remove-piece', function () {
        if ($('#el-bk-split-pieces .el-bk-split-piece-input').length <= 2) {
            alert('A split requires at least 2 pieces.');
            return;
        }
        $(this).closest('.el-bk-split-piece-input').remove();
        updateSplitTally();
    });

    $(document).on('input', '.el-bk-split-amount-input', function () {
        updateSplitTally();
    });

    $(document).on('click', '#el-bk-split-confirm-btn', function () {
        var pieces = [];
        var valid  = true;
        $('#el-bk-split-pieces .el-bk-split-piece-input').each(function () {
            var amount   = parseFloat($(this).find('.el-bk-split-amount-input').val()) || 0;
            var category = $(this).find('.el-bk-split-cat-select').val();
            if (amount <= 0) { valid = false; }
            pieces.push({ amount: amount, category: category });
        });
        if (!valid) { alert('Each piece must have an amount greater than zero.'); return; }

        var $btn = $(this).prop('disabled', true).text('Saving…');
        elBkAjax('bk_split_transaction', { transaction_id: splitTxnId, pieces: pieces }, function (res) {
            alert(res.message || 'Transaction split successfully.');
            location.reload();
        }, function (msg) {
            alert('Error: ' + msg);
            $btn.prop('disabled', false).text('Confirm Split');
        });
    });

    // Unsplit — restore parent row
    $(document).on('click', '.el-bk-unsplit-btn', function (e) {
        e.stopPropagation();
        var txnId = $(this).data('id');
        if (!confirm('Remove the split? This will delete the split pieces and restore the original transaction.')) return;
        var $btn = $(this).prop('disabled', true);
        elBkAjax('bk_unsplit_transaction', { transaction_id: txnId }, function (res) {
            alert(res.message || 'Split removed.');
            location.reload();
        }, function (msg) {
            alert('Error: ' + msg);
            $btn.prop('disabled', false);
        });
    });

    // Refresh reconciliation summary widget
    function refreshIncomeSummaryWidget() {
        if (!$('.el-bk-income-summary-widget').length) return;
        elBkAjax('bk_get_income_summary', { tax_year: elBookkeeping.taxYear }, function (res) {
            $('#el-bk-income-reconciled-count').text(res.reconciled_count + ' of ' + res.total_with_1099);
            $('#el-bk-income-unassigned').text(res.unassigned_count + ' ($' + parseFloat(res.unassigned_total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')');
            var pct = res.total_with_1099 > 0 ? Math.round((res.reconciled_count / res.total_with_1099) * 100) : 0;
            $('#el-bk-income-progress-bar').css('width', pct + '%');
        });
    }

    // Load widget on page ready
    $(document).ready(function () {
        refreshIncomeSummaryWidget();
    });

    // Expose shared utilities for Phase A.6 IIFEs
    window.elBkAjax = elBkAjax;
    window.elBkRefreshIncomeSummary = refreshIncomeSummaryWidget;

}(jQuery));

// ═══════════════════════════════════════════════════════════════════
// PHASE A.6.1: RECONCILIATION DETAIL PANEL
// ═══════════════════════════════════════════════════════════════════

(function ($) {
    'use strict';

    // Toggle detail panel open/closed
    $(document).on('click', '.el-bk-view-reconciliation-btn', function () {
        var $btn = $(this);
        var necId = $btn.attr('data-id');
        var $row = $btn.closest('tr');
        var $existingDetail = $row.next('.el-bk-reconciliation-detail-row');

        // If already open for this row, close it
        if ($existingDetail.length && $existingDetail.attr('data-nec-id') === necId) {
            $existingDetail.remove();
            $btn.text('Details');
            return;
        }

        // Close any other open detail rows
        $('.el-bk-reconciliation-detail-row').remove();
        $('.el-bk-view-reconciliation-btn').text('Details');

        $btn.text('Loading\u2026').prop('disabled', true);

        elBkAjax('bk_get_reconciliation', { nec_id: necId }, function (res) {
            $btn.text('Hide').prop('disabled', false);
            $row.after(buildReconciliationPanel(res.data));
        }, function (msg) {
            $btn.text('Details').prop('disabled', false);
            alert('Error: ' + msg);
        });
    });

    function buildReconciliationPanel(data) {
        var clientDisplay = data.short_name || data.client_name;

        var varianceClass = 'el-bk-variance--zero';
        var varianceDisplay = '$0.00 \u2713';
        if (Math.abs(data.variance) >= 0.01) {
            varianceClass = data.variance > 0 ? 'el-bk-variance--positive' : 'el-bk-variance--negative';
            varianceDisplay = (data.variance > 0 ? '+' : '') + '$' +
                Math.abs(data.variance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        var depositsHtml = '';
        if (data.deposits && data.deposits.length > 0) {
            data.deposits.forEach(function (d) {
                depositsHtml += '<tr>' +
                    '<td>' + escapeHtmlRec(d.date) + '</td>' +
                    '<td>$' + parseFloat(d.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                    '<td>' + escapeHtmlRec(d.merchant || '') + '</td>' +
                    '<td>' + escapeHtmlRec(d.bank_account || '') + '</td>' +
                    '</tr>';
            });
        } else {
            depositsHtml = '<tr><td colspan="4" style="text-align:center;color:#6c757d;">No deposits matched to this client for ' + data.tax_year + '</td></tr>';
        }

        var verifiedInfo = '';
        if (data.verified_at) {
            var vDate = new Date(data.verified_at);
            verifiedInfo = '<span class="el-bk-verified-info">Verified on <strong>' +
                vDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
                '</strong></span>';
        }

        var statusBadge = '<span class="el-bk-status-badge el-bk-rec-status--' + data.reconciliation_status + '">' +
            data.reconciliation_status.charAt(0).toUpperCase() + data.reconciliation_status.slice(1) +
            '</span>';

        return '<tr class="el-bk-reconciliation-detail-row" data-nec-id="' + data.nec_id + '">' +
            '<td colspan="10">' +
                '<div class="el-bk-reconciliation-panel">' +
                    '<div class="el-bk-reconciliation-header">' +
                        '<h4>' + escapeHtmlRec(clientDisplay) + ' \u2014 ' + data.tax_year + ' Reconciliation</h4>' +
                        statusBadge +
                    '</div>' +
                    '<div class="el-bk-reconciliation-grid">' +
                        '<div class="el-bk-reconciliation-stat">' +
                            '<span class="el-bk-stat-label">1099-NEC Amount</span>' +
                            '<span class="el-bk-stat-value">$' +
                                data.box1_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                            '</span>' +
                        '</div>' +
                        '<div class="el-bk-reconciliation-stat">' +
                            '<span class="el-bk-stat-label">Matched Deposits (' + data.deposits_count + ')</span>' +
                            '<span class="el-bk-stat-value">$' +
                                data.deposits_total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                            '</span>' +
                        '</div>' +
                        '<div class="el-bk-reconciliation-stat">' +
                            '<span class="el-bk-stat-label">Variance</span>' +
                            '<span class="el-bk-stat-value ' + varianceClass + '">' + varianceDisplay + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="el-bk-reconciliation-deposits">' +
                        '<h5>Matched Deposits</h5>' +
                        '<table class="el-bk-deposits-table widefat">' +
                            '<thead><tr><th>Date</th><th>Amount</th><th>Description</th><th>Bank Account</th></tr></thead>' +
                            '<tbody>' + depositsHtml + '</tbody>' +
                            '<tfoot><tr>' +
                                '<td><strong>Total</strong></td>' +
                                '<td><strong>$' + data.deposits_total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong></td>' +
                                '<td colspan="2"></td>' +
                            '</tr></tfoot>' +
                        '</table>' +
                    '</div>' +
                    '<div class="el-bk-reconciliation-actions">' +
                        '<button class="el-btn el-btn-primary el-bk-verify-reconciliation-btn" data-nec-id="' + data.nec_id + '">Mark as Verified</button>' +
                        verifiedInfo +
                    '</div>' +
                '</div>' +
            '</td>' +
        '</tr>';
    }

    function escapeHtmlRec(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

}(jQuery));

// ═══════════════════════════════════════════════════════════════════
// PHASE A.6.2: VERIFY RECONCILIATION
// ═══════════════════════════════════════════════════════════════════

(function ($) {
    'use strict';

    $(document).on('click', '.el-bk-verify-reconciliation-btn', function () {
        var $btn = $(this);
        var necId = $btn.attr('data-nec-id');

        if (!confirm('Mark this reconciliation as verified? This confirms you have reviewed the deposits against the 1099-NEC.')) {
            return;
        }

        $btn.prop('disabled', true).text('Verifying\u2026');

        elBkAjax('bk_verify_reconciliation', { nec_id: necId }, function (res) {
            var d = res.data;
            var $panel = $btn.closest('.el-bk-reconciliation-panel');

            // Update status badge in the detail panel header
            $panel.find('.el-bk-status-badge')
                .removeClass('el-bk-rec-status--pending el-bk-rec-status--discrepancy el-bk-rec-status--reconciled')
                .addClass('el-bk-rec-status--' + d.status)
                .text(d.status.charAt(0).toUpperCase() + d.status.slice(1));

            // Replace button with verified date
            var vDate = new Date(d.verified_at);
            var verifiedHtml = '<span class="el-bk-verified-info">Verified on <strong>' +
                vDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
                '</strong></span>';
            $btn.after(verifiedHtml);
            $btn.remove();

            // Update the main 1099 table row status badge
            var $mainRow = $('.el-bk-nec-row[data-id="' + necId + '"]');
            $mainRow.find('.el-bk-status-badge[class*="el-bk-rec-status--"]')
                .removeClass('el-bk-rec-status--pending el-bk-rec-status--discrepancy el-bk-rec-status--reconciled')
                .addClass('el-bk-rec-status--' + d.status)
                .text(d.status.charAt(0).toUpperCase() + d.status.slice(1));

            // Refresh the annual summary and income widget
            if (typeof loadAnnualSummary === 'function') {
                loadAnnualSummary();
            }
            if (typeof window.elBkRefreshIncomeSummary === 'function') {
                window.elBkRefreshIncomeSummary();
            }
        }, function (msg) {
            $btn.prop('disabled', false).text('Mark as Verified');
            alert('Error: ' + msg);
        });
    });

}(jQuery));

// ═══════════════════════════════════════════════════════════════════
// PHASE A.6.3: ANNUAL INCOME SUMMARY
// ═══════════════════════════════════════════════════════════════════

(function ($) {
    'use strict';

    window.loadAnnualSummary = function () {
        var $wrap = $('#el-bk-annual-summary-table-wrap');
        if (!$wrap.length) return;

        $wrap.html('<p class="el-bk-loading">Loading summary\u2026</p>');

        elBkAjax('bk_get_annual_summary', { tax_year: elBookkeeping.taxYear }, function (res) {
            var d = res.data;
            if (!d.records || d.records.length === 0) {
                $wrap.html('<p style="color:#6c757d;">No 1099-NEC records for ' + d.tax_year + '. Create one using the \u201c+ 1099\u201d button on a client row.</p>');
                return;
            }
            $wrap.html(buildAnnualSummaryTable(d));
        }, function (msg) {
            $wrap.html('<p style="color:#dc3545;">Error loading summary: ' + msg + '</p>');
        });
    };

    function buildAnnualSummaryTable(data) {
        var rowsHtml = '';

        data.records.forEach(function (r) {
            var clientDisplay = r.short_name || r.client_name;

            var varianceClass = 'el-bk-variance--zero';
            var varianceDisplay = '$0.00 \u2713';
            if (Math.abs(r.variance) >= 0.01) {
                varianceClass = r.variance > 0 ? 'el-bk-variance--positive' : 'el-bk-variance--negative';
                varianceDisplay = (r.variance > 0 ? '+' : '-') + '$' +
                    Math.abs(r.variance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            var verifiedDisplay = r.verified_at
                ? new Date(r.verified_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : '\u2014';

            rowsHtml += '<tr data-nec-id="' + r.nec_id + '">' +
                '<td><strong>' + escHtml(r.short_name || r.client_name) + '</strong></td>' +
                '<td><span class="el-bk-status-badge el-bk-nec-status--' + r.document_status + '">' + cap(r.document_status) + '</span></td>' +
                '<td>$' + r.box1_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                '<td>$' + r.deposits_total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                '<td class="' + varianceClass + '">' + varianceDisplay + '</td>' +
                '<td><span class="el-bk-status-badge el-bk-rec-status--' + r.reconciliation_status + '">' + cap(r.reconciliation_status) + '</span></td>' +
                '<td>' + verifiedDisplay + '</td>' +
            '</tr>';
        });

        var tvClass = 'el-bk-variance--zero';
        var tvDisplay = '$0.00 \u2713';
        if (Math.abs(data.total_variance) >= 0.01) {
            tvClass = data.total_variance > 0 ? 'el-bk-variance--positive' : 'el-bk-variance--negative';
            tvDisplay = (data.total_variance > 0 ? '+' : '-') + '$' +
                Math.abs(data.total_variance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        return '<table class="el-bk-annual-summary-table widefat">' +
            '<thead><tr>' +
                '<th>Client</th><th>1099 Status</th><th>1099 Amount</th>' +
                '<th>Deposits Total</th><th>Variance</th><th>Reconciliation</th><th>Verified</th>' +
            '</tr></thead>' +
            '<tbody>' + rowsHtml + '</tbody>' +
            '<tfoot><tr class="el-bk-summary-totals">' +
                '<td><strong>Totals (' + data.count + ' clients)</strong></td>' +
                '<td></td>' +
                '<td><strong>$' + data.total_1099.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong></td>' +
                '<td><strong>$' + data.total_deposits.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong></td>' +
                '<td><strong class="' + tvClass + '">' + tvDisplay + '</strong></td>' +
                '<td colspan="2"></td>' +
            '</tr></tfoot>' +
        '</table>';
    }

    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function cap(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Refresh button
    $(document).on('click', '#el-bk-refresh-summary-btn', function () {
        loadAnnualSummary();
    });

    // Auto-load when the Clients tab is active
    $(document).ready(function () {
        if ($('#el-bk-annual-summary-table-wrap').length) {
            loadAnnualSummary();
        }
    });

    // ── INVOICES (Phase A.7) ──────────────────────────────────────────────────

    // Reset invoice form
    function elBkResetInvoiceForm() {
        $('#el-bk-invoice-id').val('');
        $('#el-bk-invoice-doc-attachment-id').val('');
        $('#el-bk-invoice-ai-extracted-data').val('');
        $('#el-bk-invoice-client-id').val('');
        $('#el-bk-invoice-number').val('');
        $('#el-bk-invoice-date').val(new Date().toISOString().split('T')[0]);
        $('#el-bk-invoice-amount').val('');
        $('#el-bk-invoice-status').val('unpaid');
        $('#el-bk-invoice-withholding-type').val('');
        $('#el-bk-invoice-withholding-amount').val('');
        $('#el-bk-invoice-withholding-amount-row').hide();
        $('#el-bk-invoice-description').val('');
        $('#el-bk-invoice-doc-file').val('');
        $('#el-bk-invoice-doc-current').hide().empty();
        $('#el-bk-invoice-notes').val('');
        $('#el-bk-invoice-form-title').text('Add Invoice');
    }
    window.elBkResetInvoiceForm = elBkResetInvoiceForm;

    // Show/hide withholding amount based on type
    $('#el-bk-invoice-withholding-type').on('change', function () {
        var type = $(this).val();
        if (type) {
            $('#el-bk-invoice-withholding-amount-row').show();
        } else {
            $('#el-bk-invoice-withholding-amount-row').hide();
            $('#el-bk-invoice-withholding-amount').val('');
        }
    });

    // Add invoice button
    $('#el-bk-add-invoice-btn').on('click', function () {
        elBkResetInvoiceForm();
        $('#el-bk-invoice-form').slideDown(200);
        $('#el-bk-invoice-client-id').focus();
    });

    // Cancel invoice form
    $('#el-bk-cancel-invoice-btn').on('click', function () {
        $('#el-bk-invoice-form').slideUp(200);
        elBkResetInvoiceForm();
    });

    // Edit invoice button
    $(document).on('click', '.el-bk-edit-invoice-btn', function () {
        var $btn = $(this);
        elBkResetInvoiceForm();

        $('#el-bk-invoice-id').val($btn.attr('data-id'));
        $('#el-bk-invoice-client-id').val($btn.attr('data-client-id'));
        $('#el-bk-invoice-number').val($btn.attr('data-invoice-number'));
        $('#el-bk-invoice-date').val($btn.attr('data-invoice-date'));
        $('#el-bk-invoice-amount').val($btn.attr('data-amount'));
        $('#el-bk-invoice-status').val($btn.attr('data-status'));
        $('#el-bk-invoice-withholding-type').val($btn.attr('data-withholding-type'));
        $('#el-bk-invoice-withholding-amount').val($btn.attr('data-withholding-amount'));
        $('#el-bk-invoice-description').val($btn.attr('data-description'));
        $('#el-bk-invoice-doc-attachment-id').val($btn.attr('data-document-attachment-id'));
        $('#el-bk-invoice-notes').val($btn.attr('data-notes'));

        if ($btn.attr('data-withholding-type')) {
            $('#el-bk-invoice-withholding-amount-row').show();
        }

        var docUrl = $btn.attr('data-doc-url');
        if (docUrl) {
            $('#el-bk-invoice-doc-current')
                .html('<a href="' + docUrl + '" target="_blank">View current document</a>')
                .show();
        }

        $('#el-bk-invoice-form-title').text('Edit Invoice');
        $('#el-bk-invoice-form').slideDown(200);
        $('html, body').animate({ scrollTop: $('#el-bk-invoice-form').offset().top - 100 }, 300);
    });

    // Save invoice (shared function)
    function elBkSaveInvoice(saveAndAdd) {
        var $saveBtn    = $('#el-bk-save-invoice-btn');
        var $saveAddBtn = $('#el-bk-save-invoice-add-btn');
        $saveBtn.prop('disabled', true).text('Saving…');
        $saveAddBtn.prop('disabled', true);

        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_save_invoice');
        fd.append('nonce', elBookkeeping.nonce);
        fd.append('id', $('#el-bk-invoice-id').val());
        fd.append('document_attachment_id', $('#el-bk-invoice-doc-attachment-id').val());
        fd.append('client_id', $('#el-bk-invoice-client-id').val());
        fd.append('invoice_number', $('#el-bk-invoice-number').val());
        fd.append('invoice_date', $('#el-bk-invoice-date').val());
        fd.append('amount', $('#el-bk-invoice-amount').val());
        fd.append('status', $('#el-bk-invoice-status').val());
        fd.append('withholding_type', $('#el-bk-invoice-withholding-type').val());
        fd.append('withholding_amount', $('#el-bk-invoice-withholding-amount').val());
        fd.append('description', $('#el-bk-invoice-description').val());
        fd.append('notes', $('#el-bk-invoice-notes').val());
        fd.append('ai_extracted_data', $('#el-bk-invoice-ai-extracted-data').val());
        fd.append('save_and_add', saveAndAdd ? '1' : '');

        var fileInput = document.getElementById('el-bk-invoice-doc-file');
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
            fd.append('invoice_doc_file', fileInput.files[0]);
        }

        $.ajax({
            url: elBookkeeping.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                $saveBtn.prop('disabled', false).text('Save Invoice');
                $saveAddBtn.prop('disabled', false);
                if (res && res.success) {
                    if (saveAndAdd) {
                        elBkResetInvoiceForm();
                        $('#el-bk-invoice-form').show();
                        $('#el-bk-invoice-client-id').focus();
                        alert('Invoice saved! Ready for next entry.');
                    } else {
                        location.reload();
                    }
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Unknown error';
                    alert('Error: ' + msg);
                }
            },
            error: function (xhr) {
                $saveBtn.prop('disabled', false).text('Save Invoice');
                $saveAddBtn.prop('disabled', false);
                var msg = 'Request failed. Please try again.';
                try {
                    var errRes = JSON.parse(xhr.responseText);
                    if (errRes && errRes.data && errRes.data.message) msg = errRes.data.message;
                } catch (e) {}
                alert('Error: ' + msg);
            }
        });
    }
    window.elBkSaveInvoice = elBkSaveInvoice;

    // Save invoice button
    $('#el-bk-save-invoice-btn').on('click', function () {
        elBkSaveInvoice(false);
    });

    // Save & Add Another button
    $('#el-bk-save-invoice-add-btn').on('click', function () {
        elBkSaveInvoice(true);
    });

    // Delete invoice
    $(document).on('click', '.el-bk-delete-invoice-btn', function () {
        var num  = $(this).attr('data-number') || 'this invoice';
        if (!confirm('Delete invoice ' + num + '? This cannot be undone.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Deleting…');
        elBkAjax('bk_delete_invoice', { id: $btn.attr('data-id') }, function () {
            $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
        }, function (msg) {
            $btn.prop('disabled', false).text('Delete');
            alert('Error: ' + msg);
        });
    });

    // Invoice search filter
    $('#el-bk-invoice-search').on('input', function () {
        var q = $(this).val().toLowerCase();
        $('#el-bk-invoice-table tbody tr.el-bk-invoice-row').each(function () {
            var searchText = $(this).attr('data-search') || '';
            $(this).toggle(q === '' || searchText.indexOf(q) !== -1);
        });
    });

    // Client filter
    $('#el-bk-invoice-client-filter').on('change', function () {
        var clientId = $(this).val();
        $('#el-bk-invoice-table tbody tr.el-bk-invoice-row').each(function () {
            var rowClientId = $(this).attr('data-client-id') || '';
            $(this).toggle(clientId === '' || rowClientId === clientId);
        });
    });

    // Status filter
    $('#el-bk-invoice-status-filter').on('change', function () {
        var status = $(this).val();
        $('#el-bk-invoice-table tbody tr.el-bk-invoice-row').each(function () {
            var rowStatus = $(this).attr('data-status') || '';
            $(this).toggle(status === '' || rowStatus === status);
        });
    });

    // ── INVOICE UPLOAD (Phase A.8) ────────────────────────────────────────────────

    var INVOICE_MAX_BYTES = 10 * 1024 * 1024;
    var INVOICE_EXTS = ['jpg', 'jpeg', 'png', 'pdf'];

    function escapeHtml(str) {
        if (!str) return '';
        return $('<span>').text(str).html();
    }
    function escapeAttr(str) {
        if (!str) return '';
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /**
     * Upload a single invoice file to bk_upload_invoice.
     */
    function uploadInvoiceFile(file, onDone) {
        var fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'bk_upload_invoice');
        fd.append('nonce', elBookkeeping.nonce);
        fd.append('invoice_file', file);

        $.ajax({
            url: elBookkeeping.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res && res.success) {
                    onDone(null, res.data.data || res.data || {});
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
     * Build a review card and append it to the review queue.
     */
    function addInvoiceToReviewQueue(data, filename) {
        var $queue = $('#el-bk-invoice-review-queue');

        var thumbHtml;
        if (data.is_image && data.file_url) {
            thumbHtml = '<img src="' + escapeAttr(data.file_url) + '" alt="invoice">';
        } else {
            thumbHtml = '<div class="el-bk-invoice-review-thumb-placeholder">📄</div>';
        }

        var clientOptions = '<option value="">— Select Client —</option>';
        (window.elBkInvoiceClients || []).forEach(function (c) {
            var selected = (data.matched_client_id && data.matched_client_id == c.id) ? ' selected' : '';
            var label = c.name + (c.short ? ' (' + c.short + ')' : '');
            clientOptions += '<option value="' + c.id + '"' + selected + '>' + escapeHtml(label) + '</option>';
        });

        var badges = '';
        if (data.ai_extracted) {
            badges += '<span class="el-bk-invoice-review-badge el-bk-invoice-review-badge--ai">✓ AI Extracted</span>';
        }
        if (data.matched_client_id) {
            badges += '<span class="el-bk-invoice-review-badge el-bk-invoice-review-badge--matched">Client Matched: ' + escapeHtml(data.matched_client_name) + '</span>';
        } else if (data.client_name) {
            badges += '<span class="el-bk-invoice-review-badge el-bk-invoice-review-badge--no-match">Client Not Found: ' + escapeHtml(data.client_name) + '</span>';
        }

        var cardClass = 'el-bk-invoice-review-card' + (data.ai_extracted ? '' : ' el-bk-invoice-review-card--no-ai');

        var $card = $('<div>').addClass(cardClass).attr('data-attachment-id', data.attachment_id).html(
            '<div class="el-bk-invoice-review-thumb">' + thumbHtml + '</div>' +
            '<div class="el-bk-invoice-review-body">' +
                '<div class="el-bk-invoice-review-filename">' + escapeHtml(filename) + '</div>' +
                '<div class="el-bk-invoice-review-badges">' + badges + '</div>' +
                '<div class="el-bk-invoice-review-fields">' +
                    '<div class="el-bk-invoice-review-field">' +
                        '<label>Client</label>' +
                        '<select class="el-bk-review-client">' + clientOptions + '</select>' +
                    '</div>' +
                    '<div class="el-bk-invoice-review-field">' +
                        '<label>Invoice #</label>' +
                        '<input type="text" class="el-bk-review-invoice-number" value="' + escapeAttr(data.invoice_number || '') + '">' +
                    '</div>' +
                    '<div class="el-bk-invoice-review-field">' +
                        '<label>Date</label>' +
                        '<input type="date" class="el-bk-review-invoice-date" value="' + escapeAttr(data.invoice_date || '') + '">' +
                    '</div>' +
                    '<div class="el-bk-invoice-review-field">' +
                        '<label>Amount ($)</label>' +
                        '<input type="text" class="el-bk-review-amount" value="' + escapeAttr(data.amount || '') + '" inputmode="decimal">' +
                    '</div>' +
                    '<div class="el-bk-invoice-review-field">' +
                        '<label>Withholding ($)</label>' +
                        '<input type="text" class="el-bk-review-withholding" value="' + escapeAttr(data.withholding_amount || '') + '" placeholder="0.00" inputmode="decimal">' +
                    '</div>' +
                    '<div class="el-bk-invoice-review-field el-bk-invoice-review-field--wide">' +
                        '<label>Description</label>' +
                        '<input type="text" class="el-bk-review-description" value="' + escapeAttr(data.description || '') + '">' +
                    '</div>' +
                '</div>' +
                '<input type="hidden" class="el-bk-review-ai-raw" value="' + escapeAttr(data.ai_raw || '') + '">' +
                '<input type="hidden" class="el-bk-review-withholding-type" value="' + escapeAttr(data.withholding_type || '') + '">' +
                '<div class="el-bk-invoice-review-actions">' +
                    '<button class="el-btn el-btn-primary el-bk-review-confirm-btn">Save Invoice</button>' +
                    '<button class="el-btn el-btn-outline el-bk-review-another-btn">Save & Upload Another</button>' +
                '</div>' +
            '</div>' +
            '<button class="el-bk-invoice-review-dismiss" title="Dismiss">✕</button>'
        );

        $queue.append($card);
    }

    /**
     * Validate and upload an array of files; build review cards as each completes.
     */
    function processInvoiceUploads(files) {
        if (!files || !files.length) return;

        var $zone   = $('#el-bk-invoice-upload-zone');
        var $status = $('#el-bk-invoice-upload-status');
        var fileArr = Array.from(files);
        var total   = fileArr.length;
        var done    = 0;
        var uploaded = 0;
        var errors   = 0;

        $zone.addClass('el-bk-upload-zone--uploading');
        $status.text('Uploading 1 of ' + total + '…');

        fileArr.forEach(function (file) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();

            if (INVOICE_EXTS.indexOf(ext) === -1) {
                $status.append(' | Skipped "' + escapeHtml(file.name) + '": unsupported type.');
                done++; errors++;
                if (done === total) finishInvoiceUploads();
                return;
            }
            if (file.size > INVOICE_MAX_BYTES) {
                $status.append(' | Skipped "' + escapeHtml(file.name) + '": exceeds 10 MB.');
                done++; errors++;
                if (done === total) finishInvoiceUploads();
                return;
            }

            uploadInvoiceFile(file, function (err, data) {
                done++;
                if (err) {
                    errors++;
                    $status.append(' | Error: ' + escapeHtml(err));
                } else {
                    uploaded++;
                    addInvoiceToReviewQueue(data, file.name);
                    if (done < total) {
                        $status.text('Uploading ' + (done + 1) + ' of ' + total + '…');
                    }
                }
                if (done === total) finishInvoiceUploads();
            });
        });

        function finishInvoiceUploads() {
            $zone.removeClass('el-bk-upload-zone--uploading');
            $('#el-bk-invoice-file-input').val('');

            if (errors === 0 && uploaded > 0) {
                $status.text(uploaded + (uploaded === 1 ? ' invoice' : ' invoices') + ' uploaded. Review and confirm below.');
            } else if (uploaded > 0) {
                $status.prepend(uploaded + ' uploaded, ' + errors + ' skipped. ');
            } else {
                $status.text(errors + ' file(s) could not be uploaded.');
            }
        }
    }

    // Browse button
    $('#el-bk-invoice-browse-btn').on('click', function () {
        $('#el-bk-invoice-file-input').trigger('click');
    });

    // File picker change
    $('#el-bk-invoice-file-input').on('change', function () {
        processInvoiceUploads(this.files);
    });

    // "Add Manually" button (replaces old #el-bk-add-invoice-btn)
    $('#el-bk-invoice-manual-btn').on('click', function () {
        elBkResetInvoiceForm();
        $('#el-bk-invoice-form').slideDown(200);
        $('#el-bk-invoice-client-id').focus();
    });

    // Drag-and-drop onto upload zone
    $('#el-bk-invoice-upload-zone')
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
            var dt = e.originalEvent.dataTransfer;
            if (dt && dt.files) {
                processInvoiceUploads(dt.files);
            }
        });

    // Dismiss a review card
    $(document).on('click', '.el-bk-invoice-review-dismiss', function () {
        $(this).closest('.el-bk-invoice-review-card').fadeOut(200, function () {
            $(this).remove();
        });
    });

    // Confirm (save) from a review card
    $(document).on('click', '.el-bk-review-confirm-btn, .el-bk-review-another-btn', function () {
        var $btn        = $(this);
        var $card       = $btn.closest('.el-bk-invoice-review-card');
        var saveAnother = $btn.hasClass('el-bk-review-another-btn');

        var clientId        = $card.find('.el-bk-review-client').val();
        var invoiceNumber   = $card.find('.el-bk-review-invoice-number').val();
        var invoiceDate     = $card.find('.el-bk-review-invoice-date').val();
        var amount          = $card.find('.el-bk-review-amount').val();
        var withholding     = $card.find('.el-bk-review-withholding').val();
        var withholdingType = $card.find('.el-bk-review-withholding-type').val();
        var description     = $card.find('.el-bk-review-description').val();
        var attachmentId    = $card.attr('data-attachment-id');
        var aiRaw           = $card.find('.el-bk-review-ai-raw').val();

        if (!clientId) {
            alert('Please select a client.');
            $card.find('.el-bk-review-client').focus();
            return;
        }
        if (!invoiceDate) {
            alert('Please enter an invoice date.');
            $card.find('.el-bk-review-invoice-date').focus();
            return;
        }
        if (!amount || parseFloat(amount) <= 0) {
            alert('Please enter an amount greater than zero.');
            $card.find('.el-bk-review-amount').focus();
            return;
        }

        $btn.prop('disabled', true).text('Saving…');
        $card.find('button').prop('disabled', true);

        // Auto-set withholding type if amount entered but type missing
        if (withholding && parseFloat(withholding) > 0 && !withholdingType) {
            withholdingType = 'CA Withholding';
        }

        elBkAjax('bk_save_invoice', {
            client_id:              clientId,
            invoice_number:         invoiceNumber,
            invoice_date:           invoiceDate,
            amount:                 amount,
            status:                 'unpaid',
            withholding_amount:     withholding,
            withholding_type:       withholdingType,
            description:            description,
            document_attachment_id: attachmentId,
            ai_extracted_data:      aiRaw,
            notes:                  ''
        }, function () {
            $card.fadeOut(200, function () { $(this).remove(); });
            if (saveAnother) {
                $('#el-bk-invoice-upload-status').text('Invoice saved! Upload another or click "Add Manually".');
            } else {
                location.reload();
            }
        }, function (msg) {
            $btn.prop('disabled', false).text($btn.hasClass('el-bk-review-another-btn') ? 'Save & Upload Another' : 'Save Invoice');
            $card.find('button').prop('disabled', false);
            alert('Error: ' + msg);
        });
    });

// ── INVOICE-DEPOSIT MATCHING (Phase A.9) ──────────────────────────────────────

function escapeHtmlMatch(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

var matchModalMode = null; // 'invoice' or 'deposit'
var matchSourceId = null;
var matchSourceData = null;
var selectedMatchId = null;
var selectedMatchData = null;

function openInvoiceMatchModal(invoiceId) {
    matchModalMode = 'invoice';
    matchSourceId = invoiceId;
    selectedMatchId = null;
    selectedMatchData = null;

    $('#el-bk-match-modal-title').text('Match Invoice to Deposit');
    $('#el-bk-match-suggestions').html('<p class="el-bk-loading">Loading suggestions\u2026</p>');
    $('#el-bk-match-withholding').hide();
    $('#el-bk-match-confirm-btn').prop('disabled', true);
    $('#el-bk-match-modal').fadeIn(200);

    elBkAjax('bk_suggest_invoice_matches', { invoice_id: invoiceId }, function(res) {
        var payload = res.data || res;
        matchSourceData = payload.invoice;
        renderMatchSource(payload.invoice, 'invoice');
        renderMatchSuggestions(payload.suggestions, 'deposit');
    }, function(msg) {
        $('#el-bk-match-suggestions').html('<p class="el-bk-error">Error: ' + escapeHtmlMatch(msg) + '</p>');
    });
}

function openDepositMatchModal(transactionId) {
    matchModalMode = 'deposit';
    matchSourceId = transactionId;
    selectedMatchId = null;
    selectedMatchData = null;

    $('#el-bk-match-modal-title').text('Link Deposit to Invoice');
    $('#el-bk-match-suggestions').html('<p class="el-bk-loading">Loading suggestions\u2026</p>');
    $('#el-bk-match-withholding').hide();
    $('#el-bk-match-confirm-btn').prop('disabled', true);
    $('#el-bk-match-modal').fadeIn(200);

    elBkAjax('bk_suggest_deposit_matches', { transaction_id: transactionId }, function(res) {
        var payload = res.data || res;
        matchSourceData = payload.transaction;
        renderMatchSource(payload.transaction, 'deposit');
        renderMatchSuggestions(payload.suggestions, 'invoice');
    }, function(msg) {
        $('#el-bk-match-suggestions').html('<p class="el-bk-error">Error: ' + escapeHtmlMatch(msg) + '</p>');
    });
}

function renderMatchSource(data, type) {
    var html = '<div class="el-bk-match-source-card">';
    if (type === 'invoice') {
        html += '<div class="el-bk-match-source-label">Invoice</div>';
        html += '<div class="el-bk-match-source-main">';
        html += '<strong>' + escapeHtmlMatch(data.invoice_number || 'No Number') + '</strong>';
        html += ' \u2014 $' + escapeHtmlMatch(formatMatchNumber(data.amount));
        html += '</div>';
        html += '<div class="el-bk-match-source-meta">';
        html += escapeHtmlMatch(data.client_name || 'No Client') + ' \u2022 ' + escapeHtmlMatch(data.invoice_date);
        html += '</div>';
    } else {
        html += '<div class="el-bk-match-source-label">Deposit</div>';
        html += '<div class="el-bk-match-source-main">';
        html += '<strong>$' + escapeHtmlMatch(formatMatchNumber(data.amount)) + '</strong>';
        html += ' \u2014 ' + escapeHtmlMatch(data.date);
        html += '</div>';
        html += '<div class="el-bk-match-source-meta">';
        html += escapeHtmlMatch(data.merchant);
        if (data.client_name) html += ' \u2022 ' + escapeHtmlMatch(data.client_name);
        html += '</div>';
    }
    html += '</div>';
    $('#el-bk-match-source').html(html);
}

function renderMatchSuggestions(suggestions, type) {
    if (!suggestions || !suggestions.length) {
        $('#el-bk-match-suggestions').html('<p class="el-bk-empty">No matching ' + type + 's found.</p>');
        return;
    }

    var html = '';
    suggestions.forEach(function(s) {
        var scoreClass = s.score >= 100 ? 'high' : (s.score >= 50 ? 'medium' : 'low');
        var id = type === 'deposit' ? s.transaction_id : s.invoice_id;
        var alreadyLinked = s.already_linked || false;

        html += '<div class="el-bk-match-suggestion' + (alreadyLinked ? ' el-bk-match-suggestion--linked' : '') + '"';
        html += ' data-id="' + id + '" data-type="' + type + '"';
        html += ' data-amount="' + s.amount + '"';
        html += ' data-suggested-withholding="' + (s.suggested_withholding || 0) + '"';
        html += ' data-match-type="' + (s.match_type || '') + '">';

        html += '<div class="el-bk-match-suggestion-main">';
        if (type === 'deposit') {
            html += '<div class="el-bk-match-suggestion-amount">$' + escapeHtmlMatch(formatMatchNumber(s.amount)) + '</div>';
            html += '<div class="el-bk-match-suggestion-details">';
            html += '<span>' + escapeHtmlMatch(s.date) + '</span>';
            html += '<span>' + escapeHtmlMatch(s.merchant) + '</span>';
            if (alreadyLinked) {
                var invoiceWord = s.linked_count === 1 ? 'invoice' : 'invoices';
                html += '<span class="el-bk-match-already-linked">&#8627; already linked to ' + s.linked_count + ' ' + invoiceWord + '</span>';
            }
            html += '</div>';
        } else {
            html += '<div class="el-bk-match-suggestion-amount">' + escapeHtmlMatch(s.invoice_number || 'No #') + '</div>';
            html += '<div class="el-bk-match-suggestion-details">';
            html += '<span>$' + escapeHtmlMatch(formatMatchNumber(s.amount)) + '</span>';
            html += '<span>' + escapeHtmlMatch(s.invoice_date) + '</span>';
            html += '<span>' + escapeHtmlMatch(s.client_name || '') + '</span>';
            html += '</div>';
        }
        html += '</div>';

        html += '<div class="el-bk-match-suggestion-meta">';
        html += '<span class="el-bk-match-score el-bk-match-score--' + scoreClass + '">' + s.score + '</span>';
        html += '<span class="el-bk-match-type">' + escapeHtmlMatch(formatMatchType(s.match_type)) + '</span>';
        html += '</div>';

        html += '<button class="el-btn el-btn-outline el-btn-sm el-bk-select-match-btn">Select</button>';
        html += '</div>';
    });

    $('#el-bk-match-suggestions').html(html);

    // Re-bind live search
    $('#el-bk-match-search').val('').off('input.matchsearch').on('input.matchsearch', function() {
        var q = $(this).val().toLowerCase();
        $('#el-bk-match-suggestions .el-bk-match-suggestion').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(!q || text.indexOf(q) !== -1);
        });
    });
}

function formatMatchType(type) {
    var labels = {
        'exact':        'Exact Match',
        'withholding':  'Withholding Match',
        'amount_close': 'Amount Close',
        'client_match': 'Client Match',
        'partial':      'Partial Match'
    };
    return labels[type] || type || 'Suggested';
}

function formatMatchNumber(n) {
    return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

$(document).on('click', '.el-bk-select-match-btn', function() {
    var $card = $(this).closest('.el-bk-match-suggestion');
    $('.el-bk-match-suggestion').removeClass('el-bk-match-suggestion--selected');
    $card.addClass('el-bk-match-suggestion--selected');

    selectedMatchId   = $card.attr('data-id');
    selectedMatchData = {
        amount:              parseFloat($card.attr('data-amount')),
        suggestedWithholding: parseFloat($card.attr('data-suggested-withholding') || 0),
        matchType:           $card.attr('data-match-type')
    };

    var sourceAmount = parseFloat(matchSourceData.amount);
    var targetAmount = selectedMatchData.amount;
    var diff = Math.abs(sourceAmount - targetAmount);

    if (diff > 0.01) {
        var suggestedWithholding = selectedMatchData.suggestedWithholding || (sourceAmount - targetAmount);
        if (suggestedWithholding > 0) {
            $('#el-bk-match-withholding-amount').val(suggestedWithholding.toFixed(2));
            $('#el-bk-match-withholding-type').val('CA Withholding');
        }
        updateWithholdingCalculation();
        $('#el-bk-match-withholding').slideDown(200);
    } else {
        $('#el-bk-match-withholding').hide();
        $('#el-bk-match-withholding-amount').val('');
    }

    $('#el-bk-match-confirm-btn').prop('disabled', false);
});

function updateWithholdingCalculation() {
    if (!matchSourceData || !selectedMatchData) return;
    var sourceAmount = parseFloat(matchSourceData.amount);
    var targetAmount = selectedMatchData.amount;
    var withholding  = parseFloat($('#el-bk-match-withholding-amount').val()) || 0;
    var expected     = targetAmount + withholding;
    var diff         = Math.abs(sourceAmount - expected);

    var html = 'Invoice: $' + formatMatchNumber(sourceAmount);
    html += ' = Deposit: $' + formatMatchNumber(targetAmount);
    if (withholding > 0) {
        html += ' + Withholding: $' + formatMatchNumber(withholding);
    }
    html += ' \u2192 ';

    if (diff < 0.01) {
        html += '<span class="el-bk-calc-match">\u2713 Match</span>';
    } else {
        html += '<span class="el-bk-calc-diff">Difference: $' + formatMatchNumber(diff) + '</span>';
    }

    $('#el-bk-match-calculation').html(html);
}

$(document).on('input', '#el-bk-match-withholding-amount', updateWithholdingCalculation);

$(document).on('click', '#el-bk-match-confirm-btn', function() {
    if (!selectedMatchId) return;

    var $btn = $(this).prop('disabled', true).text('Matching\u2026');

    var invoiceId, transactionId;
    if (matchModalMode === 'invoice') {
        invoiceId     = matchSourceId;
        transactionId = selectedMatchId;
    } else {
        transactionId = matchSourceId;
        invoiceId     = selectedMatchId;
    }

    var withholding     = parseFloat($('#el-bk-match-withholding-amount').val()) || 0;
    var withholdingType = $('#el-bk-match-withholding-type').val();

    elBkAjax('bk_match_invoice_to_deposit', {
        invoice_id:         invoiceId,
        transaction_id:     transactionId,
        withholding_amount: withholding,
        withholding_type:   withholdingType
    }, function() {
        closeMatchModal();
        location.reload();
    }, function(msg) {
        $btn.prop('disabled', false).text('Confirm Match');
        alert('Error: ' + msg);
    });
});

function closeMatchModal() {
    $('#el-bk-match-modal').fadeOut(200);
    matchModalMode    = null;
    matchSourceId     = null;
    matchSourceData   = null;
    selectedMatchId   = null;
    selectedMatchData = null;
}

$(document).on('click', '.el-bk-modal-close, .el-bk-modal-cancel, .el-bk-modal-backdrop', closeMatchModal);

$(document).on('click', '.el-bk-match-deposit-btn', function() {
    openInvoiceMatchModal($(this).attr('data-invoice-id'));
});

$(document).on('click', '.el-bk-link-invoice-btn', function() {
    openDepositMatchModal($(this).attr('data-transaction-id'));
});

$(document).on('click', '.el-bk-unmatch-btn', function(e) {
    e.stopPropagation();
    if (!confirm('Unmatch this invoice from its deposit?')) return;

    var $btn      = $(this);
    var invoiceId = $btn.attr('data-invoice-id');

    elBkAjax('bk_unmatch_invoice', { invoice_id: invoiceId }, function() {
        location.reload();
    }, function(msg) {
        alert('Error: ' + msg);
    });
});

// ── Phase C.1 — Settings Calculations & Toggles ───────────────────────────────

function updateHomeOfficeCalculation() {
    var enabled    = $('#el-bk-home-office-enabled').is(':checked');
    var method     = $('input[name="home_office_method"]:checked').val();
    var officeSqft = parseFloat($('#el-bk-home-office-sqft').val()) || 0;
    var totalSqft  = parseFloat($('#el-bk-home-total-sqft').val()) || 0;

    var pct = totalSqft > 0 ? (officeSqft / totalSqft * 100) : 0;
    $('#el-bk-home-office-pct').text(pct.toFixed(1) + '%');

    if (!enabled) {
        $('#el-bk-home-calc-result').text('—');
        return;
    }

    var deduction = 0;
    if (method === 'simplified') {
        var clampedSqft = Math.min(officeSqft, 300);
        deduction = clampedSqft * 5;
        $('#el-bk-home-calc-result').html(
            clampedSqft + ' sq ft &times; $5.00 = <strong>$' + deduction.toFixed(2) + '</strong> deduction'
        );
    } else if (method === 'actual') {
        var mortgage     = parseFloat($('#el-bk-home-mortgage-rent').val()) || 0;
        var taxes        = parseFloat($('#el-bk-home-real-estate-taxes').val()) || 0;
        var utilities    = parseFloat($('#el-bk-home-utilities').val()) || 0;
        var insurance    = parseFloat($('#el-bk-home-insurance').val()) || 0;
        var repairs      = parseFloat($('#el-bk-home-repairs').val()) || 0;
        var depreciation = parseFloat($('#el-bk-home-depreciation').val()) || 0;

        var totalExpenses = mortgage + taxes + utilities + insurance + repairs + depreciation;
        deduction = totalExpenses * (pct / 100);
        $('#el-bk-home-calc-result').html(
            '$' + totalExpenses.toFixed(2) + ' &times; ' + pct.toFixed(1) + '% = <strong>$' + deduction.toFixed(2) + '</strong> deduction'
        );
    }
}

function updateVehicleCalculation() {
    var enabled       = $('#el-bk-vehicle-enabled').is(':checked');
    var method        = $('input[name="vehicle_method"]:checked').val();
    var totalMilesStd = parseFloat($('#el-bk-vehicle-total-miles').val()) || 0;
    var bizMilesStd   = parseFloat($('#el-bk-vehicle-business-miles').val()) || 0;
    var totalMilesAct = parseFloat($('#el-bk-vehicle-actual-total-miles').val()) || 0;
    var bizMilesAct   = parseFloat($('#el-bk-vehicle-actual-business-miles').val()) || 0;

    var totalMiles  = method === 'actual' ? totalMilesAct : totalMilesStd;
    var bizMiles    = method === 'actual' ? bizMilesAct   : bizMilesStd;
    var pct         = totalMiles > 0 ? (bizMiles / totalMiles * 100) : 0;

    $('#el-bk-vehicle-pct').text(pct.toFixed(1) + '%');
    $('#el-bk-vehicle-actual-pct').text(pct.toFixed(1) + '%');

    if (!enabled) {
        $('#el-bk-vehicle-calc-result').text('—');
        return;
    }

    var deduction = 0;
    if (method === 'standard') {
        var rate = parseFloat($('#el-bk-vehicle-mileage-rate').val()) || 0.70;
        deduction = bizMiles * rate;
        $('#el-bk-vehicle-calc-result').html(
            bizMiles.toLocaleString() + ' miles &times; $' + rate.toFixed(2) + ' = <strong>$' + deduction.toFixed(2) + '</strong> deduction'
        );
    } else if (method === 'actual') {
        var gas          = parseFloat($('#el-bk-vehicle-gas').val()) || 0;
        var insurance    = parseFloat($('#el-bk-vehicle-insurance').val()) || 0;
        var repairs      = parseFloat($('#el-bk-vehicle-repairs').val()) || 0;
        var registration = parseFloat($('#el-bk-vehicle-registration').val()) || 0;
        var lease        = parseFloat($('#el-bk-vehicle-lease').val()) || 0;
        var depreciation = parseFloat($('#el-bk-vehicle-depreciation').val()) || 0;

        var totalExpenses = gas + insurance + repairs + registration + lease + depreciation;
        deduction = totalExpenses * (pct / 100);
        $('#el-bk-vehicle-calc-result').html(
            '$' + totalExpenses.toFixed(2) + ' &times; ' + pct.toFixed(1) + '% = <strong>$' + deduction.toFixed(2) + '</strong> deduction'
        );
    }
}

// Toggle section collapse/expand
$(document).on('click', '.el-bk-settings-toggle', function() {
    var targetId = $(this).data('target');
    var $body    = $('#' + targetId);
    var $icon    = $(this).find('.el-bk-settings-toggle-icon');
    var isOpen   = $body.is(':visible');

    $body.toggle(!isOpen);
    $icon.html(isOpen ? '&#9654;' : '&#9660;');
    $(this).toggleClass('el-bk-settings-toggle--collapsed', isOpen);
});

// Home office enable/disable
$('#el-bk-home-office-enabled').on('change', function() {
    $('#el-bk-home-office-fields').toggle(this.checked);
    updateHomeOfficeCalculation();
});

// Vehicle enable/disable
$('#el-bk-vehicle-enabled').on('change', function() {
    $('#el-bk-vehicle-fields').toggle(this.checked);
    updateVehicleCalculation();
});

// Home office method toggle
$('input[name="home_office_method"]').on('change', function() {
    var method = $(this).val();
    $('#el-bk-home-office-actual-fields').toggle(method === 'actual');
    updateHomeOfficeCalculation();
});

// Vehicle method toggle
$('input[name="vehicle_method"]').on('change', function() {
    var method = $(this).val();
    $('#el-bk-vehicle-standard-fields').toggle(method === 'standard');
    $('#el-bk-vehicle-actual-fields').toggle(method === 'actual');
    updateVehicleCalculation();
});

// Recalculate on input — home office
$(document).on('input', '#el-bk-home-office-sqft, #el-bk-home-total-sqft, #el-bk-home-mortgage-rent, #el-bk-home-real-estate-taxes, #el-bk-home-utilities, #el-bk-home-insurance, #el-bk-home-repairs, #el-bk-home-depreciation', updateHomeOfficeCalculation);

// Recalculate on input — vehicle
$(document).on('input', '#el-bk-vehicle-total-miles, #el-bk-vehicle-business-miles, #el-bk-vehicle-mileage-rate, #el-bk-vehicle-gas, #el-bk-vehicle-insurance, #el-bk-vehicle-repairs, #el-bk-vehicle-registration, #el-bk-vehicle-lease, #el-bk-vehicle-depreciation, #el-bk-vehicle-actual-total-miles, #el-bk-vehicle-actual-business-miles', updateVehicleCalculation);

// Run on page load if on settings tab
if ($('#el-bk-home-office-sqft').length) {
    updateHomeOfficeCalculation();
    updateVehicleCalculation();
}

// ── ADD EXPENSE MODAL ─────────────────────────────────────────────────────────

$(document).on('click', '#el-bk-add-expense-btn', function() {
    $('#el-bk-ae-merchant').val('');
    $('#el-bk-ae-date').val(new Date().toISOString().slice(0, 10));
    $('#el-bk-ae-amount').val('');
    $('#el-bk-ae-category').val('');
    $('#el-bk-ae-bank').val('');
    $('#el-bk-ae-comments').val('');
    $('#el-bk-ae-status').text('');
    $('#el-bk-add-expense-modal').fadeIn(200);
    setTimeout(function() { $('#el-bk-ae-merchant').focus(); }, 220);
});

$(document).on('click', '.el-bk-add-expense-close', function() {
    $('#el-bk-add-expense-modal').fadeOut(200);
});

$(document).on('click', '#el-bk-ae-save-btn', function() {
    var $btn    = $(this).prop('disabled', true).text('Saving\u2026');
    var $status = $('#el-bk-ae-status').text('');

    var merchant = $.trim($('#el-bk-ae-merchant').val());
    var date     = $('#el-bk-ae-date').val();
    var amount   = $('#el-bk-ae-amount').val();
    var category = $('#el-bk-ae-category').val();
    var bank     = $('#el-bk-ae-bank').val();
    var comments = $.trim($('#el-bk-ae-comments').val());

    if (!merchant || !date || !amount || parseFloat(amount) <= 0) {
        $status.html('<span style="color:#dc2626;">Merchant, date, and amount are required.</span>');
        $btn.prop('disabled', false).text('Save Expense');
        return;
    }

    elBkAjax('bk_add_expense', {
        merchant:     merchant,
        date:         date,
        amount:       amount,
        category:     category,
        bank_account: bank,
        comments:     comments
    }, function() {
        $btn.prop('disabled', false).text('Save Expense');
        $status.html('<span style="color:#16a34a;">&#10003; Saved \u2014 reloading\u2026</span>');
        setTimeout(function() { location.reload(); }, 800);
    }, function(msg) {
        $btn.prop('disabled', false).text('Save Expense');
        $status.html('<span style="color:#dc2626;">Error: ' + msg + '</span>');
    });
});

// ── DELETE SINGLE INCOME (DEPOSIT) ────────────────────────────────────────────

$(document).on('click', '.el-bk-delete-income-btn', function() {
    var $btn     = $(this);
    var id       = $btn.data('id');
    var merchant = $btn.data('merchant') || 'this deposit';
    var $row     = $btn.closest('tr');

    if (!confirm('Delete "' + merchant + '"?\n\nThis will permanently remove the deposit. Any linked invoice will be reset to Unpaid.')) return;

    $btn.prop('disabled', true).css('opacity', '0.3');

    elBkAjax('bk_delete_income', { id: id }, function() {
        $row.fadeOut(250, function() { $(this).remove(); });
    }, function(msg) {
        $btn.prop('disabled', false).css('opacity', '0.6');
        alert('Could not delete: ' + msg);
    });
});

// ── CONVERT DEPOSIT TO REFUND OFFSET ─────────────────────────────────────────

$(document).on('click', '.el-bk-convert-refund-btn', function () {
    var $btn      = $(this);
    var incomeId  = $btn.data('id');
    var merchant  = $btn.data('merchant') || '';
    var amount    = $btn.data('amount')   || '0.00';
    var date      = $btn.data('date')     || '';

    $('#el-bk-ro-info').html(
        '<strong>' + $('<span>').text(merchant).html() + '</strong>' +
        ' &nbsp;|&nbsp; $' + amount +
        ' &nbsp;|&nbsp; ' + date
    );
    $('#el-bk-ro-comments').val('Refund offset \u2014 ' + merchant);
    $('#el-bk-ro-status').html('');
    $('#el-bk-ro-confirm-btn').prop('disabled', false).text('Create Offset');
    $('#el-bk-refund-offset-modal')
        .data('income-id', incomeId)
        .data('income-row', $btn.closest('tr'))
        .fadeIn(200);
});

$(document).on('click', '#el-bk-ro-confirm-btn', function () {
    var $modal    = $('#el-bk-refund-offset-modal');
    var incomeId  = $modal.data('income-id');
    var $row      = $modal.data('income-row');
    var category  = $('#el-bk-ro-category').val();
    var comments  = $.trim($('#el-bk-ro-comments').val());
    var $btn      = $(this).prop('disabled', true).text('Saving\u2026');
    var $status   = $('#el-bk-ro-status');

    elBkAjax('bk_convert_refund_offset', {
        income_id: incomeId,
        category:  category,
        comments:  comments,
    }, function (data) {
        $modal.fadeOut(200);
        // Update the category dropdown on the income row to show Refund.
        $row.find('.el-bk-inline-select[data-field="category"]').val('Refund');
        // Dim the row to indicate it is now excluded.
        $row.css('opacity', '0.5').attr('title', 'Marked as Refund \u2014 excluded from taxable income');
        $btn.prop('disabled', false).text('Create Offset');
    }, function (msg) {
        $status.html('<span style="color:#dc2626;">Error: ' + msg + '</span>');
        $btn.prop('disabled', false).text('Create Offset');
    });
});

// ── DELETE SINGLE EXPENSE ─────────────────────────────────────────────────────

$(document).on('click', '.el-bk-delete-expense-btn', function() {
    var $btn     = $(this);
    var id       = $btn.data('id');
    var merchant = $btn.data('merchant') || 'this expense';
    var $row     = $btn.closest('tr');

    if (!confirm('Delete "' + merchant + '"?\n\nThis will permanently remove the transaction and cannot be undone.')) return;

    $btn.prop('disabled', true).css('opacity', '0.3');

    elBkAjax('bk_delete_expense', { id: id }, function() {
        // Remove split children (if any) then the row itself.
        $('[data-parent-id="' + id + '"]').fadeOut(200, function() { $(this).remove(); });
        $row.fadeOut(250, function() { $(this).remove(); });
    }, function(msg) {
        $btn.prop('disabled', false).css('opacity', '0.6');
        alert('Could not delete: ' + msg);
    });
});

// ── ACCOUNTANT EXPORT (Phase C.2) ─────────────────────────────────────────────

$('#el-bk-export-accountant-btn').on('click', function() {
    var $btn    = $(this).prop('disabled', true).text('Generating…');
    var $status = $('#el-bk-export-status').text('');

    fetch(elBookkeeping.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action:    'el_core_action',
            el_action: 'bk_export_accountant',
            nonce:     elBookkeeping.nonce,
            tax_year:  elBookkeeping.taxYear
        }).toString()
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Server returned ' + response.status);
        var disposition = response.headers.get('Content-Disposition') || '';
        var match = disposition.match(/filename="?([^"]+)"?/);
        var filename = match ? match[1] : 'ELS-Tax-Export.xlsx';
        return response.blob().then(function(blob) {
            return { blob: blob, filename: filename };
        });
    })
    .then(function(data) {
        var url  = URL.createObjectURL(data.blob);
        var link = document.createElement('a');
        link.href     = url;
        link.download = data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        $btn.prop('disabled', false).text('Download Tax Export (.xlsx)');
        $status.html('<span class="el-bk-success">&#10003; Export ready — downloading now</span>');
    })
    .catch(function(err) {
        $btn.prop('disabled', false).text('Download Tax Export (.xlsx)');
        $status.html('<span class="el-bk-error">Error: ' + err.message + '</span>');
    });
});

}(jQuery));