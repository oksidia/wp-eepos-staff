<?php

function eepos_staff_define_staff_list_columns() {
	return [
		'cb'    => '<input type="checkbox">',
		'title' => 'Nimi'
	];
}

add_filter( 'manage_eepos_staff_member_posts_columns', 'eepos_staff_define_staff_list_columns' );

function eepos_staff_meta_box() {
	global $post;

	$fields       = EeposStaffUtils::getFields();
	$fieldsBySlug = EeposStaffUtils::indexBy( $fields, 'slug' );

	$postFields = json_decode( get_post_meta( $post->ID, 'eepos_staff_fields', true ) ?? '[]' );
	foreach ( $postFields as $field ) {
		if ( ! isset( $fieldsBySlug[ $field->slug ] ) ) {
			continue;
		}
		$fieldsBySlug[ $field->slug ]->value = $field->value;
	}

	$lang = EeposStaffUtils::getCurrentAdminLanguage();

	$existingImage = get_post_meta($post->ID, 'eepos_staff_image', true);

	?>
	<table class="form-table">
		<tr>
			<th>Kuva</th>
			<td>
				<?php if ($existingImage) { ?>
					<div style="width: 128px">
						<img src="<?= esc_attr( $existingImage['url'] ) ?>" alt="Nykyinen kuva" style="max-width: 100%">
					</div>
				<?php } ?>
				<input type="file" name="eepos_staff_image">
			</td>
		</tr>
		<?php foreach ( $fields as $field ) { ?>
			<tr>
				<th><?= esc_html( EeposStaffUtils::translate( $field->name, $lang ) ) ?></th>
				<td>
					<input type="text" name="eepos_staff_field_<?= esc_attr( $field->slug ) ?>"
					       value="<?= esc_attr( EeposStaffUtils::translate( $field->value, $lang ) ) ?>">
				</td>
			</tr>
		<?php } ?>
	</table>
	<?php
}

function eepos_staff_add_meta_box() {
	add_meta_box( 'eepos_staff_fields', 'Tiedot', 'eepos_staff_meta_box', 'eepos_staff_member', 'normal' );
}

add_action( 'add_meta_boxes', 'eepos_staff_add_meta_box' );

function eepos_staff_save( $post_id ) {
	$postType = get_post_type( $post_id );
	if ( $postType !== 'eepos_staff_member' ) {
		return;
	}

	$language = $_POST['wpglobus_language'] ?? null;

	// Upload staff image
	if ( isset( $_FILES['eepos_staff_image'] ) && $_FILES['eepos_staff_image']['name'] !== '' ) {
		$file = $_FILES['eepos_staff_image'];

		$allowedTypes = [ 'image/png', 'image/jpg', 'image/jpeg' ];
		$fileType     = wp_check_filetype( $file['name'] );

		if ( ! in_array( $fileType['type'], $allowedTypes ) ) {
			wp_die( 'Tiedoston tulee olla PNG- tai JPG-muotoinen kuva' );
		}

		$upload = wp_upload_bits( $file['name'], null, file_get_contents( $file['tmp_name'] ) );
		if ( $upload['error'] !== false ) {
			wp_die( 'Virhe kuvaa lisättäessä: ' . $upload['error'] );
		}

		update_post_meta( $post_id, 'eepos_staff_image', $upload );
	}

	// Update staff fields
	$oldFields       = json_decode( get_post_meta( $post_id, 'eepos_staff_fields', true ) ?? '[]' );
	$oldFieldsBySlug = EeposStaffUtils::indexBy( $oldFields, 'slug' );

	$newFields = [];
	foreach ( $_POST as $field => $value ) {
		if ( ! EeposStaffUtils::startsWith( $field, 'eepos_staff_field_' ) ) {
			continue;
		}

		$fieldSlug  = substr( $field, strlen( 'eepos_staff_field_' ) );
		$fieldValue = $value;

		if ( EeposStaffUtils::translationsEnabled() ) {
			if ( ! $language ) {
				wp_die( 'Translations are enabled but no language key was supplied?' );
			}
			$oldField   = $oldFieldsBySlug[ $fieldSlug ] ?? null;
			$fieldValue = EeposStaffUtils::mergeLanguageStrings( $oldField->value ?? '', $language, $fieldValue );
		}

		$newFields[] = [ 'slug' => $fieldSlug, 'value' => $fieldValue ];
	}

	update_post_meta( $post_id, 'eepos_staff_fields', json_encode( $newFields ) );
}

add_action( 'save_post', 'eepos_staff_save' );

function eepos_staff_enable_file_uploads() {
	echo ' enctype="multipart/form-data"';
}
add_action('post_edit_form_tag', 'eepos_staff_enable_file_uploads');

function eepos_staff_add_manage_menu_item() {
	add_submenu_page(
		'edit.php?post_type=eepos_staff_member',
		'Henkilökunnan kentät',
		'Henkilökunnan kentät',
		'manage_options',
		'manage-eepos-staff-options',
		'eepos_staff_manage_page'
	);
}

add_action( 'admin_menu', 'eepos_staff_add_manage_menu_item' );

