# EL Core

**Version:** 1.2.1  
**Author:** Expanded Learning Solutions LLC

## What Is This?

EL Core is a modular WordPress plugin that provides the foundation for educational technology platforms. It powers LMS, events, certificates, analytics, and more — all configurable per installation through an admin UI.

## Installation

1. Upload the `el-core` folder to `/wp-content/plugins/`
2. Activate through WordPress admin → Plugins
3. Navigate to **EL Core** in the admin menu
4. Configure Brand, Modules, and Roles

## Architecture

- **Plugin** handles data, logic, AI, APIs
- **Theme** (coming soon) handles visual presentation
- **Modules** are self-contained features toggled from the admin
- **Component shortcodes** render individual UI pieces within block editor layouts

## Included Modules

- **Events & Calendar** — Event creation, RSVP tracking, calendar display

## Creating Pages

Use the block editor with EL Core component shortcodes:

```
[el_event_list limit="6" layout="cards"]
[el_event_rsvp event_id="123"]
```

Page templates are available in the `templates/` folder.

## For Developers

Each module includes a `module.json` manifest that declares its database schema, capabilities, shortcodes, and settings. The core handles all infrastructure automatically.

See the Architecture Guide document for the complete technical reference.
