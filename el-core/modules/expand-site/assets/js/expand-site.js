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
