# URGENT BUGFIX - v1.12.1

## What Happened

The transcript processing feature in v1.12.0 had a bug where I was using the AI API wrapper function incorrectly. This caused it to try parsing HTML error messages as JSON, which resulted in the error you saw:

> "Unexpected token '<', "<p>There i"... is not valid JSON"

## What Was Fixed in v1.12.1

1. **Corrected AI wrapper usage**: The `el_core_ai_complete()` function returns an array with `['success' => bool, 'content' => string, 'error' => string]`, not just the content string
2. **Added AI configuration check**: Now checks if AI is configured BEFORE trying to process, with a helpful error message
3. **Better error handling**: Logs AI responses for debugging and shows clear messages

## What You Need to Do

### Step 1: Configure AI API Key

The error revealed that AI isn't configured yet. You need to add your API key:

1. In WordPress admin, go to **EL Core** (in sidebar)
2. Click **Brand**
3. Scroll down to the **AI Settings** section
4. You'll see:
   - **Provider**: Choose "Anthropic (Claude)" or "OpenAI (GPT)"
   - **API Key**: Enter your API key here
   - **Model**: Use default or specify (e.g., `gpt-4o` for OpenAI or `claude-sonnet-4-5-20250929` for Anthropic)
   - **Max Tokens**: Leave at 1024 (or adjust as needed)
5. Click **Save AI Settings**

**Which API to use:**
- **Anthropic (Claude)** - Recommended if you have a Claude API key
  - Model: `claude-sonnet-4-5-20250929`
  - Get key at: https://console.anthropic.com/
- **OpenAI (GPT)** - Use if you have OpenAI API access
  - Model: `gpt-4o` or `gpt-4`
  - Get key at: https://platform.openai.com/api-keys

### Step 2: Upload the Fixed Plugin

1. Download the fixed version: `C:\Github\EL Core\releases\el-core-v1.12.1.zip`
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload `el-core-v1.12.1.zip`
4. Click "Replace active with uploaded"

### Step 3: Test Again

1. Go back to your test project
2. Click the **Discovery** tab
3. Paste a transcript
4. Click **Process with AI**

**Expected behavior now:**
- If AI is not configured: "AI is not configured. Go to EL Core → Brand → AI Settings to add your API key."
- If AI is configured: Processing should work and extract data into the form fields

## Testing Tips

**Quick test transcript** (if you don't have one handy):

```
Meeting with Sarah about their school website project.

Sarah: We need a website for Bright Horizons Academy, our K-12 private school.

The main goal is to increase enrollment by showcasing our STEM programs.

We want to reach affluent families in the metro area, typically professionals aged 35-50.

We have three user types: prospective parents researching schools, current parents needing portal access, and alumni who want to stay connected.

This is an educational website with some e-commerce features for donations.
```

## Error Message Reference

**Before Fix (v1.12.0):**
```
Unexpected token '<', "<p>There i"... is not valid JSON
```

**After Fix (v1.12.1) - If AI not configured:**
```
AI is not configured. Go to EL Core → Brand → AI Settings to add your API key.
```

**After Fix (v1.12.1) - If AI configured correctly:**
- Success message: "Transcript processed successfully! Review the extracted data below..."
- Form fields populate with extracted data

## Files Changed

- `el-core\modules\expand-site\class-expand-site-module.php` - Fixed AI wrapper usage
- `el-core\el-core.php` - Version bumped to 1.12.1
- `CHANGELOG.md` - Documented bugfix

## Next Steps After This Works

Once you configure the AI and the transcript processing works:

1. Test the full workflow:
   - Process transcript ✓
   - Edit extracted data ✓
   - Save definition ✓
   - Lock definition ✓
2. Move on to Phase 2G (Branding Workflow)

---

**Questions?**

Let me know if:
- The AI configuration doesn't save properly
- You get a different error after configuring AI
- The transcript processing works but extraction quality is poor
- You need help getting an API key from Anthropic or OpenAI
