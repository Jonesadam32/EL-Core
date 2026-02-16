/**
 * EL Core — Frontend JavaScript
 * 
 * Provides AJAX helper and handles interactive components.
 * elCore.ajaxUrl and elCore.nonce are provided by wp_localize_script.
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════
    // AJAX HELPER
    // ═══════════════════════════════════════════

    window.ELCore = {
        /**
         * Send an AJAX request to EL Core
         * 
         * @param {string} elAction - The el_action name (e.g., 'rsvp_event')
         * @param {object} data     - Additional data to send
         * @returns {Promise}
         */
        ajax: function(elAction, data = {}) {
            const formData = new FormData();
            formData.append('action', 'el_core_action');
            formData.append('el_action', elAction);
            formData.append('nonce', elCore.nonce);

            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }

            return fetch(elCore.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.data?.message || 'Request failed');
                }
                return result.data;
            });
        }
    };

    // ═══════════════════════════════════════════
    // REGISTRATION FORM HANDLER
    // ═══════════════════════════════════════════

    document.addEventListener('submit', function(e) {
        const form = e.target.closest('#el-register-form');
        if (!form) return;

        e.preventDefault();

        const statusEl = form.querySelector('.el-form-status');
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;

        // Client-side password match check
        const password = form.querySelector('[name="password"]');
        const confirm = form.querySelector('[name="password_confirm"]');
        if (password && confirm && password.value !== confirm.value) {
            showStatus(statusEl, 'Passwords do not match.', 'error');
            confirm.focus();
            return;
        }

        // Disable submit
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating account...';
        clearStatus(statusEl);

        // Gather all form data
        const data = {};
        new FormData(form).forEach((value, key) => {
            data[key] = value;
        });

        ELCore.ajax('register_user', data)
            .then(result => {
                showStatus(statusEl, result.message || 'Account created successfully!', 'success');
                form.reset();

                // If there's a redirect URL, go there after a short delay
                if (result.data?.redirect) {
                    setTimeout(() => { window.location.href = result.data.redirect; }, 1500);
                }
            })
            .catch(err => {
                showStatus(statusEl, err.message, 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
    });

    // ═══════════════════════════════════════════
    // PROFILE FORM HANDLER
    // ═══════════════════════════════════════════

    document.addEventListener('submit', function(e) {
        const form = e.target.closest('#el-profile-form');
        if (!form) return;

        e.preventDefault();

        const statusEl = form.querySelector('.el-form-status');
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        clearStatus(statusEl);

        const data = {};
        new FormData(form).forEach((value, key) => {
            data[key] = value;
        });

        ELCore.ajax('update_profile', data)
            .then(result => {
                showStatus(statusEl, result.message || 'Profile updated.', 'success');
            })
            .catch(err => {
                showStatus(statusEl, err.message, 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
    });

    // ═══════════════════════════════════════════
    // INVITE CODE TOGGLE (optional field on open/approval modes)
    // ═══════════════════════════════════════════

    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.el-invite-toggle');
        if (!toggle) return;

        e.preventDefault();

        const wrapper = toggle.closest('.el-field');
        const input = wrapper?.querySelector('.el-invite-field-wrapper');
        if (!input) return;

        const isHidden = input.style.display === 'none';
        input.style.display = isHidden ? 'block' : 'none';
        toggle.textContent = isHidden ? 'Hide invite code' : 'Have an invite code?';
    });

    // ═══════════════════════════════════════════
    // RESEND VERIFICATION EMAIL
    // ═══════════════════════════════════════════

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.el-resend-verification');
        if (!btn) return;

        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Sending...';

        ELCore.ajax('resend_verification', {})
            .then(result => {
                btn.textContent = 'Email sent!';
                setTimeout(() => {
                    btn.textContent = 'Resend verification email';
                    btn.disabled = false;
                }, 5000);
            })
            .catch(err => {
                btn.textContent = 'Failed — try again';
                btn.disabled = false;
            });
    });

    // ═══════════════════════════════════════════
    // STATUS HELPERS
    // ═══════════════════════════════════════════

    function showStatus(el, message, type) {
        if (!el) return;
        el.textContent = message;
        el.className = 'el-form-status el-notice el-notice-' + (type === 'error' ? 'error' : 'success');
        el.style.display = 'block';
    }

    function clearStatus(el) {
        if (!el) return;
        el.textContent = '';
        el.style.display = 'none';
        el.className = 'el-form-status';
    }

    // ═══════════════════════════════════════════
    // RSVP BUTTON HANDLER
    // ═══════════════════════════════════════════

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.el-rsvp-btn');
        if (!btn) return;

        e.preventDefault();

        const eventId = btn.dataset.eventId;
        const container = btn.closest('.el-event-rsvp');
        const statusEl = container?.querySelector('.el-rsvp-status');

        // Disable button during request
        btn.disabled = true;
        btn.textContent = 'Processing...';

        ELCore.ajax('rsvp_event', { event_id: eventId })
            .then(result => {
                if (result.data?.rsvp_status === 'attending') {
                    btn.textContent = 'Cancel RSVP';
                    btn.className = 'el-btn el-btn-outline el-rsvp-btn';
                } else {
                    btn.textContent = 'RSVP Now';
                    btn.className = 'el-btn el-btn-primary el-rsvp-btn';
                }

                if (statusEl) {
                    statusEl.textContent = result.message || '';
                    statusEl.style.display = 'inline';
                    setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
                }
            })
            .catch(err => {
                if (statusEl) {
                    statusEl.textContent = err.message;
                    statusEl.style.display = 'inline';
                    statusEl.style.color = '#e94560';
                }
                // Restore button text
                btn.textContent = btn.className.includes('outline') ? 'Cancel RSVP' : 'RSVP Now';
            })
            .finally(() => {
                btn.disabled = false;
            });
    });

})();
