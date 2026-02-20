/**
 * EL Core — Admin UI Framework Scripts
 *
 * Provides the elAdmin namespace with reusable functions for modals,
 * tabs, filter bars, and dismissible notices.
 *
 * Modules use these functions — they never re-implement this logic.
 *
 * @package EL_Core
 * @since   1.1.0
 */

/* global elAdminData */

const elAdmin = ( function() {

    'use strict';

    // -------------------------------------------------------------------------
    // MODALS
    // -------------------------------------------------------------------------

    /**
     * Open a modal by ID.
     * @param {string} id - The modal element ID.
     */
    function openModal( id ) {
        const modal = document.getElementById( id );
        if ( ! modal ) return;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        // Focus the first focusable element inside
        const focusable = modal.querySelector( 'input, select, textarea, button, [href]' );
        if ( focusable ) {
            setTimeout( () => focusable.focus(), 50 );
        }
    }

    /**
     * Close a modal by ID.
     * @param {string} id - The modal element ID.
     */
    function closeModal( id ) {
        const modal = document.getElementById( id );
        if ( ! modal ) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    /**
     * Close all open modals.
     */
    function closeAllModals() {
        document.querySelectorAll( '.el-modal' ).forEach( modal => {
            modal.style.display = 'none';
        } );
        document.body.style.overflow = '';
    }

    // Bind modal close buttons and overlay clicks (event delegation)
    document.addEventListener( 'click', function( e ) {
        const closer = e.target.closest( '[data-modal-close]' );
        if ( closer ) {
            closeModal( closer.dataset.modalClose );
            return;
        }

        const opener = e.target.closest( '[data-modal-open]' );
        if ( opener ) {
            openModal( opener.dataset.modalOpen );
            return;
        }
    } );

    // Close modal on Escape key
    document.addEventListener( 'keydown', function( e ) {
        if ( e.key === 'Escape' ) {
            closeAllModals();
        }
    } );

    // -------------------------------------------------------------------------
    // TABS
    // -------------------------------------------------------------------------

    /**
     * Switch to a tab within a group.
     * @param {string} tabId  - The tab ID to activate.
     * @param {string} groupId - The tab group ID.
     */
    function switchTab( tabId, groupId ) {
        // Deactivate all buttons in this group
        document.querySelectorAll( `.el-tab-btn[data-group="${ groupId }"]` ).forEach( btn => {
            btn.classList.remove( 'active' );
        } );

        // Hide all panels in this group
        document.querySelectorAll( `.el-tab-content[data-group="${ groupId }"]` ).forEach( panel => {
            panel.style.display = 'none';
        } );

        // Activate the selected button
        const activeBtn = document.querySelector(
            `.el-tab-btn[data-group="${ groupId }"][data-tab="${ tabId }"]`
        );
        if ( activeBtn ) activeBtn.classList.add( 'active' );

        // Show the selected panel
        const activePanel = document.querySelector(
            `.el-tab-content[data-group="${ groupId }"][data-tab="${ tabId }"]`
        );
        if ( activePanel ) activePanel.style.display = 'block';
    }

    // Bind tab button clicks (event delegation)
    document.addEventListener( 'click', function( e ) {
        const btn = e.target.closest( '.el-tab-btn' );
        if ( ! btn ) return;
        const tabId  = btn.dataset.tab;
        const groupId = btn.dataset.group;
        if ( tabId && groupId ) {
            switchTab( tabId, groupId );
        }
    } );

    // -------------------------------------------------------------------------
    // NOTICES
    // -------------------------------------------------------------------------

    /**
     * Dismiss a notice element.
     * @param {HTMLElement} notice - The .el-notice element to remove.
     */
    function dismissNotice( notice ) {
        notice.style.transition = 'opacity 0.2s ease';
        notice.style.opacity    = '0';
        setTimeout( () => notice.remove(), 200 );
    }

    // Bind notice close buttons (event delegation)
    document.addEventListener( 'click', function( e ) {
        const btn = e.target.closest( '.el-notice-close' );
        if ( ! btn ) return;
        const notice = btn.closest( '.el-notice' );
        if ( notice ) dismissNotice( notice );
    } );

    // -------------------------------------------------------------------------
    // FILTER BAR
    // -------------------------------------------------------------------------

    /**
     * Initialize auto-submit behavior on filter selects.
     * Selects in .el-filter-form submit the form on change automatically.
     */
    function initFilters() {
        document.querySelectorAll( '.el-filter-form .el-filter-select' ).forEach( select => {
            select.addEventListener( 'change', function() {
                this.closest( 'form' ).submit();
            } );
        } );
    }

    // -------------------------------------------------------------------------
    // UTILITIES
    // -------------------------------------------------------------------------

    /**
     * Show a temporary notice at the top of the page.
     * Useful for AJAX responses where you need to show feedback without a reload.
     *
     * @param {string} message - Notice text.
     * @param {string} type    - 'success', 'error', 'warning', or 'info'.
     * @param {number} duration - Auto-dismiss after this many ms. 0 = no auto-dismiss.
     */
    function flashNotice( message, type = 'success', duration = 4000 ) {
        const icons = {
            success: 'yes-alt',
            error:   'dismiss',
            warning: 'warning',
            info:    'info',
        };
        const icon = icons[ type ] || 'info';

        const el = document.createElement( 'div' );
        el.className = `el-notice el-notice-${ type } el-notice-dismissible`;
        el.style.marginBottom = '1rem';
        el.innerHTML = `
            <span class="dashicons dashicons-${ icon }"></span>
            <div class="el-notice-message">${ message }</div>
            <button type="button" class="el-notice-close" aria-label="Dismiss">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        `;

        // Insert after .el-page-header if it exists, otherwise at top of wrap
        const wrap   = document.querySelector( '.el-admin-wrap' );
        const header = document.querySelector( '.el-page-header' );
        if ( wrap ) {
            if ( header && header.nextSibling ) {
                wrap.insertBefore( el, header.nextSibling );
            } else {
                wrap.prepend( el );
            }
        }

        if ( duration > 0 ) {
            setTimeout( () => { if ( el.parentNode ) dismissNotice( el ); }, duration );
        }
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    document.addEventListener( 'DOMContentLoaded', function() {
        initFilters();
    } );

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    return {
        openModal,
        closeModal,
        closeAllModals,
        switchTab,
        dismissNotice,
        flashNotice,
        initFilters,
    };

} )();
