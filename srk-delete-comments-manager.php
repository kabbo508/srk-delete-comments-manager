<?php
/**
 * Plugin Name: SRK Delete Comments Manager
 * Plugin URI: https://sumonrahmankabbo.com/
 * Description: Interactive admin tool to view comment counts and safely delete WordPress comments in batches.
 * Version: 1.0.1
 * Author: Sumon Rahman Kabbo
 * Author URI: https://sumonrahmankabbo.com/
 * License: GPLv2 or later
 * Text Domain: srk-delete-comments
 */

if ( ! defined('ABSPATH') ) exit;

class SRK_Delete_Comments_Manager {

	public static function init() {
		add_action('admin_menu', [__CLASS__, 'menu']);
	}

	public static function menu() {
		add_menu_page(
			__('Delete Comments', 'srk-delete-comments'),
			__('Delete Comments', 'srk-delete-comments'),
			'manage_options',
			'srk-delete-comments',
			[__CLASS__, 'page'],
			'dashicons-trash',
			58
		);
	}

	private static function counts_map() {
		$c = wp_count_comments();
		return [
			'all'     => (int) $c->total_comments,
			'approve' => (int) $c->approved,
			'hold'    => (int) $c->moderated,
			'spam'    => (int) $c->spam,
			'trash'   => (int) $c->trash,
		];
	}

	public static function page() {
		if ( ! current_user_can('manage_options') ) {
			wp_die(esc_html__('You do not have permission to access this page.', 'srk-delete-comments'));
		}

		$batch_size = 300;
		$map = self::counts_map();

		$deleted = 0;
		$did_run = false;
		$status  = 'all';

		if ( isset($_POST['srk_run']) ) {
			check_admin_referer('srk_delete_comments_action');

			$did_run = true;
			$status = isset($_POST['srk_status']) ? sanitize_key($_POST['srk_status']) : 'all';
			if ( ! isset($map[$status]) ) $status = 'all';

			$args = [
				'number' => $batch_size,
				'fields' => 'ids',
			];
			if ( $status !== 'all' ) {
				$args['status'] = $status;
			}

			$ids = get_comments($args);

			if ( ! empty($ids) ) {
				foreach ( $ids as $id ) {
					wp_delete_comment((int) $id, true);
					$deleted++;
				}
			}

			$map = self::counts_map();
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Delete Comments Manager', 'srk-delete-comments') . '</h1>';

		if ( $did_run ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(sprintf(__('Batch complete. Deleted %d comments.', 'srk-delete-comments'), (int) $deleted));
			echo '</p></div>';
		}

		echo '<p><strong>' . esc_html__('Warning:', 'srk-delete-comments') . '</strong> ';
		echo esc_html__('This tool permanently deletes comments in batches. Consider taking a backup first.', 'srk-delete-comments');
		echo '</p>';

		echo '<h2>' . esc_html__('Comment Summary', 'srk-delete-comments') . '</h2>';
		echo '<table class="widefat striped" style="max-width:640px">';
		echo '<thead><tr><th>' . esc_html__('Status', 'srk-delete-comments') . '</th><th>' . esc_html__('Count', 'srk-delete-comments') . '</th></tr></thead><tbody>';

		$labels = [
			'all'     => __('Total', 'srk-delete-comments'),
			'approve' => __('Approved', 'srk-delete-comments'),
			'hold'    => __('Pending', 'srk-delete-comments'),
			'spam'    => __('Spam', 'srk-delete-comments'),
			'trash'   => __('Trash', 'srk-delete-comments'),
		];

		foreach ( $labels as $k => $label ) {
			echo '<tr><td><strong>' . esc_html($label) . '</strong></td><td>' . esc_html((int) $map[$k]) . '</td></tr>';
		}

		echo '</tbody></table>';

		$confirm = esc_js(__('This will permanently delete comments. Continue?', 'srk-delete-comments'));

		echo '<h2 style="margin-top:22px;">' . esc_html__('Delete in Batches', 'srk-delete-comments') . '</h2>';
		echo '<form method="post">';
		wp_nonce_field('srk_delete_comments_action');

		echo '<table class="form-table"><tbody><tr>';
		echo '<th scope="row"><label for="srk_status">' . esc_html__('Choose comments to delete', 'srk-delete-comments') . '</label></th>';
		echo '<td><select id="srk_status" name="srk_status">';

		foreach ( $labels as $k => $label ) {
			$count = (int) $map[$k];
			$selected = selected($status, $k, false);
			echo '<option value="' . esc_attr($k) . '" ' . $selected . '>' . esc_html($label . ' (' . $count . ')') . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html(sprintf(__('Deletes up to %d comments per click to avoid timeouts.', 'srk-delete-comments'), (int) $batch_size)) . '</p>';
		echo '</td></tr></tbody></table>';

		echo '<p>';
		echo '<button type="submit" class="button button-primary" name="srk_run" value="1" onclick="return confirm(\'' . $confirm . '\');">';
		echo esc_html__('Run one batch', 'srk-delete-comments');
		echo '</button>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}
}

SRK_Delete_Comments_Manager::init();
