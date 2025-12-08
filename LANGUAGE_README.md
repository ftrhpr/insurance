# Language Management System

This system allows administrators to manage website text and translations across the entire OTOMOTORS portal.

## Features

- **Multi-language Support**: Add and manage multiple languages
- **Admin-only Access**: Only administrators can modify languages
- **Real-time Editing**: Edit language strings directly in the web interface
- **JSON-based Storage**: Language files stored as JSON for easy maintenance
- **Fallback System**: Automatically falls back to English if translations are missing

## File Structure

```
languages/
├── en.json          # English (default language)
├── ka.json          # Georgian
├── ru.json          # Russian
└── ...             # Other languages

language.php         # Language management class
languages.php        # Admin interface for language management
```

## Usage

### For Developers

1. **Include the language system** at the top of your PHP files:
```php
require_once 'language.php';
```

2. **Use language strings** instead of hardcoded text:
```php
// Instead of: <h1>Welcome</h1>
<h1><?php echo Language::get('common.welcome'); ?></h1>

// With fallback: echo Language::get('custom.key', 'Default Text');
```

3. **Access nested strings** using dot notation:
```php
Language::get('navigation.dashboard');  // "Dashboard"
Language::get('status.completed');      // "Completed"
```

### For Administrators

1. **Access Language Management**: Navigate to "Languages" in the admin menu
2. **Switch Languages**: Use the language selector to edit different languages
3. **Edit Strings**: Click on any string to edit its value
4. **Add Languages**: Use "Add New Language" to create support for new languages
5. **Delete Languages**: Remove unused languages (English cannot be deleted)

## API Endpoints

The following API endpoints are available for language management:

- `GET /api.php?action=get_languages` - Get available languages
- `GET /api.php?action=get_language_strings&lang=en` - Get strings for a language
- `POST /api.php?action=save_language_string` - Save a language string
- `POST /api.php?action=create_language` - Create a new language
- `POST /api.php?action=delete_language` - Delete a language
- `POST /api.php?action=switch_language` - Switch current language

## Adding New Languages

1. Go to the Languages page
2. Click "Add New Language"
3. Enter language code (2-3 lowercase letters, e.g., `ka`, `ru`, `de`)
4. Enter language name (e.g., "ქართული", "Русский", "Deutsch")
5. The system will create a new language file based on English

## Language File Format

Language files are JSON objects with nested structure:

```json
{
  "app": {
    "title": "OTOMOTORS Manager Portal",
    "brand_name": "OTOMOTORS"
  },
  "navigation": {
    "dashboard": "Dashboard",
    "templates": "SMS Templates"
  }
}
```

## Best Practices

1. **Use descriptive keys**: Use clear, descriptive key names
2. **Group related strings**: Organize strings into logical groups
3. **Keep keys consistent**: Use the same key across all language files
4. **Test translations**: Always test that translations display correctly
5. **Backup language files**: Keep backups before major changes

## Security

- Language management is restricted to admin users only
- All API endpoints validate permissions
- File operations are sanitized and validated
- CSRF protection is enabled for state-changing operations

## Troubleshooting

**Strings not updating**: Clear browser cache and reload the page
**Language not switching**: Check that the language file exists and is valid JSON
**Permission denied**: Ensure you're logged in as an administrator
**API errors**: Check the browser console and server logs for error details