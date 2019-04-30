<?php

class EeposStaffListWidget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'eepos_staff_list_widget',
			'Henkilökuntalista',
			[
				'description' => 'Lista henkilökunnan jäsenistä'
			]
		);

		wp_register_style( 'eepos_staff_list_widget_styles', plugin_dir_url( __FILE__ ) . '/widget-list-basic.css' );
		wp_register_script( 'eepos_staff_list_widget_script', plugin_dir_url( __FILE__ ) . '/widget-list.js' );
	}

	public function form( $instance ) {
		$defaults = [
			'title'              => '',
			'filter_fields'      => '',
			'staff_desc_format'  => '',
			'use_default_styles' => true
		];

		$args = wp_parse_args( $instance, $defaults );

		$fields = EeposStaffUtils::getFields();
		$lang   = EeposStaffUtils::getCurrentAdminLanguage();

		$slugs    = array_map( function ( $f ) {
			return $f->slug;
		}, $fields );
		$descVars = array_map( function ( $slug ) {
			return "{{$slug}}";
		}, $slugs );

		$filterFieldOpts = array_map( function ( $field ) use ( $lang ) {
			return (object) [
				'slug' => $field->slug,
				'name' => EeposStaffUtils::translate( $field->name, $lang )
			];
		}, $fields );

		?>
		<p>
			<label>
				<?php _e( 'Otsikko', 'eepos_staff' ) ?>
				<input type="text" class="widefat" name="<?= $this->get_field_name( 'title' ) ?>"
				       value="<?= esc_attr( $args['title'] ) ?>">
			</label>
		</p>
		<p>
			<label>
				<strong><?php _e( 'Kuvauksen formaatti', 'eepos_staff' ) ?></strong><br>
				Muuttujat: <?= implode( ' ', $descVars ) ?><br>
				<textarea
						name="<?= $this->get_field_name( 'staff_desc_format' ) ?>"><?= esc_html( $args['staff_desc_format'] ) ?></textarea>
			</label>
		</p>
		<p>
			<?php _e( 'Suodatettavat kentät', 'eepos_staff' ) ?><br>
			<select name="<?= $this->get_field_name( 'filter_fields' ) ?>[]" multiple>
				<?php foreach ( $filterFieldOpts as $opt ) { ?>
					<option value="<?= esc_attr( $opt->slug ) ?>"<?= in_array( $opt->slug, $args['filter_fields'] ) ? ' selected' : '' ?>>
						<?= esc_html( $opt->name ) ?>
					</option>
				<?php } ?>
			</select>
		</p>

		<p>
			<label>
				<input type="checkbox"
				       name="<?= $this->get_field_name( 'use_default_styles' ) ?>"<?= $args['use_default_styles'] ? ' checked' : '' ?>>
				<?php _e( 'Käytä perustyylejä', 'eepos_staff' ) ?>
			</label>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance                       = $old_instance;
		$instance['title']              = wp_strip_all_tags( $new_instance['title'] ?? '' );
		$instance['filter_fields']      = $new_instance['filter_fields'] ?? [];
		$instance['staff_desc_format']  = $new_instance['staff_desc_format'] ?? '';
		$instance['use_default_styles'] = ( $new_instance['use_default_styles'] ?? null ) === 'on';

		return $instance;
	}

	public function widget( $args, $instance ) {
		wp_enqueue_style( 'eepos_staff_list_widget_styles' );
		wp_enqueue_script( 'eepos_staff_list_widget_script' );

		$fields       = EeposStaffUtils::getFields();
		$fieldsBySlug = EeposStaffUtils::indexBy( $fields, 'slug' );

		$staffMembers = get_posts( [
			'post_type'      => 'eepos_staff_member',
			'orderby'        => 'post_title',
			'order'          => 'ASC',
			'posts_per_page' => - 1
		] );

		foreach ( $staffMembers as $member ) {
			$member->meta = get_post_meta( $member->ID );
		}

		$lang = EeposStaffUtils::getCurrentSiteLanguage();

		$filterFields           = $instance['filter_fields'] ?? [];
		$filterValues           = [];
		$filterNormalizedValues = [];

		foreach ( $staffMembers as $member ) {
			$member->fields = get_post_meta( $member->ID, 'eepos_staff_fields', true ) ?: [];
			if ( is_string( $member->fields ) ) {
				// Backwards compat
				$member->fields = json_decode( $member->fields );
			}

			foreach ( $member->fields as $field ) {
				$filterValues[ $field->slug ]           = $filterValues[ $field->slug ] ?? [];
				$filterNormalizedValues[ $field->slug ] = $filterNormalizedValues[ $field->slug ] ?? [];

				$translatedFieldValue = EeposStaffUtils::translate( $field->value, $lang );
				$fieldValues          = preg_split( '/,\s*/', $translatedFieldValue );

				foreach ( $fieldValues as $value ) {
					$normalizedValue = mb_strtolower( $value );
					if ( in_array( $normalizedValue, $filterNormalizedValues[ $field->slug ] ) ) {
						continue;
					}

					$filterValues[ $field->slug ][]           = $value;
					$filterNormalizedValues[ $field->slug ][] = $normalizedValue;
				}
			}
		}

		usort( $staffMembers, function ( $a, $b ) {
			$normalizedTitleA = mb_strtolower( $a->post_title );
			$normalizedTitleB = mb_strtolower( $b->post_title );
			if ( $normalizedTitleA > $normalizedTitleB ) {
				return 1;
			}
			if ( $normalizedTitleA < $normalizedTitleB ) {
				return - 1;
			}

			return 0;
		} );

		?>
		<div class="staff-member-list-widget">
			<?php if ( count( $filterFields ) ) { ?>
				<div class="staff-member-filters">
					<?php
					foreach ( $filterFields as $field ) {
						$fieldName = $fieldsBySlug[ $field ]->name ?? null;
						$fieldName = EeposStaffUtils::translate( $fieldName, $lang );

						$thisFilterValues = $filterValues[ $field ] ?? [];
						sort( $thisFilterValues );
						array_unshift( $thisFilterValues, '' );
						?>
						<div class="staff-member-filter">
							<div class="filter-title"><?= esc_html( $fieldName ) ?></div>
							<select class="staff-member-filter-select" data-field="<?= esc_attr( $field ) ?>">
								<?php foreach ( $thisFilterValues as $value ) { ?>
									<option value="<?= esc_attr( $value ) ?>"><?= esc_html( $value ) ?></option>
								<?php } ?>
							</select>
						</div>
					<?php } ?>
				</div>
			<?php } ?>
			<div class="staff-member-list">
				<?php
				foreach ( $staffMembers as $member ) {
					$image = isset( $member->meta['eepos_staff_image'][0] ) ? unserialize( $member->meta['eepos_staff_image'][0] ) : null;

					$vars = array_reduce( $member->fields, function ( $map, $field ) use ( $lang ) {
						$key         = "{{$field->slug}}";
						$map[ $key ] = esc_html( EeposStaffUtils::translate( $field->value, $lang ) );

						return $map;
					}, [] );

					$desc  = str_replace( array_keys( $vars ), array_values( $vars ), $instance['staff_desc_format'] );
					$attrs = array_map( function ( $field ) use ( $lang ) {
						$normalizedTranslatedValue = mb_strtolower( EeposStaffUtils::translate( $field->value, $lang ) );

						return "data-field-" . esc_html( $field->slug ) . "=\"" . esc_attr( $normalizedTranslatedValue ) . "\"";
					}, $member->fields );
					?>
					<div class="staff-member" <?= implode( ' ', $attrs ) ?>>
						<?php if ( $image ) { ?>
							<div class="image-container">
								<img src="<?= esc_attr( $image['url'] ) ?>" aria-hidden="true" alt="">
							</div>
						<?php } ?>
						<div class="name"><?= esc_html( $member->post_title ) ?></div>
						<div class="desc"><?= $desc ?></div>
					</div>
				<?php } ?>
			</div>
		</div>
		<?php
	}
}

function eepos_staff_register_list_widget() {
	register_widget( 'EeposStaffListWidget' );
}

add_action( 'widgets_init', 'eepos_staff_register_list_widget' );