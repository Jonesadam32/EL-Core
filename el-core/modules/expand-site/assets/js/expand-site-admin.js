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
        document.addEventListener('submit', handleSaveDefinition);
        document.addEventListener('click', handleLockDefinition);
        
        // Proposals
        document.addEventListener('click', handleNewProposal);
        document.addEventListener('click', handleEditProposal);
        document.addEventListener('submit', handleSaveProposalForm);
        document.addEventListener('click', handleSendProposal);
        document.addEventListener('click', handleDeleteProposal);
        document.addEventListener('click', handleGenerateProposalAI);

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

    // ═══════════════════════════════════════════
    // DISCOVERY TRANSCRIPT & DEFINITION
    // ═══════════════════════════════════════════

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

    function handleLockDefinition(e) {
        const btn = e.target.closest('#lock-definition-btn');
        if (!btn) return;

        e.preventDefault();

        const projectId = btn.dataset.projectId;

        if (!confirm('Are you sure you want to lock this definition?\n\nOnce locked, it cannot be edited. This confirms the project scope is finalized.')) {
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

    // ═══════════════════════════════════════════
    // PROPOSALS
    // ═══════════════════════════════════════════

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
            'prop-budget-low': data.budget_low,
            'prop-budget-high': data.budget_high,
            'prop-final-price': data.final_price,
            'prop-payment': data.payment_terms,
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

/* ═══════════════════════════════════════════
   TEMPLATE LIBRARY
   ═══════════════════════════════════════════ */
(function() {
    'use strict';

    const ajaxUrl = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.ajaxUrl : ajaxurl;
    const nonce   = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.nonce   : '';

    function domReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    domReady(function() {
        if (!document.getElementById('btn-add-template') && !document.getElementById('btn-add-template-empty')) {
            return; // Not on the template library page
        }
        initTemplateLibrary();
    });

    function initTemplateLibrary() {

    // ── Modal helpers ──────────────────────────────────
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // Close modals on overlay / close button click
    document.addEventListener('click', function(e) {
        if (e.target.dataset.modalClose) {
            closeModal(e.target.dataset.modalClose);
        }
        const closeBtn = e.target.closest('.el-modal-close');
        if (closeBtn) {
            const modal = closeBtn.closest('.el-modal');
            if (modal) modal.style.display = 'none';
        }
    });

    // ── Add template button ────────────────────────────
    function initAddButtons() {
        ['btn-add-template', 'btn-add-template-empty'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.addEventListener('click', () => {
                    resetTemplateForm();
                    document.querySelector('#modal-template .el-modal-title').textContent = 'Add Template';
                    openModal('modal-template');
                });
            }
        });
    }

    // ── Edit template ──────────────────────────────────
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-template');
        if (!btn) return;

        const d = btn.dataset;
        document.getElementById('tpl-id').value          = d.id;
        document.getElementById('tpl-title').value       = d.title;
        document.getElementById('tpl-category').value    = d.category;
        document.getElementById('tpl-description').value = d.description || '';
        document.getElementById('tpl-image-url').value   = d.imageUrl || '';
        document.getElementById('tpl-active').checked    = d.active === '1';

        const preview = document.getElementById('tpl-image-preview');
        const img     = document.getElementById('tpl-preview-img');
        if (d.imageUrl) {
            img.src = d.imageUrl;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }

        document.querySelector('#modal-template .el-modal-title').textContent = 'Edit Template';
        openModal('modal-template');
    });

    // ── Cancel button ──────────────────────────────────
    const cancelBtn = document.getElementById('btn-tpl-cancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => closeModal('modal-template'));
    }

    // ── Save template form ─────────────────────────────
    const form = document.getElementById('form-template');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const saveBtn = document.getElementById('btn-tpl-save');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';

            const formData = new FormData(form);
            const body = new URLSearchParams();
            body.append('action', 'el_core_action');
            body.append('el_action', 'es_save_template');
            body.append('nonce', nonce);
            body.append('template_id', document.getElementById('tpl-id').value);
            body.append('title', document.getElementById('tpl-title').value);
            body.append('style_category', document.getElementById('tpl-category').value);
            body.append('description', document.getElementById('tpl-description').value);
            body.append('image_url', document.getElementById('tpl-image-url').value);
            body.append('is_active', document.getElementById('tpl-active').checked ? '1' : '0');

            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                .then(r => r.json())
                .then(result => {
                    if (!result.success) throw new Error(result.data?.message || 'Save failed');
                    closeModal('modal-template');
                    window.location.reload();
                })
                .catch(err => {
                    alert(err.message || 'Failed to save template.');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Template';
                });
        });
    }

    // ── Delete template ────────────────────────────────
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-template');
        if (!btn) return;

        document.getElementById('delete-tpl-id').value       = btn.dataset.id;
        document.getElementById('delete-tpl-name').textContent = btn.dataset.title;
        openModal('modal-delete-template');
    });

    const confirmDeleteBtn = document.getElementById('btn-confirm-delete-tpl');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const id = document.getElementById('delete-tpl-id').value;
            this.disabled = true;
            this.textContent = 'Deleting…';

            const body = new URLSearchParams();
            body.append('action', 'el_core_action');
            body.append('el_action', 'es_delete_template');
            body.append('nonce', nonce);
            body.append('template_id', id);

            fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                .then(r => r.json())
                .then(result => {
                    if (!result.success) throw new Error(result.data?.message || 'Delete failed');
                    closeModal('modal-delete-template');
                    window.location.reload();
                })
                .catch(err => {
                    alert(err.message || 'Failed to delete template.');
                    this.disabled = false;
                    this.textContent = 'Delete';
                });
        });
    }

    // ── Media uploader ─────────────────────────────────
    const mediaUploadBtn = document.getElementById('btn-tpl-media-upload');
    if (mediaUploadBtn && typeof wp !== 'undefined' && wp.media) {
        let mediaFrame;
        mediaUploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (mediaFrame) { mediaFrame.open(); return; }

            mediaFrame = wp.media({
                title: 'Select Template Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' },
            });

            mediaFrame.on('select', function() {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                document.getElementById('tpl-image-url').value = attachment.url;
                const preview = document.getElementById('tpl-image-preview');
                const img     = document.getElementById('tpl-preview-img');
                img.src = attachment.url;
                preview.style.display = 'block';
            });

            mediaFrame.open();
        });
    }

    // Update preview when URL is typed manually
    const imageUrlInput = document.getElementById('tpl-image-url');
    if (imageUrlInput) {
        imageUrlInput.addEventListener('blur', function() {
            const url = this.value.trim();
            const preview = document.getElementById('tpl-image-preview');
            const img     = document.getElementById('tpl-preview-img');
            if (url) {
                img.src = url;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });
    }

    // ── Drag-to-reorder within category ───────────────
    function initDragReorder() {
        document.querySelectorAll('.el-tpl-card-grid').forEach(grid => {
            let dragSrc = null;

            grid.querySelectorAll('.el-tpl-card').forEach(card => {
                card.setAttribute('draggable', 'true');

                card.addEventListener('dragstart', function() {
                    dragSrc = this;
                    this.classList.add('el-tpl-dragging');
                });

                card.addEventListener('dragend', function() {
                    this.classList.remove('el-tpl-dragging');
                    saveOrder(grid);
                });

                card.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    if (dragSrc && dragSrc !== this) {
                        const rect = this.getBoundingClientRect();
                        const midX = rect.left + rect.width / 2;
                        if (e.clientX < midX) {
                            grid.insertBefore(dragSrc, this);
                        } else {
                            grid.insertBefore(dragSrc, this.nextSibling);
                        }
                    }
                });
            });
        });
    }

    function saveOrder(grid) {
        const cards = grid.querySelectorAll('.el-tpl-card');
        const order = [];
        cards.forEach((card, idx) => {
            order.push({ id: card.dataset.id, sort_order: idx });
        });

        const body = new URLSearchParams();
        body.append('action', 'el_core_action');
        body.append('el_action', 'es_reorder_templates');
        body.append('nonce', nonce);
        body.append('order', JSON.stringify(order));

        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
            .catch(err => console.error('Reorder failed:', err));
    }

    // ── Reset form helper ──────────────────────────────
    function resetTemplateForm() {
        document.getElementById('tpl-id').value          = '';
        document.getElementById('tpl-title').value       = '';
        document.getElementById('tpl-category').value    = '';
        document.getElementById('tpl-description').value = '';
        document.getElementById('tpl-image-url').value   = '';
        document.getElementById('tpl-active').checked    = true;
        document.getElementById('tpl-image-preview').style.display = 'none';
        document.getElementById('tpl-preview-img').src   = '';
    }

    // ── Init ───────────────────────────────────────────
    initAddButtons();
    initDragReorder();

    } // end initTemplateLibrary

})();

