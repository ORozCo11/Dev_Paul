import { test } from 'node:test';
import assert from 'node:assert/strict';
import { boundaryLabelPoint, geoJsonToPolygons, isLocationPointAllowed, isPointWithinBoundaryPolygons } from '../src/utils/boundary.js';

const square = (min, max) => [[min, min], [max, min], [max, max], [min, max], [min, min]];

test('polygon holes remain empty in map coordinates and location validation', () => {
  const polygons = geoJsonToPolygons({ type: 'Polygon', coordinates: [square(0, 10), square(3, 7)] });
  assert.equal(polygons[0].length, 2);
  assert.equal(isPointWithinBoundaryPolygons({ lat: 1, lng: 1 }, polygons), true);
  assert.equal(isPointWithinBoundaryPolygons({ lat: 5, lng: 5 }, polygons), false);
  assert.equal(isPointWithinBoundaryPolygons({ lat: 20, lng: 20 }, polygons), false);
});

test('separate islands are accepted without filling the sea between them', () => {
  const polygons = geoJsonToPolygons({ type: 'MultiPolygon', coordinates: [[square(0, 2)], [square(8, 10)]] });
  assert.equal(polygons.length, 2);
  assert.equal(isPointWithinBoundaryPolygons({ lat: 1, lng: 1 }, polygons), true);
  assert.equal(isPointWithinBoundaryPolygons({ lat: 9, lng: 9 }, polygons), true);
  assert.equal(isPointWithinBoundaryPolygons({ lat: 5, lng: 5 }, polygons), false);
});

test('invalid and missing geometry never admit a location', () => {
  assert.deepEqual(geoJsonToPolygons(null), []);
  assert.deepEqual(geoJsonToPolygons({ type: 'Polygon', coordinates: [square(0, 10), null] }), []);
  assert.equal(isPointWithinBoundaryPolygons({ lat: NaN, lng: 1 }, [[square(0, 10)]]), false);
});

test('reference labels stay inside land boundaries and outside polygon holes', () => {
  const polygons = geoJsonToPolygons({ type: 'Polygon', coordinates: [square(0, 10), square(3, 7)] });
  const [lat, lng] = boundaryLabelPoint(polygons);
  assert.equal(isPointWithinBoundaryPolygons({ lat, lng }, polygons), true);
  assert.equal(boundaryLabelPoint([]), null);
});

test('reference labels stay on the largest island', () => {
  const polygons = geoJsonToPolygons({ type: 'MultiPolygon', coordinates: [[square(0, 2)], [square(8, 20)]] });
  const [lat, lng] = boundaryLabelPoint(polygons);
  assert.equal(isPointWithinBoundaryPolygons({ lat, lng }, [polygons[1]]), true);
});

test('unreviewed outlines do not reject legitimate coordinates outside the reference', () => {
  const polygons = geoJsonToPolygons({ type: 'Polygon', coordinates: [square(0, 10)] });
  assert.equal(isLocationPointAllowed({ lat: 20, lng: 20 }, polygons, false), true);
  assert.equal(isLocationPointAllowed({ lat: 20, lng: 20 }, polygons, true), false);
  assert.equal(isLocationPointAllowed({ lat: NaN, lng: 20 }, polygons, false), false);
  assert.equal(isLocationPointAllowed({ lat: 95, lng: 20 }, polygons, false), false);
});
