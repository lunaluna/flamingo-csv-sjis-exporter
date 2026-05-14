<?php
/**
 * Plugin Name:       Flamingo CSV Shift_JIS Exporter
 * Plugin URI:        https://github.com/lunaluna/flamingo-csv-sjis-exporter.php
 * Description:       Flamingo の受信メッセージ CSV 出力を Shift_JIS (CP932) に変換します.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            lunaluna_dev
 * Author URI:        https://profiles.wordpress.org/lunaluna_dev/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flamingo-csv-sjis-exporter.php
 * Domain Path:       /languages
 *
 * @package FCSE
 */

declare( strict_types=1 );

namespace Flamingo_Sjis_Exporter;

/**
 * `wp_options` に保存する既知バージョンのオプションキー.
 * アンインストール時に uninstall.php から削除される.
 */
const OPTION_KEY = 'flamingo_sjis_known_version';

/**
 * バージョン変化通知の「確認済み」ボタンで使用する nonce アクション名.
 */
const NOTICE_NONCE = 'flamingo_sjis_ack_version';

/**
 * 「確認済み」ボタンのクリックを識別するクエリパラメータ名.
 */
const NOTICE_ACTION = 'flamingo_sjis_ack_version';

/**
 * 依存する Flamingo プラグインのファイルパス（wp-content/plugins/ 以下の相対パス）.
 */
const TARGET_PLUGIN = 'flamingo/flamingo.php';

/**
 * このプラグインが動作確認済みの Flamingo バージョン.
 * Flamingo のアップデート後に動作確認が取れたタイミングで手動更新する.
 */
const TESTED_VERSION = '2.6.2';

// ---------------------------------------------------------------------------
// Flamingo 有効化チェックユーティリティ
// ---------------------------------------------------------------------------

/**
 * Flamingo が有効化されているかどうかを返す.
 *
 * `is_plugin_active()` は管理画面以外では読み込まれないため、
 * 未読み込みの場合は plugin.php を require して補完する.
 *
 * @return bool Flamingo が有効化されていれば true.
 */
function is_flamingo_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( TARGET_PLUGIN );
}

// ---------------------------------------------------------------------------
// 有効化フック：Flamingo が無効なら有効化を中断
// ---------------------------------------------------------------------------

/**
 * プラグイン有効化時の処理.
 *
 * Flamingo が有効化されていない場合は wp_die() で有効化を中断する.
 * Flamingo が有効な場合はその時点のバージョンを wp_options に保存し、
 * 以降のバージョン変化検知の基準値とする.
 */
