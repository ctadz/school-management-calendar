<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load License Manager (ready for future)
require_once SMC_PLUGIN_DIR . 'includes/class-smc-license.php';

// Load Admin Menu
require_once SMC_PLUGIN_DIR . 'includes/class-smc-admin-menu.php';

// Load Schedules Page
require_once SMC_PLUGIN_DIR . 'includes/class-smc-schedules-page.php';

// Load Events Page
require_once SMC_PLUGIN_DIR . 'includes/class-smc-events-page.php';

// Load Calendar Page
require_once SMC_PLUGIN_DIR . 'includes/class-smc-calendar-page.php';

// Load Helper Functions
require_once SMC_PLUGIN_DIR . 'includes/smc-helpers.php';

// Load enqueue scripts
require_once SMC_PLUGIN_DIR . 'includes/smc-enqueue.php';