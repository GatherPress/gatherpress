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

## GP JS Functions

```js
apiFetch( {
   path: '/gatherpress/v1/event/datetime/',
   method: 'POST',
   data: {
    // eslint-disable-next-line no-undef
    post_id: GatherPress.post_id,
    datetime_start: moment(
    // eslint-disable-next-line no-undef
     GatherPress.event_datetime.datetime_start,
    ).format( 'YYYY-MM-DD HH:mm:ss' ),
    datetime_end: moment(
    // eslint-disable-next-line no-undef
     GatherPress.event_datetime.datetime_end,
    ).format( 'YYYY-MM-DD HH:mm:ss' ),
    // eslint-disable-next-line no-undef
    _wpnonce: GatherPress.nonce,
   },
  } )

```

## Some JS Functions

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
