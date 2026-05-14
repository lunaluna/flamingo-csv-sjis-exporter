=== Flamingo CSV Shift_JIS Exporter ===
Contributors: lunaluna_dev
Tags: flamingo, csv, export, shift-jis, sjis, encoding
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flamingo の受信メッセージ CSV エクスポートを Shift_JIS（CP932 / SJIS-win）に変換します。Excel など Windows 環境での文字化けを防ぎます。

== Description ==

Contact Form 7 付属の [Flamingo](https://wordpress.org/plugins/flamingo/) が出力する受信メッセージの CSV は UTF-8 です。このプラグインは Flamingo の「受信メッセージ」画面から CSV をダウンロードするときに、UTF-8 を **Shift_JIS（Windows 拡張 CP932、PHP の SJIS-win）** に変換してから送信します。

主な特徴。

* Flamingo が有効なときのみ CSV 変換を行う。
* Flamingo が無効のときは管理画面にエラー通知を表示する。
* Flamingo が有効化されていない状態ではプラグインを有効化できない。
* Flamingo のバージョンが更新されたときに警告を表示し、動作確認を促す。「確認済みにする」で通知を消せる。
* プラグイン削除時に保存したバージョン情報オプションを削除する（`uninstall.php`）。

開発者向けメモ：プラグイン動作確認済みとしてコード内で宣言している Flamingo のバージョンは 2.6.2 です。Flamingo をアップデートしたあとは CSV 出力が問題ないか確認してください。

== Installation ==

1. **Flamingo** をインストールし、有効化する。
2. このプラグインのフォルダを `wp-content/plugins/` にアップロードする。
3. WordPress の「プラグイン」画面で **Flamingo CSV Shift_JIS Exporter** を有効化する。

Flamingo が先に有効化されていない場合、有効化は中断されます。

== Frequently Asked Questions ==

= Flamingo が必要ですか？ =

はい。Flamingo が無効だと CSV の変換は行われず、管理画面に通知が表示されます。

= どの画面で動きますか？ =

Flamingo の「受信メッセージ」（`flamingo-inbound`）で CSV エクスポート（`export` パラメータ付き）が行われたときのみです。

= 文字コードは何ですか？ =

UTF-8 から **SJIS-win（CP932）** に変換します。ダウンロード応答の `Content-Type` は `application/octet-stream; charset=Shift_JIS` に設定されます。

== Changelog ==

= 1.0.0 =
* 初版リリース。

== Upgrade Notice ==

= 1.0.0 =
初版です。
