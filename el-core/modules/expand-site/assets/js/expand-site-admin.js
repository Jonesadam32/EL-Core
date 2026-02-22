/**
 * Expand Site Module — Admin JavaScript
 * 
 * Handles project creation form submission via AJAX
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Handle project creation form
        document.addEventListener('submit', handleProjectCreate);
        
        // Handle stage advancement form
        document.addEventListener('submit', handleAdvanceStage);
        
        // Handle stakeholder forms
        document.addEventListener('submit', handleAddStakeholder);
        document.addEventListener('click', handleRemoveStakeholder);
        document.addEventListener('click', handleChangeRole);
        document.addEventListener('click', handleDeleteProject);
        
        // User search for stakeholder modal - use event delegation since modal is dynamic
        const debouncedSearch = debounce(handleUserSearch, 300);
        document.addEventListener('input', function(e) {
            if (e.target && e.target.id === 'stakeholder-user-search') {
                debouncedSearch(e);
            }
        });
    }

    function handleProjectCreate(e) {
        const form = e.target.closest('#create-project-form');
        if (!form) return;

        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Create Project';

        // Gather form data
        const formData = new FormData(form);
        const data = {
            name: formData.get('name'),
            client_name: formData.get('client_name'),
            budget_range_low: formData.get('budget_range_low') || 0,
            budget_range_high: formData.get('budget_range_high') || 0,
            notes: formData.get('notes') || ''
        };

        // Validate required fields
        if (!data.name || !data.client_name) {
            alert('Project Name and Client Name are required.');
            return;
        }

        // Disable submit button
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';
        }

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_create_project');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('name', data.name);
        ajaxData.append('client_name', data.client_name);
        ajaxData.append('budget_range_low', data.budget_range_low);
        ajaxData.append('budget_range_high', data.budget_range_high);
        ajaxData.append('notes', data.notes);

        // Submit via AJAX using native fetch
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            console.log('AJAX Response:', result);
            
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            // Success - redirect to project detail page
            // The project_id is nested at result.data.data.project_id
            const projectId = result.data?.data?.project_id || result.data?.project_id;
            console.log('Project ID:', projectId);
            
            if (projectId) {
                const redirectUrl = elExpandSiteAdmin.projectUrl.replace('PROJECT_ID', projectId);
                console.log('Redirecting to:', redirectUrl);
                window.location.href = redirectUrl;
            } else {
                alert('Project created but could not redirect. Please refresh the page.');
            }
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to create project.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    function handleAdvanceStage(e) {
        const form = e.target.closest('#advance-stage-form');
        if (!form) return;

        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Approve & Advance';

        // Gather form data
        const formData = new FormData(form);
        const data = {
            project_id: formData.get('project_id'),
            deadline: formData.get('deadline') || '',
            notes: formData.get('notes') || ''
        };

        // Disable submit button
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Advancing...';
        }

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_advance_stage');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('project_id', data.project_id);
        ajaxData.append('deadline', data.deadline);
        ajaxData.append('notes', data.notes);

        // Submit via AJAX
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            // Success - reload page to show new stage
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to advance stage.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    function handleAddStakeholder(e) {
        const form = e.target.closest('#add-stakeholder-form');
        if (!form) return;

        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Add Stakeholder';

        // Gather form data
        const formData = new FormData(form);
        const data = {
            project_id: formData.get('project_id'),
            user_id: formData.get('user_id') || 0,
            role: formData.get('role') || 'contributor',
            new_user_email: formData.get('new_user_email') || '',
            new_user_first_name: formData.get('new_user_first_name') || '',
            new_user_last_name: formData.get('new_user_last_name') || ''
        };

        // Validate: need either user_id or all three new user fields
        if (!data.user_id && (!data.new_user_email || !data.new_user_first_name || !data.new_user_last_name)) {
            alert('Please select an existing user or provide email, first name, and last name for a new user.');
            return;
        }

        // Disable submit button
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding...';
        }

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_add_stakeholder');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('project_id', data.project_id);
        ajaxData.append('user_id', data.user_id);
        ajaxData.append('role', data.role);
        ajaxData.append('new_user_email', data.new_user_email);
        ajaxData.append('new_user_first_name', data.new_user_first_name);
        ajaxData.append('new_user_last_name', data.new_user_last_name);

        // Submit via AJAX
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            // Success - reload page to show new stakeholder
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to add stakeholder.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    function handleRemoveStakeholder(e) {
        const btn = e.target.closest('.el-es-remove-stakeholder-btn');
        if (!btn) return;

        e.preventDefault();

        // Check if button is disabled
        if (btn.classList.contains('disabled')) {
            const msg = btn.dataset.disabledMsg || 'This action is not available.';
            alert(msg);
            return;
        }

        if (!confirm('Are you sure you want to remove this stakeholder from the project?')) {
            return;
        }

        const stakeholderId = btn.dataset.stakeholderId;
        if (!stakeholderId) return;

        // Disable button
        btn.disabled = true;
        btn.textContent = 'Removing...';

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_remove_stakeholder');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('stakeholder_id', stakeholderId);

        // Submit via AJAX
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            // Success - reload page
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to remove stakeholder.');
            btn.disabled = false;
            btn.textContent = 'Remove';
        });
    }

    function handleChangeRole(e) {
        const btn = e.target.closest('.el-es-change-role-btn');
        if (!btn) return;

        e.preventDefault();

        // Check if button is disabled
        if (btn.classList.contains('disabled')) {
            const msg = btn.dataset.disabledMsg || 'This action is not available.';
            alert(msg);
            return;
        }

        const stakeholderId = btn.dataset.stakeholderId;
        const newRole = btn.dataset.newRole;
        if (!stakeholderId || !newRole) return;

        // Disable button
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Updating...';

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_change_stakeholder_role');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('stakeholder_id', stakeholderId);
        ajaxData.append('new_role', newRole);

        // Submit via AJAX
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            // Success - reload page
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to change role.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function handleUserSearch(e) {
        const input = e.target;
        const searchTerm = input.value.trim();
        const resultsDiv = document.getElementById('user-search-results');
        const userIdInput = document.getElementById('selected-user-id');

        console.log('User search triggered:', searchTerm);

        if (searchTerm.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_search_users');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('search', searchTerm);

        console.log('Sending search request...');

        // Submit via AJAX
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            console.log('Search response:', result);
            
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            const users = result.data?.data?.users || result.data?.users || [];
            console.log('Found users:', users);
            
            if (users.length === 0) {
                resultsDiv.innerHTML = '<p style="margin: 0; color: #666;">No users found. Enter email below to create a new user.</p>';
                resultsDiv.style.display = 'block';
                return;
            }

            // Display results
            let html = '<p style="margin: 0 0 8px 0; font-weight: 600;">Select a user:</p>';
            users.forEach(user => {
                html += `<div style="padding: 8px; margin-bottom: 4px; background: white; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;" 
                         class="user-search-result" data-user-id="${user.id}">
                    <strong>${user.name}</strong><br>
                    <small>${user.email}</small>
                </div>`;
            });
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';

            // Add click handlers to results
            resultsDiv.querySelectorAll('.user-search-result').forEach(result => {
                result.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const userName = this.querySelector('strong').textContent;
                    console.log('User selected:', userId, userName);
                    userIdInput.value = userId;
                    input.value = userName;
                    resultsDiv.style.display = 'none';
                });
            });
        })
        .catch(err => {
            console.error('Search error:', err);
            resultsDiv.innerHTML = '<p style="margin: 0; color: #d63638;">Search failed. Please try again.</p>';
            resultsDiv.style.display = 'block';
        });
    }

    function handleDeleteProject(e) {
        const btn = e.target.closest('.el-es-delete-project-btn');
        if (!btn) return;

        e.preventDefault();

        const projectId = btn.dataset.projectId;
        const projectName = btn.dataset.projectName;

        if (!projectId) return;

        if (!confirm(`Are you sure you want to delete "${projectName}"?\n\nThis will permanently delete:\n- The project\n- All stakeholders\n- All deliverables\n- All feedback\n- All pages\n- All stage history\n\nThis action cannot be undone.`)) {
            return;
        }

        // Disable button
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Deleting...';

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_delete_project');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('project_id', projectId);

        // Submit via AJAX
        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }
            
            // Success - reload page
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to delete project.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

})();
