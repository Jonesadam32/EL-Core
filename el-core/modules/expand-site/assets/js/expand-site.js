/**
 * Expand Site — Client Portal JavaScript
 *
 * Vanilla JS only. Uses ELCore.ajax(). Event delegation on document.
 * v1.14.0 - Added stage navigation
 * v1.14.1 - Modal-based UX for deliverables/feedback/definition
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════
    // MODALS — Open/Close
    // ═══════════════════════════════════════════

    // Open modal
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.el-es-modal-trigger');
        if (!trigger) return;

        e.preventDefault();
        const modalId = trigger.dataset.modal;
        if (!modalId) return;

        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden'; // Prevent background scroll
    });

    // Close modal (close button or overlay click)
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('[data-close-modal]');
        if (!closeBtn) return;

        e.preventDefault();
        const modalId = closeBtn.dataset.closeModal;
        if (!modalId) return;

        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = ''; // Restore scroll
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.el-es-modal[aria-hidden="false"]');
            if (openModal) {
                openModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        }
    });

    // ═══════════════════════════════════════════
    // STAGE NAVIGATION — Switch between stages
    // ═══════════════════════════════════════════

    document.addEventListener('click', function(e) {
        const stageBtn = e.target.closest('.el-es-stage-btn');
        if (!stageBtn || stageBtn.disabled) return;

        e.preventDefault();
        const targetStage = stageBtn.dataset.stage;
        if (!targetStage) return;

        // Update active button state
        const portal = stageBtn.closest('.el-es-portal');
        if (!portal) return;

        // Remove active class from all buttons
        portal.querySelectorAll('.el-es-stage-btn').forEach(btn => {
            btn.classList.remove('el-es-stage-active');
        });

        // Add active class to clicked button
        stageBtn.classList.add('el-es-stage-active');

        // Hide all stage content areas
        portal.querySelectorAll('.el-es-stage-content').forEach(content => {
            content.style.display = 'none';
        });

        // Show selected stage content
        const targetContent = portal.querySelector('.el-es-stage-content[data-stage="' + targetStage + '"]');
        if (targetContent) {
            targetContent.style.display = 'block';
            // Smooth scroll to content
            targetContent.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Update URL hash (for bookmarking)
        history.replaceState(null, null, '#stage-' + targetStage);
    });

    // On page load, check for hash and activate that stage
    window.addEventListener('DOMContentLoaded', function() {
        const hash = window.location.hash;
        const match = hash.match(/#stage-(\d+)/);
        if (match) {
            const stage = match[1];
            const stageBtn = document.querySelector('.el-es-stage-btn[data-stage="' + stage + '"]');
            if (stageBtn && !stageBtn.disabled) {
                stageBtn.click();
            }
        }
    });

    // ═══════════════════════════════════════════
    // PAGE REVIEW — Approve / Request Revision
    // ═══════════════════════════════════════════

    document.addEventListener('click', function(e) {
        const approveBtn = e.target.closest('.el-es-approve-btn');
        const revisionBtn = e.target.closest('.el-es-revision-btn');
        const btn = approveBtn || revisionBtn;
        if (!btn) return;

        e.preventDefault();
        const pageId = btn.dataset.pageId;
        const status = btn.dataset.status;
        if (!pageId || !status) return;

        const container = btn.closest('.el-es-page-review');
        const statusEl = container?.querySelector('.el-es-page-status-msg');

        btn.disabled = true;

        ELCore.ajax('es_client_review_page', { page_id: pageId, status: status })
            .then(function(result) {
                if (statusEl) {
                    statusEl.textContent = result.message || (status === 'approved' ? 'Page approved!' : 'Revision requested.');
                    statusEl.style.display = 'block';
                    statusEl.className = 'el-es-page-status-msg el-notice el-notice-success';
                }
                const row = btn.closest('.el-es-page-row');
                if (row) {
                    const badge = row.querySelector('.el-es-page-status');
                    if (badge) {
                        badge.textContent = status === 'approved' ? 'Approved' : 'Needs revision';
                        badge.className = 'el-es-page-status el-es-status-' + status.replace(/_/g, '-');
                    }
                    btn.closest('.el-es-page-actions')?.remove();
                }
            })
            .catch(function(err) {
                if (statusEl) {
                    statusEl.textContent = err.message || 'Request failed.';
                    statusEl.style.display = 'block';
                    statusEl.className = 'el-es-page-status-msg el-notice el-notice-error';
                }
            })
            .finally(function() {
                btn.disabled = false;
            });
    });

    // ═══════════════════════════════════════════
    // FEEDBACK FORM — Submit
    // ═══════════════════════════════════════════

    document.addEventListener('submit', function(e) {
        const form = e.target.closest('.el-es-feedback-form-inner');
        if (!form) return;

        e.preventDefault();

        const wrapper = form.closest('.el-es-feedback-form');
        const statusEl = wrapper?.querySelector('.el-es-feedback-status');
        const submitBtn = form.querySelector('.el-es-feedback-submit');
        const originalText = submitBtn?.textContent;

        const projectId = wrapper?.dataset.projectId;
        const deliverableId = wrapper?.dataset.deliverableId;
        const content = form.querySelector('[name="content"]')?.value?.trim();
        const feedbackType = form.querySelector('[name="feedback_type"]')?.value;
        const stage = form.querySelector('[name="stage"]')?.value;

        if (!content) {
            if (statusEl) {
                statusEl.textContent = 'Feedback content is required.';
                statusEl.style.display = 'block';
                statusEl.className = 'el-es-feedback-status el-notice el-notice-error';
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }
        if (statusEl) statusEl.style.display = 'none';

        const data = {
            project_id: projectId,
            stage: stage,
            feedback_type: feedbackType || 'revision',
            content: content
        };
        if (deliverableId && deliverableId !== '0') {
            data.deliverable_id = deliverableId;
        }

        ELCore.ajax('es_submit_feedback', data)
            .then(function(result) {
                if (statusEl) {
                    statusEl.textContent = result.message || 'Feedback submitted!';
                    statusEl.style.display = 'block';
                    statusEl.className = 'el-es-feedback-status el-notice el-notice-success';
                }
                form.reset();
            })
            .catch(function(err) {
                if (statusEl) {
                    statusEl.textContent = err.message || 'Failed to submit feedback.';
                    statusEl.style.display = 'block';
                    statusEl.className = 'el-es-feedback-status el-notice el-notice-error';
                }
            })
            .finally(function() {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText || 'Submit feedback';
                }
            });
    });

    // ═══════════════════════════════════════════
    // PROPOSAL ACCEPT / DECLINE
    // ═══════════════════════════════════════════

    document.addEventListener('click', function(e) {
        var acceptBtn = e.target.closest('.el-es-accept-proposal-btn');
        if (acceptBtn) {
            e.preventDefault();
            var proposalId = acceptBtn.dataset.proposalId;
            if (!proposalId) return;

            if (!confirm('Accept this proposal? This will finalize the scope of service and advance your project to the next stage.')) {
                return;
            }

            acceptBtn.disabled = true;
            acceptBtn.textContent = 'Accepting...';

            ELCore.ajax('es_accept_proposal', { proposal_id: proposalId })
                .then(function(result) {
                    alert(result.message || 'Proposal accepted!');
                    window.location.reload();
                })
                .catch(function(err) {
                    alert(err.message || 'Failed to accept proposal.');
                    acceptBtn.disabled = false;
                    acceptBtn.textContent = 'Accept Proposal';
                });
        }

        var declineBtn = e.target.closest('.el-es-decline-proposal-btn');
        if (declineBtn) {
            e.preventDefault();
            var proposalId = declineBtn.dataset.proposalId;
            if (!proposalId) return;

            if (!confirm('Decline this proposal? The agency will be notified and may send a revised proposal.')) {
                return;
            }

            declineBtn.disabled = true;
            declineBtn.textContent = 'Declining...';

            ELCore.ajax('es_decline_proposal', { proposal_id: proposalId })
                .then(function(result) {
                    alert(result.message || 'Proposal declined.');
                    window.location.reload();
                })
                .catch(function(err) {
                    alert(err.message || 'Failed to decline proposal.');
                    declineBtn.disabled = false;
                    declineBtn.textContent = 'Decline';
                });
        }
    });

})();

// ═══════════════════════════════════════════
// DEFINITION CONSENSUS REVIEW
// ═══════════════════════════════════════════

(function() {
    'use strict';

    var container = document.getElementById('el-es-definition-review');
    if (!container) return;

    var projectId = container.dataset.projectId;
    if (!projectId) return;

    var DEF_FIELDS = [
        { key: 'site_description', label: 'Site Description' },
        { key: 'primary_goal', label: 'Primary Goal' },
        { key: 'secondary_goals', label: 'Secondary Goals' },
        { key: 'target_customers', label: 'Target Customers' },
        { key: 'user_types', label: 'User Types' },
        { key: 'site_type', label: 'Site Type' }
    ];

    var scrollDepthSeen = {};
    var countdownInterval = null;
    var localVerdicts = {};  // client-side verdict cache, survives loadReview() calls

    function loadReview() {
        var loading = container.querySelector('.el-es-definition-review-loading');
        if (loading) loading.textContent = 'Loading…';

        ELCore.ajax('es_get_definition_review', { project_id: projectId })
            .then(function(resp) {
                // EL_AJAX_Handler::success() wraps payload in { message, data }
                var data = (resp && resp.data !== undefined) ? resp.data : resp;
                renderReviewUI(data);
                if (loading) loading.remove();
            })
            .catch(function(err) {
                if (loading) loading.textContent = 'Error: ' + (err.message || 'Failed to load');
            });
    }

    function renderReviewUI(data) {
        var def = data.definition || {};
        var review = data.review || {};
        var comments = data.comments || {};
        var verdicts = data.verdicts || {};
        var userVerdicts = data.user_verdicts || {};
        // Merge server verdicts into local cache (server is source of truth, but local fills gaps)
        Object.keys(userVerdicts).forEach(function(k) {
            if (userVerdicts[k]) localVerdicts[k] = userVerdicts[k];
        });
        var deadlineTs = data.deadline_ts;
        var deadlinePassed = data.deadline_passed;
        var isDm = data.is_dm;
        var prevSnapshot = data.prev_snapshot || null;

        var html = '';

        // Countdown timer
        if (deadlineTs && !deadlinePassed) {
            html += '<div class="el-es-definition-countdown" data-deadline="' + deadlineTs + '">';
            html += '<span class="el-es-countdown-label">Time remaining:</span> ';
            html += '<span class="el-es-countdown-value">--</span>';
            html += '</div>';
        } else if (deadlinePassed) {
            html += '<div class="el-es-definition-countdown el-es-countdown-expired">';
            html += '<span class="el-es-countdown-label">Deadline passed.</span>';
            html += '</div>';
        }

        // DM "Needs Revision" banner — shown while review is still open after DM posted note
        if (review.dm_decision === 'needs_revision' && review.status === 'open') {
            html += '<div class="el-es-dm-revision-banner">';
            html += '<strong>Decision Maker requested changes:</strong> ';
            if (review.dm_note) {
                html += '<span class="el-es-dm-revision-note">' + escapeHtml(review.dm_note).replace(/\n/g, '<br>') + '</span>';
            } else {
                html += '<span class="el-es-dm-revision-note">Please review and update the fields below, then the Decision Maker will re-evaluate.</span>';
            }
            html += '</div>';
        }

        // Per-field layout
        html += '<div class="el-es-definition-review-fields">';
        DEF_FIELDS.forEach(function(f) {
            var val = def[f.key] || '';
            if (!val) return;
            var fieldComments = comments[f.key] || [];
            var userV = localVerdicts[f.key] || '';
            var isUpdated = prevSnapshot && prevSnapshot[f.key] !== undefined &&
                            prevSnapshot[f.key].trim() !== val.trim();
            html += '<div class="el-es-definition-field-block" data-field-key="' + f.key + '" data-scroll-marker="' + f.key + '">';
            html += '<div class="el-es-definition-field-label">' + escapeHtml(f.label);
            if (isUpdated) {
                html += ' <span class="el-es-updated-badge">Updated</span>';
            }
            html += '</div>';
            html += '<div class="el-es-definition-field-value" data-current-value="' + escapeHtml(val) + '">' + escapeHtml(val).replace(/\n/g, '<br>') + '</div>';
            if (review.id && review.status === 'open') {
                html += '<button type="button" class="el-es-btn el-es-btn-ghost el-es-edit-field-btn" data-field-key="' + f.key + '">✏ Edit</button>';
            }
            html += '<div class="el-es-definition-comments" data-field-key="' + f.key + '">';
            fieldComments.forEach(function(c) {
                html += renderComment(c, f.key);
                (c.replies || []).forEach(function(r) {
                    html += renderComment(r, f.key, true);
                });
            });
            html += '</div>';
            html += '<div class="el-es-definition-actions">';
            html += '<button type="button" class="el-es-btn el-es-btn-ghost el-es-add-comment-btn" data-field-key="' + f.key + '">+ Add comment</button>';
        // Verdict buttons — render active state based on localVerdicts
        if (review.id && review.status === 'open') {
                html += '<p class="el-es-verdict-help">Use these to flag individual fields for the team\'s attention. The Decision Maker makes the overall final decision below.</p>';
                html += '<div class="el-es-verdict-buttons">';
                html += '<button type="button" class="el-es-verdict-btn el-es-verdict-approved' + (userV === 'approved' ? ' el-es-verdict-active' : '') + '" data-field-key="' + f.key + '" data-verdict="approved">✓ Approve Field</button>';
                html += '<button type="button" class="el-es-verdict-btn el-es-verdict-revision' + (userV === 'needs_revision' ? ' el-es-verdict-active' : '') + '" data-field-key="' + f.key + '" data-verdict="needs_revision">⚑ Flag for changes</button>';
                html += '</div>';
        }
        // Per-user verdict indicators — show who voted and what they chose
        var fieldVerdictUsers = (verdicts[f.key] && verdicts[f.key].users) ? verdicts[f.key].users : [];
        if (fieldVerdictUsers.length > 0) {
            html += '<div class="el-es-verdict-indicators">';
            fieldVerdictUsers.forEach(function(v) {
                var icon = v.verdict === 'approved' ? '✓' : '⚑';
                var cls  = v.verdict === 'approved' ? 'el-es-vi-approved' : 'el-es-vi-revision';
                html += '<span class="el-es-verdict-indicator ' + cls + '">' + icon + ' ' + escapeHtml(v.name) + ' <span class="el-es-vi-date">' + escapeHtml(v.date) + '</span></span>';
            });
            html += '</div>';
        }
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';

        // Overall comment
        html += '<div class="el-es-definition-overall-comment">';
        html += '<h4>Overall feedback</h4>';
        html += '<div class="el-es-definition-comments" data-field-key="overall">';
        (comments.overall || []).forEach(function(c) {
            html += renderComment(c, 'overall');
            (c.replies || []).forEach(function(r) {
                html += renderComment(r, 'overall', true);
            });
        });
        html += '</div>';
        html += '<button type="button" class="el-es-btn el-es-btn-ghost el-es-add-comment-btn" data-field-key="overall">+ Add comment</button>';
        html += '</div>';

        // Submit / DM section
        html += '<div class="el-es-definition-review-actions">';
        if (review.id && review.status === 'open') {
            if (isDm) {
                html += '<div class="el-es-dm-decision-section">';
                html += '<h4>Make Final Decision</h4>';
                html += '<textarea class="el-es-dm-note-input" name="dm_note" placeholder="Optional note for the team…" rows="3"></textarea>';
                html += '<div class="el-es-dm-buttons">';
                html += '<button type="button" class="el-es-btn el-es-btn-primary el-es-dm-accept-btn" data-review-id="' + review.id + '">Accept</button>';
                html += '<button type="button" class="el-es-btn el-es-btn-warning el-es-dm-revision-btn" data-review-id="' + review.id + '">Needs Revision</button>';
                html += '</div></div>';
            } else {
                html += '<button type="button" class="el-es-btn el-es-btn-primary el-es-submit-input-btn" disabled>';
                html += 'Submit My Input';
                html += '</button>';
                html += '<p class="el-es-scroll-gate-msg">Scroll through all fields to enable.</p>';
            }
        }
        html += '</div>';

        container.innerHTML = html;

        // Wrap content in a fresh node so event listeners can be discarded on next reload
        var inner = document.createElement('div');
        inner.className = 'el-es-definition-review-inner';
        inner.innerHTML = html;
        container.innerHTML = '';
        container.appendChild(inner);

        // Start countdown
        if (deadlineTs && !deadlinePassed) {
            startCountdown(inner.querySelector('.el-es-definition-countdown'));
        }

        // Scroll-depth gate for contributors
        if (!isDm && review.id && review.status === 'open') {
            setupScrollDepthGate(inner);
        }

        // Bind events on the fresh inner node (discarded on next loadReview)
        bindDefinitionReviewEvents(inner, review);
    }

    function renderComment(c, fieldKey, isReply) {
        var cls = 'el-es-definition-comment' + (isReply ? ' el-es-comment-reply' : '');
        var verdict = c.verdict ? '<span class="el-es-comment-verdict el-es-verdict-' + c.verdict + '">' + (c.verdict === 'approved' ? '✓' : '↻') + '</span>' : '';
        return '<div class="' + cls + '" data-comment-id="' + c.id + '" data-parent-id="' + (c.parent_id || 0) + '">' +
            '<div class="el-es-comment-meta">' + escapeHtml(c.display_name || 'Unknown') + ' ' + verdict + ' <span class="el-es-comment-date">' + (c.created_at || '') + '</span></div>' +
            '<div class="el-es-comment-text">' + escapeHtml(c.comment || '').replace(/\n/g, '<br>') + '</div>' +
            (c.parent_id === 0 ? '<button type="button" class="el-es-btn el-es-btn-ghost el-es-reply-btn" data-field-key="' + fieldKey + '" data-parent-id="' + c.id + '">Reply</button>' : '') +
            '</div>';
    }

    function escapeHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function startCountdown(el) {
        if (!el) return;
        var deadline = parseInt(el.dataset.deadline, 10);
        if (!deadline) return;
        function tick() {
            var now = Math.floor(Date.now() / 1000);
            var left = deadline - now;
            var val = el.querySelector('.el-es-countdown-value');
            if (!val) return;
            if (left <= 0) {
                val.textContent = '0:00:00';
                el.classList.add('el-es-countdown-expired');
                if (countdownInterval) clearInterval(countdownInterval);
                return;
            }
            var h = Math.floor(left / 3600);
            var m = Math.floor((left % 3600) / 60);
            var s = left % 60;
            val.textContent = h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
        tick();
        countdownInterval = setInterval(tick, 1000);
    }

    function setupScrollDepthGate(container) {
        var blocks = container.querySelectorAll('.el-es-definition-field-block');
        var btn = container.querySelector('.el-es-submit-input-btn');
        var msg = container.querySelector('.el-es-scroll-gate-msg');
        if (!btn || blocks.length === 0) return;

        function checkScroll() {
            var allSeen = true;
            blocks.forEach(function(b) {
                var key = b.dataset.scrollMarker;
                if (scrollDepthSeen[key]) return;
                var rect = b.getBoundingClientRect();
                if (rect.top < window.innerHeight - 50) {
                    scrollDepthSeen[key] = true;
                } else {
                    allSeen = false;
                }
            });
            if (allSeen) {
                btn.disabled = false;
                if (msg) msg.style.display = 'none';
            }
        }
        window.addEventListener('scroll', checkScroll, { passive: true });
        checkScroll();
    }

    function bindDefinitionReviewEvents(container, review) {
        if (!review || !review.id) return;

        // Add comment
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.el-es-add-comment-btn');
            if (!btn) return;
            e.preventDefault();
            var fieldKey = btn.dataset.fieldKey;
            var wrap = btn.closest('.el-es-definition-field-block, .el-es-definition-overall-comment');
            var existing = wrap && wrap.querySelector('.el-es-add-comment-form');
            if (existing) {
                existing.remove();
                return;
            }
            var form = document.createElement('div');
            form.className = 'el-es-add-comment-form';
            form.innerHTML = '<textarea rows="2" placeholder="Your comment…"></textarea>' +
                '<button type="button" class="el-es-btn el-es-btn-primary el-es-post-comment-btn" data-field-key="' + fieldKey + '" data-parent-id="0">Post</button>' +
                '<button type="button" class="el-es-btn el-es-btn-ghost el-es-cancel-comment-btn">Cancel</button>';
            btn.parentNode.insertBefore(form, btn);
            form.querySelector('textarea').focus();
        });

        // Cancel comment
        container.addEventListener('click', function(e) {
            if (!e.target.closest('.el-es-cancel-comment-btn')) return;
            e.target.closest('.el-es-add-comment-form').remove();
        });

        // Post comment
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.el-es-post-comment-btn');
            if (!btn) return;
            e.preventDefault();
            var fieldKey = btn.dataset.fieldKey;
            var parentId = btn.dataset.parentId || '0';
            var form = btn.closest('.el-es-add-comment-form');
            var textarea = form && form.querySelector('textarea');
            var comment = textarea && textarea.value.trim();
            if (!comment) return;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'el_core_action');
            fd.append('el_action', 'es_post_definition_comment');
            fd.append('nonce', typeof elCore !== 'undefined' ? elCore.nonce : '');
            fd.append('project_id', projectId);
            fd.append('review_id', review.id);
            fd.append('field_key', fieldKey);
            fd.append('parent_id', parentId);
            fd.append('comment', comment);
            fetch(typeof elCore !== 'undefined' ? elCore.ajaxUrl : '', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) throw new Error(res.data && res.data.message || 'Failed');
                if (form) form.remove();
                loadReview();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to post comment');
                btn.disabled = false;
            });
        });

        // Reply
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.el-es-reply-btn');
            if (!btn) return;
            e.preventDefault();
            var fieldKey = btn.dataset.fieldKey;
            var parentId = btn.dataset.parentId;
            var commentEl = btn.closest('.el-es-definition-comment');
            var commentsDiv = commentEl && commentEl.closest('.el-es-definition-comments');
            var existing = commentsDiv && commentsDiv.querySelector('.el-es-add-comment-form');
            if (existing) existing.remove();
            var form = document.createElement('div');
            form.className = 'el-es-add-comment-form el-es-reply-form';
            form.innerHTML = '<textarea rows="2" placeholder="Reply…"></textarea>' +
                '<button type="button" class="el-es-btn el-es-btn-primary el-es-post-comment-btn" data-field-key="' + fieldKey + '" data-parent-id="' + parentId + '">Reply</button>' +
                '<button type="button" class="el-es-btn el-es-btn-ghost el-es-cancel-comment-btn">Cancel</button>';
            commentEl.appendChild(form);
            form.querySelector('textarea').focus();
        });

        // Edit field value
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.el-es-edit-field-btn');
            if (!btn) return;
            e.preventDefault();
            var fieldKey = btn.dataset.fieldKey;
            var block = btn.closest('.el-es-definition-field-block');
            if (!block) return;
            var valueEl = block.querySelector('.el-es-definition-field-value');
            if (!valueEl) return;
            if (block.querySelector('.el-es-edit-field-form')) return; // already open
            var currentVal = valueEl.dataset.currentValue || valueEl.textContent;
            var form = document.createElement('div');
            form.className = 'el-es-edit-field-form';
            form.innerHTML = '<textarea rows="4">' + escapeHtml(currentVal) + '</textarea>' +
                '<div class="el-es-edit-field-actions">' +
                '<button type="button" class="el-es-btn el-es-btn-primary el-es-save-field-btn" data-field-key="' + fieldKey + '">Save</button>' +
                '<button type="button" class="el-es-btn el-es-btn-ghost el-es-cancel-edit-btn">Cancel</button>' +
                '</div>';
            block.insertBefore(form, btn.nextSibling);
            form.querySelector('textarea').focus();
            btn.style.display = 'none';
        });

        // Cancel edit
        container.addEventListener('click', function(e) {
            if (!e.target.closest('.el-es-cancel-edit-btn')) return;
            var form = e.target.closest('.el-es-edit-field-form');
            if (!form) return;
            var block = form.closest('.el-es-definition-field-block');
            var editBtn = block && block.querySelector('.el-es-edit-field-btn');
            if (editBtn) editBtn.style.display = '';
            form.remove();
        });

        // Save field edit
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.el-es-save-field-btn');
            if (!btn) return;
            e.preventDefault();
            var fieldKey = btn.dataset.fieldKey;
            var form = btn.closest('.el-es-edit-field-form');
            var textarea = form && form.querySelector('textarea');
            var newVal = textarea && textarea.value.trim();
            if (!newVal) return;
            btn.disabled = true;
            btn.textContent = 'Saving…';
            var fd = new FormData();
            fd.append('action', 'el_core_action');
            fd.append('el_action', 'es_client_edit_definition_field');
            fd.append('nonce', typeof elCore !== 'undefined' ? elCore.nonce : '');
            fd.append('project_id', projectId);
            fd.append('field_key', fieldKey);
            fd.append('value', newVal);
            fetch(typeof elCore !== 'undefined' ? elCore.ajaxUrl : '', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) throw new Error(res.data && res.data.message || 'Failed');
                loadReview();
            })
            .catch(function(err) {
                alert(err.message || 'Failed to save');
                btn.disabled = false;
                btn.textContent = 'Save';
            });
        });

        // Verdict buttons
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('.el-es-verdict-btn');
            if (!btn) return;
            e.preventDefault();
            var fieldKey = btn.dataset.fieldKey;
            var verdict = btn.dataset.verdict;
            if (!fieldKey || !verdict) return;
            var strip = btn.closest('.el-es-verdict-buttons');
            strip.querySelectorAll('.el-es-verdict-btn').forEach(function(b) {
                b.classList.remove('el-es-verdict-active');
            });
            btn.classList.add('el-es-verdict-active');
            // Cache verdict locally so it survives loadReview() re-renders
            localVerdicts[fieldKey] = verdict;
            ELCore.ajax('es_field_verdict', {
                project_id: projectId,
                review_id: review.id,
                field_key: fieldKey,
                verdict: verdict
            }).then(function() {
                loadReview();
            }).catch(function(err) {
                btn.classList.remove('el-es-verdict-active');
                delete localVerdicts[fieldKey];
                alert(err.message || 'Failed to save');
            });
        });

        // DM decision
        container.addEventListener('click', function(e) {
            var acceptBtn = e.target.closest('.el-es-dm-accept-btn');
            var revBtn = e.target.closest('.el-es-dm-revision-btn');
            var btn = acceptBtn || revBtn;
            if (!btn) return;
            e.preventDefault();
            var reviewId = btn.dataset.reviewId;
            var decision = acceptBtn ? 'accepted' : 'needs_revision';
            var noteEl = container.querySelector('.el-es-dm-note-input');
            var note = noteEl && noteEl.value ? noteEl.value.trim() : '';
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'el_core_action');
            fd.append('el_action', 'es_dm_decision');
            fd.append('nonce', typeof elCore !== 'undefined' ? elCore.nonce : '');
            fd.append('project_id', projectId);
            fd.append('review_id', reviewId);
            fd.append('decision', decision);
            fd.append('dm_note', note);
            fetch(typeof elCore !== 'undefined' ? elCore.ajaxUrl : '', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) throw new Error(res.data && res.data.message || 'Failed');
                if (decision === 'needs_revision') {
                    // Keep review open — reload the review UI to show DM banner
                    loadReview();
                } else {
                    // Accepted — full page reload to show the approved state
                    window.location.reload();
                }
            })
            .catch(function(err) {
                alert(err.message || 'Failed');
                btn.disabled = false;
            });
        });

        // Submit My Input (contributors — just reload to show current state)
        container.addEventListener('click', function(e) {
            if (!e.target.closest('.el-es-submit-input-btn')) return;
            e.preventDefault();
            window.location.reload();
        });
    }

    loadReview();
})();

// ═══════════════════════════════════════════
// MOOD BOARD — Voting, Lightbox, DM Results
// ═══════════════════════════════════════════

(function() {
    'use strict';

    var moodBoard = document.getElementById('el-es-mood-board');
    if (!moodBoard) return;

    var reviewItemId = moodBoard.dataset.reviewItemId;

    // ── Vote button clicks ──
    document.addEventListener('click', function(e) {
        var voteBtn = e.target.closest('.el-es-vote-btn');
        if (!voteBtn) return;

        var card = voteBtn.closest('.el-es-mood-board-card');
        if (!card) return;

        var templateId = card.dataset.templateId;
        var preference = voteBtn.dataset.preference;
        var reviewId   = reviewItemId;

        if (!templateId || !preference || !reviewId) return;

        // Toggle: clicking active selection returns to neutral
        var currentlyActive = voteBtn.classList.contains('el-es-vote-active');
        if (currentlyActive) {
            preference = 'neutral';
        }

        // Optimistic UI update
        var strip = card.querySelector('.el-es-mood-board-vote-strip');
        strip.querySelectorAll('.el-es-vote-btn').forEach(function(btn) {
            btn.classList.remove('el-es-vote-active', 'el-es-vote-liked', 'el-es-vote-neutral', 'el-es-vote-disliked');
            btn.setAttribute('aria-pressed', 'false');
        });
        if (preference !== 'neutral') {
            voteBtn.classList.add('el-es-vote-active', 'el-es-vote-' + preference);
            voteBtn.setAttribute('aria-pressed', 'true');
        } else {
            // Neutral: mark the neutral button
            var neutralBtn = strip.querySelector('[data-preference="neutral"]');
            if (neutralBtn) {
                neutralBtn.classList.add('el-es-vote-active', 'el-es-vote-neutral');
                neutralBtn.setAttribute('aria-pressed', 'true');
            }
        }

        // Update card border class
        card.classList.remove('el-es-card-liked', 'el-es-card-disliked');
        if (preference === 'liked') card.classList.add('el-es-card-liked');
        if (preference === 'disliked') card.classList.add('el-es-card-disliked');

        // AJAX save
        ELCore.ajax('es_save_template_vote', {
            review_item_id: reviewId,
            template_id:    templateId,
            preference:     preference
        }).then(function(result) {
            // Update progress tracker
            var progress = document.querySelector('.el-es-review-progress');
            if (progress && result.responded !== undefined && result.total !== undefined) {
                var label = progress.querySelector('.el-es-review-progress-label');
                var fill  = progress.querySelector('.el-es-review-progress-fill');
                if (label) label.textContent = result.responded + ' of ' + result.total + ' team members responded';
                if (fill) {
                    var pct = result.total > 0 ? Math.round((result.responded / result.total) * 100) : 0;
                    fill.style.width = pct + '%';
                }

                // If DM and all have now voted, show View Results button
                var dmActions = document.getElementById('el-es-dm-mood-board-actions');
                if (dmActions && result.responded >= result.total && result.total > 0) {
                    dmActions.innerHTML = '<button type="button" class="el-es-btn el-es-btn-secondary el-es-view-results-btn" data-review-item-id="' + reviewId + '">'
                        + '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>'
                        + ' View Results</button>';
                }
            }
        }).catch(function(err) {
            console.error('Vote save failed:', err.message);
        });
    });

    // ── Lightbox ──
    var lightbox     = document.getElementById('el-es-lightbox');
    var lightboxImg  = document.getElementById('el-es-lightbox-img');
    var lightboxCap  = document.getElementById('el-es-lightbox-caption');

    if (lightbox && lightboxImg) {
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('.el-es-lightbox-trigger');
            if (!trigger) return;
            e.preventDefault();
            lightboxImg.src = trigger.dataset.src || '';
            if (lightboxCap) lightboxCap.textContent = trigger.dataset.caption || '';
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });

        // Close on overlay or close button
        lightbox.addEventListener('click', function(e) {
            if (e.target.classList.contains('el-es-lightbox-overlay') || e.target.closest('.el-es-lightbox-close')) {
                lightbox.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.getAttribute('aria-hidden') === 'false') {
                lightbox.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        });
    }

    // ── DM: View Results ──
    document.addEventListener('click', function(e) {
        var viewBtn = e.target.closest('.el-es-view-results-btn');
        if (!viewBtn) return;

        var rid = viewBtn.dataset.reviewItemId || reviewItemId;
        var modal = document.getElementById('mood-board-results');
        if (!modal) return;

        var body   = document.getElementById('mood-board-results-body');
        var footer = document.getElementById('mood-board-results-footer');

        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (body) body.innerHTML = '<div class="el-es-loading-spinner">Loading results…</div>';
        if (footer) footer.innerHTML = '';

        ELCore.ajax('es_get_review_results', { review_item_id: rid })
            .then(function(result) {
                if (!body) return;
                var results   = result.results || {};
                var reviewItem = result.review_item || {};
                var dmDec      = reviewItem.dm_decision ? JSON.parse(reviewItem.dm_decision) : {};
                var selIds     = dmDec.selected_template_ids || [];

                var html = '<table class="el-es-results-table"><thead><tr>'
                    + '<th></th><th>Template</th>'
                    + '<th class="el-es-tally-liked">♥ Liked</th>'
                    + '<th class="el-es-tally-neutral">— Neutral</th>'
                    + '<th class="el-es-tally-disliked">✕ Disliked</th>'
                    + '<th>Confirm?</th>'
                    + '</tr></thead><tbody>';

                selIds.forEach(function(tid) {
                    var r = results[tid] || { liked: 0, neutral: 0, disliked: 0, voters: [] };
                    html += '<tr data-template-id="' + tid + '">'
                        + '<td></td>'
                        + '<td><strong>Template #' + tid + '</strong></td>'
                        + '<td class="el-es-tally-liked">' + r.liked + '</td>'
                        + '<td class="el-es-tally-neutral">' + r.neutral + '</td>'
                        + '<td class="el-es-tally-disliked">' + r.disliked + '</td>'
                        + '<td><label class="el-es-results-confirm-row">'
                        + '<input type="checkbox" class="el-es-confirm-checkbox" name="confirm_template_ids" value="' + tid + '"> Select'
                        + '</label></td>'
                        + '</tr>';
                });

                html += '</tbody></table>';
                body.innerHTML = html;

                if (footer) {
                    footer.innerHTML = '<button type="button" class="el-es-btn el-es-btn-secondary" data-close-modal="mood-board-results">Cancel</button>'
                        + '<button type="button" class="el-es-btn el-es-btn-primary" id="confirm-style-direction-btn" data-review-item-id="' + rid + '">'
                        + '✓ Confirm Style Direction'
                        + '</button>';
                }
            })
            .catch(function(err) {
                if (body) body.innerHTML = '<p style="color:#ef4444;padding:1rem;">Error loading results: ' + (err.message || 'Unknown error') + '</p>';
            });
    });

    // ── DM: Confirm Style Direction ──
    document.addEventListener('click', function(e) {
        var confirmBtn = e.target.closest('#confirm-style-direction-btn');
        if (!confirmBtn) return;

        var rid = confirmBtn.dataset.reviewItemId;
        var checkboxes = document.querySelectorAll('#mood-board-results .el-es-confirm-checkbox:checked');
        var confirmedIds = [];
        checkboxes.forEach(function(cb) { confirmedIds.push(parseInt(cb.value, 10)); });

        if (confirmedIds.length === 0) {
            alert('Please select at least one template as the confirmed style direction.');
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Confirming…';

        ELCore.ajax('es_close_review', {
            review_item_id:       rid,
            confirmed_template_ids: confirmedIds
        }).then(function(result) {
            alert(result.message || 'Style direction confirmed!');
            window.location.reload();
        }).catch(function(err) {
            alert(err.message || 'Failed to confirm direction.');
            confirmBtn.disabled = false;
            confirmBtn.textContent = '✓ Confirm Style Direction';
        });
    });
})();

// ===========================================
// USER JOURNEY -- Portal (Stage 4)
// ===========================================

(function() {
    'use strict';

    // === User Journey â€” Portal (Stage 4) ===

    // Assign button (DM assigns a stakeholder to a journey)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-journey-assign-btn');
        if (!btn) return;

        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;

        // Scope to the card so we get the right select when multiple cards exist
        var card   = btn.closest('.el-es-journey-card');
        var select = card
            ? card.querySelector('.el-es-journey-assign-select')
            : document.querySelector('.el-es-journey-assign-select[data-journey-id="' + journeyId + '"]');

        // Find or create a status message element inside the card
        var statusEl = card ? card.querySelector('.el-es-journey-assign-status') : null;
        if (!statusEl && card) {
            var assignRow = card.querySelector('.el-es-journey-assign-row');
            if (assignRow) {
                statusEl = document.createElement('p');
                statusEl.className = 'el-es-journey-assign-status';
                statusEl.style.cssText = 'margin-top:8px;font-size:13px;color:#c0392b;';
                assignRow.appendChild(statusEl);
            }
        }

        var showError = function(msg) {
            if (statusEl) { statusEl.textContent = msg; statusEl.style.display = 'block'; }
            else { alert(msg); }
        };

        if (!select || !select.value) {
            showError('Please select a team member first.');
            return;
        }

        if (statusEl) { statusEl.style.display = 'none'; statusEl.textContent = ''; }

        var assigneeId   = select.value;
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Assigning...';

        ELCore.ajax('es_dm_assign_journey', { journey_id: journeyId, project_id: projectId, assigned_to: assigneeId })
            .then(function() {
                window.location.reload();
            })
            .catch(function(err) {
                showError(err.message || 'Failed to assign. Please try again.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

    // Reassign toggle (show/hide reassign form)
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.el-es-journey-reassign-toggle');
        if (!toggle) return;
        e.preventDefault();
        var journeyId = toggle.dataset.journeyId;
        var form      = document.querySelector('.el-es-journey-reassign-form[data-journey-id="' + journeyId + '"]');
        if (!form) return;
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    // Journey answer form -- enable submit when all 5 fields have text
    document.addEventListener('input', function(e) {
        var textarea = e.target.closest('.el-es-journey-answer');
        if (!textarea) return;
        var form   = textarea.closest('.el-es-journey-form');
        if (!form) return;
        var fields = form.querySelectorAll('.el-es-journey-answer');
        var allFilled = true;
        fields.forEach(function(f) { if (!f.value.trim()) allFilled = false; });
        var submitBtn = form.querySelector('.el-es-journey-submit-btn');
        if (submitBtn) submitBtn.disabled = !allFilled;
    });

    // Journey answer form submit
    document.addEventListener('submit', function(e) {
        var form = e.target.closest('.el-es-journey-form');
        if (!form) return;
        e.preventDefault();
        var journeyId = form.dataset.journeyId;
        var projectId = form.dataset.projectId;
        var submitBtn = form.querySelector('.el-es-journey-submit-btn');
        var statusEl  = form.querySelector('.el-es-journey-submit-status');

        var answers = {};
        for (var n = 1; n <= 6; n++) {
            var field = form.querySelector('[name="answer_' + n + '"]');
            answers['answer_' + n] = field ? field.value : '';
        }

        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }
        if (statusEl)  { statusEl.style.display = 'inline'; statusEl.textContent = 'Generating workflow...'; }

        ELCore.ajax('es_submit_journey_answers', Object.assign({ journey_id: journeyId, project_id: projectId }, answers))
            .then(function(result) {
                if (statusEl) statusEl.textContent = '';
                form.innerHTML = '<div class="el-es-journey-submitted-notice">'
                    + '<p>' + (result.message || 'Thank you! Our team will review and build out this workflow.') + '</p>'
                    + '</div>';
            })
            .catch(function(err) {
                if (statusEl) { statusEl.style.display = 'none'; }
                alert(err.message || 'Failed to submit answers. Please try again.');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit My Input'; }
            });
    });

})();

// ===========================================
// USER JOURNEY — Pending DM Review state
// ===========================================

(function() {
    'use strict';

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-journey-pdm-send-btn');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var projectId = btn.dataset.projectId;
        var wrapper   = btn.closest('.el-es-journey-pdm-wrapper');
        var notesEl   = wrapper ? wrapper.querySelector('.el-es-journey-pdm-dm-notes') : null;
        var notes     = notesEl ? notesEl.value.trim() : '';

        if (!confirm('Send these answers to the project manager? They will generate the workflow from this point.')) return;

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Sending…';

        var answers = {};
        wrapper.querySelectorAll('.el-es-journey-pdm-answer-edit').forEach(function(ta, idx) {
            answers['answer_' + (idx + 1)] = ta.value;
        });

        ELCore.ajax('es_dm_send_to_admin', Object.assign({ journey_id: journeyId, project_id: projectId, dm_notes: notes }, answers))
            .then(function() {
                var dmSection = btn.closest('.el-es-journey-pdm-dm-section');
                if (dmSection) {
                    dmSection.innerHTML = '<p class="el-es-journey-card-info">'
                        + '✓ Sent to the project manager. They will generate the workflow shortly.'
                        + '</p>';
                }
            })
            .catch(function(err) {
                alert(err.message || 'Failed to send. Please try again.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

})();

// ===========================================
// USER JOURNEY — Review (in_review state)
// ===========================================

(function() {
    'use strict';

    // Verdict buttons (Looks good / Flag for changes per step)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-journey-verdict-btn');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var reviewId  = btn.dataset.reviewId;
        var stepKey   = btn.dataset.stepKey;
        var verdict   = btn.dataset.verdict;
        var row       = btn.closest('.el-es-journey-verdict-row');

        ELCore.ajax('es_journey_step_verdict', { journey_id: journeyId, review_id: reviewId, step_key: stepKey, verdict: verdict })
            .then(function(resp) {
                if (row) {
                    row.querySelectorAll('.el-es-journey-verdict-btn').forEach(function(b) {
                        b.classList.toggle('el-es-journey-verdict-btn--active', b.dataset.verdict === verdict);
                    });
                }
                // Update or create my verdict banner
                var step = btn.closest('.el-es-journey-review-step');
                if (step) {
                    var existing = step.querySelector('.el-es-journey-my-verdict-banner');
                    if (existing) existing.remove();
                    var banner = document.createElement('div');
                    banner.className = 'el-es-journey-my-verdict-banner el-es-journey-my-verdict-banner--' + verdict;
                    banner.dataset.stepKey = stepKey;
                    if (verdict === 'approved') {
                        banner.innerHTML = '&#10003; You marked this step "Looks good"';
                    } else {
                        banner.innerHTML = '&#9872; You flagged this step for changes';
                    }
                    if (row) row.after(banner);
                }
            })
            .catch(function(err) { alert(err.message || 'Failed to save verdict.'); });
    });

    // ── Step inline edit ──
    document.addEventListener('click', function(e) {
        // Show edit form
        var toggleBtn = e.target.closest('.el-es-journey-step-edit-toggle');
        if (toggleBtn) {
            var stepKey = toggleBtn.dataset.stepKey;
            var stepEl  = toggleBtn.closest('.el-es-journey-review-step');
            if (!stepEl) return;
            stepEl.querySelector('.el-es-journey-step-view').style.display    = 'none';
            stepEl.querySelector('.el-es-journey-step-edit-form').style.display = 'block';
            return;
        }
        // Cancel edit
        var cancelBtn = e.target.closest('.el-es-journey-step-edit-cancel');
        if (cancelBtn) {
            var stepEl = cancelBtn.closest('.el-es-journey-review-step');
            if (!stepEl) return;
            stepEl.querySelector('.el-es-journey-step-view').style.display    = 'block';
            stepEl.querySelector('.el-es-journey-step-edit-form').style.display = 'none';
            return;
        }
        // Save edit
        var saveBtn = e.target.closest('.el-es-journey-step-edit-save');
        if (saveBtn) {
            var journeyId = saveBtn.dataset.journeyId;
            var reviewId  = saveBtn.dataset.reviewId;
            var stepKey   = saveBtn.dataset.stepKey;
            var stepEl    = saveBtn.closest('.el-es-journey-review-step');
            if (!stepEl) return;
            var labelEl = stepEl.querySelector('.el-es-journey-step-edit-label');
            var descEl  = stepEl.querySelector('.el-es-journey-step-edit-desc');
            var newLabel = labelEl ? labelEl.value.trim() : '';
            var newDesc  = descEl  ? descEl.value.trim()  : '';
            var orig = saveBtn.textContent;
            saveBtn.disabled = true; saveBtn.textContent = 'Saving…';
            ELCore.ajax('es_save_journey_step_edit', {
                journey_id: journeyId, review_id: reviewId, step_key: stepKey,
                edit_action: 'update', new_label: newLabel, new_desc: newDesc
            }).then(function() {
                // Update the visible label/desc without a full reload
                var viewEl = stepEl.querySelector('.el-es-journey-step-view');
                if (viewEl) {
                    viewEl.querySelector('.el-es-journey-step-label').innerHTML = '<strong>' + newLabel + '</strong>';
                    viewEl.querySelector('.el-es-journey-step-desc').textContent = newDesc;
                }
                stepEl.querySelector('.el-es-journey-step-view').style.display    = 'block';
                stepEl.querySelector('.el-es-journey-step-edit-form').style.display = 'none';
                saveBtn.disabled = false; saveBtn.textContent = orig;
            }).catch(function(err) {
                alert(err.message || 'Failed to save step edit.');
                saveBtn.disabled = false; saveBtn.textContent = orig;
            });
            return;
        }
    });

    // ── Portal step list: Insert step below ──
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-portal-insert-step-btn');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var reviewId  = btn.dataset.reviewId;
        var stepEl    = btn.closest('.el-es-journey-review-step');
        var stepKey   = stepEl ? stepEl.dataset.stepKey : '';
        ELCore.ajax('es_save_journey_step_edit', {
            journey_id: journeyId, review_id: reviewId, step_key: stepKey,
            edit_action: 'insert_after', new_label: '', new_desc: ''
        }).then(function() {
            window.location.reload();
        }).catch(function(err) { alert(err.message || 'Failed to insert step.'); });
    });

    // ── Portal step list: Remove step ──
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-portal-remove-step-btn');
        if (!btn) return;
        if (!confirm('Remove this step from the workflow?')) return;
        var journeyId = btn.dataset.journeyId;
        var reviewId  = btn.dataset.reviewId;
        var stepEl    = btn.closest('.el-es-journey-review-step');
        var stepKey   = stepEl ? stepEl.dataset.stepKey : '';
        ELCore.ajax('es_save_journey_step_edit', {
            journey_id: journeyId, review_id: reviewId, step_key: stepKey,
            edit_action: 'remove', new_label: '', new_desc: ''
        }).then(function() {
            window.location.reload();
        }).catch(function(err) { alert(err.message || 'Failed to remove step.'); });
    });

    // Comment toggle (+ Add comment per step)
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.el-es-journey-comment-toggle');
        if (!toggle) return;
        var stepKey = toggle.dataset.stepKey || '';
        var step    = toggle.closest('.el-es-journey-review-step, .el-es-journey-overall-comments');
        if (!step) return;
        var form = step.querySelector('.el-es-journey-comment-form[data-step-key="' + stepKey + '"]');
        if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    // Reply toggle
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.el-es-journey-reply-toggle');
        if (!toggle) return;
        var commentId = toggle.dataset.commentId;
        var form = document.querySelector('.el-es-journey-reply-form[data-comment-id="' + commentId + '"]');
        if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    // Post comment (step comment or overall)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-journey-comment-submit');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var reviewId  = btn.dataset.reviewId;
        var stepKey   = btn.dataset.stepKey;
        var container = btn.closest('.el-es-journey-comment-form, .el-es-journey-overall-comments');
        var textarea  = container ? container.querySelector('.el-es-journey-comment-input') : null;
        var comment   = textarea ? textarea.value.trim() : '';
        if (!comment) { alert('Please enter a comment.'); return; }

        var originalText = btn.textContent;
        btn.disabled = true;

        ELCore.ajax('es_post_journey_comment', { journey_id: journeyId, review_id: reviewId, step_key: stepKey, comment: comment })
            .then(function(result) {
                // Append comment to the list
                var list = container ? container.querySelector('.el-es-journey-step-comments') : null;
                if (!list) {
                    list = document.createElement('ul');
                    list.className = 'el-es-journey-step-comments';
                    container.insertBefore(list, btn.previousElementSibling || btn);
                }
                var li = document.createElement('li');
                li.innerHTML = '<span class="el-es-journey-comment-author">' + (result.author || 'You') + '</span> '
                    + '<span class="el-es-journey-comment-text">' + comment + '</span>';
                list.appendChild(li);
                if (textarea) textarea.value = '';
                btn.disabled = false;
                btn.textContent = originalText;
            })
            .catch(function(err) {
                alert(err.message || 'Failed to post comment.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

    // Post reply
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-journey-reply-submit');
        if (!btn) return;
        var journeyId = btn.dataset.journeyId;
        var reviewId  = btn.dataset.reviewId;
        var stepKey   = btn.dataset.stepKey;
        var parentId  = btn.dataset.parentId;
        var form      = btn.closest('.el-es-journey-reply-form');
        var textarea  = form ? form.querySelector('.el-es-journey-reply-input') : null;
        var comment   = textarea ? textarea.value.trim() : '';
        if (!comment) { alert('Please enter a reply.'); return; }

        var originalText = btn.textContent;
        btn.disabled = true;

        ELCore.ajax('es_post_journey_comment', { journey_id: journeyId, review_id: reviewId, step_key: stepKey, parent_id: parentId, comment: comment })
            .then(function(result) {
                var commentLi = document.querySelector('.el-es-journey-comment[data-comment-id="' + parentId + '"]');
                var replies = commentLi ? commentLi.querySelector('.el-es-journey-comment-replies') : null;
                if (!replies && commentLi) {
                    replies = document.createElement('ul');
                    replies.className = 'el-es-journey-comment-replies';
                    commentLi.appendChild(replies);
                }
                if (replies) {
                    var li = document.createElement('li');
                    li.innerHTML = '<span class="el-es-journey-comment-author">' + (result.author || 'You') + '</span> '
                        + '<span class="el-es-journey-comment-text">' + comment + '</span>';
                    replies.appendChild(li);
                }
                if (textarea) textarea.value = '';
                if (form) form.style.display = 'none';
                btn.disabled = false;
                btn.textContent = originalText;
            })
            .catch(function(err) {
                alert(err.message || 'Failed to post reply.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

    // DM decision (Accept / Needs Revision)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.el-es-journey-dm-decision-btn');
        if (!btn) return;
        var decision  = btn.dataset.decision;
        var journeyId = btn.dataset.journeyId;
        var reviewId  = btn.dataset.reviewId;
        var dmSection = btn.closest('.el-es-journey-dm-decision');
        // Read suggested edits from the new edit notes textarea
        var editEl    = dmSection ? dmSection.querySelector('.el-es-journey-dm-edit-notes') : null;
        var dm_note   = editEl ? editEl.value.trim() : '';

        if (decision === 'approved') {
            var confirmMsg = dm_note
                ? 'You have suggested edits — are you sure you want to Accept? Your notes will be sent to the project manager but the workflow will be marked approved.'
                : 'Accept this workflow? This cannot be undone.';
            if (!confirm(confirmMsg)) return;
        }
        if (decision === 'needs_revision' && !confirm('Request changes? The project manager will receive your notes and revise the workflow before sending it back.')) return;

        var originalText = btn.textContent;
        btn.disabled = true;

        ELCore.ajax('es_dm_journey_decision', { journey_id: journeyId, review_id: reviewId, decision: decision, dm_note: dm_note })
            .then(function(result) {
                if (dmSection) {
                    dmSection.innerHTML = '<p class="el-es-journey-dm-decided">'
                        + (decision === 'approved'
                            ? '✓ You approved this journey. The project manager will lock it shortly.'
                            : '⚑ Revision requested. The project manager will review your notes and send an updated version.')
                        + '</p>';
                }
            })
            .catch(function(err) {
                alert(err.message || 'Failed to submit decision.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

})();
