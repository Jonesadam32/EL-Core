# Fluent CRM Integration Module - Complete

**Date:** February 15, 2026  
**Source:** EL Solutions v2.15.31 (CRM tables extracted)  
**Target:** EL Core modules/fluent-crm-integration/  
**Status:** ✅ Complete

---

## What Was Built

A lightweight API wrapper module that provides a clean interface for other EL Core modules to interact with Fluent CRM data.

### Files Created

1. **`module.json`** - Manifest (no dependencies, no tables, no capabilities)
2. **`class-fluent-crm-integration-module.php`** - Main module class (~530 lines)

---

## Module Features

### Graceful Degradation
- ✅ Checks if Fluent CRM is installed on initialization
- ✅ Returns `null`/`false`/`[]` safely when Fluent CRM is missing
- ✅ Shows admin notice on EL Core pages if Fluent CRM not found
- ✅ All methods have error handling with `try/catch`
- ✅ Errors logged to WordPress error log

### Clean API for Other Modules

```php
$crm = EL_FluentCRM_Integration_Module::instance();

// Check if available
if ($crm->is_available()) {
    // Safe to use Fluent CRM features
}
```

---

## API Reference

### General

| Method | Description | Returns |
|--------|-------------|---------|
| `is_available()` | Check if Fluent CRM is installed and active | `bool` |

### Contacts API

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `get_contact($contact_id)` | `int $contact_id` | `object\|null` | Get a contact by ID |
| `get_contact_by_email($email)` | `string $email` | `object\|null` | Get a contact by email |
| `get_contacts($args)` | `array $args` | `array` | Get all contacts (supports search, limit, offset, status) |
| `create_contact($data)` | `array $data` | `object\|null` | Create a new contact (or return existing) |
| `update_contact($contact_id, $data)` | `int $contact_id, array $data` | `bool` | Update contact data |

### Companies API

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `get_company($company_id)` | `int $company_id` | `object\|null` | Get a company by ID |
| `get_companies($args)` | `array $args` | `array` | Get all companies (supports search, limit, offset) |
| `get_company_contacts($company_id)` | `int $company_id` | `array` | Get all contacts in a company |

### Tags API

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `contact_has_tag($contact_id, $tag)` | `int $contact_id, string\|int $tag` | `bool` | Check if contact has a specific tag (by name or ID) |
| `get_contact_tags($contact_id)` | `int $contact_id` | `array` | Get all tags for a contact |
| `add_tag_to_contact($contact_id, $tag_ids)` | `int $contact_id, int\|array $tag_ids` | `bool` | Add tag(s) to a contact |
| `remove_tag_from_contact($contact_id, $tag_ids)` | `int $contact_id, int\|array $tag_ids` | `bool` | Remove tag(s) from a contact |
| `get_all_tags()` | none | `array` | Get all available tags |

### Lists API

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `contact_in_list($contact_id, $list_id)` | `int $contact_id, int $list_id` | `bool` | Check if contact is in a list |
| `get_contact_lists($contact_id)` | `int $contact_id` | `array` | Get all lists for a contact |
| `get_all_lists()` | none | `array` | Get all available lists |

---

## Usage Examples

### Example 1: Project Management Module

```php
// In Project Management module
class EL_Project_Management_Module {
    private $crm;
    
    private function __construct() {
        $this->crm = EL_FluentCRM_Integration_Module::instance();
    }
    
    public function render_create_project_form() {
        // Check if CRM is available
        if (!$this->crm->is_available()) {
            echo '<p>Fluent CRM required for client selection.</p>';
            return;
        }
        
        // Get all companies for dropdown
        $companies = $this->crm->get_companies(['limit' => 100]);
        
        echo '<select name="client_company_id">';
        foreach ($companies as $company) {
            echo '<option value="' . $company['id'] . '">' . $company['name'] . '</option>';
        }
        echo '</select>';
    }
    
    public function get_project_client_contacts($project) {
        // Get contacts for the project's company
        return $this->crm->get_company_contacts($project->company_id);
    }
}
```

### Example 2: Client Portals Module

