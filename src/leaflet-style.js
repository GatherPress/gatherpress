/**
 * Leaflet stylesheet + marker asset anchor for the venue-map frontend.
 *
 * The venue-map view script is a script module, and webpack's async CSS
 * chunk loading does not work under ESM module output — a dynamic
 * `import( '…/leaflet.css' )` dies in the chunk-loading runtime. So the
 * Leaflet styles build here as a plain stylesheet that `render.php`
 * enqueues whenever an interactive Leaflet map renders, and the marker
 * images are imported so webpack emits them to `build/images/` for
 * `L.Icon.Default.imagePath` to resolve at runtime (#2009).
 */
import 'leaflet/dist/leaflet.css';
// eslint-disable-next-line import/no-extraneous-dependencies
import 'leaflet-gesture-handling/dist/leaflet-gesture-handling.css';

import 'leaflet/dist/images/marker-icon.png';
import 'leaflet/dist/images/marker-icon-2x.png';
import 'leaflet/dist/images/marker-shadow.png';
