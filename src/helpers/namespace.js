/**
 * The REST API namespace for all GatherPress endpoints.
 *
 * Note: earlier versions of this file also exported post type and taxonomy
 * constants such as CPT_EVENT and TAX_VENUE. Those were intentionally removed
 * in favor of post-type-support checks (post_type_supports / isPostTypeSupporting),
 * which allow third-party post types to participate in GatherPress features
 * without relying on hardcoded slug comparisons.
 *
 * @since 0.34.0
 *
 * @type {string}
 */
export const REST_NAMESPACE = 'gatherpress/v1';

/**
 * The REST API path for event endpoints.
 *
 * @since 0.34.0
 *
 * @type {string}
 */
export const EVENT_REST_API = `/${ REST_NAMESPACE }/event`;
