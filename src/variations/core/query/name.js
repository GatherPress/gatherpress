/**
 * Block-variation name for the GatherPress Event Query loop.
 *
 * Kept in its own side-effect-free module so unit tests (and any future
 * consumers) can import the constant without dragging in the variation
 * registration, starter patterns, and `start-blank` modules pulled in by
 * `./index.js`.
 */
export const NAME = 'gatherpress-event-query';
