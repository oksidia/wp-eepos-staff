<?php

class EeposStaffUtils {
	public static function startsWith($str, $with) {
		return (substr($str, 0, strlen($with)) === $with);
	}

	public static function indexBy($arr, $key) {
		return array_reduce($arr, function($map, $item) use ($key) {
			$map[$item->{$key}] = $item;
			return $map;
		}, []);
	}

	public static function getFields() {
		return json_decode( get_option( 'eepos_staff_fields', '[]' ) ) ?? [];
	}

	public static function translationsEnabled() {
		return class_exists('WPGlobus');
	}

	public static function getAvailableLanguages() {
		if (class_exists('WPGlobus')) {
			return WPGlobus::Config()->enabled_languages;
		}

		return [];
	}

	public static function getCurrentAdminLanguage() {
		global $pagenow;

		if (! class_exists('WPGlobus')) {
			return null;
		}

		// Adapting the "current tab" code from WPGlobus::on_admin_scripts()
		$page = $pagenow ?: '';
		if (WPGlobus::Config()->builder->is_running()) {
			if ($page === 'post.php' || $page === 'post-new.php') {
				if (isset($_GET['post'])) {
					$postTab = get_post_meta( $_GET['post'], WPGlobus::Config()->builder->get_language_meta_key(), 'true' );
					if ($postTab) return $postTab;
				}
			}
		}

		if (isset($_GET['language'])) {
			$lang = $_GET['language'];
			$availableLanguages = self::getAvailableLanguages();
			if (in_array($lang, $availableLanguages)) return $lang;
		}

		return WPGlobus::Config()->default_language;
	}

	public static function getCurrentSiteLanguage() {
		if (! class_exists('WPGlobus')) {
			return null;
		}

		return WPGlobus::Config()->language;
	}

	public static function extractLanguageStrings($str) {
		if (! class_exists('WPGlobus')) {
			return [];
		}

		$availableLanguages = self::getAvailableLanguages();
		$strings = [];

		foreach ($availableLanguages as $lang) {
			$result = WPGlobus_Core::text_filter($str, $lang, WPGlobus::RETURN_EMPTY);
			if ($result !== '') $strings[$lang] = $result;
		}

		return $strings;
	}

	public static function translate($str, $lang) {
		if (! class_exists('WPGlobus')) {
			return $str;
		}

		if ($lang === null) {
			return $str;
		}

		return WPGlobus_Core::text_filter($str, $lang, WPGlobus::RETURN_EMPTY);
	}

	public static function serializeLanguageStrings($languageStringMap) {
		$result = '';
		foreach ($languageStringMap as $lang => $string) {
			$result .= sprintf(WPGlobus::LOCALE_TAG_START, $lang);
			$result .= $string;
			$result .= WPGlobus::LOCALE_TAG_END;
		}
		return $result;
	}

	public static function mergeLanguageStrings($originalStringWithTranslations, $newLanguage, $newString) {
		$languageStringMap = EeposStaffUtils::extractLanguageStrings($originalStringWithTranslations);
		$languageStringMap[$newLanguage] = $newString;
		return EeposStaffUtils::serializeLanguageStrings($languageStringMap);
	}

	/**
	 * Otherwise identical to menu_page_url but doesn't escape the URL and doesn't have an "echo" option
	 * @param string $menu_slug
	 */
	public static function menuPageUrl($menu_slug) {
		global $_parent_pages;

		if ( isset( $_parent_pages[ $menu_slug ] ) ) {
			$parent_slug = $_parent_pages[ $menu_slug ];
			if ( $parent_slug && ! isset( $_parent_pages[ $parent_slug ] ) ) {
				$url = admin_url( add_query_arg( 'page', $menu_slug, $parent_slug ) );
			} else {
				$url = admin_url( 'admin.php?page=' . $menu_slug );
			}
		} else {
			$url = '';
		}

		return $url;
	}
}