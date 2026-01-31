=== Permanent Plugin Theme Downloader ===

Contributors:      WordPress Telex
Tags:              block, plugins, themes, download, share
Tested up to:      6.8
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Generate permanent, shareable download URLs for installed plugins and themes with secure token-based authentication that bypasses basic auth.

== Description ==

The Permanent Plugin Theme Downloader block creates physical ZIP files of all your installed WordPress plugins and themes, stored in wp-content/pptd-downloads/. These ZIP files are automatically created and updated, with secure permanent URLs that work even when your WordPress site is behind basic authentication.

**Key Features:**

* **Token-Based Security**: Secure download URLs with embedded authentication tokens that bypass basic auth
* **Zero Configuration**: Automatic setup - no manual configuration required
* **Physical ZIP Storage**: All plugin and theme ZIPs are stored in wp-content/pptd-downloads/
* **Permanent URLs**: Secure links that never change and always point to the latest version
* **Auto-Update Support**: ZIP files automatically regenerate when plugins or themes are updated
* **Basic Auth Bypass**: Download URLs work even when your site requires basic authentication
* **Easy Sharing**: One-click copy-to-clipboard functionality in the editor
* **Clean Interface**: List view of all installed plugins and themes with their download URLs
* **Frontend Downloads**: Clickable links that trigger immediate ZIP file downloads
* **Automatic Management**: ZIPs regenerate on plugin/theme activation, deactivation, and updates

**How It Works:**

1. When you activate the plugin, it automatically generates a secure secret key
2. ZIP files are created for each installed plugin and theme in wp-content/pptd-downloads/
3. Each ZIP gets a permanent, tokenized URL like: yoursite.com/?pptd_download=plugin-name.zip&token=abc123
4. The token is cryptographically signed and validates against the secret key
5. Downloads work even if your WordPress site is behind basic authentication
6. When you update a plugin or theme, its ZIP is automatically regenerated
7. The URL stays the same (tokens are permanent for each file), always serving the latest version

**Security Features:**

* **HMAC-SHA256 Tokens**: Cryptographically secure tokens that cannot be forged
* **Automatic Secret Key**: 64-character random secret key generated on activation
* **Path Validation**: Prevents directory traversal attacks
* **File Type Verification**: Only allows .zip file downloads
* **Protected Directory**: Download directory includes .htaccess protection
* **No Direct File Access**: All downloads go through the token validation system

**Perfect For:**

* Sites behind basic authentication or firewalls
* Development teams sharing internal plugins
* Agencies distributing custom themes to clients
* Plugin developers providing direct downloads
* Theme creators offering alternative download methods
* Anyone needing persistent, authenticated download URLs for WordPress extensions

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/permanent-plugin-theme-downloader` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin automatically:
   - Creates wp-content/pptd-downloads/ directory
   - Generates a secure secret key
   - Creates ZIP files for all plugins and themes
   - Generates secure tokens for each file
4. Add the "Permanent Plugin Theme Downloader" block to any post or page
5. The block will display all plugins and themes with their secure download URLs

== Frequently Asked Questions ==

= How does this work with basic authentication? =

The plugin uses a custom download endpoint with token-based authentication that completely bypasses basic auth. When someone accesses a download URL, the token is validated server-side without requiring basic auth credentials.

= What are the permanent URLs? =

Each plugin gets a URL like: yoursite.com/?pptd_download=plugin-slug.zip&token=abc123
Each theme gets a URL like: yoursite.com/?pptd_download=theme-slug.zip&token=def456

The tokens are permanent and cryptographically secure, tied to each specific file.

= Do I need to configure anything? =

No! The plugin automatically generates a secure secret key on activation and handles all token generation. There's no manual configuration needed.

= What happens when I update a plugin or theme? =

The ZIP file is automatically regenerated with the new version. The URL (including the token) stays exactly the same, so all your shared links continue to work and serve the updated version.

= Are the tokens secure? =

Yes! Tokens are generated using HMAC-SHA256 with a 64-character random secret key. They cannot be forged or guessed, and each token is permanently tied to a specific file.

= Can I manually regenerate all ZIPs? =

Yes! In the block's sidebar settings, there's a "Regenerate All ZIPs" button that rebuilds all ZIP files with the current versions.

= Is this secure? =

Yes! The plugin includes:
- HMAC-SHA256 cryptographic tokens
- Automatic secret key generation
- Sanitized file names to prevent directory traversal
- Path validation to ensure only legitimate files are served
- File type verification (only .zip files)
- Protected directory with .htaccess
- WordPress capability checks for admin operations

= Does this work with both plugins and themes? =

Yes, the block supports both WordPress plugins and themes seamlessly.

= What if I deactivate the plugin? =

The ZIP files in wp-content/pptd-downloads/ are automatically cleaned up when you deactivate the plugin. The secret key remains stored in your database.

== Screenshots ==

1. Editor view showing the list of installed plugins and themes with copy-to-clipboard and download buttons
2. Frontend view displaying clickable download links for visitors
3. Sidebar settings with regenerate button
4. Successfully copied URL notification with secure token

== Changelog ==

= 0.1.0 =
* Initial release
* Token-based authentication system
* Automatic secret key generation
* Basic authentication bypass support
* Physical ZIP file storage in wp-content/pptd-downloads/
* Permanent tokenized URLs
* Automatic ZIP regeneration on plugin/theme updates
* Copy-to-clipboard functionality
* Frontend download links
* Manual regenerate all ZIPs option
* HMAC-SHA256 security
* Automatic cleanup on deactivation

== Security ==

This plugin implements multiple security layers:
- Generates HMAC-SHA256 tokens with a 64-character random secret key
- Validates all tokens cryptographically before allowing downloads
- Verifies file paths to prevent unauthorized access
- Sanitizes file names to prevent injection attacks
- Protects download directory with .htaccess
- Only allows .zip file downloads
- Uses WordPress core functions for file operations
- Automatically cleans up on deactivation
- Bypasses basic authentication securely without exposing credentials