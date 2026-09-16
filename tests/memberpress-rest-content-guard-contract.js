#!/usr/bin/env node

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const entrypoint = fs.readFileSync(path.join(root, 'tomschooloflife-plugin.php'), 'utf8');
const loader = fs.readFileSync(path.join(root, 'includes/class-plugin.php'), 'utf8');
const guardPath = path.join(
  root,
  'includes/features/memberpress-rest-content-guard/class-memberpress-rest-content-guard.php'
);

assert.ok(fs.existsSync(guardPath), 'The MemberPress REST content guard must exist.');

const guard = fs.readFileSync(guardPath, 'utf8');

assert.match(entrypoint, /class-memberpress-rest-content-guard\.php/, 'The plugin entrypoint must load the guard.');
assert.match(loader, /new TSOL_MemberPress_REST_Content_Guard\(\)/, 'The site plugin must register the guard.');
assert.match(guard, /implements TSOL_Site_Feature/, 'The guard must use the site feature contract.');
assert.match(guard, /add_filter\('posts_results'/, 'REST collection queries must remove locked MemberPress posts.');
assert.match(guard, /add_filter\('rest_pre_dispatch'/, 'Direct REST item requests must be denied before content serialization.');
assert.match(guard, /add_filter\('rest_post_dispatch'/, 'Protected REST responses must receive private no-store headers.');
assert.match(guard, /MeprRule::is_locked/, 'Authorization must defer to MemberPress rather than a second membership list.');
assert.match(guard, /MeprRule::get_rules/, 'Response hardening must recognize protected content even for authorized members.');
assert.match(guard, /DEFAULT_SENSITIVE_POST_IDS\s*=\s*array\(100164\)/, 'The known protected bonus page must fail closed.');
assert.match(guard, /private, no-store/, 'Protected REST responses must not be cached publicly.');
assert.match(guard, /X-Content-Type-Options/, 'Protected REST responses must disable content-type sniffing.');
assert.doesNotMatch(guard, /Email Domination|libertyplatform|amazonaws/i, 'The guard must not embed course names, passwords, buckets, or object URLs.');

console.log('MemberPress REST content guard contract passed.');
