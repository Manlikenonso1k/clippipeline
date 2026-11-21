import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkProps } from '../../motion/src/templates/registry.js';

// Edits saved while Laravel converted "" to null must still render (schema defaults fill the gaps).
test('null template props fall back to their defaults', () => {
  const title = checkProps('kinetic-title', 1, { title: 'Owo', kicker: null, subtitle: null });
  assert.ok(title.ok, title.ok ? '' : title.error);
  assert.equal(title.ok && title.props.kicker, 'NEW VIDEO');

  const stat = checkProps('stat-counter', 1, { value: 10, prefix: null, suffix: '%' });
  assert.ok(stat.ok, stat.ok ? '' : stat.error);
  assert.equal(stat.ok && stat.props.prefix, '');
});

test('PHP empty props ([]) are treated as {}', () => {
  assert.ok(checkProps('kinetic-title', 1, []).ok);
});

test('wrong types are still rejected', () => {
  const r = checkProps('stat-counter', 1, { value: 'lots' });
  assert.equal(r.ok, false);
});
