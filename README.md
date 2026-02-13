# EL Core

Modular WordPress plugin for educational organizations. Built by Expanded Learning Solutions LLC.

## Repository Structure

```
EL Core/
├── docs/                  ← Architecture guide, project brief
├── releases/              ← ZIP files for uploading to WordPress (NOT tracked by Git)
├── el-core/               ← The actual plugin (this IS the codebase)
│   ├── el-core.php
│   ├── includes/
│   ├── modules/
│   ├── admin/
│   ├── assets/
│   └── templates/
├── .gitignore
└── README.md
```

## Deployment

1. ZIP the `el-core/` folder
2. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate

## Version History

Use `git log` or GitHub's release tags to see all versions.
Each version is tagged: `v1.0.0`, `v1.1.0`, etc.
