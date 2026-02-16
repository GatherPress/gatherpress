# Multisite Shared Options

This document explains how the **shared options** system works in GatherPress. Shared options enable network-wide sharing and synchronization of specific GatherPress settings across blogs of a multisite installations.

## How It Works

Shared options utilize the `site options` mechanism provided by WordPress to centralize settings for all sites in a multisite network. Key behaviors include:

1. **Storage**: Shared options are stored in the network's `wp_sitemeta` table using the `get_site_option()` and `update_site_option()` functions.
2. **Hook Integration**: Filters and actions override standard WordPress option retrieval and update processes to sync shared options between the network and individual sites.
3. **Initialization**: Shared options are initialized during the `init` action to ensure proper registration and behavior.

## Usage in Multisite

1. **Defining Shared Options**:
    - Use the `gatherpress_shared_options` site option to define the list of option-slugs to be shared across the network:
        
        ```php
        update_site_option(
            'gatherpress_shared_options',
            array( 'gatherpress_general' )
        );
        ```
        
    - Use the `pre_site_option_{key}` filter to programmatically modify the list of shared options if needed.
        
        ```php
        add_filter(
            sprintf( 'pre_site_option_%s', 'gatherpress_shared_options' ),
            function () {
                return array( 'gatherpress_general' );
            }
        );
        ```
        
2. **Synchronizing Options**:
   - Updates to shared options on the main site automatically propagate to the network via `update_site_option`.

3. **Read-Only UI**:
   - For non-main sites, UI elements for shared options are disabled to prevent local modifications.

<!-- markdownlint-disable-next-line MD034 -->
https://github.com/user-attachments/assets/3a5ef773-072d-465b-bf90-b42e299e044d

