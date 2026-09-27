<?php
/**
 * Prevent MemberPress-protected post bodies from bypassing authorization via REST.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TSOL_MemberPress_REST_Content_Guard implements TSOL_Site_Feature {

    /**
     * Known high-sensitivity content that must fail closed for anonymous REST.
     *
     * The filter keeps the emergency fallback configurable without embedding
     * any course credential, bucket name, or object URL in source control.
     */
    const DEFAULT_SENSITIVE_POST_IDS = array(100164);

    public function init() {
        add_filter('posts_results', array($this, 'filter_locked_rest_posts'), 20, 2);
        add_filter('rest_pre_dispatch', array($this, 'guard_direct_rest_item'), 9, 3);
        add_filter('rest_post_dispatch', array($this, 'harden_protected_rest_response'), 10, 3);
    }

    /**
     * Remove content the current user cannot access from REST collection queries.
     *
     * This intentionally enforces the same MemberPress authority even when its
     * optional WordPress REST protection setting has not been enabled.
     *
     * @param array    $posts Queried posts.
     * @param WP_Query $query WordPress query.
     * @return array
     */
    public function filter_locked_rest_posts($posts, $query) {
        unset($query);

        if (!$this->is_rest_request() || !is_array($posts)) {
            return $posts;
        }

        foreach ($posts as $key => $post) {
            if (!$this->is_rest_visible_post($post)) {
                continue;
            }

            if ($this->is_locked_for_current_user($post)) {
                unset($posts[$key]);
            }
        }

        return array_values($posts);
    }

    /**
     * Deny direct core REST reads before WordPress serializes a protected body.
     *
     * A generic 404 avoids confirming the existence of protected material.
     *
     * @param mixed           $result  Existing pre-dispatch result.
     * @param WP_REST_Server  $server  REST server.
     * @param WP_REST_Request $request Current request.
     * @return mixed
     */
    public function guard_direct_rest_item($result, $server, $request) {
        unset($server);

        if (null !== $result) {
            return $result;
        }

        $post = $this->post_from_direct_rest_request($request);
        if (!$post || !$this->is_locked_for_current_user($post)) {
            return $result;
        }

        $response = new WP_REST_Response(array(
            'code'    => 'rest_post_invalid_id',
            'message' => __('Invalid post ID.', 'tomschooloflife-plugin'),
            'data'    => array('status' => 404),
        ), 404);

        return $this->add_private_headers($response);
    }

    /**
     * Prevent an authorized protected response from entering a shared cache.
     *
     * @param WP_HTTP_Response $response REST response.
     * @param WP_REST_Server   $server   REST server.
     * @param WP_REST_Request  $request  Current request.
     * @return WP_HTTP_Response
     */
    public function harden_protected_rest_response($response, $server, $request) {
        unset($server);

        $post = $this->post_from_direct_rest_request($request);
        if (!$post || !$this->has_memberpress_protection($post)) {
            return $response;
        }

        return $this->add_private_headers($response);
    }

    /**
     * Determine whether MemberPress denies the current WordPress identity.
     *
     * Administrators retain editorial REST access. The known sensitive fallback
     * remains unavailable to guests if MemberPress is temporarily unavailable.
     *
     * @param WP_Post $post Post being read.
     * @return bool
     */
    public function is_locked_for_current_user($post) {
        if (!$post instanceof WP_Post || current_user_can('manage_options')) {
            return false;
        }

        $sensitive = $this->is_sensitive_post($post);

        if (class_exists('MeprRule') && is_callable(array('MeprRule', 'is_locked'))) {
            if (MeprRule::is_locked($post)) {
                return true;
            }

            return $sensitive && !is_user_logged_in();
        }

        return $sensitive;
    }

    /**
     * @param WP_Post $post Post being inspected.
     * @return bool
     */
    private function has_memberpress_protection($post) {
        if ($this->is_sensitive_post($post)) {
            return true;
        }

        if (!class_exists('MeprRule') || !is_callable(array('MeprRule', 'get_rules'))) {
            return false;
        }

        return !empty(MeprRule::get_rules($post));
    }

    /**
     * Resolve a direct core REST item route to its WordPress post.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_Post|null
     */
    private function post_from_direct_rest_request($request) {
        if (!is_object($request) || !is_callable(array($request, 'get_method')) || !is_callable(array($request, 'get_route'))) {
            return null;
        }

        $method = strtoupper((string) $request->get_method());
        if (!in_array($method, array('GET', 'HEAD'), true)) {
            return null;
        }

        $id = is_callable(array($request, 'get_param')) ? absint($request->get_param('id')) : 0;
        if (!$id) {
            return null;
        }

        $post = get_post($id);
        if (!$this->is_rest_visible_post($post)) {
            return null;
        }

        $post_type = get_post_type_object($post->post_type);
        $namespace = !empty($post_type->rest_namespace) ? $post_type->rest_namespace : 'wp/v2';
        $rest_base = !empty($post_type->rest_base) ? $post_type->rest_base : $post->post_type;
        $expected_route = '/' . trim($namespace, '/') . '/' . trim($rest_base, '/') . '/' . $post->ID;
        $actual_route = untrailingslashit((string) $request->get_route());

        return $actual_route === $expected_route ? $post : null;
    }

    /**
     * @param mixed $post Candidate post.
     * @return bool
     */
    private function is_rest_visible_post($post) {
        if (!$post instanceof WP_Post) {
            return false;
        }

        $post_type = get_post_type_object($post->post_type);

        return $post_type && !empty($post_type->show_in_rest) && is_post_type_viewable($post_type);
    }

    /**
     * @param WP_Post $post Post being inspected.
     * @return bool
     */
    private function is_sensitive_post($post) {
        return in_array((int) $post->ID, $this->sensitive_post_ids(), true);
    }

    /**
     * @return int[]
     */
    private function sensitive_post_ids() {
        $ids = apply_filters('tsol_site_rest_guard_sensitive_post_ids', self::DEFAULT_SENSITIVE_POST_IDS);
        $ids = is_array($ids) ? array_map('absint', $ids) : self::DEFAULT_SENSITIVE_POST_IDS;

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param WP_HTTP_Response $response REST response.
     * @return WP_HTTP_Response
     */
    private function add_private_headers($response) {
        if (!is_object($response) || !is_callable(array($response, 'header'))) {
            return $response;
        }

        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Vary', 'Cookie, Authorization');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * @return bool
     */
    private function is_rest_request() {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}
