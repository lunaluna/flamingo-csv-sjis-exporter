<?php
/**
 * アンインストール処理.
 *
 * WordPress の管理画面からプラグインを「削除」した際にのみ呼び出される.
 * deactivate（無効化）では呼び出されない点に注意.
 *
 * このプラグインが wp_options に保存したデータを削除する.
 *
 * @package FCSE
 */

// WordPress のアンインストールフロー以外からの直接アクセスを防止する.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// バージョン管理に使用したオプションを削除する.
// 定数 OPTION_KEY はこのファイルのスコープでは使用できないため文字列リテラルで指定する.
delete_option( 'flamingo_sjis_known_version' );
