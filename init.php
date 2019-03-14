<?php

function eepos_staff_register_post_type() {
	register_post_type( 'eepos_staff_member', [
		'labels' => [
			'name' => __('Henkilökunta', 'eepos_staff'),
			'singular_name' => __('Henkilökunnan jäsen', 'eepos_staff'),
			'add_new_item' => __('Lisää uusi henkilökunnan jäsen', 'eepos_staff'),
			'not_found' => __('Henkilökunnan jäsenia ei löytynyt', 'eepos_staff'),
		],
		'menu_position' => 5,
		'public'        => true,
		'has_archive'   => false,
		'supports'      => ['title'],
		'menu_icon' => 'dashicons-admin-users'
	] );
}

add_action( 'init', 'eepos_staff_register_post_type' );

function eepos_staff_replace_enter_title_text( $input ) {
	if (get_post_type() === 'eepos_staff_member') {
		return __('Nimi', 'eepos_staff');
	}

	return $input;
}

add_filter( 'enter_title_here', 'eepos_staff_replace_enter_title_text' );
