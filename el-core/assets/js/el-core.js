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
