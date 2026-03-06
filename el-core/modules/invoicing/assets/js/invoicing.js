/**
 * Invoicing module scripts
 * Product management (Step 2); invoice editor, payment modal, export (Steps 3–6).
 */

(function() {
    'use strict';

    var ajaxUrl = (typeof elInvAdmin !== 'undefined') ? elInvAdmin.ajaxUrl : (typeof elAdminData !== 'undefined' ? elAdminData.ajaxUrl : '');
    var nonce   = (typeof elInvAdmin !== 'undefined') ? elInvAdmin.nonce   : (typeof elAdminData !== 'undefined' ? elAdminData.nonce   : '');

    function slugify(text) {
        if (!text) return '';
        return text.toString().toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    function initProductsPage() {
        var form = document.getElementById('el-inv-form-product');
        if (!form) return;

        // Add Product / Edit Product: open modal with empty or filled form
        document.addEventListener('click', function(e) {
            var addBtn = e.target.closest('#el-inv-btn-add-product, #el-inv-btn-add-product-empty');
            if (addBtn) {
                form.reset();
                document.getElementById('el-inv-product-id').value = '';
                document.getElementById('el-inv-product-status').checked = true;
                document.querySelector('#el-inv-modal-product .el-modal-title').textContent = 'Add Product';
                if (typeof elAdmin !== 'undefined') elAdmin.openModal('el-inv-modal-product');
                return;
            }

            var editBtn = e.target.closest('.el-inv-btn-edit-product');
            if (editBtn) {
                document.getElementById('el-inv-product-id').value = editBtn.dataset.id || '';
                document.getElementById('el-inv-product-name').value = editBtn.dataset.name || '';
                document.getElementById('el-inv-product-slug').value = editBtn.dataset.slug || '';
                document.getElementById('el-inv-product-category').value = editBtn.dataset.category || 'service';
                document.getElementById('el-inv-product-default-price').value = editBtn.dataset.defaultPrice || '0';
                document.getElementById('el-inv-product-billing-cycle').value = editBtn.dataset.billingCycle || 'one-time';
                document.getElementById('el-inv-product-description').value = editBtn.dataset.description || '';
                document.getElementById('el-inv-product-status').checked = (editBtn.dataset.status || 'active') === 'active';
                document.querySelector('#el-inv-modal-product .el-modal-title').textContent = 'Edit Product';
                if (typeof elAdmin !== 'undefined') elAdmin.openModal('el-inv-modal-product');
                return;
            }

            var delBtn = e.target.closest('.el-inv-btn-delete-product');
            if (delBtn) {
                document.getElementById('el-inv-delete-product-id').value = delBtn.dataset.id || '';
                document.getElementById('el-inv-delete-product-name').textContent = delBtn.dataset.name || '';
                if (typeof elAdmin !== 'undefined') elAdmin.openModal('el-inv-modal-delete-product');
                return;
            }

            var confirmDel = e.target.closest('#el-inv-btn-confirm-delete-product');
            if (confirmDel) {
                var id = document.getElementById('el-inv-delete-product-id').value;
                if (!id) return;
                confirmDel.disabled = true;
                confirmDel.textContent = 'Deleting...';
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_delete_product');
                body.append('nonce', nonce);
                body.append('product_id', id);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            if (typeof elAdmin !== 'undefined') elAdmin.closeModal('el-inv-modal-delete-product');
                            window.location.reload();
                        } else {
                            alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
                            confirmDel.disabled = false;
                            confirmDel.textContent = 'Delete';
                        }
                    })
                    .catch(function() {
                        alert('Request failed.');
                        confirmDel.disabled = false;
                        confirmDel.textContent = 'Delete';
                    });
                return;
            }
        });

        // Slug auto-fill from name
        var nameField = document.getElementById('el-inv-product-name');
        var slugField = document.getElementById('el-inv-product-slug');
        if (nameField && slugField) {
            nameField.addEventListener('blur', function() {
                if (!slugField.value || slugField.value === slugify(slugField.dataset.previousName || '')) {
                    slugField.value = slugify(nameField.value);
                }
                slugField.dataset.previousName = nameField.value;
            });
        }

        // Form submit: create or update product
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var productId = document.getElementById('el-inv-product-id').value;
            var isEdit = !!productId;
            var saveBtn = form.querySelector('#el-inv-btn-product-save');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = isEdit ? 'Updating...' : 'Creating...';
            }
            var body = new FormData(form);
            body.append('action', 'el_core_action');
            body.append('el_action', isEdit ? 'inv_update_product' : 'inv_create_product');
            body.append('nonce', nonce);
            if (isEdit) body.append('product_id', productId);
            body.append('name', document.getElementById('el-inv-product-name').value);
            body.append('slug', document.getElementById('el-inv-product-slug').value);
            body.append('category', document.getElementById('el-inv-product-category').value);
            body.append('default_price', document.getElementById('el-inv-product-default-price').value);
            body.append('billing_cycle', document.getElementById('el-inv-product-billing-cycle').value);
            body.append('description', document.getElementById('el-inv-product-description').value);
            body.append('status', document.getElementById('el-inv-product-status').checked ? '1' : '0');

            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        if (typeof elAdmin !== 'undefined') elAdmin.closeModal('el-inv-modal-product');
                        window.location.reload();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Save failed.');
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Product'; }
                    }
                })
                .catch(function() {
                    alert('Request failed.');
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Product'; }
                });
        });

        // Seed default products
        var seedBtn = document.getElementById('el-inv-btn-seed-products');
        if (seedBtn) {
            seedBtn.addEventListener('click', function() {
                if (!confirm('Create the 6 default ELS products if they do not exist? Existing products will not be changed.')) return;
                seedBtn.disabled = true;
                seedBtn.textContent = 'Seeding...';
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_seed_products');
                body.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            var msg = (res.data && res.data.message) ? res.data.message : 'Done.';
                            var created = (res.data && res.data.created !== undefined) ? res.data.created : 0;
                            alert(msg + (created > 0 ? ' Page will reload.' : ''));
                            if (created > 0) window.location.reload();
                        } else {
                            alert(res.data && res.data.message ? res.data.message : 'Seed failed.');
                        }
                        seedBtn.disabled = false;
                        seedBtn.textContent = 'Seed Default Products';
                    })
                    .catch(function() {
                        alert('Request failed.');
                        seedBtn.disabled = false;
                        seedBtn.textContent = 'Seed Default Products';
                    });
            });
        }
    }

    // ─── Invoice list page: Duplicate, Cancel ───
    function initInvoiceListPage() {
        document.addEventListener('click', function(e) {
            var dupBtn = e.target.closest('.el-inv-btn-duplicate');
            if (dupBtn) {
                e.preventDefault();
                var id = dupBtn.dataset.invoiceId;
                if (!id) return;
                dupBtn.disabled = true;
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_duplicate_invoice');
                body.append('nonce', nonce);
                body.append('invoice_id', id);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success && res.data && res.data.data && res.data.data.invoice_id) {
                            var editUrl = window.location.pathname + '?page=el-core-invoices&invoice_id=' + res.data.data.invoice_id;
                            window.location.href = editUrl;
                        } else {
                            alert(res.data && res.data.message ? res.data.message : 'Duplicate failed.');
                            dupBtn.disabled = false;
                        }
                    })
                    .catch(function() { alert('Request failed.'); dupBtn.disabled = false; });
                return;
            }

            var deleteBtn = e.target.closest('.el-inv-btn-delete-invoice');
            if (deleteBtn) {
                e.preventDefault();
                var id = deleteBtn.dataset.invoiceId;
                if (!id || !confirm('Delete this invoice? It will be marked as cancelled and removed from active lists.')) return;
                deleteBtn.disabled = true;
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_delete_invoice');
                body.append('nonce', nonce);
                body.append('invoice_id', id);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) window.location.reload();
                        else { alert(res.data && res.data.message ? res.data.message : 'Failed.'); deleteBtn.disabled = false; }
                    })
                    .catch(function() { alert('Request failed.'); deleteBtn.disabled = false; });
                return;
            }

            var sendBtn = e.target.closest('.el-inv-btn-send-invoice');
            if (sendBtn) {
                e.preventDefault();
                var id = sendBtn.dataset.invoiceId;
                if (!id) return;
                if (!confirm('Send this invoice to the client by email? It will be marked as sent.')) return;
                sendBtn.disabled = true;
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_send_invoice');
                body.append('nonce', nonce);
                body.append('invoice_id', id);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            alert((res.data && res.data.message) ? res.data.message : 'Invoice sent.');
                            window.location.reload();
                        } else {
                            alert((res.data && res.data.message) ? res.data.message : 'Send failed.');
                            sendBtn.disabled = false;
                        }
                    })
                    .catch(function() { alert('Request failed.'); sendBtn.disabled = false; });
                return;
            }

            var recordPaymentBtn = e.target.closest('.el-inv-btn-record-payment');
            if (recordPaymentBtn) {
                e.preventDefault();
                var invoiceId = recordPaymentBtn.dataset.invoiceId;
                var balanceDue = recordPaymentBtn.dataset.balanceDue || '0';
                var invoiceNumber = recordPaymentBtn.dataset.invoiceNumber || '';
                var modal = document.getElementById('el-inv-modal-payment');
                var form = document.getElementById('el-inv-form-payment');
                if (!modal || !form) return;
                document.getElementById('el-inv-payment-invoice-id').value = invoiceId || '';
                document.getElementById('el-inv-payment-amount').value = balanceDue;
                document.getElementById('el-inv-payment-method').value = 'check';
                var today = new Date().toISOString().slice(0, 10);
                document.getElementById('el-inv-payment-date').value = today;
                document.getElementById('el-inv-payment-reference').value = '';
                document.getElementById('el-inv-payment-notes').value = '';
                var titleEl = modal.querySelector('.el-modal-title');
                if (titleEl) titleEl.textContent = 'Record Payment' + (invoiceNumber ? ' — ' + invoiceNumber : '');
                if (typeof elAdmin !== 'undefined') elAdmin.openModal('el-inv-modal-payment');
            }
        });

        var paymentForm = document.getElementById('el-inv-form-payment');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var invoiceId = document.getElementById('el-inv-payment-invoice-id').value;
                if (!invoiceId) return;
                var saveBtn = document.getElementById('el-inv-btn-payment-save');
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Recording...'; }
                var body = new FormData(paymentForm);
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_record_payment');
                body.append('nonce', nonce);
                body.append('invoice_id', invoiceId);
                body.append('amount', document.getElementById('el-inv-payment-amount').value);
                body.append('payment_method', document.getElementById('el-inv-payment-method').value);
                body.append('payment_date', document.getElementById('el-inv-payment-date').value);
                body.append('reference_number', document.getElementById('el-inv-payment-reference').value);
                body.append('notes', document.getElementById('el-inv-payment-notes').value);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            if (typeof elAdmin !== 'undefined') elAdmin.closeModal('el-inv-modal-payment');
                            window.location.reload();
                        } else {
                            alert((res.data && res.data.message) ? res.data.message : 'Failed to record payment.');
                            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Record Payment'; }
                        }
                    })
                    .catch(function() {
                        alert('Request failed.');
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Record Payment'; }
                    });
            });
        }
    }

    // ─── Invoice editor page ───
    function initInvoiceEditorPage() {
        var wrap = document.querySelector('.el-inv-invoice-editor');
        if (!wrap) return;

        var orgSearch = document.getElementById('el-inv-org-search');
        var orgIdInput = document.getElementById('el-inv-organization-id');
        var orgResults = document.getElementById('el-inv-org-results');
        var contactSelect = document.getElementById('el-inv-contact-id');
        var projectSelect = document.getElementById('el-inv-project-id');
        var invoiceId = wrap.dataset.invoiceId || '0';
        var isNew = wrap.dataset.isNew === '1';
        var orgSearchTimer = null;

        function searchOrgs(term) {
            if (!term || term.length < 2) {
                if (orgResults) orgResults.style.display = 'none';
                return;
            }
            var body = new FormData();
            body.append('action', 'el_core_action');
            body.append('el_action', 'search_organizations');
            body.append('nonce', nonce);
            body.append('search', term);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (!res.success) return;
                    var orgs = (res.data && res.data.data && res.data.data.organizations) ? res.data.data.organizations : (res.data && res.data.organizations) ? res.data.organizations : [];
                    if (!orgResults) return;
                    if (orgs.length === 0) {
                        orgResults.innerHTML = '<p class="el-inv-autocomplete-empty">No matches.</p>';
                        orgResults.style.display = 'block';
                        return;
                    }
                    var html = '';
                    orgs.forEach(function(org) {
                        html += '<div class="el-inv-org-result" data-org-id="' + (org.id || '') + '" data-org-name="' + (org.name || '').replace(/"/g, '&quot;') + '">' + (org.name || '') + '</div>';
                    });
                    orgResults.innerHTML = html;
                    orgResults.style.display = 'block';
                    orgResults.querySelectorAll('.el-inv-org-result').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var id = this.dataset.orgId;
                            var name = this.dataset.orgName;
                            if (orgIdInput) orgIdInput.value = id;
                            if (orgSearch) orgSearch.value = name;
                            orgResults.style.display = 'none';
                            loadContactsAndProjects(id);
                        });
                    });
                })
                .catch(function() { if (orgResults) orgResults.style.display = 'none'; });
        }

        function loadContactsAndProjects(orgId) {
            if (!orgId) {
                if (contactSelect) { contactSelect.innerHTML = '<option value="">— Select contact —</option>'; contactSelect.value = ''; }
                if (projectSelect) { projectSelect.innerHTML = '<option value="">— None —</option>'; projectSelect.value = ''; }
                return;
            }
            var body = new FormData();
            body.append('action', 'el_core_action');
            body.append('el_action', 'inv_get_organization_contacts');
            body.append('nonce', nonce);
            body.append('organization_id', orgId);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var contacts = (res.data && res.data.data && res.data.data.contacts) ? res.data.data.contacts : [];
                    if (contactSelect) {
                        var opt = '<option value="">— Select contact —</option>';
                        contacts.forEach(function(c) {
                            var label = (c.first_name || '') + ' ' + (c.last_name || '');
                            if (c.email) label += ' (' + c.email + ')';
                            opt += '<option value="' + (c.id || '') + '">' + (label || 'Contact') + '</option>';
                        });
                        contactSelect.innerHTML = opt;
                    }
                });

            body = new FormData();
            body.append('action', 'el_core_action');
            body.append('el_action', 'inv_get_org_projects');
            body.append('nonce', nonce);
            body.append('organization_id', orgId);
            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var projects = (res.data && res.data.data && res.data.data.projects) ? res.data.data.projects : [];
                    if (projectSelect) {
                        var opt = '<option value="">— None —</option>';
                        projects.forEach(function(p) {
                            opt += '<option value="' + (p.id || '') + '">' + (p.name || '') + '</option>';
                        });
                        projectSelect.innerHTML = opt;
                    }
                });
        }

        if (orgSearch) {
            orgSearch.addEventListener('input', function() {
                clearTimeout(orgSearchTimer);
                var term = orgSearch.value.trim();
                if (!term) {
                    if (orgIdInput) orgIdInput.value = '';
                    if (orgResults) orgResults.style.display = 'none';
                    loadContactsAndProjects('');
                    return;
                }
                orgSearchTimer = setTimeout(function() { searchOrgs(term); }, 250);
            });
            orgSearch.addEventListener('focus', function() {
                if (orgSearch.value.trim().length >= 2) searchOrgs(orgSearch.value.trim());
            });
        }
        document.addEventListener('click', function(e) {
            if (orgResults && orgSearch && !orgResults.contains(e.target) && e.target !== orgSearch) orgResults.style.display = 'none';
        });

        // Line items: product change → description + price; qty/price → amount; add/remove; recalc totals
        var tbody = document.getElementById('el-inv-line-items-tbody');
        var template = document.getElementById('el-inv-line-row-template');
        var taxRateInput = document.getElementById('el-inv-tax-rate');

        function lineAmount(row) {
            var qty = parseFloat(row.querySelector('.el-inv-line-qty').value) || 0;
            var price = parseFloat(row.querySelector('.el-inv-line-unit-price').value) || 0;
            return Math.round(qty * price * 100) / 100;
        }

        function updateLineAmount(row) {
            var amt = lineAmount(row);
            row.querySelector('.el-inv-line-amount').value = amt.toFixed(2);
            updateTotals();
        }

        function updateTotals() {
            var subtotal = 0;
            if (tbody) {
                tbody.querySelectorAll('.el-inv-line-row').forEach(function(tr) {
                    if (tr.id === 'el-inv-line-row-template') return;
                    subtotal += parseFloat(tr.querySelector('.el-inv-line-amount').value) || 0;
                });
            }
            var taxRate = parseFloat(taxRateInput && taxRateInput.value ? taxRateInput.value : 0) || 0;
            var taxAmount = Math.round(subtotal * (taxRate / 100) * 100) / 100;
            var total = Math.round((subtotal + taxAmount) * 100) / 100;
            var subtotalEl = document.getElementById('el-inv-subtotal');
            var taxEl = document.getElementById('el-inv-tax-amount');
            var totalEl = document.getElementById('el-inv-total');
            if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);
            if (taxEl) taxEl.textContent = taxAmount.toFixed(2);
            if (totalEl) totalEl.textContent = total.toFixed(2);
        }

        if (tbody) {
            tbody.addEventListener('change', function(e) {
                var productSelect = e.target.closest('.el-inv-line-product');
                if (productSelect) {
                    var opt = productSelect.options[productSelect.selectedIndex];
                    var row = productSelect.closest('tr');
                    if (opt && opt.value && row) {
                        var name = opt.dataset.name || opt.textContent;
                        var price = opt.dataset.price || '0';
                        row.querySelector('.el-inv-line-description').value = name;
                        row.querySelector('.el-inv-line-unit-price').value = price;
                        updateLineAmount(row);
                    }
                }
            });
            tbody.addEventListener('input', function(e) {
                if (e.target.classList.contains('el-inv-line-qty') || e.target.classList.contains('el-inv-line-unit-price')) {
                    var row = e.target.closest('tr');
                    if (row) updateLineAmount(row);
                }
            });
            tbody.addEventListener('click', function(e) {
                if (e.target.closest('.el-inv-remove-line')) {
                    var row = e.target.closest('tr');
                    if (row && row.id !== 'el-inv-line-row-template' && tbody.querySelectorAll('.el-inv-line-row').length > 1) {
                        row.remove();
                        updateTotals();
                    }
                }
            });
        }

        var addLineBtn = document.getElementById('el-inv-add-line');
        if (addLineBtn && template) {
            addLineBtn.addEventListener('click', function() {
                var clone = template.cloneNode(true);
                clone.id = '';
                clone.style.display = '';
                clone.removeAttribute('style');
                tbody.appendChild(clone);
                updateTotals();
            });
        }

        if (taxRateInput) taxRateInput.addEventListener('input', updateTotals);
        updateTotals();

        // Save Draft
        var saveBtn = document.getElementById('el-inv-btn-save-draft');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                var orgId = orgIdInput ? orgIdInput.value : '';
                if (!orgId) {
                    alert('Please select a client (organization).');
                    return;
                }
                var lineItems = [];
                tbody.querySelectorAll('.el-inv-line-row').forEach(function(tr) {
                    if (tr.id === 'el-inv-line-row-template') return;
                    var desc = (tr.querySelector('.el-inv-line-description') && tr.querySelector('.el-inv-line-description').value) ? tr.querySelector('.el-inv-line-description').value.trim() : '';
                    if (!desc) return;
                    var productId = (tr.querySelector('.el-inv-line-product') && tr.querySelector('.el-inv-line-product').value) ? tr.querySelector('.el-inv-line-product').value : '0';
                    var qty = parseFloat(tr.querySelector('.el-inv-line-qty').value) || 1;
                    var price = parseFloat(tr.querySelector('.el-inv-line-unit-price').value) || 0;
                    lineItems.push({ product_id: productId, description: desc, quantity: qty, unit_price: price });
                });
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', isNew ? 'inv_create_invoice' : 'inv_update_invoice');
                body.append('nonce', nonce);
                body.append('organization_id', orgId);
                body.append('contact_id', contactSelect ? contactSelect.value : '0');
                body.append('project_id', projectSelect ? projectSelect.value : '0');
                body.append('issue_date', document.getElementById('el-inv-issue-date') ? document.getElementById('el-inv-issue-date').value : '');
                body.append('due_date', document.getElementById('el-inv-due-date') ? document.getElementById('el-inv-due-date').value : '');
                body.append('tax_rate', taxRateInput ? taxRateInput.value : '0');
                body.append('notes', document.getElementById('el-inv-notes') ? document.getElementById('el-inv-notes').value : '');
                body.append('internal_notes', document.getElementById('el-inv-internal-notes') ? document.getElementById('el-inv-internal-notes').value : '');
                body.append('line_items', JSON.stringify(lineItems));
                if (!isNew) body.append('invoice_id', invoiceId);

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            var data = (res.data && res.data.data) ? res.data.data : {};
                            var newId = data.invoice_id;
                            if (isNew && newId) {
                                window.location.href = window.location.pathname + '?page=el-core-invoices&invoice_id=' + newId;
                            } else {
                                saveBtn.textContent = 'Saved';
                                saveBtn.disabled = false;
                                setTimeout(function() { saveBtn.textContent = 'Save Draft'; }, 2000);
                            }
                        } else {
                            alert((res.data && res.data.message) ? res.data.message : 'Save failed.');
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Save Draft';
                        }
                    })
                    .catch(function() {
                        alert('Request failed.');
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Draft';
                    });
            });
        }

        // Preview: open view in new tab
        var previewBtn = document.getElementById('el-inv-btn-preview');
        if (previewBtn) {
            previewBtn.addEventListener('click', function() {
                if (!invoiceId || invoiceId === '0') {
                    alert('Save the invoice first to preview.');
                    return;
                }
                var viewUrl = window.location.origin + '/?el_invoice_view=1&id=' + invoiceId;
                window.open(viewUrl, '_blank');
            });
        }

        // Send Invoice (from editor)
        var sendInvoiceBtn = document.getElementById('el-inv-btn-send-invoice');
        if (sendInvoiceBtn) {
            sendInvoiceBtn.addEventListener('click', function() {
                if (!invoiceId || invoiceId === '0') {
                    alert('Save the invoice first, then send it.');
                    return;
                }
                if (!confirm('Send this invoice to the client by email? It will be marked as sent.')) return;
                sendInvoiceBtn.disabled = true;
                sendInvoiceBtn.textContent = 'Sending...';
                var body = new FormData();
                body.append('action', 'el_core_action');
                body.append('el_action', 'inv_send_invoice');
                body.append('nonce', nonce);
                body.append('invoice_id', invoiceId);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            var msg = (res.data && res.data.message) ? res.data.message : 'Invoice sent.';
                            alert(msg);
                            window.location.reload();
                        } else {
                            alert((res.data && res.data.message) ? res.data.message : 'Send failed.');
                            sendInvoiceBtn.disabled = false;
                            sendInvoiceBtn.textContent = 'Send Invoice';
                        }
                    })
                    .catch(function() {
                        alert('Request failed.');
                        sendInvoiceBtn.disabled = false;
                        sendInvoiceBtn.textContent = 'Send Invoice';
                    });
            });
        }
    }

    function init() {
        initProductsPage();
        initInvoiceListPage();
        initInvoiceEditorPage();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