// ═══════════════════════════════════════════
// BRANDING TAB — Review Management (admin)
// ═══════════════════════════════════════════

(function() {
    'use strict';

    var brandingTab = document.getElementById('es-branding-tab');
    if (!brandingTab) return;

    var ajaxUrl = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.ajaxUrl : '';
    var nonce   = (typeof elExpandSiteAdmin !== 'undefined') ? elExpandSiteAdmin.nonce   : '';

    function elAdminAjax(action, data) {
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

    // ── Create Review Session form submit ──
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('#create-review-form');
        if (!form) return;
        e.preventDefault();

        var projectId  = form.querySelector('[name="project_id"]').value;
        var reviewType = form.querySelector('[name="review_type"]').value;
        var title      = form.querySelector('[name="title"]').value;
        var deadline   = form.querySelector('[name="deadline"]') ? form.querySelector('[name="deadline"]').value : '';
        var checkboxes = form.querySelectorAll('[name="template_ids[]"]:checked');

        if (checkboxes.length === 0) {
            alert('Please select at least one template for this review session.');
            return;
        }

        var templateIds = [];
        checkboxes.forEach(function(cb) { templateIds.push(cb.value); });

        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating…'; }

        var data = {
            project_id:   projectId,
            review_type:  reviewType,
            title:        title,
            deadline:     deadline
        };
        templateIds.forEach(function(id, i) {
            data['template_ids[' + i + ']'] = id;
        });

        elAdminAjax('es_create_review_item', data)
            .then(function(result) {
                alert(result.message || 'Review session created!');
                window.location.reload();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to create review session.');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Review Session'; }
            });
    });

    // ── Set Deadline button ──
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action="set-review-deadline"]');
        if (!btn) return;

        var rid = btn.dataset.reviewItemId;
        var current = btn.dataset.currentDeadline || '';
        var newDate = prompt('Enter new deadline (YYYY-MM-DD):', current);
        if (!newDate) return;

        elAdminAjax('es_set_review_deadline', {
            review_item_id: rid,
            deadline:       newDate
        }).then(function(result) {
            alert(result.message || 'Deadline updated!');
            window.location.reload();
        }).catch(function(err) {
            alert(err.message || 'Failed to update deadline.');
        });
    });

})();
