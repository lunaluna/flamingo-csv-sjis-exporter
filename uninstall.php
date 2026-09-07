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

// バージョン確認済み操作(ack)で保存したオプションを削除する.
// 定数 OPTION_KEY はこのファイルのスコープでは使用できないため文字列リテラルで指定する.
delete_option( 'flamingo_sjis_acked_version' );

// レガシー掃除: 1.2.1 より前に使用していた旧キー. 新規インストールでは作成されないが、
// 既存サイトのアンインストール時に残留させないため削除を継続する.
delete_option( 'flamingo_sjis_known_version' );

// 自動更新機構が保存する GitHub Release のキャッシュを削除する.
delete_site_transient( 'flamingo_sjis_github_release_cache' );