```php
// In Client Portals module
class EL_Client_Portals_Module {
    private $crm;
    
    private function __construct() {
        $this->crm = EL_FluentCRM_Integration_Module::instance();
    }
    
    public function check_portal_access($user_id) {
        if (!$this->crm->is_available()) {
            return false;
        }
        
        // Get WordPress user's email
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Find Fluent CRM contact
        $contact = $this->crm->get_contact_by_email($user->user_email);
        if (!$contact) {
            return false;
        }
        
        // Check if they have portal access tag
        return $this->crm->contact_has_tag($contact->id, 'portal-access');
    }
    
    public function grant_portal_access($contact_id) {
        // Add portal access tag
        $this->crm->add_tag_to_contact($contact_id, 'portal-access');
    }
}
```

### Example 3: Invoicing Module

```php
// In Invoicing module
class EL_Invoicing_Module {
    private $crm;
    
    private function __construct() {
        $this->crm = EL_FluentCRM_Integration_Module::instance();
    }
    
    public function create_invoice($data) {
        // Get billing contact from Fluent CRM
        $contact = $this->crm->get_contact($data['contact_id']);
        if (!$contact) {
            return false;
        }
        
        // Create invoice with contact details
        $invoice = [
            'client_name' => $contact->full_name,
            'client_email' => $contact->email,
            'billing_address' => $contact->address_line_1,
            // ... more fields
        ];
        
        // Insert invoice
        // ...
        
        return $invoice;
    }
}
```

---

## Integration Points

### Fluent CRM Tables Used

The module directly queries Fluent CRM's database tables:
- `{prefix}fc_subscribers` - Contacts
- `{prefix}fc_subscriber_pivot` - Contact relationships (tags, lists)
- `{prefix}fc_tags` - Tags
- `{prefix}fc_lists` - Lists
- `{prefix}fc_companies` - Companies (if available)

### Fluent CRM Classes Used

- `FluentCrm\App\Models\Subscriber` - Contact model
- `FluentCrm\App\Models\Company` - Company model (optional feature)
- `FluentCrm\App\Models\Tag` - Tag model
- `FluentCrm\App\Models\Lists` - List model

---

## Error Handling

All methods have robust error handling:

```php
try {
    return \FluentCrm\App\Models\Subscriber::find($contact_id);
} catch (\Exception $e) {
    error_log('EL Core - Fluent CRM Integration: Error getting contact - ' . $e->getMessage());
    return null;
}
```

Errors are:
- ✅ Caught and logged
- ✅ Return safe defaults (`null`, `false`, `[]`)
- ✅ Never throw exceptions to calling code

---

## Admin Notice

If Fluent CRM is not installed, an admin notice appears on EL Core pages:

> **Fluent CRM Integration:** Fluent CRM is not installed or active. Some EL Core features require Fluent CRM to function. [Install Fluent CRM]

Only shows on pages where `screen->id` contains `'el-core'`.

---

## Benefits

### For Developers
- ✅ **One API to learn** - Consistent interface across all modules
- ✅ **No duplicate code** - All Fluent CRM queries in one place
- ✅ **Easy to test** - Mock the integration module for unit tests
- ✅ **Safe defaults** - Never crashes if Fluent CRM missing

### For Architecture
- ✅ **Loose coupling** - Modules depend on interface, not Fluent CRM directly
- ✅ **Easy to swap** - Could replace Fluent CRM with another CRM by changing this one module
- ✅ **Follows EL Core patterns** - Singleton, error handling, logging

### For Future
- ✅ **Fluent CRM updates** - Only update integration module if their API changes
- ✅ **Add features** - Add new methods here, all modules benefit
- ✅ **Switch CRMs** - Replace implementation, keep interface

---

## Module Dependencies

Modules that will depend on this:
```json
{
  "requires": {
    "modules": ["fluent-crm-integration"]
  }
}
```

- **Project Management** - For client selection
- **Invoicing** - For billing contacts
- **Client Portals** - For access control via tags
- **Notifications** (future) - For triggering Fluent CRM emails

---

## Next Steps

This module is ready to be used by:
1. **Phase 3: Project Management Module** - Will use this for client/contact selection
2. **Phase 7: Invoicing Module** - Will use this for billing info
3. **Phase 8: Client Portals Module** - Will use this for access control

---

## File Locations

```
el-core/modules/fluent-crm-integration/
├── module.json
└── class-fluent-crm-integration-module.php
```

**Total Lines:** ~530 lines  
**Status:** ✅ Ready for use by other modules
