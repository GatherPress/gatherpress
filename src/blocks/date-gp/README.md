# Saving via REST API

## Define Constants

```php
define( 'SOME_REST_NAMESPACE', 'some-namespace/v1' );
define( 'SOME_BLOCK_SETTING', 'some_block_setting' );
```

## Define Endpoints

```php
add_action( 'rest_api_init', __NAMESPACE__ . '\custom_endpoints' );
/**
 * Create custom endpoints for block settings
 *
 * @return void
 */
function custom_endpoints() {
 register_rest_route(
  SOME_REST_NAMESPACE,
  'block-setting/',
  [
   'methods'  => \WP_REST_Server::READABLE,
   'callback' => __NAMESPACE__ . '\get_block_setting',
   'permission_callback' => __NAMESPACE__ . '\check_permissions',
  ]
 );

 register_rest_route(
  SOME_REST_NAMESPACE,
  'block-setting/',
  [
   'methods'             => \WP_REST_Server::EDITABLE,
   'callback'            => __NAMESPACE__ . '\update_block_setting',
   'permission_callback' => __NAMESPACE__ . '\check_permissions',
  ]
 );

}
```

## Define PHP Functions

```php
/**
 * Get Block Setting
 *
 * @return string
 */
function get_block_setting() {
 $block_setting = get_option( SOME_BLOCK_SETTING );

 $response = new \WP_REST_Response( $block_setting );
 $response->set_status( 200 );

 return $response;
}
```

```php
/**
 * Update Block Setting
 *
 * @return string
 */
function update_block_setting( $request ) {
 $new_block_setting = $request->get_body();
 update_option( SOME_BLOCK_SETTING, $new_block_setting );

 $block_setting = get_option( SOME_BLOCK_SETTING );
 $response      = new \WP_REST_Response( $block_setting );
 $response->set_status( 201 );

 return $response;
}
```

```php
/**
 * permission_callback
 *
 * @return boolean
 */
function check_permissions() {
 return current_user_can( 'edit_posts' );
}
```

## Define PHP Functions

```js
function getSetting() {
 return apiFetch({
  path: '/some-namespace/v1/block-setting'
 })
  .then(blockSetting => blockSetting)
  .catch(error => error);
}

function setSetting(setting) {
	return apiFetch({
		path: '/some-namespace/v1/block-setting',
		method: 'POST',
		body: setting
	})
	.then(blockSetting => blockSetting)
	.catch(error => error);
}
```
