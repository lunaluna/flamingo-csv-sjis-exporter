<?php
/**
 * Plugin Name:       Flamingo CSV Shift_JIS Exporter
 * Plugin URI:        https://github.com/lunaluna/flamingo-csv-sjis-exporter
 * Description:       Flamingo の受信メッセージ CSV 出力を Shift_JIS (CP932) に変換します.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Requires Plugins:  flamingo
 * Author:            lunaluna_dev
 * Author URI:        https://profiles.wordpress.org/lunaluna_dev/
 * Update URI:        false
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flamingo-csv-sjis-exporter
 *
 * @package FCSE
 */

declare( strict_types=1 );

namespace Flamingo_Sjis_Exporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp_options` に保存する「確認済みにする」で黙らせたバージョンのオプションキー.
 * 未 ack のサイトにはこのオプション自体が存在しない（値は '' として扱う）.
 * アンインストール時に uninstall.php から削除される.
 */
const OPTION_KEY = 'flamingo_sjis_acked_version';

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
 * インストール済みの Flamingo がこの値を超えたときだけ通知が出る.
 * この値を上げて対応を宣言すれば、通知は次のページ読み込みで自動的に消える
 * （既知バージョンのオプションを書き換える必要はない）.
 */
const TESTED_VERSION = '2.6.4';

// ---------------------------------------------------------------------------
// 自動更新機構の登録
//
// Flamingo が無効な状態でもプラグイン自身の更新はできなければならないため、
// Flamingo の有効化判定より前・無条件に登録する.
// ---------------------------------------------------------------------------

$flamingo_sjis_updater_register = require plugin_dir_path( __FILE__ ) . 'lib/l2d-updater/loader.php';
$flamingo_sjis_updater_register(
	array(
		'plugin_file' => __FILE__,
		'github_repo' => 'lunaluna/flamingo-csv-sjis-exporter',
		'cache_key'   => 'flamingo_sjis_github_release_cache',
	)
);

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
 *
 * 既知バージョンの保存はしない. OPTION_KEY への書き込みは
 * 「確認済みにする」操作（maybe_handle_ack_request()）だけが行う唯一の経路であり、
 * ここで書いてしまうと危険セル（$acked > $tested）を有効化のたびに作り出してしまう.
 */
function on_activation(): void {
	if ( ! is_flamingo_active() ) {
		wp_die(
			esc_html__( 'Flamingo CSV Shift_JIS Exporter を有効化するには Flamingo プラグインが必要です.先に Flamingo を有効化してください.', 'flamingo-csv-sjis-exporter' ),
			esc_html__( 'プラグインの有効化エラー', 'flamingo-csv-sjis-exporter' ),
			array( 'back_link' => true )
		);
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
	// Inbound のサブメニュー slug は load-flamingo_page_flamingo_inbound.
	add_action( 'load-flamingo_page_flamingo_inbound', __NAMESPACE__ . '\\maybe_start_buffer', 1 );
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
 * 1 リクエスト内で check_flamingo_version() と render_version_notice() の
 * 両方から呼ばれ得るため、静的変数で結果をメモ化して `get_plugin_data()` の
 * 二重実行を避ける. 関数内 static はローカル変数のため型宣言できないので、
 * 「解決済みフラグ + 値」の 2 変数に分けている.
 *
 * @return string|null バージョン文字列.取得できない場合は null.
 */
function get_flamingo_version(): ?string {
	static $resolved = false;
	static $cached   = null; // string|null. キャッシュされたバージョン文字列.

	if ( $resolved ) {
		return $cached;
	}

	$resolved = true;

	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = WP_PLUGIN_DIR . '/' . TARGET_PLUGIN;

	if ( ! file_exists( $plugin_file ) ) {
		return $cached;
	}

	$data    = get_plugin_data( $plugin_file, false, false );
	$version = $data['Version'];

	$cached = '' !== $version ? $version : null;

	return $cached;
}

/**
 * 管理者が「確認済みにする」で黙らせたバージョンを返す.
 *
 * `get_option()` の戻り値は `mixed` なので `is_string()` で正規化し、
 * `strict_types=1`（21行）下で should_notify_version() に渡したときの
 * TypeError を予防する型の門番.
 *
 * @return string 確認済みバージョン.未 ack の場合は ''.
 */
function get_acked_version(): string {
	$acked = get_option( OPTION_KEY, '' );

	return is_string( $acked ) ? $acked : '';
}

// ---------------------------------------------------------------------------
// バージョン変化の検知と通知
// ---------------------------------------------------------------------------

/**
 * 動作確認済みバージョンとの対比でバージョン通知を表示すべきかを判定する純関数.
 *
 * 通知条件（すべて満たすとき true）：
 * 1. $current が null でない（Flamingo が検出できている）
 * 2. $current が $tested より新しい（動作確認済みバージョンを超えている）
 * 3. $acked が空、または $current が $acked より新しい（未確認 or 確認済み版より進んでいる）
 *
 * $tested を上げて対応を宣言すれば、$acked を書き換えなくても条件2で自動的に false になる
 * （バージョン通知が消えないバグの根本原因は、この判定が $tested を見ていなかったこと）.
 *
 * WP 関数を一切呼ばない純関数にすることで、`wp eval` から実環境を汚さずに
 * 全状態パターンを検証できるようにしている（第3引数のデフォルト値はそのための後方互換）.
 *
 * @param string|null $current インストール済み Flamingo のバージョン. 検出不能なら null.
 * @param string      $acked   管理者が確認済みとして黙らせたバージョン. 未 ack は ''.
 * @param string      $tested  動作確認済みバージョン. 検証用に差し替え可能.
 * @return bool 通知すべきなら true.
 */
function should_notify_version( ?string $current, string $acked, string $tested = TESTED_VERSION ): bool {
	// version_compare() に null を渡すと PHPStan level 5 で型エラーになるため先に弾く.
	if ( null === $current ) {
		return false;
	}

	if ( ! version_compare( $current, $tested, '>' ) ) {
		return false;
	}

	// '' === $acked は version_compare( $current, '', '>' ) が真になるため厳密には冗長だが、
	// 「未 ack なら常に通知する」という意図を自己文書化するために明示的に残す.
	if ( '' === $acked ) {
		return true;
	}

	return version_compare( $current, $acked, '>' );
}

/**
 * 「確認済みにする」ボタン押下時の処理.
 *
 * Nonce 検証に成功し、かつ現時点でも通知条件を満たしている場合のみ
 * 現在の Flamingo バージョンを既知バージョンとして保存する（OPTION_KEY への
 * 唯一の書き込み経路）. 条件を満たさない場合（対象バージョンが既に ack 済み、
 * tested 以下に戻っている等）は何も書かずにリダイレクトのみ行う.
 *
 * `isset( $_GET[ NOTICE_ACTION ] )` と check_admin_referer() は同一関数・
 * 同一条件式に置くこと. 分割すると WPCS の nonce スニフが
 * 検証漏れとして誤検知する.
 */
function maybe_handle_ack_request(): void {
	if (
		! isset( $_GET[ NOTICE_ACTION ] ) ||
		! check_admin_referer( NOTICE_NONCE )
	) {
		return;
	}

	$current = get_flamingo_version();

	if ( should_notify_version( $current, get_acked_version() ) ) {
		// null !== $current は should_notify_version() が真である時点で保証される.
		update_option( OPTION_KEY, $current, false );
	}

	// クエリパラメータを除去してリダイレクトし、ブラウザの再送信を防ぐ.
	wp_safe_redirect( remove_query_arg( array( NOTICE_ACTION, '_wpnonce' ) ) );
	exit;
}

/**
 * `admin_init` タイミングで Flamingo のバージョン変化を検知する.
 *
 * 処理の流れ：
 * 1. 「確認済み」ボタン押下時は maybe_handle_ack_request() に委譲する.
 * 2. should_notify_version() が真なら admin_notices に通知を登録.
 *
 * `activate_plugins` 権限を持たないユーザーには何もしない.
 * 「CSV 出力の動作確認」は権限のないユーザーには行動不可能な指示であるため.
 * マルチサイトではサブサイトの管理者がこの権限を持たない場合がある.
 */
function check_flamingo_version(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	maybe_handle_ack_request();

	$current = get_flamingo_version();

	if ( should_notify_version( $current, get_acked_version() ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\render_version_notice' );
	}
}

/**
 * Flamingo のバージョン変化を知らせる警告通知を描画する.
 *
 * `notice-warning`（黄）で表示し、動作確認を促す.
 * DB の旧値（既知バージョン）には一切依存せず、動作確認済みバージョン
 * （TESTED_VERSION）とインストール済みバージョンとの対比のみで文面を組み立てる.
 * `admin_init` → `admin_notices` は同一リクエストなので $current の再取得で
 * 状態が変わる心配はない（guard は null チェックのみで足りる）.
 * 「確認済みにする」ボタンをクリックすると maybe_handle_ack_request() で
 * 確認済みバージョンが更新され、通知が非表示になる.
 */
function render_version_notice(): void {
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
			インストールされている Flamingo のバージョン <code><?php echo esc_html( $current ); ?></code> は、
			このプラグインが動作確認済みのバージョン <code><?php echo esc_html( TESTED_VERSION ); ?></code> より新しいものです.<br>
			受信メッセージの CSV 出力（Shift_JIS 変換）が正しく動作するか確認してください.
			問題がなければ「確認済みにする」で通知を消せます.
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
 * Flamingo の CSV エクスポート時に出力バッファを開始し、flush 時に Shift_JIS へ変換する.
 *
 * Flamingo は inbound 読み込みで $_GET['export'] があると CSV を echo して exit する.
 * そのため同一 load フック上で「export の後」に別コールバックを置いても exit により
 * 決して実行されない. ob_start() のハンドラなら exit 時のバッファ flush で必ず実行される.
 * 優先度 1 で開始し、flamingo_load_inbound_admin（既定 10）より先にバッファを掛ける.
 */
function maybe_start_buffer(): void {
	// Flamingo が export 用に使用する GET は本体側でも nonce を検証しない.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['export'] ) ) {
		return;
	}

	ob_start(
		static function ( string $buffer, int $phase ): string {
			unset( $phase );

			if ( ! headers_sent() ) {
				header_remove( 'Content-Type' );
				header( 'Content-Type: application/octet-stream; charset=Shift_JIS' );
			}

			if ( '' === $buffer ) {
				return '';
			}

			// UTF-8 → SJIS-win (CP932). Windows 向け CSV で一般的.
			return mb_convert_encoding( $buffer, 'SJIS-win', 'UTF-8' );
		},
		0,
		PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE
	);
}
