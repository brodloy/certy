<?php
/**
 * TESTS — plain PHP, no framework. Run with:  php tests.php
 *
 * These cover the pure logic that doesn't need a database (escaping, routing,
 * CSRF, url building). It's deliberately tiny — the point is that you can read
 * and extend it in five minutes.
 */

define('BASE_PATH', __DIR__);
$GLOBALS['config'] = require BASE_PATH . '/config.example.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Router.php';

$pass = 0;
$fail = 0;
function check(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   $label\n"; }
    else       { $fail++; echo "  FAIL $label\n"; }
}

check('e() escapes HTML', e('<b>') === '&lt;b&gt;');
check('e() handles null', e(null) === '');
check('url() builds clean links', url('/login') === url('login'));

$r = new Router();
$r->get('/examples/{id}/edit', ['C', 'm']);
$ref = (new ReflectionClass($r))->getProperty('routes');
$ref->setAccessible(true);
$route = $ref->getValue($r)[0];
check('router captures {id}', $route['params'] === ['id']);
check('router regex matches', (bool) preg_match($route['regex'], '/examples/7/edit'));

$_SESSION = [];
check('csrf token is 64 hex chars', ctype_xdigit(csrf_token()) && strlen(csrf_token()) === 64);

flash('success', 'hi');
check('flash stored then cleared', (flash_all()['success'] ?? '') === 'hi' && !isset($_SESSION['_flash']));

check('format_date formats UTC', format_date('2026-01-05 14:30:00', 'Y-m-d H:i') === '2026-01-05 14:30');
check('human_size MB', human_size(5 * 1024 * 1024) === '5 MB');
check('pager hidden for 1 page', pagination_links(['page' => 1, 'totalPages' => 1], '/x') === '');
check('pager shows Next', str_contains(pagination_links(['page' => 1, 'totalPages' => 3], '/e'), 'page=2'));

$_SESSION = [];
remember_errors(['email' => 'bad']);
check('error() reads a field', error('email') === 'bad' && error('name') === '');
check('has_error true/false', has_error('email') && !has_error('name'));
check('invalid_class adds class only on error', invalid_class('email') === ' is-invalid' && invalid_class('name') === '');
check('field_error renders message', str_contains(field_error('email'), 'bad') && field_error('name') === '');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
