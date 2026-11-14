VOICEVANTA V2 - GODADDY CPANEL DEPLOYMENT
========================================
Requirements: GoDaddy Linux cPanel hosting with PHP 8.1+ and PHP cURL enabled, outbound HTTPS to Azure Speech endpoint, SSL certificate/HTTPS, writable private folder. Verify with GoDaddy if unsure. This is a PHP implementation, not Node.js.

1. In Azure Portal, create an Azure AI Speech resource (or compatible Azure Speech resource). Note its region and subscription key. Check current Azure Speech pricing, quota and supported voices before making the site public. Billing may be required even for free allowances.
2. Extract ZIP on your computer. Upload the CONTENTS of public_html/ into cPanel File Manager > public_html/ (or your domain's document root). Make sure public_html/api/tts.php exists.
3. In cPanel File Manager navigate to your ACCOUNT HOME directory, one level ABOVE public_html. Create a folder named private. Upload private/config.example.php to it, then RENAME to config.php. Never upload config.php to public_html. Ensure the private/usage directory can be created by PHP, or create it manually with suitable write permissions.
4. Edit private/config.php: enter azure_key, azure_region (example eastus; use YOUR resource's region), and secret (a long random unique string). Set daily_requests, daily_characters, and max_chars_per_request to manage spending. Default 10 requests, 5000 characters/day per IP, 1000 characters/request. For stronger protection, add CAPTCHA, global quota/cost alerts, and account-based quotas before significant public traffic.
5. Enable PHP 8.1+ for the domain in cPanel MultiPHP Manager and enable cURL extension. If your hosting does not support this, request it or use a compatible VPS/managed backend.
6. Open https://YOURDOMAIN/ and generate a short test. If it fails, check PHP error logs, Azure region, key, resource access, quotas, and outbound network access. PHP syntax can be checked with `php -l public_html/api/tts.php`.
7. Update privacy.html, terms.html, contact.html and your website identity. Only add AdSense script/units after approval, and comply with cookie consent requirements and AdSense policies. Placeholder does NOT show live ads.
8. Test mobile, Urdu, Hindi, Arabic and English, download MP3, invalid voice, empty text, limit enforcement, and failure handling.

SECURITY AND COST NOTES
- API key lives only in private/config.php outside document root; NEVER paste it into HTML/JavaScript.
- Daily quotas are PER IP, UTC-day; shared NAT users share quotas, and IP changes can bypass them. These are soft abuse limits, NOT a guaranteed spend cap. Configure Azure budgets/alerts, provider quotas and global caps for production.
- The API reserves quota BEFORE the Azure request, including failed calls; this discourages retries but can consume user allowance on an upstream error.
- Audio is generated on demand and returned directly; no MP3 is saved on the server.
- No Google or Azure API credentials are included. Working speech requires a valid Azure resource and supported region/voice.
- The curated language list is not 'all languages'; extend the server allowlist and UI after checking Azure voice support.
- Some Azure Speech service configurations or subscriptions may not allow every listed voice/region. Consult Microsoft documentation.
