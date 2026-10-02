<?php
/**
 * The Mercury Schema admin menu and its pages:
 *   Schema Types (dashboard), Site-wide Schemas, Settings, and the setup wizard.
 * Pages are shells; admin/js renders them through the REST API.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Admin;

use MercurySchema\API\SchemaController;
use MercurySchema\Helpers\SetupState;

class Pages
{
    public const TYPES = 'mercury-schema';
    public const SITE_WIDE = 'mercury-schema-site-wide';
    public const SETTINGS = 'mercury-schema-settings';
    public const SETUP = 'mercury-schema-setup';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'activationRedirect']);
        add_action('admin_notices', [$this, 'setupNotice']);
        add_action('admin_enqueue_scripts', [$this, 'menuIconStyle']);
        add_action('in_admin_header', [$this, 'quietWizard'], 1000);
        add_filter('admin_title', [$this, 'wizardTitle']);
    }

    /** Hidden pages get no title from the menu; give the wizard one. */
    public function wizardTitle(string $title): string
    {
        return self::current() === self::SETUP && strpos($title, __('Setup Wizard', 'mercury-schema')) === false
            ? __('Setup Wizard', 'mercury-schema') . ' ' . ltrim($title)
            : $title;
    }

    /** Other plugins' notices would push the wizard below the fold; hide them on this one screen. */
    public function quietWizard(): void
    {
        if (self::current() === self::SETUP) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    public static function url(string $page = self::TYPES): string
    {
        return admin_url('admin.php?page=' . $page);
    }

    /** The page slug being viewed, if it is one of ours. */
    public static function current(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return in_array($page, [self::TYPES, self::SITE_WIDE, self::SETTINGS, self::SETUP], true) ? $page : '';
    }

    public function addMenu(): void
    {
        $cap = SchemaController::globalCapability();
        add_menu_page(__('Mercury Schema', 'mercury-schema'), __('Mercury Schema', 'mercury-schema'), $cap, self::TYPES, [$this, 'renderTypes'], 'none', 81);
        add_submenu_page(self::TYPES, __('Schema Types', 'mercury-schema'), __('Schema Types', 'mercury-schema'), $cap, self::TYPES, [$this, 'renderTypes']);
        add_submenu_page(self::TYPES, __('Site-wide Schemas', 'mercury-schema'), __('Site-wide Schemas', 'mercury-schema'), $cap, self::SITE_WIDE, [$this, 'renderSiteWide']);
        add_submenu_page(self::TYPES, __('Settings', 'mercury-schema'), __('Settings', 'mercury-schema'), 'manage_options', self::SETTINGS, [$this, 'renderSettings']);
        // Listed in the menu until setup is done; afterwards a hidden page, still reachable by URL.
        add_submenu_page(SetupState::isComplete() ? '' : self::TYPES, __('Setup Wizard', 'mercury-schema'), __('Setup Wizard', 'mercury-schema'), $cap, self::SETUP, [$this, 'renderSetup']);
    }

    public function activationRedirect(): void
    {
        if (!get_transient(SetupState::REDIRECT)) {
            return;
        }
        delete_transient(SetupState::REDIRECT);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- bulk activation flag only.
        if (SetupState::isComplete() || wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi']) || !current_user_can(SchemaController::globalCapability())) {
            return;
        }
        wp_safe_redirect(self::url(self::SETUP));
        exit;
    }

    public function setupNotice(): void
    {
        $screen = get_current_screen();
        $where = ['dashboard', 'plugins'];
        if (SetupState::isComplete() || !current_user_can(SchemaController::globalCapability())
            || self::current() === self::SETUP || !$screen || (!in_array($screen->id, $where, true) && self::current() === '')) {
            return;
        }
        printf(
            '<div class="notice notice-info"><p><strong>%s</strong> %s <a class="button button-primary" style="margin-left:8px" href="%s">%s</a></p></div>',
            esc_html__('Mercury Schema is almost ready.', 'mercury-schema'),
            esc_html__('Pick the schema types for your site in a one-minute setup.', 'mercury-schema'),
            esc_url(self::url(self::SETUP)),
            esc_html__('Start setup', 'mercury-schema')
        );
    }

    /** A highlighted, brand-blue menu item with the white Mercury mark. */
    public function menuIconStyle(): void
    {
        $mask = 'url("data:image/svg+xml,' . rawurlencode(Brand::mark('#000')) . '") center/contain no-repeat';
        $item = '#adminmenu #toplevel_page_mercury-schema';
        // Static CSS from constants, attached to WordPress's own admin-menu stylesheet.
        wp_add_inline_style('admin-menu', implode('', [
            "$item>a.menu-top,$item>a.menu-top:focus{background:" . Brand::COLOR . ';color:#fff}',
            "$item>a.menu-top:hover,$item.opensub>a.menu-top{background:#1A43B8;color:#fff}",
            "$item>a.menu-top .wp-menu-name{color:#fff}",
            "$item .wp-menu-image::before{content:'';display:block;width:20px;height:20px;margin:7px auto 0;padding:0;background:#fff;-webkit-mask:$mask;mask:$mask}",
            "$item.wp-has-current-submenu>a.menu-top{background:" . Brand::COLOR . '}',
        ]));
    }

    private function header(string $active): void
    {
        $tabs = [
            self::TYPES     => __('Schema Types', 'mercury-schema'),
            self::SITE_WIDE => __('Site-wide Schemas', 'mercury-schema'),
            self::SETTINGS  => __('Settings', 'mercury-schema'),
        ];
        ?>
        <header class="ms-page__head">
            <span class="ms-logo" aria-hidden="true"><?php echo Brand::mark('#fff'); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
            <div>
                <h1><?php esc_html_e('Mercury Schema', 'mercury-schema'); ?> <span class="ms-page__ver"><?php echo esc_html(MERCURY_SCHEMA_VERSION); ?></span></h1>
                <p><?php esc_html_e('Structured data that helps search engines and AI answers understand your site.', 'mercury-schema'); ?></p>
            </div>
        </header>
        <nav class="ms-page__tabs" aria-label="<?php esc_attr_e('Mercury Schema sections', 'mercury-schema'); ?>">
            <?php foreach ($tabs as $page => $label) : ?>
                <a href="<?php echo esc_url(self::url($page)); ?>" class="ms-page__tab<?php echo $active === $page ? ' is-active' : ''; ?>"<?php echo $active === $page ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <hr class="wp-header-end"><?php // WordPress moves admin notices here instead of into our header. ?>
        <?php
    }

    public function renderTypes(): void
    {
        echo '<div class="wrap ms-page">';
        $this->header(self::TYPES);
        echo '<div id="mercury-schema-setup" class="ms-setup ms-setup--types" data-mode="types"><p class="ms-muted">' . esc_html__('Loading…', 'mercury-schema') . '</p></div>';
        echo '<noscript><p>' . esc_html__('Mercury Schema needs JavaScript for this screen.', 'mercury-schema') . '</p></noscript></div>';
    }

    public function renderSiteWide(): void
    {
        echo '<div class="wrap ms-page">';
        $this->header(self::SITE_WIDE);
        echo '<div id="mercury-schema-app" class="ms-app ms-app--page"><p class="ms-muted">' . esc_html__('Loading…', 'mercury-schema') . '</p></div>';
        echo '<noscript><p>' . esc_html__('Mercury Schema needs JavaScript for this screen.', 'mercury-schema') . '</p></noscript></div>';
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap ms-page">';
        $this->header(self::SETTINGS);
        $this->settings->renderForm();
        echo '</div>';
    }

    public function renderSetup(): void
    {
        echo '<div class="wrap ms-page ms-page--setup">';
        echo '<div id="mercury-schema-setup" class="ms-setup ms-setup--wizard" data-mode="wizard"><p class="ms-muted">' . esc_html__('Loading…', 'mercury-schema') . '</p></div>';
        echo '<noscript><p>' . esc_html__('Mercury Schema needs JavaScript for this screen.', 'mercury-schema') . '</p></noscript></div>';
    }
}
