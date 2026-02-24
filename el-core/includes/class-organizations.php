<?php
/**
 * EL Core — Organizations & Contacts Manager
 *
 * Core infrastructure for managing client organizations and contacts.
 * Referenced by Expand Site projects, future Expand Partners, invoicing, etc.
 * Tables use el_ prefix (not module-specific).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Organizations {

    private EL_Database $db;

    public function __construct( EL_Database $db ) {
        $this->db = $db;
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action( 'el_core_ajax_create_organization',  [ $this, 'handle_create_organization' ] );
        add_action( 'el_core_ajax_update_organization',  [ $this, 'handle_update_organization' ] );
        add_action( 'el_core_ajax_delete_organization',  [ $this, 'handle_delete_organization' ] );
        add_action( 'el_core_ajax_get_organization',     [ $this, 'handle_get_organization' ] );
        add_action( 'el_core_ajax_search_organizations', [ $this, 'handle_search_organizations' ] );
        add_action( 'el_core_ajax_add_contact',          [ $this, 'handle_add_contact' ] );
        add_action( 'el_core_ajax_update_contact',       [ $this, 'handle_update_contact' ] );
        add_action( 'el_core_ajax_delete_contact',       [ $this, 'handle_delete_contact' ] );
        add_action( 'el_core_ajax_get_contact',          [ $this, 'handle_get_contact' ] );

        add_action( 'admin_menu', [ $this, 'register_admin_pages' ], 15 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    // ═══════════════════════════════════════════
    // ADMIN PAGES
    // ═══════════════════════════════════════════

    public function register_admin_pages(): void {
        add_submenu_page(
            'el-core',
            __( 'Clients', 'el-core' ),
            __( 'Clients', 'el-core' ),
            'manage_options',
            'el-core-clients',
            [ $this, 'render_clients_page' ],
            2
        );
    }

    public function render_clients_page(): void {
        if ( isset( $_GET['client_id'] ) ) {
            require_once EL_CORE_DIR . 'admin/views/client-profile.php';
        } else {
            require_once EL_CORE_DIR . 'admin/views/client-list.php';
        }
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'el-core-clients' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'el-core-admin',
            EL_CORE_URL . 'admin/css/admin.css',
            [],
            EL_CORE_VERSION
        );
        wp_enqueue_script(
            'el-core-admin',
            EL_CORE_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            EL_CORE_VERSION,
            true
        );
        wp_enqueue_script(
            'el-core-clients',
            EL_CORE_URL . 'admin/js/clients.js',
            [ 'el-core-admin' ],
            EL_CORE_VERSION,
            true
        );
        wp_localize_script( 'el-core-clients', 'elClientsAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'el_core_nonce' ),
        ] );
    }

    // ═══════════════════════════════════════════
    // QUERIES
    // ═══════════════════════════════════════════

    public function get_organization( int $id ): ?object {
        return $this->db->get( 'el_organizations', $id );
    }

    public function get_all_organizations(): array {
        global $wpdb;
        $org_table     = $this->db->get_table_name( 'el_organizations' );
        $contact_table = $this->db->get_table_name( 'el_contacts' );
        $project_table = $this->db->get_table_name( 'el_es_projects' );

        // Check if el_es_projects table exists before joining
        $projects_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$project_table}'" );

        if ( $projects_exist ) {
            return $wpdb->get_results( "
                SELECT o.*,
                    COUNT(DISTINCT c.id) AS contact_count,
                    COUNT(DISTINCT p.id) AS project_count
                FROM {$org_table} o
                LEFT JOIN {$contact_table} c ON o.id = c.organization_id
                LEFT JOIN {$project_table} p ON o.id = p.organization_id
                GROUP BY o.id
                ORDER BY o.name ASC
            " );
        }

        return $wpdb->get_results( "
            SELECT o.*,
                COUNT(DISTINCT c.id) AS contact_count,
                0 AS project_count
            FROM {$org_table} o
            LEFT JOIN {$contact_table} c ON o.id = c.organization_id
            GROUP BY o.id
            ORDER BY o.name ASC
        " );
    }

    public function get_contacts( int $organization_id ): array {
        return $this->db->query( 'el_contacts', [
            'organization_id' => $organization_id,
        ], [
            'orderby' => 'is_primary DESC, first_name',
            'order'   => 'ASC',
        ] );
    }

    public function get_contact( int $id ): ?object {
        return $this->db->get( 'el_contacts', $id );
    }

    public function get_primary_contact( int $organization_id ): ?object {
        $results = $this->db->query( 'el_contacts', [
            'organization_id' => $organization_id,
            'is_primary'      => 1,
        ], [
            'limit' => 1,
        ] );
        return ! empty( $results ) ? $results[0] : null;
    }

    public function get_projects_for_org( int $organization_id ): array {
        global $wpdb;
        $table = $this->db->get_table_name( 'el_es_projects' );

        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( ! $exists ) {
            return [];
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE organization_id = %d ORDER BY created_at DESC",
            $organization_id
        ) );
    }

    public function search_organizations( string $term ): array {
        global $wpdb;
        $table = $this->db->get_table_name( 'el_organizations' );
        $like  = '%' . $wpdb->esc_like( $term ) . '%';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, type, status FROM {$table} WHERE name LIKE %s ORDER BY name ASC LIMIT 10",
            $like
        ) );
    }

    // ═══════════════════════════════════════════
    // MUTATIONS
    // ═══════════════════════════════════════════

    public function create_organization( array $data ): int|false {
        return $this->db->insert( 'el_organizations', [
            'name'       => sanitize_text_field( $data['name'] ?? '' ),
            'type'       => sanitize_text_field( $data['type'] ?? 'nonprofit' ),
            'status'     => sanitize_text_field( $data['status'] ?? 'prospect' ),
            'address'    => sanitize_textarea_field( $data['address'] ?? '' ),
            'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
            'website'    => esc_url_raw( $data['website'] ?? '' ),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ] );
    }

    public function update_organization( int $id, array $data ): int|false {
        $clean = [];
        if ( isset( $data['name'] ) )    $clean['name']    = sanitize_text_field( $data['name'] );
        if ( isset( $data['type'] ) )    $clean['type']    = sanitize_text_field( $data['type'] );
        if ( isset( $data['status'] ) )  $clean['status']  = sanitize_text_field( $data['status'] );
        if ( isset( $data['address'] ) ) $clean['address'] = sanitize_textarea_field( $data['address'] );
        if ( isset( $data['phone'] ) )   $clean['phone']   = sanitize_text_field( $data['phone'] );
        if ( isset( $data['website'] ) ) $clean['website'] = esc_url_raw( $data['website'] );
        $clean['updated_at'] = current_time( 'mysql' );

        return $this->db->update( 'el_organizations', $clean, [ 'id' => $id ] );
    }

    public function delete_organization( int $id ): bool {
        // Cascade: delete all contacts for this org
        $this->db->delete( 'el_contacts', [ 'organization_id' => $id ] );

        // Unlink projects (set organization_id to 0, don't delete them)
        global $wpdb;
        $project_table = $this->db->get_table_name( 'el_es_projects' );
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$project_table}'" );
        if ( $exists ) {
            $wpdb->update( $project_table, [ 'organization_id' => 0 ], [ 'organization_id' => $id ] );
        }

        $result = $this->db->delete( 'el_organizations', [ 'id' => $id ] );
        return $result !== false;
    }

    public function add_contact( array $data ): int|false {
        $org_id = absint( $data['organization_id'] ?? 0 );
        if ( ! $org_id ) return false;

        $email = sanitize_email( $data['email'] ?? '' );
        if ( ! $email ) return false;

        $user_id = 0;
        $is_primary     = ! empty( $data['is_primary'] );
        $create_wp_user = ! empty( $data['create_wp_user'] );

        // Primary contacts always get portal access — they are the decision maker
        if ( $is_primary || $create_wp_user ) {
            $user_id = $this->create_or_update_portal_user(
                $email,
                sanitize_text_field( $data['first_name'] ?? '' ),
                sanitize_text_field( $data['last_name'] ?? '' )
            );
        }

        return $this->db->insert( 'el_contacts', [
            'organization_id' => $org_id,
            'first_name'      => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'       => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'           => $email,
            'phone'           => sanitize_text_field( $data['phone'] ?? '' ),
            'title'           => sanitize_text_field( $data['title'] ?? '' ),
            'is_primary'      => absint( $data['is_primary'] ?? 0 ),
            'user_id'         => $user_id ?: 0,
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ] );
    }

    public function update_contact( int $id, array $data ): int|false {
        $contact = $this->get_contact( $id );
        if ( ! $contact ) return false;

        $clean = [];
        if ( isset( $data['first_name'] ) ) $clean['first_name'] = sanitize_text_field( $data['first_name'] );
        if ( isset( $data['last_name'] ) )  $clean['last_name']  = sanitize_text_field( $data['last_name'] );
        if ( isset( $data['email'] ) )      $clean['email']      = sanitize_email( $data['email'] );
        if ( isset( $data['phone'] ) )      $clean['phone']      = sanitize_text_field( $data['phone'] );
        if ( isset( $data['title'] ) )      $clean['title']      = sanitize_text_field( $data['title'] );
        if ( isset( $data['is_primary'] ) ) $clean['is_primary'] = absint( $data['is_primary'] );
        $clean['updated_at'] = current_time( 'mysql' );

        // Grant portal access if: explicitly requested, OR contact is being set as primary
        $becoming_primary  = ! empty( $data['is_primary'] ) && absint( $data['is_primary'] ) === 1;
        $grant_requested   = ! empty( $data['grant_portal_access'] );

        if ( ( $grant_requested || $becoming_primary ) && ! $contact->user_id ) {
            $new_user_id = $this->create_or_update_portal_user(
                sanitize_email( $data['email'] ?? $contact->email ),
                sanitize_text_field( $data['first_name'] ?? $contact->first_name ),
                sanitize_text_field( $data['last_name'] ?? $contact->last_name )
            );
            if ( $new_user_id ) {
                $clean['user_id'] = $new_user_id;
            }
        }

        // Update WP user info if already linked
        if ( $contact->user_id && ( isset( $data['first_name'] ) || isset( $data['last_name'] ) || isset( $data['email'] ) ) ) {
            $user_update = [ 'ID' => (int) $contact->user_id ];
            if ( isset( $data['first_name'] ) ) $user_update['first_name'] = sanitize_text_field( $data['first_name'] );
            if ( isset( $data['last_name'] ) )  $user_update['last_name']  = sanitize_text_field( $data['last_name'] );
            if ( isset( $data['first_name'] ) && isset( $data['last_name'] ) ) {
                $user_update['display_name'] = trim( sanitize_text_field( $data['first_name'] ) . ' ' . sanitize_text_field( $data['last_name'] ) );
            }
            wp_update_user( $user_update );
        }

        return $this->db->update( 'el_contacts', $clean, [ 'id' => $id ] );
    }

    public function delete_contact( int $id ): bool {
        $result = $this->db->delete( 'el_contacts', [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Create or look up a WP user for portal access.
     */
    public function create_or_update_portal_user( string $email, string $first_name, string $last_name, ?int $existing_user_id = null ): int {
        $user_id = $existing_user_id;

        if ( ! $user_id ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                $user_id = $user->ID;
            }
        }

        if ( ! $user_id ) {
            $username = sanitize_user( $email );
            $password = wp_generate_password( 12, true );

            $user_id = wp_create_user( $username, $password, $email );

            if ( is_wp_error( $user_id ) && $user_id->get_error_code() === 'existing_user_login' ) {
                $username = sanitize_user( $email, true ) . '_' . rand( 100, 999 );
                $user_id = wp_create_user( $username, $password, $email );
            }

            if ( is_wp_error( $user_id ) ) {
                error_log( 'EL Core: Failed to create portal user: ' . $user_id->get_error_message() );
                return 0;
            }

            wp_update_user( [
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ),
            ] );

            $user = get_user_by( 'id', $user_id );
            if ( $user ) {
                $user->add_cap( 'view_expand_site' );
                $user->add_cap( 'submit_feedback' );
                $user->add_cap( 'es_contributor' );
            }
        }

        return (int) $user_id;
    }

    // ═══════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════

    public function handle_create_organization( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( empty( $name ) ) {
            EL_AJAX_Handler::error( __( 'Organization name is required.', 'el-core' ) );
            return;
        }

        $org_id = $this->create_organization( $data );

        if ( $org_id ) {
            EL_AJAX_Handler::success( [ 'organization_id' => $org_id ], __( 'Client created!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to create client.', 'el-core' ) );
        }
    }

    public function handle_update_organization( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['organization_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid organization ID.', 'el-core' ) );
            return;
        }

        $result = $this->update_organization( $id, $data );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Client updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update client.', 'el-core' ) );
        }
    }

    public function handle_delete_organization( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['organization_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid organization ID.', 'el-core' ) );
            return;
        }

        $result = $this->delete_organization( $id );

        if ( $result ) {
            EL_AJAX_Handler::success( null, __( 'Client deleted!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to delete client.', 'el-core' ) );
        }
    }

    public function handle_get_organization( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['organization_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid organization ID.', 'el-core' ) );
            return;
        }

        $org = $this->get_organization( $id );
        if ( ! $org ) {
            EL_AJAX_Handler::error( __( 'Client not found.', 'el-core' ), 404 );
            return;
        }

        EL_AJAX_Handler::success( (array) $org );
    }

    public function handle_search_organizations( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $term = sanitize_text_field( $data['search'] ?? '' );
        if ( strlen( $term ) < 1 ) {
            EL_AJAX_Handler::success( [ 'organizations' => [] ] );
            return;
        }

        $results = $this->search_organizations( $term );
        EL_AJAX_Handler::success( [ 'organizations' => $results ] );
    }

    public function handle_add_contact( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $email = sanitize_email( $data['email'] ?? '' );
        if ( empty( $email ) || ! is_email( $email ) ) {
            EL_AJAX_Handler::error( __( 'Valid email is required.', 'el-core' ) );
            return;
        }

        $first_name = sanitize_text_field( $data['first_name'] ?? '' );
        if ( empty( $first_name ) ) {
            EL_AJAX_Handler::error( __( 'First name is required.', 'el-core' ) );
            return;
        }

        $contact_id = $this->add_contact( $data );

        if ( $contact_id ) {
            EL_AJAX_Handler::success( [ 'contact_id' => $contact_id ], __( 'Contact added!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to add contact.', 'el-core' ) );
        }
    }

    public function handle_update_contact( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['contact_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid contact ID.', 'el-core' ) );
            return;
        }

        $result = $this->update_contact( $id, $data );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Contact updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update contact.', 'el-core' ) );
        }
    }

    public function handle_delete_contact( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['contact_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid contact ID.', 'el-core' ) );
            return;
        }

        $result = $this->delete_contact( $id );

        if ( $result ) {
            EL_AJAX_Handler::success( null, __( 'Contact deleted!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to delete contact.', 'el-core' ) );
        }
    }

    public function handle_get_contact( array $data ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['contact_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid contact ID.', 'el-core' ) );
            return;
        }

        $contact = $this->get_contact( $id );
        if ( ! $contact ) {
            EL_AJAX_Handler::error( __( 'Contact not found.', 'el-core' ), 404 );
            return;
        }

        EL_AJAX_Handler::success( (array) $contact );
    }
}
