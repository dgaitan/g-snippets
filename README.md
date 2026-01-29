# G-Snippets

A WordPress plugin for managing reusable content snippets that can be injected into posts and pages using the Gutenberg editor.

## Description

G-Snippets allows you to create reusable content snippets using the native Gutenberg editor. Each snippet can be configured to display on specific post types, before or after content, with priority-based matching when multiple snippets apply to the same post.

## Features

- **Gutenberg Editor**: Create snippets using the full Gutenberg block editor
- **Flexible Display Rules**: Configure snippets to display on specific post types
- **Location Control**: Choose to display snippets before or after post content
- **Priority System**: When multiple snippets match, the one with the lowest priority number wins
- **Include/Exclude Posts**: Fine-tune snippet display with include and exclude lists
- **Active Toggle**: Enable or disable snippets without deleting them
- **Comprehensive List View**: View all snippet settings at a glance in the admin list table

## Requirements

- WordPress 5.0 or higher
- Advanced Custom Fields (ACF) plugin (required)

## Installation

1. Upload the `g-snippets` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Advanced Custom Fields (ACF) is installed and activated
4. Navigate to 'G-Snippets' in the admin menu to start creating snippets

## Usage

### Creating a Snippet

1. Go to **G-Snippets > Add New** in the WordPress admin
2. Enter a title for your snippet
3. Use the Gutenberg editor to create your snippet content
4. Configure the snippet settings in the "Snippet Settings" meta box:
   - **Post Types**: Select which post types this snippet should apply to (default: Posts only)
   - **Location**: Choose "Before Content" or "After Content" (default: After)
   - **Priority**: Set a priority number (lower numbers = higher priority, default: 10)
   - **Active**: Toggle to enable/disable the snippet
   - **Include Posts**: Optionally select specific posts where this snippet should appear
   - **Exclude Posts**: Optionally select specific posts where this snippet should NOT appear
5. Publish the snippet

### How Snippets Match Posts

A snippet will be displayed on a post if:

1. The post's type matches one of the snippet's selected post types
2. The snippet is active
3. If an "Include Posts" list is set, the post must be in that list
4. The post must NOT be in the "Exclude Posts" list

If multiple snippets match a post, only the one with the **lowest priority number** will be displayed.

### Priority System

- Lower numbers = higher priority
- Example: A snippet with priority 5 will display over a snippet with priority 10
- Default priority is 10

## Technical Details

- Custom Post Type: `g_snippet`
- Uses ACF for field management
- Hooks into `the_content` filter with priority 15
- Caches snippet queries for performance

## Changelog

### 1.0.0
- Initial release
- Custom post type with Gutenberg support
- ACF field integration
- Content injection with priority system
- Include/exclude post functionality
- Custom admin list table columns

## Support

For issues, questions, or contributions, please visit the plugin repository.

## License

GPL v2 or later
