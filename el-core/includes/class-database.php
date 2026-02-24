<?php
/**
 * EL Core Database Manager
 * 
 * Handles schema creation, versioning, and automatic migrations.
 * Modules declare their schema in module.json — this class executes it.
 * 
 * Key features:
 * - Automatic table creation from module manifests
 * - Version-tracked schema migrations
 * - Convenience query methods with sanitization
 * - WordPress multisite compatible table prefixing
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Database {

    private \wpdb $wpdb;

    /**
     * Installed schema versions, keyed by module slug
     * Stored in wp_options: el_core_schema_versions
     * Example: ['lms' => 3, 'events' => 2]
     */
    private array $schema_versions;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->schema_versions = (array) get_option( 'el_core_schema_versions', [] );
    }

    // ═══════════════════════════════════════════
    // SCHEMA MANAGEMENT
    // ═══════════════════════════════════════════

    /**
     * Process a module's database schema
     * Called by Module Loader when a module is activated or updated
     * 
     * @param string $slug     Module slug
     * @param array  $db_config Database section from module.json
     */
    public function process_module_schema( string $slug, array $db_config ): void {
        $declared_version  = $db_config['version'] ?? 1;
        $installed_version = $this->schema_versions[ $slug ] ?? 0;

        // Nothing to do if up to date
        if ( $installed_version >= $declared_version ) {
            return;
        }

        // First time: create all tables (only in admin to avoid loading upgrade.php on frontend)
        if ( $installed_version === 0 && isset( $db_config['tables'] ) ) {
            if ( ! is_admin() ) {
                return; // Skip schema creation on frontend — will run on next admin page load
            }
            foreach ( $db_config['tables'] as $table_name => $columns ) {
                $this->create_table( $table_name, $columns );
            }
        }

        // Run any migrations between installed and declared version
        if ( isset( $db_config['migrations'] ) ) {
            for ( $v = $installed_version + 1; $v <= $declared_version; $v++ ) {
                $v_key = (string) $v;
                if ( isset( $db_config['migrations'][ $v_key ] ) ) {
                    $statements = $db_config['migrations'][ $v_key ];
                    // Migrations can be a single string or array of strings
                    if ( is_string( $statements ) ) {
                        $statements = [ $statements ];
                    }
                    foreach ( $statements as $sql ) {
                        // Replace table names with prefixed versions
                        $sql = $this->prefix_table_names_in_sql( $sql );
                        $this->wpdb->query( $sql );

                        if ( $this->wpdb->last_error ) {
                            error_log( "EL Core: Migration error (module: {$slug}, v{$v}): " . $this->wpdb->last_error );
                        }
                    }
                }
            }
        }

        // Update stored version
        $this->schema_versions[ $slug ] = $declared_version;
        update_option( 'el_core_schema_versions', $this->schema_versions );
    }

    /**
     * Create a table from a column definition array
     * 
     * WordPress dbDelta() is very picky about SQL format:
     * - No "IF NOT EXISTS"
     * - PRIMARY KEY must be on separate line with two spaces before it
     * - Column definitions need proper spacing
     * 
     * @param string $table_name Unprefixed table name (e.g., 'el_courses')
     * @param array  $columns    Column definitions
     */
    public function create_table( string $table_name, array $columns ): void {
        $full_name = $this->get_table_name( $table_name );
        $charset   = $this->wpdb->get_charset_collate();

        $column_sql = [];
        $primary_key = null;

        foreach ( $columns as $col_name => $col_def ) {
            // Check if this column has PRIMARY KEY inline
            if ( stripos( $col_def, 'PRIMARY KEY' ) !== false ) {
                // Remove PRIMARY KEY from column definition
                $col_def = preg_replace( '/\s*PRIMARY\s+KEY\s*/i', ' ', $col_def );
                $col_def = trim( $col_def );
                // Add NOT NULL if not present (required for primary key)
                if ( stripos( $col_def, 'NOT NULL' ) === false ) {
                    $col_def .= ' NOT NULL';
                }
                $primary_key = $col_name;
            }
            $column_sql[] = "{$col_name} {$col_def}";
        }

        // Build SQL - dbDelta needs exact formatting
        $sql = "CREATE TABLE {$full_name} (\n";
        $sql .= implode( ",\n", $column_sql );
        
        // Add PRIMARY KEY on separate line (two spaces before PRIMARY KEY is required by dbDelta)
        if ( $primary_key ) {
            $sql .= ",\n  PRIMARY KEY  ({$primary_key})";
        }
        
        $sql .= "\n) {$charset};";

        // Use dbDelta for safe table creation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( $this->wpdb->last_error ) {
            error_log( "EL Core: Table creation error ({$table_name}): " . $this->wpdb->last_error );
        }
    }

    /**
     * Drop all tables for a module (used during uninstall)
     */
    public function drop_module_tables( string $slug, array $table_names ): void {
        foreach ( $table_names as $table_name ) {
            $full_name = $this->get_table_name( $table_name );
            $this->wpdb->query( "DROP TABLE IF EXISTS {$full_name}" );
        }

        // Remove version tracking
        unset( $this->schema_versions[ $slug ] );
        update_option( 'el_core_schema_versions', $this->schema_versions );
    }

    /**
     * Get the full prefixed table name
     * WordPress multisite uses different prefixes per site
     */
    public function get_table_name( string $table_name ): string {
        return $this->wpdb->prefix . $table_name;
    }

    /**
     * Replace el_ table names in SQL with prefixed versions
     */
    private function prefix_table_names_in_sql( string $sql ): string {
        // Match table names that start with el_ in common SQL contexts
        return preg_replace_callback(
            '/\b(el_\w+)\b/',
            fn( $matches ) => $this->get_table_name( $matches[1] ),
            $sql
        );
    }

    // ═══════════════════════════════════════════
    // CORE TABLE MANAGEMENT
    // ═══════════════════════════════════════════

    /**
     * Ensure core infrastructure tables exist (el_organizations, el_contacts).
     * Called once during boot — uses a version option to avoid re-running.
     */
    public function ensure_core_tables(): void {
        if ( ! is_admin() ) {
            return;
        }

        $core_schema_version = (int) get_option( 'el_core_db_version', 0 );
        $target_version = 1;

        if ( $core_schema_version >= $target_version ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();

        $org_table = $this->get_table_name( 'el_organizations' );
        $sql = "CREATE TABLE {$org_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(20) DEFAULT 'nonprofit',
            status varchar(20) DEFAULT 'prospect',
            address text,
            phone varchar(50) DEFAULT '',
            website varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY type (type)
        ) {$charset};";
        dbDelta( $sql );

        $contacts_table = $this->get_table_name( 'el_contacts' );
        $sql = "CREATE TABLE {$contacts_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) UNSIGNED NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT '',
            title varchar(100) DEFAULT '',
            is_primary tinyint(1) DEFAULT 0,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id),
            KEY email (email),
            KEY user_id (user_id)
        ) {$charset};";
        dbDelta( $sql );

        update_option( 'el_core_db_version', $target_version );
    }

    // ═══════════════════════════════════════════
    // QUERY CONVENIENCE METHODS
    // ═══════════════════════════════════════════

    /**
     * Insert a row
     * 
     * @param string $table  Unprefixed table name
     * @param array  $data   Column => value pairs
     * @return int|false Insert ID or false on failure
     */
    public function insert( string $table, array $data ): int|false {
        $full_name = $this->get_table_name( $table );
        $result = $this->wpdb->insert( $full_name, $data );

        if ( $result === false ) {
            error_log( "EL Core DB Insert Error ({$table}): " . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update rows
     * 
     * @param string $table Unprefixed table name
     * @param array  $data  Column => value pairs to update
     * @param array  $where Column => value pairs for WHERE clause
     * @return int|false Number of rows updated or false on error
     */
    public function update( string $table, array $data, array $where ): int|false {
        $full_name = $this->get_table_name( $table );
        $result = $this->wpdb->update( $full_name, $data, $where );

        if ( $result === false ) {
            error_log( "EL Core DB Update Error ({$table}): " . $this->wpdb->last_error );
        }

        return $result;
    }

    /**
     * Delete rows
     * 
     * @param string $table Unprefixed table name
     * @param array  $where Column => value pairs for WHERE clause
     * @return int|false Number of rows deleted or false on error
     */
    public function delete( string $table, array $where ): int|false {
        $full_name = $this->get_table_name( $table );
        return $this->wpdb->delete( $full_name, $where );
    }

    /**
     * Get a single row by ID
     * 
     * @param string $table Unprefixed table name
     * @param int    $id    Row ID
     * @return object|null
     */
    public function get( string $table, int $id ): ?object {
        $full_name = $this->get_table_name( $table );
        return $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$full_name} WHERE id = %d", $id )
        );
    }

    /**
     * Get multiple rows with simple conditions
     * 
     * @param string $table   Unprefixed table name
     * @param array  $where   Simple conditions (column => value)
     * @param array  $options orderby, order, limit, offset
     * @return array
     */
    public function query( string $table, array $where = [], array $options = [] ): array {
        $full_name = $this->get_table_name( $table );
        $sql = "SELECT * FROM {$full_name}";

        $values = [];

        // Build WHERE clause
        if ( ! empty( $where ) ) {
            $conditions = [];
            foreach ( $where as $col => $val ) {
                // Support operators: 'start_date >' => '2024-01-01'
                if ( preg_match( '/^(\w+)\s*(>|<|>=|<=|!=|LIKE)$/i', $col, $matches ) ) {
                    $col_name = $matches[1];
                    $operator = strtoupper( $matches[2] );
                    $conditions[] = "{$col_name} {$operator} %s";
                    $values[] = $val;
                } else {
                    $conditions[] = "{$col} = %s";
                    $values[] = $val;
                }
            }
            $sql .= " WHERE " . implode( ' AND ', $conditions );
        }

        // ORDER BY
        if ( isset( $options['orderby'] ) ) {
            $orderby = sanitize_sql_orderby( $options['orderby'] . ' ' . ( $options['order'] ?? 'ASC' ) );
            if ( $orderby ) {
                $sql .= " ORDER BY {$orderby}";
            }
        }

        // LIMIT
        if ( isset( $options['limit'] ) ) {
            $sql .= " LIMIT " . absint( $options['limit'] );
        }

        // OFFSET
        if ( isset( $options['offset'] ) ) {
            $sql .= " OFFSET " . absint( $options['offset'] );
        }

        // Prepare if we have values
        if ( ! empty( $values ) ) {
            $sql = $this->wpdb->prepare( $sql, ...$values );
        }

        return $this->wpdb->get_results( $sql );
    }

    /**
     * Count rows matching conditions
     */
    public function count( string $table, array $where = [] ): int {
        $full_name = $this->get_table_name( $table );
        $sql = "SELECT COUNT(*) FROM {$full_name}";

        $values = [];

        if ( ! empty( $where ) ) {
            $conditions = [];
            foreach ( $where as $col => $val ) {
                $conditions[] = "{$col} = %s";
                $values[] = $val;
            }
            $sql .= " WHERE " . implode( ' AND ', $conditions );
        }

        if ( ! empty( $values ) ) {
            $sql = $this->wpdb->prepare( $sql, ...$values );
        }

        return (int) $this->wpdb->get_var( $sql );
    }

    /**
     * Execute a raw query (use sparingly — prefer the typed methods above)
     */
    public function raw( string $sql, ...$args ): mixed {
        if ( ! empty( $args ) ) {
            $sql = $this->wpdb->prepare( $sql, ...$args );
        }
        return $this->wpdb->get_results( $sql );
    }

    /**
     * Get the last insert ID
     */
    public function last_insert_id(): int {
        return $this->wpdb->insert_id;
    }

    /**
     * Get the last error message
     */
    public function last_error(): string {
        return $this->wpdb->last_error;
    }
}
