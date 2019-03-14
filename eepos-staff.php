<?php

/**
* Plugin Name: Eepos Staff
*/

// Utils
require_once( __DIR__ . '/utils.php' );

// Init
require_once( __DIR__ . '/init.php' );

// Install
//require_once( __DIR__ . '/install.php' );

// Admin panel
if (is_admin()) {
	require_once( __DIR__ . '/admin.php' );
}

// Staff list widget
require_once( __DIR__ . '/widget-list.php' );
