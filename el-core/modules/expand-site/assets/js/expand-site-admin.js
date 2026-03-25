/**
 * Expand Site Module â€” Admin JavaScript
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
        document.addEventListener('click', handleQuickAddStakeholder);
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
        
        // Discovery transcript processing
        document.addEventListener('click', handleProcessTranscript);
        document.addEventListener('submit', handleSaveQualification);
        document.addEventListener('submit', handleSaveDefinition);
        document.addEventListener('submit', handleSendDefinitionReview);
        document.addEventListener('click', handleLockDefinition);
        document.addEventListener('click', handleResetDefinitionDraft);
        
        // Proposals
        document.addEventListener('click', handleNewProposal);
        document.addEventListener('click', handleEditProposal);
        document.addEventListener('submit', handleSaveProposalForm);
        document.addEventListener('click', handleSendProposal);
        document.addEventListener('click', handleDeleteProposal);
        document.addEventListener('click', handleGenerateProposalAI);

        // Auto-calculate payment split when final price changes
        document.addEventListener('input', function(e) {
            if (e.target.id !== 'prop-final-price') return;
            const price = parseFloat(e.target.value) || 0;
            const firstEl = document.getElementById('prop-first-payment');
            const finalEl = document.getElementById('prop-final-payment');
            if (firstEl && !firstEl.dataset.manualOverride) firstEl.value = price > 0 ? Math.round(price * 0.25) : '';
            if (finalEl && !finalEl.dataset.manualOverride) finalEl.value = price > 0 ? Math.round(price * 0.75) : '';
        });
        document.addEventListener('input', function(e) {
            if (e.target.id === 'prop-first-payment' || e.target.id === 'prop-final-payment') {
                e.target.dataset.manualOverride = '1';
            }
        });

        // Organization search autocomplete in project creation modal
        const debouncedOrgSearch = debounce(handleOrgSearch, 300);
        document.addEventListener('input', function(e) {
            if (e.target && e.target.id === 'org-search-input') {
                debouncedOrgSearch(e);
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
            organization_id: formData.get('organization_id') || 0,
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
        ajaxData.append('organization_id', data.organization_id);
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

    function handleQuickAddStakeholder(e) {
        const btn = e.target.closest('.el-quick-add-stakeholder-btn');
        if (!btn) return;
        e.preventDefault();

        const userId    = btn.dataset.userId;
        const name      = btn.dataset.name;
        const role      = btn.dataset.role;
        const projectId = btn.dataset.projectId;

        if (!userId || !projectId) return;

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding...';

        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'es_add_stakeholder');
        fd.append('nonce', elExpandSiteAdmin.nonce);
        fd.append('project_id', projectId);
        fd.append('user_id', userId);
        fd.append('role', role);

        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(r => r.json())
        .then(result => {
            if (!result.success) throw new Error(result.data?.message || 'Request failed');
            window.location.reload();
        })
        .catch(err => {
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.textContent = originalText;
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

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // QUALIFICATION INTAKE
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    function handleSaveQualification(e) {
        const form = e.target.closest('#qualification-form');
        if (!form) return;
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Save';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Savingâ€¦';
        }

        const fd = new FormData(form);
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'es_save_qualification');
        fd.append('nonce', elExpandSiteAdmin.nonce);

        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(r => r.json())
        .then(result => {
            if (!result.success) throw new Error(result.data?.message || 'Save failed');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Saved âœ“';
                setTimeout(() => { submitBtn.textContent = originalText; }, 2000);
            }
        })
        .catch(err => {
            alert(err.message || 'Failed to save qualification intake.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // DISCOVERY TRANSCRIPT & DEFINITION
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    function handleProcessTranscript(e) {
        const btn = e.target.closest('#process-transcript-btn');
        if (!btn) return;

        e.preventDefault();

        const projectId = btn.dataset.projectId;
        const textarea = document.getElementById('discovery-transcript');
        const transcript = textarea ? textarea.value.trim() : '';

        if (!transcript) {
            alert('Please paste a transcript before processing.');
            return;
        }

        if (!confirm('This will process the transcript with AI and update the definition fields below. Continue?')) {
            return;
        }

        // Disable button
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Processing with AI...';

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_process_transcript');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('project_id', projectId);
        ajaxData.append('transcript', transcript);

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

            // Extract definition data from response
            const definition = result.data?.data?.definition || result.data?.definition;
            
            // Update form fields with extracted data
            if (definition) {
                const fields = ['site_description', 'primary_goal', 'secondary_goals', 'target_customers', 'user_types', 'site_type'];
                fields.forEach(field => {
                    const input = document.querySelector(`[name="${field}"]`);
                    if (input && definition[field]) {
                        input.value = definition[field];
                    }
                });
            }

            alert('Transcript processed successfully! Review the extracted data below and make any needed edits.');
            
            // Re-enable button
            btn.disabled = false;
            btn.textContent = originalText;
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to process transcript. Please try again or enter data manually.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function handleSaveDefinition(e) {
        const form = e.target.closest('#project-definition-form');
        if (!form) return;

        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Save Definition';

        // Gather form data
        const formData = new FormData(form);
        const data = {
            project_id: formData.get('project_id'),
            site_description: formData.get('site_description') || '',
            primary_goal: formData.get('primary_goal') || '',
            secondary_goals: formData.get('secondary_goals') || '',
            target_customers: formData.get('target_customers') || '',
            user_types: formData.get('user_types') || '',
            site_type: formData.get('site_type') || ''
        };

        // Disable submit button
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        // Use elAdminData (always available on all admin pages) as primary nonce source
        const saveNonce   = (typeof elAdminData !== 'undefined' && elAdminData.nonce)   ? elAdminData.nonce   : elExpandSiteAdmin.nonce;
        const saveAjaxUrl = (typeof elAdminData !== 'undefined' && elAdminData.ajaxUrl) ? elAdminData.ajaxUrl : elExpandSiteAdmin.ajaxUrl;

        const params = new URLSearchParams();
        params.append('action', 'el_core_action');
        params.append('el_action', 'es_save_definition');
        params.append('nonce', saveNonce);
        Object.keys(data).forEach(key => {
            params.append(key, data[key]);
        });

        // Submit via AJAX
        fetch(saveAjaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                const msg = result.data?.message || result.data || 'Save failed (unknown error)';
                throw new Error(msg);
            }

            alert('Definition saved successfully!');
            
            // Re-enable button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(err => {
            console.error('Save definition error:', err.message, err);
            alert('Failed to save definition: ' + (err.message || 'Unknown error'));
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    function handleSendDefinitionReview(e) {
        const form = e.target.closest('#send-definition-review-form');
        if (!form) return;

        e.preventDefault();

        const projectId = form.querySelector('[name="project_id"]')?.value;
        const deadline = form.querySelector('[name="deadline"]')?.value;
        if (!projectId) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }

        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'es_send_definition_review');
        fd.append('nonce', elExpandSiteAdmin.nonce);
        fd.append('project_id', projectId);
        fd.append('deadline', deadline || '');

        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(r => r.json())
        .then(result => {
            if (!result.success) throw new Error(result.data?.message || 'Request failed');
            alert(result.data?.message || 'Sent for review.');
            window.location.reload();
        })
        .catch(err => {
            alert(err.message || 'Failed to send for review.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send for Review';
            }
        });
    }

    function handleLockDefinition(e) {
        const btn = e.target.closest('#lock-definition-btn, #lock-definition-banner-btn');
        if (!btn) return;

        e.preventDefault();

        const projectId = btn.dataset.projectId;
        const reviewStatus = btn.dataset.reviewStatus || '';

        let msg = 'Are you sure you want to lock this definition?\n\nOnce locked, it cannot be edited.';
        if (reviewStatus && reviewStatus !== 'approved') {
            msg = 'The definition has not yet been approved by the client.\n\nLock anyway? This will override the normal review flow.';
        }
        if (!confirm(msg)) {
            return;
        }

        // Disable button
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Locking...';

        // Build FormData for WordPress AJAX
        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_lock_definition');
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

            // Success - reload page to show locked state
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to lock definition.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function handleResetDefinitionDraft(e) {
        const btn = e.target.closest('#reset-definition-draft-btn');
        if (!btn) return;
        e.preventDefault();

        const projectId = btn.dataset.projectId;
        if (!projectId) return;

        if (!confirm('Cancel the active client review and return this definition to Draft?\n\nThe client will no longer see the review UI until you send again.')) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Resetting...';

        const fd = new FormData();
        fd.append('action', 'el_core_action');
        fd.append('el_action', 'es_reset_definition');
        fd.append('nonce', elExpandSiteAdmin.nonce);
        fd.append('project_id', projectId);

        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(r => r.json())
        .then(result => {
            if (!result.success) throw new Error(result.data?.message || 'Request failed');
            window.location.reload();
        })
        .catch(err => {
            alert(err.message || 'Failed to reset definition.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // PROPOSALS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    function handleNewProposal(e) {
        const btn = e.target.closest('.el-es-new-proposal-btn');
        if (!btn) return;
        e.preventDefault();

        const projectId = btn.dataset.projectId;
        if (!projectId) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Creating...';

        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_create_proposal');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('project_id', projectId);

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
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to create proposal.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

    function handleEditProposal(e) {
        const btn = e.target.closest('.el-es-edit-proposal-btn');
        if (!btn) return;
        e.preventDefault();

        const proposalId = btn.dataset.proposalId;
        if (!proposalId || typeof elProposalsData === 'undefined') return;

        const data = elProposalsData[proposalId];
        if (!data) return;

        // Populate modal form
        const fields = {
            'edit-proposal-id': data.id,
            'prop-title': data.proposal_title,
            'prop-client-name': data.client_name,
            'prop-client-org': data.client_organization,
            'prop-client-email': data.client_email,
            'prop-dates': data.project_dates,
            'prop-location': data.project_location,
            'prop-situation': data.section_situation,
            'prop-what-we-build': data.section_what_we_build,
            'prop-why-els': data.section_why_els,
            'prop-investment': data.section_investment,
            'prop-next-steps': data.section_next_steps,
            'prop-final-price': data.final_price,
            'prop-platform-fee': data.annual_platform_fee,
            'prop-first-payment': data.first_payment_amount,
            'prop-final-payment': data.final_payment_amount,
            'prop-terms': data.terms_conditions,
        };

        Object.keys(fields).forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = fields[id] || '';
        });

        // Open the modal
        const modal = document.getElementById('edit-proposal-modal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('el-modal--active');
        }
    }

    function handleSaveProposalForm(e) {
        const form = e.target.closest('#edit-proposal-form');
        if (!form) return;
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent || 'Save Proposal';

        const formData = new FormData(form);
        const proposalId = formData.get('proposal_id');

        if (!proposalId) {
            alert('No proposal selected.');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_save_proposal');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);

        for (const [key, value] of formData.entries()) {
            ajaxData.append(key, value);
        }

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
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to save proposal.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    function handleSendProposal(e) {
        const btn = e.target.closest('.el-es-send-proposal-btn');
        if (!btn) return;
        e.preventDefault();

        const proposalId = btn.dataset.proposalId;
        if (!proposalId) return;

        if (!confirm('Mark this proposal as sent to the client?\n\nThe client will be able to view, accept, or decline it in their portal.')) {
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Sending...';

        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_send_proposal');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('proposal_id', proposalId);

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
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to send proposal.');
            btn.disabled = false;
            btn.textContent = 'Send';
        });
    }

    function handleDeleteProposal(e) {
        const btn = e.target.closest('.el-es-delete-proposal-btn');
        if (!btn) return;
        e.preventDefault();

        const proposalId = btn.dataset.proposalId;
        if (!proposalId) return;

        if (!confirm('Delete this proposal? This cannot be undone.')) {
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Deleting...';

        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_delete_proposal');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('proposal_id', proposalId);

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
            window.location.reload();
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to delete proposal.');
            btn.disabled = false;
            btn.textContent = 'Delete';
        });
    }

    function handleOrgSearch(e) {
        const input = e.target;
        const searchTerm = input.value.trim();
        const resultsDiv = document.getElementById('org-search-results');
        const orgIdInput = document.getElementById('selected-org-id');

        // Reset org ID when typing (user may be changing selection)
        if (orgIdInput) orgIdInput.value = '0';

        if (searchTerm.length < 2) {
            if (resultsDiv) resultsDiv.style.display = 'none';
            return;
        }

        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'search_organizations');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('search', searchTerm);

        fetch(elExpandSiteAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: ajaxData
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) return;

            const orgs = result.data?.data?.organizations || result.data?.organizations || [];

            if (orgs.length === 0) {
                resultsDiv.innerHTML = '<p style="margin:0;color:#6b7280;font-size:13px;">No matches. A new client will be created automatically.</p>';
                resultsDiv.style.display = 'block';
                return;
            }

            let html = '';
            orgs.forEach(org => {
                const typeLabel = org.type ? org.type.replace('_', ' ') : '';
                html += '<div style="padding:8px 10px;margin-bottom:4px;background:white;border:1px solid #e5e7eb;border-radius:4px;cursor:pointer;transition:background .15s;" '
                     + 'class="org-search-result" data-org-id="' + org.id + '" data-org-name="' + (org.name || '').replace(/"/g, '&quot;') + '" '
                     + 'onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'white\'">'
                     + '<strong>' + (org.name || '') + '</strong>'
                     + (typeLabel ? ' <span style="color:#9ca3af;font-size:12px;">(' + typeLabel + ')</span>' : '')
                     + '</div>';
            });
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';

            resultsDiv.querySelectorAll('.org-search-result').forEach(el => {
                el.addEventListener('click', function() {
                    const orgId = this.dataset.orgId;
                    const orgName = this.dataset.orgName;
                    if (orgIdInput) orgIdInput.value = orgId;
                    input.value = orgName;
                    resultsDiv.style.display = 'none';
                });
            });
        })
        .catch(() => {
            if (resultsDiv) resultsDiv.style.display = 'none';
        });
    }

    function handleGenerateProposalAI(e) {
        const btn = e.target.closest('#generate-proposal-ai-btn');
        if (!btn) return;
        e.preventDefault();

        const projectId = btn.dataset.projectId;
        if (!projectId) return;

        if (!confirm('Generate proposal content using AI?\n\nThis will use the locked project definition and discovery transcript to draft the proposal fields.')) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Generating...';

        const statusEl = document.getElementById('ai-proposal-status');
        if (statusEl) statusEl.textContent = 'AI is generating proposal content...';

        const ajaxData = new FormData();
        ajaxData.append('action', 'el_core_action');
        ajaxData.append('el_action', 'es_generate_proposal_ai');
        ajaxData.append('nonce', elExpandSiteAdmin.nonce);
        ajaxData.append('project_id', projectId);

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

            const rd = result.data?.data || result.data;
            if (rd) {
                const narrativeMap = {
                    'situation': 'prop-situation',
                    'what_we_are_building': 'prop-what-we-build',
                    'why_els': 'prop-why-els',
                    'investment': 'prop-investment',
                    'next_steps': 'prop-next-steps',
                };

                Object.keys(narrativeMap).forEach(key => {
                    const el = document.getElementById(narrativeMap[key]);
                    if (el) el.value = rd[key] || '';
                });
            }

            if (statusEl) statusEl.textContent = 'Content generated! Review and edit as needed.';
            btn.disabled = false;
            btn.textContent = originalText;
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            alert(err.message || 'Failed to generate proposal content.');
            if (statusEl) statusEl.textContent = '';
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }

})();
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// USER JOURNEY PHASE â€” Admin JS (Phase 4)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

(function() {
    'use strict';

    var ajaxUrl = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.ajaxUrl : '';
    var nonce   = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.nonce   : '';

    function ujAjax(action, data) {
        var body = new URLSearchParams(Object.assign({
            action:    'el_core_action',
            el_action: action,
            nonce:     nonce
        }, data));
        return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (!r.success) throw new Error(r.data && r.data.message ? r.data.message : 'Request failed');
                return r.data;
            });
    }

    // â”€â”€ Toggle expand/collapse journey cards â”€â”€
    document.addEventListener('click', function(e) {
        var header = e.target.closest('.el-es-uj-card__header');
        if (!header) return;
        var bodyId = header.dataset.toggle;
        if (!bodyId) return;
        var body = document.getElementById(bodyId);
        if (!body) return;
        var icon = header.querySelector('.el-es-uj-expand-icon');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            if (icon) { icon.classList.remove('dashicons-arrow-down-alt2'); icon.classList.add('dashicons-arrow-up-alt2'); }
        } else {
            body.style.display = 'none';
            if (icon) { icon.classList.remove('dashicons-arrow-up-alt2'); icon.classList.add('dashicons-arrow-down-alt2'); }
        }
    });

    // â”€â”€ Reassign link toggle â”€â”€
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.el-es-uj-reassign-link');
        if (!link) return;
        e.preventDefault();
        var row = link.closest('.el-es-uj-reassign-row');
        if (!row) return;
        var form = row.querySelector('.el-es-uj-reassign-form');
        if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    // â”€â”€ Assign button â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-assign-btn');
        if (!btn) return;
        var journeyId  = btn.dataset.journeyId;
        var projectId  = btn.dataset.projectId;
        // Find the closest select
        var card       = btn.closest('.el-es-uj-card');
        if (!card) return;
        var select = card.querySelector('.el-es-uj-assign-select[data-journey-id="' + journeyId + '"]');
        if (!select || !select.value) {
            alert('Please select a stakeholder.');
            return;
        }
        var userId = select.value;
        var originalText = btn.textContent;
        btn.disabled = true; btn.textContent = 'Assigningâ€¦';

        ujAjax('es_assign_journey', { journey_id: journeyId, assigned_to: userId, project_id: projectId })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to assign stakeholder.');
                btn.disabled = false; btn.textContent = originalText;
            });
    });

    // â”€â”€ Generate with AI button (awaiting_ai state) â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-generate-ai-btn');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var card      = btn.closest('.el-es-uj-card');
        var statusEl  = card ? card.querySelector('.el-es-uj-generate-status') : null;

        btn.disabled = true; btn.textContent = 'Generatingâ€¦';
        if (statusEl) statusEl.textContent = 'Calling AIâ€¦';

        ujAjax('es_generate_journey_ai', { journey_id: journeyId, project_id: projectId })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'AI generation failed. Please try again.');
                btn.disabled = false; btn.textContent = 'Generate with AI';
                if (statusEl) statusEl.textContent = '';
            });
    });

    // â”€â”€ Manual edit toggle â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-manual-edit-toggle');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var form = document.querySelector('.el-es-uj-manual-edit-form[data-journey-id="' + journeyId + '"]');
        if (!form) return;
        var isVisible = form.style.display !== 'none';
        form.style.display = isVisible ? 'none' : 'block';
        btn.textContent = isVisible ? 'âœŽ Manually edit workflow' : 'âœŽ Hide manual editor';
    });

    // â”€â”€ Add Step button (insert after a specific step) â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-add-step-btn');
        if (!btn) return;
        var stepsContainer = btn.closest('.el-es-uj-edit-steps');
        if (!stepsContainer) return;

        // Find the step this button belongs to (insert after it), or append if standalone
        var afterStep = btn.closest('.el-es-uj-edit-step');

        var div = document.createElement('div');
        div.className = 'el-es-uj-edit-step';
        div.innerHTML =
            '<p class="el-es-uj-edit-step-num">Step <span class="el-es-uj-step-num-label"></span> ' +
            '<button type="button" class="el-es-uj-remove-step-btn" style="margin-left:8px;font-size:11px;color:#EF4444;background:none;border:none;cursor:pointer;">âœ• Remove</button></p>' +
            '<div class="el-form-row"><label class="el-form-label">Label</label>' +
            '<div class="el-form-field"><input type="text" class="el-input el-es-uj-edit-step-label" value="" style="width:100%;"></div></div>' +
            '<div class="el-form-row"><label class="el-form-label">Description</label>' +
            '<div class="el-form-field"><textarea class="el-textarea el-es-uj-edit-step-desc" rows="2" style="resize:both;width:100%;"></textarea></div></div>' +
            '<button type="button" class="el-es-uj-add-step-btn" style="margin:6px 0 0;font-size:12px;color:#6366F1;background:none;border:1px dashed #6366F1;border-radius:4px;padding:3px 10px;cursor:pointer;">+ Insert step below</button>';

        if (afterStep) {
            afterStep.after(div);
        } else {
            stepsContainer.appendChild(div);
        }

        // Renumber all steps
        renumberSteps(stepsContainer);
        div.querySelector('.el-es-uj-edit-step-label').focus();
    });

    // â”€â”€ Remove Step button â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-remove-step-btn');
        if (!btn) return;
        var stepEl = btn.closest('.el-es-uj-edit-step');
        if (!stepEl) return;
        var stepsContainer = stepEl.closest('.el-es-uj-edit-steps');
        stepEl.remove();
        if (stepsContainer) renumberSteps(stepsContainer);
    });

    function renumberSteps(stepsContainer) {
        stepsContainer.querySelectorAll('.el-es-uj-edit-step').forEach(function(el, idx) {
            el.dataset.stepIndex = idx;
            var numLabel = el.querySelector('.el-es-uj-step-num-label');
            if (numLabel) numLabel.textContent = (idx + 1);
        });
    }

    // â”€â”€ Save Manual Edits button â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-save-workflow-btn');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var card      = btn.closest('.el-es-uj-card');
        var form      = card ? card.querySelector('.el-es-uj-manual-edit-form[data-journey-id="' + journeyId + '"]') : null;
        var statusEl  = btn.parentElement ? btn.parentElement.querySelector('.el-es-uj-save-workflow-status') : null;
        if (!form) return;

        // Collect structured form fields into a workflow object
        var summaryEl = form.querySelector('.el-es-uj-edit-summary');
        var summary   = summaryEl ? summaryEl.value.trim() : '';
        var steps     = [];
        form.querySelectorAll('.el-es-uj-edit-step').forEach(function(stepEl, idx) {
            var labelEl = stepEl.querySelector('.el-es-uj-edit-step-label');
            var descEl  = stepEl.querySelector('.el-es-uj-edit-step-desc');
            steps.push({
                id:          'step_' + (idx + 1),
                label:       labelEl ? labelEl.value.trim() : '',
                description: descEl  ? descEl.value.trim()  : '',
                branch:      null
            });
        });
        var workflow = { summary: summary, steps: steps, implied_pages: [], open_questions: [] };
        var jsonStr  = JSON.stringify(workflow);

        var originalText = btn.textContent;
        btn.disabled = true; btn.textContent = 'Savingâ€¦';
        if (statusEl) statusEl.textContent = '';

        ujAjax('es_save_journey_workflow', { journey_id: journeyId, project_id: projectId, workflow_json: jsonStr })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to save workflow.');
                btn.disabled = false; btn.textContent = originalText;
                if (statusEl) statusEl.textContent = '';
            });
    });

    // â”€â”€ Refine with AI button â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-refine-btn');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var card = btn.closest('.el-es-uj-card');
        var notesEl = card ? card.querySelector('.el-es-uj-admin-notes[data-journey-id="' + journeyId + '"]') : null;
        var notes = notesEl ? notesEl.value : '';
        var statusEl = btn.parentElement ? btn.parentElement.querySelector('.el-es-uj-refine-status') : null;

        btn.disabled = true; btn.textContent = 'Refiningâ€¦';
        if (statusEl) { statusEl.textContent = 'Calling AIâ€¦'; }

        ujAjax('es_refine_journey', { journey_id: journeyId, project_id: projectId, admin_notes: notes })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'AI refinement failed.');
                btn.disabled = false; btn.textContent = 'Refine with AI';
                if (statusEl) { statusEl.textContent = ''; }
            });
    });

    // â”€â”€ Send for Review form submit (modal form) â”€â”€
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('.el-es-uj-send-review-form');
        if (!form) return;
        e.preventDefault();
        var journeyId  = form.dataset.journeyId;
        var projectId  = form.dataset.projectId;
        var deadlineEl = form.querySelector('[name="deadline"]');
        var deadline   = deadlineEl ? deadlineEl.value : '';
        var btn        = form.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Sendingâ€¦'; }

        ujAjax('es_send_journey_review', { journey_id: journeyId, project_id: projectId, deadline: deadline })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to send for review.');
                if (btn) { btn.disabled = false; btn.textContent = 'Send for Review'; }
            });
    });

    // â”€â”€ Reset to Draft button â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-reset-btn');
        if (!btn) return;
        if (!confirm('Cancel the active review and return this journey to Admin Refined status?')) return;
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var originalText = btn.textContent;
        btn.disabled = true; btn.textContent = 'Resettingâ€¦';

        ujAjax('es_reset_journey_review', { journey_id: journeyId, project_id: projectId })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to reset journey.');
                btn.disabled = false; btn.textContent = originalText;
            });
    });

    // â”€â”€ Lock Journey button â”€â”€
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-lock-btn');
        if (!btn) return;
        if (!confirm('Lock this journey? This cannot be undone.')) return;
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        btn.disabled = true; btn.textContent = 'Lockingâ€¦';

        ujAjax('es_lock_journey', { journey_id: journeyId, project_id: projectId })
            .then(function(result) {
                if (result.data && result.data.all_locked) {
                    alert('All journeys are now locked. Phase 5 (Visual Identity) is unlocked.');
                }
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to lock journey.');
                btn.disabled = false; btn.textContent = 'Lock Journey';
            });
    });

    // â”€â”€ Add User Type modal form submit â”€â”€
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('#add-user-type-form');
        if (!form) return;
        e.preventDefault();
        var projectId = form.querySelector('[name="project_id"]').value;
        var userType  = form.querySelector('[name="user_type"]').value.trim();
        if (!userType) { alert('Please enter a user type name.'); return; }
        var btn = form.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Addingâ€¦'; }

        ujAjax('es_add_user_type', { project_id: projectId, user_type: userType })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to add user type.');
                if (btn) { btn.disabled = false; btn.textContent = 'Add User Type'; }
            });
    });

    // -- Edit user type name: pencil button toggles inline edit form --
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-edit-type-btn');
        if (!btn) return;
        e.stopPropagation();
        var journeyId = btn.dataset.journeyId;
        var card = btn.closest('.el-es-uj-card');
        if (!card) return;
        var displayEl = card.querySelector('.el-es-uj-type-display');
        var formEl    = card.querySelector('.el-es-uj-edit-type-form[data-journey-id="' + journeyId + '"]');
        if (!formEl) return;
        if (displayEl) displayEl.style.display = 'none';
        btn.style.display = 'none';
        formEl.style.display = 'inline-flex';
        formEl.style.alignItems = 'center';
        var input = formEl.querySelector('.el-es-uj-edit-type-input');
        if (input) { input.focus(); input.select(); }
    });

    // -- Edit user type name: cancel --
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-cancel-edit-type-btn');
        if (!btn) return;
        e.stopPropagation();
        var journeyId = btn.dataset.journeyId;
        var card = btn.closest('.el-es-uj-card');
        if (!card) return;
        var displayEl = card.querySelector('.el-es-uj-type-display');
        var formEl    = card.querySelector('.el-es-uj-edit-type-form[data-journey-id="' + journeyId + '"]');
        var pencilBtn = card.querySelector('.el-es-uj-edit-type-btn[data-journey-id="' + journeyId + '"]');
        if (formEl) formEl.style.display = 'none';
        if (displayEl) displayEl.style.display = '';
        if (pencilBtn) pencilBtn.style.display = '';
    });

    // -- Edit user type name: save --
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-save-type-btn');
        if (!btn) return;
        e.stopPropagation();
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var card = btn.closest('.el-es-uj-card');
        if (!card) return;
        var input   = card.querySelector('.el-es-uj-edit-type-input');
        var newName = input ? input.value.trim() : '';
        if (!newName) { alert('Please enter a user type name.'); return; }
        var originalText = btn.textContent;
        btn.disabled = true; btn.textContent = 'Saving...';

        ujAjax('es_rename_user_type', { journey_id: journeyId, project_id: projectId, user_type: newName })
            .then(function(result) {
                var displayEl = card.querySelector('.el-es-uj-type-display');
                if (displayEl) displayEl.textContent = result.user_type || newName;
                if (input) input.value = result.user_type || newName;
                var formEl    = card.querySelector('.el-es-uj-edit-type-form[data-journey-id="' + journeyId + '"]');
                var pencilBtn = card.querySelector('.el-es-uj-edit-type-btn[data-journey-id="' + journeyId + '"]');
                if (formEl) formEl.style.display = 'none';
                if (displayEl) displayEl.style.display = '';
                if (pencilBtn) pencilBtn.style.display = '';
                btn.disabled = false; btn.textContent = originalText;
            })
            .catch(function(err) {
                alert(err.message || 'Failed to rename user type.');
                btn.disabled = false; btn.textContent = originalText;
            });
    });

    // -- Delete user type button (trash icon, only on pending_assignment cards) --
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-delete-type-btn');
        if (!btn) return;
        e.stopPropagation();
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var userType  = btn.dataset.userType || 'this user type';
        if (!confirm('Delete "' + userType + '"? This cannot be undone.')) return;
        btn.disabled = true;

        ujAjax('es_delete_user_type', { journey_id: journeyId, project_id: projectId })
            .then(function() {
                // Remove the card from the DOM without a full reload
                var card = btn.closest('.el-es-uj-card');
                if (card) card.remove();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to delete user type.');
                btn.disabled = false;
            });
    });

    // -- Send List to Client button --
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-approve-list-btn');
        if (!btn) return;
        var projectId = btn.dataset.projectId;
        if (!confirm('Send the user type list to the client? The Decision Maker will be able to assign team members once you confirm.')) return;
        var originalText = btn.textContent;
        btn.disabled = true; btn.textContent = 'Sending...';

        ujAjax('es_approve_journey_list', { project_id: projectId })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to send list to client.');
                btn.disabled = false; btn.textContent = originalText;
            });
    });

    // -- Retry AI (admin retries AI generation for stuck awaiting_ai journeys) --
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-uj-retry-ai-btn');
        if (!btn) return;
        e.stopPropagation();
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var card      = btn.closest('.el-es-uj-card');
        var statusEl  = card ? card.querySelector('.el-es-uj-retry-status') : null;
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Generating...';
        if (statusEl) statusEl.textContent = '';

        ujAjax('es_retry_journey_ai', { journey_id: journeyId, project_id: projectId })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                if (statusEl) statusEl.textContent = err.message || 'AI generation failed.';
                else alert(err.message || 'AI generation failed.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

})();

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// VISUAL IDENTITY PHASE â€” Admin JS (Phase 5)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

(function() {
    'use strict';

    var panel = document.getElementById('es-vi-admin-panel');
    if (!panel) return;

    var ajaxUrl = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.ajaxUrl : '';
    var nonce   = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.nonce   : '';

    function viAdminAjax(action, data) {
        var body = new URLSearchParams(Object.assign({ action: 'el_core_action', el_action: action, nonce: nonce }, data));
        return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (!r.success) throw new Error(r.data && r.data.message ? r.data.message : 'Request failed');
                return r.data;
            });
    }

    // Generate Brand Brief
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-vi-generate-btn');
        if (!btn || !panel.contains(btn)) return;

        var projectId  = btn.dataset.projectId || panel.dataset.projectId;
        var regenerate = btn.dataset.regenerate === '1';

        if (regenerate && !confirm('Regenerate will overwrite the current brief. Continue?')) return;

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Generating\u2026';

        viAdminAjax('es_generate_visual_brief', { project_id: projectId }).then(function(data) {
            // Update the brief display
            var outputWrap = panel.querySelector('.el-es-brief-output-wrap');
            if (outputWrap) {
                outputWrap.querySelector('.el-es-brief-output').textContent = data.brief;
                var dateSpan = outputWrap.querySelector('.el-es-brief-generated-date');
                if (dateSpan) dateSpan.textContent = 'Brand Brief â€” Generated ' + new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } else {
                // First generation â€” reload to render full state
                window.location.reload();
                return;
            }
            btn.disabled = false;
            btn.textContent = regenerate ? 'Regenerate' : 'Generate Brand Brief';
            // Show lock button if not already visible
            var briefSection = panel.querySelector('.el-es-vi-brief-section');
            if (briefSection && !briefSection.querySelector('.el-es-vi-lock-btn')) {
                window.location.reload();
            }
        }).catch(function(err) {
            alert(err.message || 'Failed to generate brief.');
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });

    // Copy to Clipboard
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-vi-copy-brief');
        if (!btn || !panel.contains(btn)) return;

        var output = panel.querySelector('.el-es-brief-output');
        if (!output) return;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(output.textContent).then(function() {
                var orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            }).catch(function() {
                alert('Copy failed â€” please select the text manually.');
            });
        } else {
            // Fallback
            var range = document.createRange();
            range.selectNodeContents(output);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    });

    // Lock Brief
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-vi-lock-btn');
        if (!btn || !panel.contains(btn)) return;

        if (!confirm('Locking the brief will enable Phase 6 (Wireframes). You can unlock it later if needed.')) return;

        var projectId = btn.dataset.projectId || panel.dataset.projectId;
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Locking\u2026';

        viAdminAjax('es_lock_visual_brief', { project_id: projectId }).then(function() {
            window.location.reload();
        }).catch(function(err) {
            alert(err.message || 'Failed to lock brief.');
            btn.disabled = false;
            btn.textContent = orig;
        });
    });

    // Unlock Brief
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-vi-unlock-btn');
        if (!btn || !panel.contains(btn)) return;

        if (!confirm('Unlock the brief? The brief will be retained but can be regenerated.')) return;

        var projectId = btn.dataset.projectId || panel.dataset.projectId;
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Unlocking\u2026';

        viAdminAjax('es_unlock_visual_brief', { project_id: projectId }).then(function() {
            window.location.reload();
        }).catch(function(err) {
            alert(err.message || 'Failed to unlock brief.');
            btn.disabled = false;
            btn.textContent = orig;
        });
    });

})();
