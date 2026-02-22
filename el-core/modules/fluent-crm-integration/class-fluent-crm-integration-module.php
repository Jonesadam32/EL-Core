<?php
/**
 * Fluent CRM Integration Module
 * 
 * Provides a clean API wrapper around Fluent CRM for other EL Core modules.
 * Handles graceful degradation when Fluent CRM is not installed.
 * 
 * @package EL_Core
 * @subpackage Modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class EL_FluentCRM_Integration_Module {
    
    private static ?EL_FluentCRM_Integration_Module $instance = null;
    private ?EL_Core $core = null;
    private bool $is_available = false;
    
    public static function instance( ?EL_Core $core = null ): self {
        if (null === self::$instance) {
            self::$instance = new self( $core );
        }
        return self::$instance;
    }
    
    private function __construct( ?EL_Core $core = null ) {
        $this->core = $core;
        $this->check_availability();
        $this->init_hooks();
    }
    
    /**
     * Check if Fluent CRM is installed and active
     */
    private function check_availability(): void {
        // Check if Fluent CRM main class exists
        $this->is_available = defined('FLUENTCRM') && class_exists('FluentCrm\\App\\Models\\Subscriber');
        
        if (!$this->is_available) {
            add_action('admin_notices', [$this, 'show_missing_notice']);
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Admin notice if Fluent CRM is not available
        // Other modules will check is_available() before using this module
    }
    
    /**
     * Show admin notice if Fluent CRM is not installed
     */
    public function show_missing_notice(): void {
        // Only show on EL Core pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'el-core') === false) {
            return;
        }
        
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Fluent CRM Integration:', 'el-core'); ?></strong>
                <?php _e('Fluent CRM is not installed or active. Some EL Core features require Fluent CRM to function.', 'el-core'); ?>
                <a href="<?php echo admin_url('plugin-install.php?s=fluent+crm&tab=search&type=term'); ?>" class="button button-small">
                    <?php _e('Install Fluent CRM', 'el-core'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Check if Fluent CRM is available
     * 
     * @return bool True if Fluent CRM is installed and active
     */
    public function is_available(): bool {
        return $this->is_available;
    }
    
    // ==========================================
    // CONTACTS API
    // ==========================================
    
    /**
     * Get a contact by ID
     * 
     * @param int $contact_id The Fluent CRM subscriber ID
     * @return object|null Contact object or null if not found
     */
    public function get_contact(int $contact_id): ?object {
        if (!$this->is_available) {
            return null;
        }
        
        try {
            return \FluentCrm\App\Models\Subscriber::find($contact_id);
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting contact - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a contact by email
     * 
     * @param string $email The contact's email address
     * @return object|null Contact object or null if not found
     */
    public function get_contact_by_email(string $email): ?object {
        if (!$this->is_available) {
            return null;
        }
        
        try {
            return \FluentCrm\App\Models\Subscriber::where('email', $email)->first();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting contact by email - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all contacts
     * 
     * @param array $args Query arguments (limit, offset, status, etc.)
     * @return array Array of contact objects
     */
    public function get_contacts(array $args = []): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            $query = \FluentCrm\App\Models\Subscriber::query();
            
            // Apply filters
            if (isset($args['status'])) {
                $query->where('status', $args['status']);
            }
            
            if (isset($args['search'])) {
                $search = $args['search'];
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            
            if (isset($args['limit'])) {
                $query->limit($args['limit']);
            }
            
            if (isset($args['offset'])) {
                $query->offset($args['offset']);
            }
            
            return $query->get()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting contacts - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new contact
     * 
     * @param array $data Contact data (email, first_name, last_name, etc.)
     * @return object|null Created contact object or null on failure
     */
    public function create_contact(array $data): ?object {
        if (!$this->is_available) {
            return null;
        }
        
        try {
            // Email is required
            if (empty($data['email'])) {
                return null;
            }
            
            // Check if contact already exists
            $existing = $this->get_contact_by_email($data['email']);
            if ($existing) {
                return $existing;
            }
            
            // Create contact
            $contact = \FluentCrm\App\Models\Subscriber::create($data);
            
            return $contact;
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error creating contact - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update a contact
     * 
     * @param int $contact_id The contact ID
     * @param array $data Updated contact data
     * @return bool True on success, false on failure
     */
    public function update_contact(int $contact_id, array $data): bool {
        if (!$this->is_available) {
            return false;
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return false;
            }
            
            $contact->update($data);
            return true;
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error updating contact - ' . $e->getMessage());
            return false;
        }
    }
    
    // ==========================================
    // COMPANIES API
    // ==========================================
    
    /**
     * Get a company by ID
     * 
     * @param int $company_id The Fluent CRM company ID
     * @return object|null Company object or null if not found
     */
    public function get_company(int $company_id): ?object {
        if (!$this->is_available) {
            return null;
        }
        
        try {
            if (!class_exists('FluentCrm\\App\\Models\\Company')) {
                return null; // Companies feature not available
            }
            
            return \FluentCrm\App\Models\Company::find($company_id);
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting company - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all companies
     * 
     * @param array $args Query arguments
     * @return array Array of company objects
     */
    public function get_companies(array $args = []): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            if (!class_exists('FluentCrm\\App\\Models\\Company')) {
                return [];
            }
            
            $query = \FluentCrm\App\Models\Company::query();
            
            if (isset($args['search'])) {
                $query->where('name', 'LIKE', "%{$args['search']}%");
            }
            
            if (isset($args['limit'])) {
                $query->limit($args['limit']);
            }
            
            if (isset($args['offset'])) {
                $query->offset($args['offset']);
            }
            
            return $query->get()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting companies - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get contacts associated with a company
     * 
     * @param int $company_id The company ID
     * @return array Array of contact objects
     */
    public function get_company_contacts(int $company_id): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            $company = $this->get_company($company_id);
            if (!$company) {
                return [];
            }
            
            return $company->subscribers()->get()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting company contacts - ' . $e->getMessage());
            return [];
        }
    }
    
    // ==========================================
    // TAGS API
    // ==========================================
    
    /**
     * Check if a contact has a specific tag
     * 
     * @param int $contact_id The contact ID
     * @param string|int $tag Tag name or ID
     * @return bool True if contact has the tag
     */
    public function contact_has_tag(int $contact_id, $tag): bool {
        if (!$this->is_available) {
            return false;
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return false;
            }
            
            $tags = $contact->tags()->get();
            
            foreach ($tags as $t) {
                if (is_numeric($tag) && $t->id == $tag) {
                    return true;
                }
                if (is_string($tag) && $t->slug === $tag) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error checking contact tag - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all tags for a contact
     * 
     * @param int $contact_id The contact ID
     * @return array Array of tag objects
     */
    public function get_contact_tags(int $contact_id): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return [];
            }
            
            return $contact->tags()->get()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting contact tags - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a tag to a contact
     * 
     * @param int $contact_id The contact ID
     * @param int|array $tag_ids Tag ID or array of tag IDs
     * @return bool True on success
     */
    public function add_tag_to_contact(int $contact_id, $tag_ids): bool {
        if (!$this->is_available) {
            return false;
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return false;
            }
            
            $tag_ids = (array) $tag_ids;
            $contact->attachTags($tag_ids);
            
            return true;
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error adding tag to contact - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove a tag from a contact
     * 
     * @param int $contact_id The contact ID
     * @param int|array $tag_ids Tag ID or array of tag IDs
     * @return bool True on success
     */
    public function remove_tag_from_contact(int $contact_id, $tag_ids): bool {
        if (!$this->is_available) {
            return false;
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return false;
            }
            
            $tag_ids = (array) $tag_ids;
            $contact->detachTags($tag_ids);
            
            return true;
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error removing tag from contact - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all available tags
     * 
     * @return array Array of tag objects
     */
    public function get_all_tags(): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            return \FluentCrm\App\Models\Tag::all()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting all tags - ' . $e->getMessage());
            return [];
        }
    }
    
    // ==========================================
    // LISTS API
    // ==========================================
    
    /**
     * Check if a contact is in a specific list
     * 
     * @param int $contact_id The contact ID
     * @param int $list_id The list ID
     * @return bool True if contact is in the list
     */
    public function contact_in_list(int $contact_id, int $list_id): bool {
        if (!$this->is_available) {
            return false;
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return false;
            }
            
            return $contact->lists()->where('id', $list_id)->exists();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error checking contact list - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all lists for a contact
     * 
     * @param int $contact_id The contact ID
     * @return array Array of list objects
     */
    public function get_contact_lists(int $contact_id): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            $contact = $this->get_contact($contact_id);
            if (!$contact) {
                return [];
            }
            
            return $contact->lists()->get()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting contact lists - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all available lists
     * 
     * @return array Array of list objects
     */
    public function get_all_lists(): array {
        if (!$this->is_available) {
            return [];
        }
        
        try {
            return \FluentCrm\App\Models\Lists::all()->toArray();
        } catch (\Exception $e) {
            error_log('EL Core - Fluent CRM Integration: Error getting all lists - ' . $e->getMessage());
            return [];
        }
    }
}