function on_activation(): void {
	if ( ! is_flamingo_active() ) {
		wp_die(
			esc_html__( 'Flamingo CSV Shift_JIS Exporter を有効化するには Flamingo プラグインが必要です.先に Flamingo を有効化してください.' ),
			esc_html__( 'プラグインの有効化エラー' ),
			array( 'back_link' => true )
		);
	}

	$version = get_flamingo_version();

	if ( null !== $version ) {
		update_option( OPTION_KEY, $version, false );
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\on_activation' );

// ---------------------------------------------------------------------------
// 管理画面：Flamingo 未有効化の通知
// ---------------------------------------------------------------------------

/**
 * Flamingo が無効化されている場合のエラー通知を描画する.
 *
 * `notice-error`（赤）で表示し、管理者に Flamingo の有効化を促す.
 */
function render_flamingo_inactive_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<strong>[Flamingo CSV Shift_JIS Exporter]</strong>
			このプラグインの動作には <strong>Flamingo</strong> が必要です.
			Flamingo を有効化してください.
		</p>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// plugins_loaded：Flamingo の状態に応じて各処理を条件付きで登録
// ---------------------------------------------------------------------------

/**
 * `plugins_loaded` タイミングで Flamingo の有効化状態を確認し、
 * 有効な場合のみ各フックを登録する.
 *
 * `plugins_loaded` はすべてのプラグインが読み込まれた後に発火するため、
 * ここで is_plugin_active() を呼ぶことで確実に Flamingo の状態を判定できる.
 *
 * Flamingo が無効な場合は admin_notices にエラー通知のみ登録し、
 * CSV 変換処理やバージョン検知は一切登録しない.
 */
function init(): void {
	if ( ! is_flamingo_active() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\render_flamingo_inactive_notice' );
		return;
	}

	// Flamingo が有効な場合のみ各処理を登録する.
	add_action( 'admin_init', __NAMESPACE__ . '\\check_flamingo_version' );
	add_action( 'load-flamingo_page_flamingo-inbound', __NAMESPACE__ . '\\maybe_start_buffer', 1 );
	add_action( 'load-flamingo_page_flamingo-inbound', __NAMESPACE__ . '\\convert_and_flush', 9999 );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

// ---------------------------------------------------------------------------
// バージョン取得ユーティリティ
// ---------------------------------------------------------------------------

/**
 * 現在インストールされている Flamingo のバージョン文字列を返す.
 *
 * `get_plugin_data()` はヘッダーコメントからバージョンを読み取る.
 * Flamingo のファイルが存在しない場合は null を返す.
 *
 * @return string|null バージョン文字列.取得できない場合は null.
 */
function get_flamingo_version(): ?string {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = WP_PLUGIN_DIR . '/' . TARGET_PLUGIN;

	if ( ! file_exists( $plugin_file ) ) {
		return null;
	}

	$data = get_plugin_data( $plugin_file, false, false );

	return $data['Version'] ?? null;
}

// ---------------------------------------------------------------------------
// バージョン変化の検知と通知
// ---------------------------------------------------------------------------

/**
 * `admin_init` タイミングで Flamingo のバージョン変化を検知する.
 *
 * 処理の流れ：
 * 1. 「確認済み」ボタン押下時は nonce を検証し、既知バージョンを現在値に更新してリダイレクト.
 * 2. 既知バージョンが未保存（初回）の場合は現在値を保存して終了.
 * 3. 既知バージョンより現在のバージョンが新しければ admin_notices に通知を登録.
 */
function check_flamingo_version(): void {
	// 「確認済み」ボタン押下時の処理.
	if (
		isset( $_GET[ NOTICE_ACTION ] ) &&
		check_admin_referer( NOTICE_NONCE )
	) {
		$current = get_flamingo_version();

		if ( null !== $current ) {
			// 現在のバージョンを既知バージョンとして上書き保存する.
			update_option( OPTION_KEY, $current, false );
		}

		// クエリパラメータを除去してリダイレクトし、ブラウザの再送信を防ぐ.
		wp_safe_redirect( remove_query_arg( array( NOTICE_ACTION, '_wpnonce' ) ) );
		exit;
	}

	$known   = get_option( OPTION_KEY, '' );
	$current = get_flamingo_version();

	if ( null === $current ) {
		return;
	}

	if ( '' === $known ) {
		// 有効化フックが走らなかったケース（手動ファイル配置など）への対応.
		// 初回アクセス時に現在のバージョンを保存して以降の比較基準とする.
		update_option( OPTION_KEY, $current, false );
		return;
	}

	// 保存済みバージョンより新しいバージョンが検出された場合のみ通知を登録する.
	if ( version_compare( $current, $known, '>' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\render_version_notice' );
	}
}

/**
 * Flamingo のバージョン変化を知らせる警告通知を描画する.
 *
 * `notice-warning`（黄）で表示し、動作確認を促す.
 * 「確認済みにする」ボタンをクリックすると check_flamingo_version() 内で
 * 既知バージョンが更新され、通知が非表示になる.
 */
function render_version_notice(): void {
	$known   = get_option( OPTION_KEY, '' );
	$current = get_flamingo_version();

	if ( null === $current ) {
		return;
	}

	// nonce 付きの「確認済み」URL を生成する.
	$ack_url = wp_nonce_url(
		add_query_arg( NOTICE_ACTION, '1' ),
		NOTICE_NONCE
	);
	?>
	<div class="notice notice-warning">
		<p>
			<strong>[Flamingo CSV Shift_JIS Exporter]</strong>
			Flamingo がバージョン <code><?php echo esc_html( $known ); ?></code> から
			<code><?php echo esc_html( $current ); ?></code> にアップデートされました.<br>
			このプラグインの動作確認済みバージョンは <code><?php echo esc_html( TESTED_VERSION ); ?></code> です.
			CSV 出力の動作に問題がないか確認してください.
		</p>
		<p>
			<a href="<?php echo esc_url( $ack_url ); ?>" class="button button-secondary">
				確認済みにする（通知を消す）
			</a>
		</p>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// CSV 出力を Shift_JIS (CP932) に変換
// ---------------------------------------------------------------------------

/**
 * Flamingo の CSV エクスポートリクエスト時に出力バッファを開始する.
 *
 * Flamingo のエクスポート処理は admin.php 内で $_GET['export'] の有無を
 * チェックして直接 echo するため、ob_start() でバッファリングしてキャプチャする.
 * 優先度 1（最優先）で登録することで、Flamingo の出力より先にバッファを開始する.
 */
function maybe_start_buffer(): void {
	// Flamingo が export 用に使用する GET は本体側でも nonce を検証しない.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['export'] ) ) {
		return;
	}

	ob_start();
}

/**
 * バッファにキャプチャした CSV を UTF-8 から Shift_JIS (CP932) に変換して出力する.
 *
 * SJIS-win（CP932）は Windows の拡張 Shift_JIS であり、
 * 純粋な SJIS より対応文字数が多く、Windows 環境向けの出力に適している.
 * 変換後に Content-Type ヘッダーを上書きし、exit でレスポンスを終了する.
 * 優先度 9999（最後）で登録することで、Flamingo の出力がすべてバッファに
 * 書き込まれた後に変換処理を行う.
 */
function convert_and_flush(): void {
	// Flamingo が export 用に使用する GET は本体側でも nonce を検証しない.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['export'] ) ) {
		return;
	}

	$output = ob_get_clean();

	if ( false === $output || '' === $output ) {
		return;
	}

	// Content-Type の charset を Shift_JIS に上書きする.
	header( 'Content-Type: application/octet-stream; charset=Shift_JIS' );

	// UTF-8 → SJIS-win (CP932) に変換して出力する.
	// SJIS-win は Windows 拡張の Shift_JIS（CP932）で、純粋な SJIS より対応文字が多い.
	echo mb_convert_encoding( $output, 'SJIS-win', 'UTF-8' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
