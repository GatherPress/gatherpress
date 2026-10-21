import { useRef } from '@wordpress/element';

/**
 * Returns a referentially-stable version of `value`, keeping the previous
 * render's reference as long as its JSON-serialized shape hasn't actually
 * changed.
 *
 * `getBlocks()`-based useSelect() results can return new array/object
 * wrappers on *any* block-editor store update - including selection
 * changes that have nothing to do with the blocks being read - even when
 * the underlying data is unchanged. An unstable reference here cascades
 * into consumers (e.g. useBlockPreview, whose internal editor context
 * fully tears down and rebuilds whenever its `blocks` prop's reference
 * changes) causing unnecessary, visible re-renders across the whole grid.
 *
 * @since 0.4.0
 *
 * @param {*} value Value to stabilize.
 *
 * @return {*} The same value, or the previous render's reference if unchanged.
 */
export function useStableValue( value ) {
	const valueRef = useRef( value );
	const serializedRef = useRef();
	const serialized = JSON.stringify( value );

	if ( serializedRef.current !== serialized ) {
		serializedRef.current = serialized;
		valueRef.current = value;
	}

	return valueRef.current;
}
