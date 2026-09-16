<?php
/**
 * Standalone behavioral tests for the MemberPress REST content guard.
 *
 * Run: php -n tests/memberpress-rest-content-guard-unit.php
 */

define('ABSPATH', __DIR__ . '/');
define('REST_REQUEST', true);

interface TSOL_Site_Feature {
    public function init();
}

class WP_Post {
    public $ID;
    public $post_type;
    public $post_content;

    public function __construct($id, $post_type = 'page', $post_content = '') {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_content = $post_content;
    }
}

class WP_REST_Request {
    private $method;
    private $route;
    private $params = array();

    public function __construct($method, $route) {
        $this->method = $method;
        $this->route = $route;
    }

    public function get_method() {
        return $this->method;
    }

    public function get_route() {
        return $this->route;
    }

    public function set_param($key, $value) {
        $this->params[$key] = $value;
    }

    public function get_param($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}

class WP_REST_Response {
    private $data;
    private $status;
    private $headers = array();

    public function __construct($data = null, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }

    public function header($name, $value) {
        $this->headers[$name] = $value;
    }

    public function get_status() {
        return $this->status;
    }

    public function get_data() {
        return $this->data;
    }

    public function get_headers() {
        return $this->headers;
    }
}

class MeprRule {
    public static $locked = array();
    public static $rules = array();

    public static function is_locked($post) {
        return !empty(self::$locked[$post->ID]);
    }

    public static function get_rules($post) {
        return isset(self::$rules[$post->ID]) ? self::$rules[$post->ID] : array();
    }
}

$test_hooks = array();
$test_posts = array();
$test_is_admin = false;
$test_is_logged_in = false;

function add_filter($name, $callback, $priority = 10, $accepted_args = 1) {
    global $test_hooks;
    $test_hooks[$name] = array($callback, $priority, $accepted_args);
}

function apply_filters($name, $value) {
    unset($name);
    return $value;
}

function current_user_can($capability) {
    global $test_is_admin;
    return 'manage_options' === $capability && $test_is_admin;
}

function is_user_logged_in() {
    global $test_is_logged_in;
    return $test_is_logged_in;
}

function get_post($id) {
    global $test_posts;
    return isset($test_posts[$id]) ? $test_posts[$id] : null;
}

function get_post_type_object($post_type) {
    if ('page' !== $post_type && 'post' !== $post_type) {
        return null;
    }

    return (object) array(
        'name' => $post_type,
        'public' => true,
        'show_in_rest' => true,
        'rest_namespace' => 'wp/v2',
        'rest_base' => 'page' === $post_type ? 'pages' : 'posts',
    );
}

function is_post_type_viewable($post_type) {
    return !empty($post_type->public);
}

function untrailingslashit($value) {
    return rtrim($value, '/');
}

function absint($value) {
    return abs((int) $value);
}

function __($value, $domain) {
    unset($domain);
    return $value;
}

require_once dirname(__DIR__) . '/includes/features/memberpress-rest-content-guard/class-memberpress-rest-content-guard.php';

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$guard = new TSOL_MemberPress_REST_Content_Guard();
$guard->init();
$assert(isset($test_hooks['posts_results']), 'Collection filter was not registered.');
$assert(isset($test_hooks['rest_pre_dispatch']), 'Direct-item filter was not registered.');
$assert(isset($test_hooks['rest_post_dispatch']), 'Response-hardening filter was not registered.');

$test_posts = array(
    2 => new WP_Post(2, 'page', 'public body'),
    3 => new WP_Post(3, 'post', 'protected body'),
    100164 => new WP_Post(100164, 'page', 'contract-only secret marker'),
);
MeprRule::$locked = array(3 => true, 100164 => true);
MeprRule::$rules = array(3 => array((object) array('ID' => 11)), 100164 => array((object) array('ID' => 12)));

$filtered = $guard->filter_locked_rest_posts(array_values($test_posts), null);
$assert(count($filtered) === 1 && $filtered[0]->ID === 2, 'Anonymous collection filtering did not remove locked posts.');

$test_is_admin = true;
$admin_results = $guard->filter_locked_rest_posts(array_values($test_posts), null);
$assert(count($admin_results) === 3, 'Administrator REST access was incorrectly filtered.');
$test_is_admin = false;

$request = new WP_REST_Request('GET', '/wp/v2/pages/100164');
$request->set_param('id', 100164);
$denied = $guard->guard_direct_rest_item(null, null, $request);
$assert($denied instanceof WP_REST_Response, 'Locked direct item did not receive a REST response.');
$assert($denied->get_status() === 404, 'Locked direct item did not return a generic 404.');
$assert(strpos(json_encode($denied->get_data()), 'contract-only secret marker') === false, 'Denied response serialized protected content.');
$assert(strpos($denied->get_headers()['Cache-Control'], 'no-store') !== false, 'Denied response is missing no-store.');
$assert($denied->get_headers()['X-Content-Type-Options'] === 'nosniff', 'Denied response is missing nosniff.');

$post_request = new WP_REST_Request('POST', '/wp/v2/pages/100164');
$post_request->set_param('id', 100164);
$assert($guard->guard_direct_rest_item(null, null, $post_request) === null, 'The read guard interfered with a non-read request.');

$wrong_route = new WP_REST_Request('GET', '/custom/v1/pages/100164');
$wrong_route->set_param('id', 100164);
$assert($guard->guard_direct_rest_item(null, null, $wrong_route) === null, 'The guard intercepted an unrelated REST namespace.');

$test_is_logged_in = true;
MeprRule::$locked[100164] = false;
$assert($guard->guard_direct_rest_item(null, null, $request) === null, 'An authorized member was denied protected content.');
$authorized = $guard->harden_protected_rest_response(new WP_REST_Response(array('content' => 'allowed')), null, $request);
$assert(strpos($authorized->get_headers()['Cache-Control'], 'no-store') !== false, 'Authorized protected content was not marked no-store.');

$test_is_logged_in = false;
MeprRule::$locked[100164] = false;
$assert($guard->guard_direct_rest_item(null, null, $request) instanceof WP_REST_Response, 'Sensitive fallback did not fail closed for an anonymous request.');

$public_request = new WP_REST_Request('GET', '/wp/v2/pages/2');
$public_request->set_param('id', 2);
$public_response = $guard->harden_protected_rest_response(new WP_REST_Response(array('content' => 'public')), null, $public_request);
$assert(empty($public_response->get_headers()), 'Unprotected public content received private response headers.');

if (!empty($failures)) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "MemberPress REST content guard unit checks passed.\n";
