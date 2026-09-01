<?php
/**
 * Plugin Name: Tom's School Of Life — Site Companion
 * Plugin URI: https://github.com/BetterBetterBetter/tomschooloflife-plugin
 * Description: TSOL site-specific features (accountability modal, cookie consent). The member Library runs in the separate Member Library Platform plugin.
 * Version: 0.6.0
 * Author: Thrice Agency
 * License: GPL v2 or later
 * Text Domain: tomschooloflife-plugin
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://github.com/BetterBetterBetter/tomschooloflife-plugin
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('TSOL_SITE_PLUGIN_VERSION', '0.6.0');
define('TSOL_SITE_PLUGIN_FILE', __FILE__);
define('TSOL_SITE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('TSOL_SITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TSOL_SITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TSOL_SITE_PLUGIN_REPOSITORY_URL', 'https://github.com/BetterBetterBetter/tomschooloflife-plugin');

// Plugin Update Checker - GitHub integration.
if (file_exists(TSOL_SITE_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php')) {
    require_once TSOL_SITE_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

    $tsol_site_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        TSOL_SITE_PLUGIN_REPOSITORY_URL,
        __FILE__,
        'tomschooloflife-plugin'
    );

    // Enable GitHub releases for better versioning and release asset downloads.
    $tsol_site_update_checker->getVcsApi()->enableReleaseAssets();
}

// Include required files immediately to ensure classes are available for all hooks.
require_once TSOL_SITE_PLUGIN_DIR . 'includes/contracts/interface-feature.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/class-dependencies.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/class-gemini-client.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal-settings.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal-repository.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal-matcher.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal-admin.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal-renderer.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal-submission-handler.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/accountability-modal/class-accountability-modal.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/cookie-consent/class-cookie-consent-settings.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/cookie-consent/class-cookie-consent-admin.php';
require_once TSOL_SITE_PLUGIN_DIR . 'includes/features/cookie-consent/class-cookie-consent.php';

// TSOL legacy data migrations (WP-CLI only). These are TSOL-specific data
// history and live here in the site companion, not in the shared library
// plugin. They operate on the Library content model provided by the
// Member Library Platform plugin (MemberLibrary_* classes), so they load
// only under WP-CLI and only when that plugin is active.
if (defined('WP_CLI') && WP_CLI) {
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-content-normalization/class-library-normalization-spec.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-content-normalization/class-library-normalization-manifest.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-catalogue-import/class-library-catalogue-import.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-catalogue-import/class-library-catalogue-import-cli.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-series-import/class-library-series-import.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-series-import/class-library-series-import-cli.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-access-rules/class-library-access-rules-migration.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-access-rules/class-library-access-rules-migration-cli.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-new-marketer-workshop/class-library-new-marketer-workshop-import.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-new-marketer-workshop/class-library-new-marketer-workshop-import-cli.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-course-body-publication/class-library-course-body-publication.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-course-body-publication/class-library-course-body-publication-cli.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-resource-backfill/class-library-resource-backfill.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-resource-backfill/class-library-resource-backfill-cli.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-publication-rehearsal/class-library-publication-rehearsal.php';
    require_once TSOL_SITE_PLUGIN_DIR . 'includes/migrations/library-publication-rehearsal/class-library-publication-rehearsal-cli.php';
    WP_CLI::add_command(TSOL_Library_Catalogue_Import_CLI::COMMAND, 'TSOL_Library_Catalogue_Import_CLI');
    WP_CLI::add_command(TSOL_Library_Series_Import_CLI::COMMAND, 'TSOL_Library_Series_Import_CLI');
    WP_CLI::add_command(TSOL_Library_Access_Rules_Migration_CLI::COMMAND, 'TSOL_Library_Access_Rules_Migration_CLI');
    WP_CLI::add_command(TSOL_Library_New_Marketer_Workshop_Import_CLI::COMMAND, 'TSOL_Library_New_Marketer_Workshop_Import_CLI');
    WP_CLI::add_command(TSOL_Library_Course_Body_Publication_CLI::COMMAND, 'TSOL_Library_Course_Body_Publication_CLI');
    WP_CLI::add_command(TSOL_Library_Resource_Backfill_CLI::COMMAND, 'TSOL_Library_Resource_Backfill_CLI');
    WP_CLI::add_command(TSOL_Library_Publication_Rehearsal_CLI::COMMAND, 'TSOL_Library_Publication_Rehearsal_CLI');
}

require_once TSOL_SITE_PLUGIN_DIR . 'includes/class-plugin.php';

// Initialize the plugin.
TomsSchoolOfLifePlugin::get_instance();

// Activation and deactivation hooks.
register_activation_hook(__FILE__, array('TomsSchoolOfLifePlugin', 'activate'));
register_deactivation_hook(__FILE__, array('TomsSchoolOfLifePlugin', 'deactivate'));
