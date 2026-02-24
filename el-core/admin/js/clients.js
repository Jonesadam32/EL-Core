/**
 * EL Core — Clients Admin JavaScript
 *
 * Handles organization/contact CRUD via AJAX on the Clients page.
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        console.log('[EL Clients] JS loaded. elClientsAdmin:', typeof elClientsAdmin !== 'undefined' ? elClientsAdmin : 'UNDEFINED');

        var addOrgForm = document.getElementById('add-org-form');
        if (addOrgForm) {
            console.log('[EL Clients] Found #add-org-form, binding submit handler');
            addOrgForm.addEventListener('submit', handleAddOrg);
        }

        var editOrgForm = document.getElementById('edit-org-form');
        if (editOrgForm) {
            editOrgForm.addEventListener('submit', handleEditOrg);
        }

        var addContactForm = document.getElementById('add-contact-form');
        if (addContactForm) {
            addContactForm.addEventListener('submit', handleAddContact);
        }

        var editContactForm = document.getElementById('edit-contact-form');
        if (editContactForm) {
            editContactForm.addEventListener('submit', handleEditContact);
        }

        document.addEventListener('click', handleDeleteOrg);
        document.addEventListener('click', handleEditContactBtn);
        document.addEventListener('click', handleDeleteContact);
    }

    function ajax(action, data) {
        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', action);
        fd.append('nonce', elClientsAdmin.nonce);
        Object.keys(data).forEach(k => fd.append(k, data[k]));

        return fetch(elClientsAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        }).then(r => r.json()).then(result => {
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            return result;
        });
    }

    function handleAddOrg(e) {
        e.preventDefault();
        e.stopPropagation();

        var form = e.target.closest ? e.target.closest('#add-org-form') : e.target;
        if (!form || form.id !== 'add-org-form') {
            form = document.getElementById('add-org-form');
        }
        if (!form) return;

        console.log('[EL Clients] Add org form submit fired');

        var fd = new FormData(form);
        var name = fd.get('name');
        if (!name) { alert('Organization name is required.'); return; }

        var btn = form.querySelector('button[type="submit"]');
        setLoading(btn, 'Creating...');

        ajax('create_organization', {
            name: fd.get('name'),
            type: fd.get('type'),
            status: fd.get('status'),
            address: fd.get('address') || '',
            phone: fd.get('phone') || '',
            website: fd.get('website') || '',
        })
        .then(function() {
            console.log('[EL Clients] Org created, reloading');
            window.location.reload();
        })
        .catch(function(err) {
            console.error('[EL Clients] Create org error:', err);
            alert('Error: ' + err.message);
            resetBtn(btn, 'Add Client');
        });
    }

    function handleEditOrg(e) {
        e.preventDefault();
        var form = document.getElementById('edit-org-form');
        if (!form) return;

        var fd = new FormData(form);
        var btn = form.querySelector('button[type="submit"]');
        setLoading(btn, 'Updating...');

        ajax('update_organization', {
            organization_id: fd.get('organization_id'),
            name: fd.get('name'),
            type: fd.get('type'),
            status: fd.get('status'),
            address: fd.get('address') || '',
            phone: fd.get('phone') || '',
            website: fd.get('website') || '',
        })
        .then(function() { window.location.reload(); })
        .catch(function(err) { alert('Error: ' + err.message); resetBtn(btn, 'Update Client'); });
    }

    function handleDeleteOrg(e) {
        const btn = e.target.closest('#delete-org-btn');
        if (!btn) return;
        e.preventDefault();

        const orgId = btn.dataset.orgId;
        const orgName = btn.dataset.orgName;
        if (!orgId) return;

        if (!confirm('Delete "' + orgName + '"?\n\nThis will:\n- Delete all contacts for this organization\n- Unlink all projects (they won\'t be deleted)\n\nThis cannot be undone.')) {
            return;
        }

        setLoading(btn, 'Deleting...');

        ajax('delete_organization', { organization_id: orgId })
        .then(() => {
            window.location.href = elClientsAdmin.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=el-core-clients');
        })
        .catch(err => { alert(err.message); resetBtn(btn, 'Delete'); });
    }

    function handleAddContact(e) {
        e.preventDefault();
        var form = document.getElementById('add-contact-form');
        if (!form) return;

        var fd = new FormData(form);
        if (!fd.get('first_name') || !fd.get('email')) {
            alert('First name and email are required.');
            return;
        }

        var btn = form.querySelector('button[type="submit"]');
        setLoading(btn, 'Adding...');

        ajax('add_contact', {
            organization_id: fd.get('organization_id'),
            first_name: fd.get('first_name'),
            last_name: fd.get('last_name') || '',
            email: fd.get('email'),
            phone: fd.get('phone') || '',
            title: fd.get('title') || '',
            is_primary: fd.get('is_primary') ? '1' : '0',
            create_wp_user: fd.get('create_wp_user') ? '1' : '0',
        })
        .then(function() { window.location.reload(); })
        .catch(function(err) { alert('Error: ' + err.message); resetBtn(btn, 'Add Contact'); });
    }

    function handleEditContactBtn(e) {
        var btn = e.target.closest('.el-edit-contact-btn');
        if (!btn) return;
        e.preventDefault();

        var contactId = btn.dataset.contactId;
        if (!contactId) return;

        ajax('get_contact', { contact_id: contactId })
        .then(function(result) {
            var data = (result.data && result.data.data) ? result.data.data : result.data;
            if (!data) return;

            document.getElementById('edit-contact-id').value = data.id || '';
            document.getElementById('edit-contact-user-id').value = data.user_id || '0';
            document.getElementById('edit-contact-first-name').value = data.first_name || '';
            document.getElementById('edit-contact-last-name').value = data.last_name || '';
            document.getElementById('edit-contact-email').value = data.email || '';
            document.getElementById('edit-contact-phone').value = data.phone || '';
            document.getElementById('edit-contact-title').value = data.title || '';

            var isPrimaryCheckbox = document.getElementById('edit-contact-is-primary');
            if (isPrimaryCheckbox) isPrimaryCheckbox.checked = data.is_primary == 1;

            // Portal access row
            var portalRow = document.getElementById('edit-contact-portal-row');
            var hasPortal = document.getElementById('edit-contact-has-portal');
            var grantPortal = document.getElementById('edit-contact-grant-portal');
            var grantCb = document.getElementById('edit-contact-grant-portal-cb');

            if (portalRow) portalRow.style.display = '';
            if (grantCb) grantCb.checked = false;

            var userId = parseInt(data.user_id, 10) || 0;
            if (userId > 0) {
                if (hasPortal) hasPortal.style.display = '';
                if (grantPortal) grantPortal.style.display = 'none';
            } else {
                if (hasPortal) hasPortal.style.display = 'none';
                if (grantPortal) grantPortal.style.display = '';
            }

            var modal = document.getElementById('edit-contact-modal');
            if (modal) modal.style.display = 'block';
        })
        .catch(function(err) { alert('Error: ' + err.message); });
    }

    function handleEditContact(e) {
        e.preventDefault();
        var form = document.getElementById('edit-contact-form');
        if (!form) return;

        var fd = new FormData(form);
        var btn = form.querySelector('button[type="submit"]');
        setLoading(btn, 'Updating...');

        ajax('update_contact', {
            contact_id: fd.get('contact_id'),
            first_name: fd.get('first_name'),
            last_name: fd.get('last_name') || '',
            email: fd.get('email'),
            phone: fd.get('phone') || '',
            title: fd.get('title') || '',
            is_primary: fd.get('is_primary') ? '1' : '0',
            grant_portal_access: fd.get('grant_portal_access') ? '1' : '0',
        })
        .then(function() { window.location.reload(); })
        .catch(function(err) { alert('Error: ' + err.message); resetBtn(btn, 'Update Contact'); });
    }

    function handleDeleteContact(e) {
        const btn = e.target.closest('.el-delete-contact-btn');
        if (!btn) return;
        e.preventDefault();

        const contactId = btn.dataset.contactId;
        const contactName = btn.dataset.contactName;
        if (!contactId) return;

        if (!confirm('Delete contact "' + contactName + '"?\n\nThe linked WordPress user (if any) will not be deleted.\nThis cannot be undone.')) {
            return;
        }

        setLoading(btn, 'Deleting...');

        ajax('delete_contact', { contact_id: contactId })
        .then(() => window.location.reload())
        .catch(err => { alert(err.message); resetBtn(btn, 'Delete'); });
    }

    function setLoading(btn, text) {
        if (!btn) return;
        btn.disabled = true;
        btn.textContent = text;
    }

    function resetBtn(btn, text) {
        if (!btn) return;
        btn.disabled = false;
        btn.textContent = text;
    }

})();
