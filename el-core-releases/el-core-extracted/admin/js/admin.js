/**
 * EL Core — Admin Scripts
 */

jQuery(document).ready(function($) {
    // Enqueue WordPress media uploader on brand settings page
    if ($('#el-upload-logo').length) {
        wp.media.editor || (wp.media.editor = {});
    }
});
