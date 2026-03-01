# How to Download EL Core from the Staging Server

You need the plugin folder from the server so you can paste or sync it into this repo. The plugin lives at:

**Path on server:** `wp-content/plugins/el-core/`

Pick the method that matches how you access the site.

---

## Option 1: Host File Manager (e.g. cPanel, Plesk)

1. Log in to your **hosting control panel** (where you manage the WordPress site).
2. Open **File Manager** (or “Files”).
3. Go to: `public_html` (or your site root) → `wp-content` → `plugins` → **el-core**.
4. Select the **el-core** folder.
5. Use **Compress** / **Zip** to create `el-core.zip`.
6. **Download** the ZIP to your PC.
7. Unzip it. You’ll get an `el-core` folder with all PHP and assets. Use that (or paste the main file) in `paste-workspace/`.

---

## Option 2: FTP/SFTP (FileZilla, WinSCP, etc.)

1. Get your **FTP or SFTP** details from your host (hostname, username, password, port 21 for FTP or 22 for SFTP).
2. Open **FileZilla** (or WinSCP) and connect.
3. On the **remote** side, go to: `wp-content/plugins/el-core/`.
4. Select the whole **el-core** folder.
5. **Download** (drag to your PC or right‑click → Download) into a folder on your computer (e.g. Desktop or `Downloads`).
6. You’ll have the full `el-core` folder. Copy the main plugin file or the whole folder into this repo / `paste-workspace/` as needed.

---

## Option 3: SSH (if your host gives you SSH access)

1. Connect with SSH (e.g. PuTTY or Windows Terminal):  
   `ssh youruser@your-server.com`
2. Go to the site root and zip the plugin:
   ```bash
   cd /path/to/wordpress   # often public_html or the site directory
   zip -r el-core.zip wp-content/plugins/el-core/
   ```
3. Download the ZIP:
   - **With SCP:** From your PC run:  
     `scp youruser@your-server.com:/path/to/wordpress/el-core.zip .`
   - Or use the host’s File Manager or SFTP to download `el-core.zip` from that path.
4. Unzip on your PC and use the `el-core` folder as in Option 1.

---

## Option 4: Ask your host

If you’re not sure how you’re supposed to access files:

- Check the **welcome email** from the host for “FTP”, “SFTP”, “File Manager”, or “SSH”.
- In their **help/FAQ**, search for “access files” or “download WordPress files”.
- Use **live chat or support** and say: “I need to download the contents of `wp-content/plugins/el-core/` from my WordPress staging site. What’s the best way (File Manager, FTP, or SSH)?”

---

## After you have the files

- If you get **one main PHP file** (everything in one file): paste it into **paste-workspace/el-core-main.php**.
- If you get an **el-core folder** with many files: you can put that folder in the repo and we can replace the current `el-core/` with it, or paste the main `el-core.php` into `paste-workspace/el-core-main.php` and we’ll work from there.

Then say “I’ve got the files” and we can recreate the plugin in the repo from that.
