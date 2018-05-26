<?php

namespace Fragen\Local_Development;

/*
 * Exit if called directly.
 */
if (! defined('WPINC')) {
	die;
}

/**
 * Class Base
 *
 */
class Base {
	/**
	 * Static to hold slugs of plugins under development.
	 * @var
	 */
	protected static $plugins;

	/**
	 * Static to hold slugs themes under development.
	 * @var
	 */
	protected static $themes;

	/**
	 * Static to hold message.
	 * @var
	 */
	protected static $message;

	/**
	 * Holds plugin settings.
	 *
	 * @var mixed|void
	 */
	protected static $options;

	/**
	 * Local_Development constructor.
	 *
	 * @param $config
	 */
	public function __construct($config) {
		self::$plugins = isset($config['plugins']) ? $config['plugins'] : null;
		self::$themes  = isset($config['themes']) ? $config['themes'] : null;
		self::$message = esc_html__('In Local Development', 'local-development');
		self::$options = get_site_option('local_development');

		add_filter('plugin_row_meta', array( $this, 'row_meta' ), 15, 2);
		add_filter('site_transient_update_plugins', array( &$this, 'hide_update_nag' ), 15, 1);

		add_filter('theme_row_meta', array( $this, 'row_meta' ), 15, 2);
		add_filter('site_transient_update_themes', array( &$this, 'hide_update_nag' ), 15, 1);

		if (! is_multisite()) {
			add_filter('wp_prepare_themes_for_js', array( $this, 'set_theme_description' ), 15, 1);
		}
		$this->allow_local_servers();
	}

	/**
	 * Add an additional element to the row meta links.
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public function row_meta($links, $file) {
		if ((! empty(self::$plugins) && array_key_exists($file, self::$plugins)) ||
			(! empty(self::$themes) && array_key_exists($file, self::$themes))
		) {
			$links[] = '<strong>' . self::$message . '</strong>';
			add_action("after_plugin_row_{$file}", array( $this, 'remove_update_row' ), 15, 1);
			add_action("after_theme_row_{$file}", array( $this, 'remove_update_row' ), 15, 1);
		}

		return $links;
	}

	/**
	 * Sets the description for the single install theme action.
	 *
	 * @param $prepared_themes
	 *
	 * @return array
	 */
	public function set_theme_description($prepared_themes) {
		foreach ($prepared_themes as $theme) {
			if (array_key_exists($theme['id'], (array) self::$themes)) {
				$message  = wp_get_theme($theme['id'])->get('Description');
				$message .= '<p><strong>' . self::$message . '</strong></p>';

				$prepared_themes[$theme['id']]['description'] = $message;
			}
		}

		return $prepared_themes;
	}

	/**
	 * Hide the update nag.
	 *
	 * @param object $transient site_transient_update_{plugins|themes}.
	 *
	 * @return object $transient Modified site_transient_update_{plugins|themes}.
	 */
	public function hide_update_nag($transient) {
		switch (current_filter()) {
			case 'site_transient_update_plugins':
				$repos = self::$plugins;
				break;
			case 'site_transient_update_themes':
				$repos = self::$themes;
				break;
			default:
				return $transient;
		}

		if (! empty($repos)) {
			foreach (array_keys($repos) as $repo) {
				if ('update_nag' === $repo) {
					continue;
				}
				if (isset($transient->response[$repo])) {
					unset($transient->response[$repo]);
				}
			}
		}

		return $transient;
	}

	/**
	 * Hide update messages.
	 */
	public function hide_update_message() {
		global $pagenow;
		if ('plugins.php' === $pagenow && ! empty(self::$options['plugins'])) {
			foreach (array_keys(self::$options['plugins']) as $plugin) {
				$this->remove_update_row($plugin);
			}
		}
		if ('themes.php' === $pagenow && ! empty(self::$options['themes'])) {
			foreach (array_keys(self::$options['themes']) as $theme) {
				$this->remove_update_row($theme);
			}
		}
	}

	/**
	 * Write out inline style to hide the update row notice.
	 *
	 * @param string $repo_name Repository file name.
	 */
	public function remove_update_row($repo_name) {
		print '<script>' ;
		print 'jQuery("tr.plugin-update-tr[data-plugin=\'' . $repo_name . '\']").remove();';
		print 'jQuery(".update[data-plugin=\'' . $repo_name . '\']").removeClass("update");';
		print '</script>' ;
	}

	/**
	 * In case the developer is running a local instance of a git server.
	 *
	 * @return void
	 */
	public function allow_local_servers() {
		add_filter('http_request_args', function ($r, $url) {
			if (! $r['reject_unsafe_urls']) {
				return $r;
			}
			$host = parse_url($url, PHP_URL_HOST);
			if (preg_match('#^(([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)$#', $host)) {
				$ip = $host;
			} else {
				return $r;
			}

			$parts = array_map('intval', explode('.', $ip));
			if (127 === $parts[0] || 10 === $parts[0] || 0 === $parts[0]
				|| (172 === $parts[0] && 16 <= $parts[1] && 31 >= $parts[1])
				|| (192 === $parts[0] && 168 === $parts[1])
			) {
				$r['reject_unsafe_urls'] = false;
			}

			return $r;
		}, 10, 2);
	}
}
