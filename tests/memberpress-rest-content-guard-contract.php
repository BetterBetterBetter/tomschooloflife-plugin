<?php
/**
 * Read-only WP-CLI contract check for MemberPress REST content protection.
 *
 * Run: wp eval-file wp-content/plugins/tomschooloflife-plugin/tests/memberpress-rest-content-guard-contract.php --skip-themes
 */

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Run this contract check through WP-CLI.');
}

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(class_exists('TSOL_MemberPress_REST_Content_Guard'), 'REST content guard class is not loaded.');
$assert(class_exists('MeprRule'), 'MemberPress rule authority is not loaded.');

$post = get_post(100164);
$assert($post instanceof WP_Post, 'The protected bonus page was not found.');

if ($post instanceof WP_Post && class_exists('MeprRule')) {
    $rules = MeprRule::get_rules($post);
    $assert(!empty($rules), 'The bonus page has no MemberPress rule to enforce.');

    $original_user_id = get_current_user_id();
    wp_set_current_user(0);

    // Route the request through the real WP_REST_Server::dispatch() pipeline
    // (rest_do_request), not a direct method call on the guard. A direct call
    // with a hand-set 'id' param is what let the rest_pre_dispatch timing bug
    // (fixed in 0.6.6) ship undetected: the guard read $request->get_param('id')
    // before WordPress's own route matching ever populates it, so a live GET
    // to this exact endpoint stayed unguarded despite this same check passing.
    $request = new WP_REST_Request('GET', '/wp/v2/pages/100164');
    $response = rest_do_request($request);

    $assert($response instanceof WP_REST_Response, 'Anonymous direct REST did not return a REST response.');
    if ($response instanceof WP_REST_Response) {
        $assert($response->get_status() === 404, 'Anonymous direct REST to a protected page did not fail closed with 404.');
        $data = $response->get_data();
        $assert(strpos(wp_json_encode($data), (string) $post->post_content) === false, 'Guarded response included the protected post body.');
        $headers = $response->get_headers();
        $assert(isset($headers['Cache-Control']) && strpos($headers['Cache-Control'], 'no-store') !== false, 'Guarded response is missing no-store.');
        $assert(isset($headers['X-Content-Type-Options']) && $headers['X-Content-Type-Options'] === 'nosniff', 'Guarded response is missing nosniff.');
    }
    wp_set_current_user($original_user_id);
}

if (!empty($failures)) {
    WP_CLI::error(implode("\n", $failures));
}

WP_CLI::success('MemberPress REST content guard contract checks passed.');
