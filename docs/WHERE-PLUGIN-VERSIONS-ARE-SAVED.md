# Where EL Core Plugin Versions Are Saved

## Summary

- **Website has 1.24.1** because that (or a newer) ZIP was uploaded from **`releases/`** or from **Downloads**.
- **You see 1.2.1 as “latest”** because you’re looking at **`el-core-releases/`**, which only has old ZIPs (1.1.0–1.2.1) and is **not** used by the current build script.

---

## Where versions actually are

### 1. `releases/` (main place for upload ZIPs)

- **Path:** `C:\Users\jones\Documents\Github\EL-Core\releases\`
- **Contents:** One ZIP per version that was built, e.g.  
  `el-core-v1.22.13.zip`, `el-core-v1.23.zip`, `el-core-v1.24.0.zip`, `el-core-v1.24.1.zip`, … `el-core-v1.24.4.zip`
- **Updated by:** `build-zip.ps1` — each run **adds** a new versioned file (filename includes version, so nothing is overwritten).
- **In Git:** **No.** `releases/` is in `.gitignore`, so these ZIPs exist only on your machine and are **not** pushed to GitHub.

So: **all recently built versions (e.g. 1.22.13 through 1.24.4) are saved in `releases/`**; the site has 1.24.1 because that (or a newer) was uploaded from here or from Downloads.

### 2. `old-versions/vX.Y.Z/` (versioned backups)

- **Path:** `C:\Users\jones\Documents\Github\EL-Core\old-versions\v1.24.4\`, etc.
- **Contents:** For each version that was built:
  - `el-core-vX.Y.Z.php` — copy of the main plugin file (always saved).
  - `el-core-vX.Y.Z.zip` — full plugin ZIP (saved when that version was the one being built).
- **Updated by:** `build-zip.ps1` — creates/updates only the folder for the **current** version in `build-zip.ps1` (e.g. `v1.24.4`).
- **In Git:** **Yes.** `old-versions/` is **not** in `.gitignore, so these backups are committed and pushed.

So: **versioned backups (PHP + ZIP for the version just built) are in `old-versions/`** and are saved in the repo.

### 3. Downloads folder

- **Path:** `%USERPROFILE%\Downloads\el-core-vX.Y.Z.zip`
- **Contents:** A **single** ZIP — the **last** version you built (e.g. 1.24.4). Each new build **overwrites** this file.
- **Updated by:** `build-zip.ps1`.
- **In Git:** N/A (outside repo).

### 4. `el-core-releases/` (legacy / not used by build)

- **Path:** `C:\Users\jones\Documents\Github\EL-Core\el-core-releases\`
- **Contents:** Old ZIPs only up to **1.2.1**: e.g. `el-core-v1.1.0.zip`, `el-core-v1.2.0.zip`, `el-core-v1.2.1.zip`, plus extracted folders.
- **Updated by:** **Nothing.** The build script does **not** write here. So no version after 1.2.1 was ever “saved” into this folder.

That’s why the “latest version saved” you see there is **1.2.1** — nothing newer was ever copied into `el-core-releases/`.

---

## Why it looked like versions weren’t saved

1. **`releases/` is gitignored**  
   So when you think of “saved” as “in the repo / on GitHub,” the ZIPs in `releases/` are intentionally **not** saved there. They are only on your machine in `releases/` (and the latest in Downloads).

2. **`el-core-releases/` is not part of the build**  
   So 1.24.x (and everything after 1.2.1) was never written there. Only the old 1.1.x–1.2.1 ZIPs are in that folder.

3. **Versions *are* saved in two ways**  
   - On disk: **`releases/`** has all recent version ZIPs (1.22.13–1.24.4).  
   - In Git: **`old-versions/vX.Y.Z/`** has versioned PHP + ZIP for each build.

---

## What to use when

- **Upload to the website:** Use a ZIP from **`releases/`** (e.g. `el-core-v1.24.4.zip`) or the one in **Downloads** if it’s the version you want.
- **Find an older built version:** Check **`releases/`** first; then **`old-versions/vX.Y.Z/`** for that version’s ZIP (if it was ever built).
- **`el-core-releases/`:** Treat as legacy. You can ignore it for “where are the plugin versions” or repurpose it (e.g. copy from `releases/` into it) if you want one extra folder for ZIPs.

---

## Optional: start saving ZIPs into `el-core-releases/` (or stop using it)

If you want **every** new build to also appear in `el-core-releases/`:

- In `build-zip.ps1`, add a line to copy the built ZIP into `el-core-releases\el-core-vX.Y.Z.zip` (same versioned filename as in `releases/`).

If you don’t care about that folder:

- You can add `el-core-releases/` to `.gitignore` so its old contents aren’t tracked, or leave it as-is for local reference only.
