<?php
/**
 * EL Core Uninstall
 *
 * Intentionally left empty.
 *
 * This plugin is a permanent installation — settings, database tables, and
 * capabilities must survive plugin replacement/updates.  The previous version
 * of this file deleted all el_core_* options and el_* tables, which caused
 * API keys, brand settings, and module config to be wiped on every update
 * when the hosting platform performs a delete-then-install cycle.
 *
 * If a full data purge is ever truly needed, run the cleanup manually via
 * WP-CLI or a custom script.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// No-op: preserve all data across plugin updates.
