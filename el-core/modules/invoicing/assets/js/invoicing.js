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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProductsPage);
    } else {
        initProductsPage();
    }
})();
