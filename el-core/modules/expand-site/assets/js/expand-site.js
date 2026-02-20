/**
 * Expand Site — Client Portal JavaScript
 *
 * Vanilla JS only. Uses ELCore.ajax(). Event delegation on document.
 */

(function() {
    'use strict';

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

})();