function eepos_staff_manage_page() {
	$availableLanguages = EeposStaffUtils::getAvailableLanguages();
	$currentLang        = EeposStaffUtils::getCurrentAdminLanguage();
	$fields             = EeposStaffUtils::getFields();
	foreach ( $fields as $field ) {
		$field->_name = EeposStaffUtils::translate( $field->name, $currentLang );
	}

	$formAction = admin_url( 'admin-post.php' );

	// @formatter:off
	?>
	<style>
		.eepos-staff-fields {
			margin-top: 24px;
		}

		.eepos-staff-fields .field {
			margin: 8px 0;
		}

		.eepos-staff-fields .field input {
			margin-right: 8px;
		}
	</style>

	<div class="wrap">
		<h1>Henkilökunnan kentät</h1>

		<?php if ( count( $availableLanguages ) ) { ?>
			<?php
			foreach ( $availableLanguages as $lang ) {
				$vars             = $_GET;
				$vars['language'] = $lang;
				$query            = http_build_query( $vars );

				$class = 'button';
				if ( $lang === $currentLang ) {
					$class .= ' button-primary';
				}
				?>
				<a href="?<?= esc_attr( $query ) ?>" class="<?= esc_attr( $class ) ?>">
					<?= esc_html( WPGlobus::Config()->en_language_name[ $lang ] ) ?>
				</a>
			<?php } ?>
		<?php } ?>

		<form action="<?= esc_attr( $formAction ) ?>" method="post">
			<input type="hidden" name="action" value="eepos_staff_manage">
			<?php if ( $currentLang ) { ?>
				<input type="hidden" name="lang" value="<?= esc_attr( $currentLang ) ?>">
			<?php } ?>
			<div class="eepos-staff-fields"></div>
			<button class="button add-eepos-staff-field-btn">Lisää uusi kenttä</button>

			<p class="submit">
				<input class="button button-primary" type="submit" value="Tallenna">
			</p>
		</form>
	</div>

	<!--suppress JSAssignmentUsedAsCondition -->
	<script>
		(function () {
			function findClosestElem(elem, theClass) {
				if (!elem) return;

				let cursor = elem;
				do {
					if (cursor.classList && cursor.classList.contains(theClass)) break;
				} while (cursor = cursor.parentNode);

				return cursor;
			}

			function addField(name, slug) {
				const field = document.createElement('div');
				field.classList.add('field');

				const nameInput = document.createElement('input');
				nameInput.type = 'text';
				nameInput.name = 'eepos_staff_field_names[]';
				nameInput.placeholder = slug;
				if (name) nameInput.value = name;

				const slugInput = document.createElement('input');
				slugInput.type = 'hidden';
				slugInput.name = 'eepos_staff_field_slugs[]';
				if (slug) slugInput.value = slug;

				const deleteBtn = document.createElement('button');
				deleteBtn.classList.add('delete-eepos-staff-field-btn', 'button');
				deleteBtn.innerText = 'Poista';

				field.appendChild(nameInput);
				field.appendChild(slugInput);
				field.appendChild(deleteBtn);

				staffFieldListRoot.appendChild(field);
			}

			const staffFieldListRoot = document.querySelector('.eepos-staff-fields');

			document.addEventListener('click', function (ev) {
				if (ev.target.classList.contains('delete-eepos-staff-field-btn')) {
					ev.preventDefault();

					if (!window.confirm('Poistetaanko kenttä?')) return;

					const field = findClosestElem(ev.target, 'field');
					field.parentNode.removeChild(field);

					return;
				}

				if (ev.target.classList.contains('add-eepos-staff-field-btn')) {
					ev.preventDefault();
					addField();
					return;
				}
			});

			const initialFields = <?= json_encode( $fields ) ?>;
			initialFields.forEach(f => addField(f._name, f.slug));
		})();
	</script>
	<?php
	// @formatter:on
}

function eepos_staff_manage_action() {
	eepos_staff_add_manage_menu_item();

	$oldFields     = EeposStaffUtils::getFields();
	$newFieldSlugs = $_POST['eepos_staff_field_slugs'];
	$newFieldNames = $_POST['eepos_staff_field_names'];

	$lang = $_POST['lang'] ?? null;

	$newFields = [];
	for ( $i = 0; $i < count( $newFieldSlugs ); $i ++ ) {
		$slug = $newFieldSlugs[ $i ];
		$name = $newFieldNames[ $i ];

		if ( $slug === '' ) {
			$slug = sanitize_title( $name );
		}

		if ( $lang ) {
			$oldField = $oldFields[ $i ] ?? null;
			$name     = EeposStaffUtils::mergeLanguageStrings( $oldField->name ?? '', $lang, $name );
		}

		$newFields[] = (object) [ 'slug' => $slug, 'name' => $name ];
	}

	update_option( 'eepos_staff_fields', json_encode( $newFields ) );
	wp_safe_redirect( EeposStaffUtils::menuPageUrl( 'manage-eepos-staff-options' ) );
	exit;
}

add_action( 'admin_post_eepos_staff_manage', 'eepos_staff_manage_action' );
