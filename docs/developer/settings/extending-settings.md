# Extending Settings

This guide shows how to add custom settings to GatherPress from a companion plugin or theme.

## Adding a New Settings Tab

Create a class that extends `GatherPress\Core\Settings\Base`:

```php
<?php

namespace My_Plugin\Settings;

use GatherPress\Core\Settings\Base;
use GatherPress\Core\Traits\Singleton;

class Notifications extends Base {
    use Singleton;

    protected function get_slug(): string {
        return 'notifications';
    }

    protected function get_name(): string {
        return __( 'Notifications', 'my-plugin' );
    }

    protected function get_priority(): int {
        return 5; // Lower = earlier in tab order.
    }

    protected function get_sections(): array {
        return array(
            'email' => array(
                'name'        => __( 'Email Settings', 'my-plugin' ),
                'description' => __( 'Configure email notifications.', 'my-plugin' ),
                'options'     => array(
                    'notify_on_rsvp' => array(
                        'labels' => array(
                            'name' => __( 'RSVP Notifications', 'my-plugin' ),
                        ),
                        'field'  => array(
                            'label'   => __( 'Send email when someone RSVPs.', 'my-plugin' ),
                            'type'    => 'checkbox',
                            'options' => array(
                                'default' => false,
                            ),
                        ),
                    ),
                    'admin_email' => array(
                        'labels' => array(
                            'name' => __( 'Admin Email', 'my-plugin' ),
                        ),
                        'field'  => array(
                            'label'   => __( 'Email address for notifications.', 'my-plugin' ),
                            'type'    => 'text',
                            'size'    => 'regular',
                            'options' => array(
                                'default' => '',
                            ),
                        ),
                    ),
                ),
            ),
        );
    }
}
```

Then instantiate it in your plugin:

```php
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'GatherPress\Core\Settings\Base' ) ) {
        My_Plugin\Settings\Notifications::get_instance();
    }
});
```

Your tab will appear in the GatherPress settings page, and your options are automatically:

- Stored in the shared `gatherpress_settings` option
- Sanitized based on field type
- Included in import/export
- Stripped when matching their defaults

## Reading and Writing Your Custom Settings

```php
use GatherPress\Core\Settings;

$settings = Settings::get_instance();

// Read.
$notify = $settings->get( 'notify_on_rsvp' ); // false (default)
$email  = $settings->get( 'admin_email' );     // '' (default)

// Write. Values matching the default are automatically removed.
$settings->set( 'notify_on_rsvp', true );
```

## Field Types

### checkbox

Boolean toggle. Sanitized to `(bool)`.

```php
'my_option' => array(
    'labels' => array( 'name' => __( 'Label', 'my-plugin' ) ),
    'field'  => array(
        'label'   => __( 'Description shown next to the checkbox.', 'my-plugin' ),
        'type'    => 'checkbox',
        'options' => array( 'default' => false ),
    ),
),
```

### text

Text input. Sanitized with `sanitize_text_field()`.

```php
'my_option' => array(
    'labels' => array( 'name' => __( 'Label', 'my-plugin' ) ),
    'field'  => array(
        'label'   => __( 'Description shown above the input.', 'my-plugin' ),
        'type'    => 'text',
        'size'    => 'regular', // 'small', 'regular', or 'large'.
        'options' => array( 'default' => 'default value' ),
    ),
),
```

### number

Numeric input. Sanitized with `intval()`.

```php
'my_option' => array(
    'labels' => array( 'name' => __( 'Label', 'my-plugin' ) ),
    'field'  => array(
        'label'   => __( 'Description.', 'my-plugin' ),
        'type'    => 'number',
        'size'    => 'small',
        'options' => array(
            'default' => 10,
            'min'     => '1',
            'max'     => '100',
        ),
    ),
),
```

### select

Dropdown. Sanitized with `sanitize_text_field()`.

```php
'my_option' => array(
    'labels' => array( 'name' => __( 'Label', 'my-plugin' ) ),
    'field'  => array(
        'label'   => __( 'Description.', 'my-plugin' ),
        'type'    => 'select',
        'options' => array(
            'default' => 'option_a',
            'items'   => array(
                'option_a' => __( 'Option A', 'my-plugin' ),
                'option_b' => __( 'Option B', 'my-plugin' ),
            ),
        ),
    ),
),
```

### autocomplete

Dynamic search field for selecting posts or users. Value stored as JSON string.

```php
'my_option' => array(
    'labels' => array( 'name' => __( 'Label', 'my-plugin' ) ),
    'field'  => array(
        'type'    => 'autocomplete',
        'options' => array(
            'type'    => 'user', // or 'page'.
            'label'   => __( 'Select users', 'my-plugin' ),
            'limit'   => 5,
            'default' => '[]',
        ),
    ),
),
```

## Field Config Options

### Preview

Add a live preview below text fields. Create a template in `includes/templates/admin/settings/partials/`:

```php
'field' => array(
    'type'    => 'text',
    'options' => array( 'default' => 'Y-m-d' ),
    'preview' => array(
        'template' => 'my-preview', // Renders partials/my-preview.php.
        'custom'   => 'extra data', // Passed to the template.
    ),
),
```

### Rewrite

Flag fields that affect permalink structure. When these change, rewrite rules are automatically flushed:

```php
'field' => array(
    'type'    => 'text',
    'rewrite' => true,
    'options' => array( 'default' => 'my-slug' ),
),
```

## Key Uniqueness

All option keys must be globally unique across all tabs and sections. If two extensions register the same key, an admin error notice is displayed:

> GatherPress: Duplicate settings keys found: my_option. Each key must be unique.

Use a prefix for your keys to avoid collisions (e.g., `myplugin_notify_on_rsvp`).

## Custom (Non-Form) Tabs

For tabs that need custom UI instead of the standard settings form (like the Tools or Credits tabs), hook into `gatherpress_settings_section` at priority 9:

```php
protected function setup_hooks(): void {
    parent::setup_hooks();

    add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
}

public function settings_section( string $page ): void {
    if ( Utility::unprefix_key( $page ) === $this->slug ) {
        remove_action(
            'gatherpress_settings_section',
            array( Settings::get_instance(), 'render_settings_form' )
        );

        // Render your custom template.
        Utility::render_template( '/path/to/your/template.php', array(), true );
    }
}
```

## Related Hooks

- [`gatherpress_sub_pages`](../hooks/gatherpress_sub_pages.md) -- Filter to modify the sub-pages array directly
- [`gatherpress_roles`](../hooks/gatherpress_roles.md) -- Filter to add custom roles to the Roles tab
