=== Flamingo CSV Shift_JIS Exporter ===
Contributors: lunaluna_dev
Tags: flamingo, csv, export, shift-jis, sjis, encoding
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.3.0
Requires PHP: 7.4
Requires Plugins: flamingo
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flamingo の受信メッセージ CSV エクスポートを Shift_JIS（CP932 / SJIS-win）に変換します。Excel など Windows 環境での文字化けを防ぎます。

== Description ==

Contact Form 7 付属の [Flamingo](https://wordpress.org/plugins/flamingo/) が出力する受信メッセージの CSV は UTF-8 です。このプラグインは Flamingo の「受信メッセージ」画面から CSV をダウンロードするときに、UTF-8 を **Shift_JIS（Windows 拡張 CP932、PHP の SJIS-win）** に変換してから送信します。

受信メッセージ一覧の読み込みフック（`load-flamingo_page_flamingo_inbound`）で `export` が付いたリクエストのときのみ `ob_start()` を開始し、Flamingo が `exit()` するまでバッファにためたあと、`ob_start()` のコールバック内で `mb_convert_encoding()` により SJIS-win に変換します（Flamingo は出力直後に `exit()` するため、同一フック上の「末尾の別コールバック」では処理できません）。

主な特徴。

* Flamingo が有効なときのみ CSV 変換を行う。
* Flamingo が無効のときは管理画面にエラー通知を表示する。
* Flamingo が有効化されていない状態ではプラグインを有効化できない。
* 動作確認済みバージョンより新しい Flamingo が入っているときに警告を表示し、プラグイン側が対応を宣言する（`TESTED_VERSION` を上げる）と自動的に消える。手動で黙らせる「確認済みにする」もある。
* プラグイン削除時に保存した確認済みバージョンオプションを削除する（`uninstall.php`）。

開発者向けメモ：プラグイン動作確認済みとしてコード内で宣言している Flamingo のバージョン（`TESTED_VERSION`、現在 2.6.4）を Flamingo の実バージョンが超えたときだけ警告が出ます。この値を上げて対応を宣言すれば、警告は次のページ読み込みで自動的に消えます。

== Installation ==

1. **Flamingo** をインストールし、有効化する。
2. このプラグインのフォルダを `wp-content/plugins/` にアップロードする。
3. WordPress の「プラグイン」画面で **Flamingo CSV Shift_JIS Exporter** を有効化する。

Flamingo が先に有効化されていない場合、有効化は中断されます。

== Frequently Asked Questions ==

= Flamingo が必要ですか？ =

はい。Flamingo が無効だと CSV の変換は行われず、管理画面に通知が表示されます。

= どの画面で動きますか？ =

Flamingo の「受信メッセージ」画面（管理画面では `page=flamingo_inbound`）から CSV をダウンロードするリクエスト（`export` クエリがある場合）のみです。

= 文字コードは何ですか？ =

UTF-8 から **SJIS-win（CP932）** に変換します。ダウンロード応答の `Content-Type` は `application/octet-stream; charset=Shift_JIS` に設定されます。

= 動作確認済みバージョンより新しい Flamingo を使うとどうなりますか？ =

管理画面（`activate_plugins` 権限を持つユーザーのみ）に警告が表示されます。CSV 出力（Shift_JIS 変換）が正しく動作するか確認してください。問題がなければ「確認済みにする」で警告を消せます。またプラグイン側が動作確認を完了して対応バージョンを引き上げた場合は、操作不要で警告が自動的に消えます。

== Changelog ==

= 1.3.0 =
* 同梱の自動更新ライブラリを dist-1.2.0 に更新（利用者から見た機能変化はなし）。

= 1.2.1 =
* バージョン通知が動作確認済みバージョン（TESTED_VERSION）を宣言し直しても消えないバグを修正。通知条件を DB の旧値ではなく TESTED_VERSION との対比に変更し、対応宣言だけで自動的に消えるようにした。
* 通知の表示と「確認済みにする」操作を activate_plugins 権限を持つユーザーに限定。
* 保存したバージョン情報オプションのキーを flamingo_sjis_acked_version に改名（値の移行なし。既存の確認済み状態は失われる）。
* プラグイン有効化時のバージョン自動保存を廃止し、書き込みを「確認済みにする」操作のみに限定。

= 1.2.0 =
* GitHub Releases からの自動アップデート機構を追加。管理画面から通常の更新フロー（通知 → ワンクリック更新）が使えるようになりました。
* WordPress 7.1 で動作確認済み（Tested up to を更新）。
* プラグイン動作確認済み Flamingo バージョンを 2.6.4 に更新。

= 1.1.0 =
* 管理画面フックを `load-flamingo_page_flamingo_inbound` に修正（これまでハイフン付きフックとなっており、変換が実行されていなかった）。
* Flamingo が CSV 出力後に `exit()` するため、同一フックの後段での変換処理に到達しなかった問題を、`ob_start()` の出力ハンドラで変換する方式に変更。

= 1.0.0 =
* 初版リリース。

== Upgrade Notice ==

= 1.3.0 =
同梱の自動更新ライブラリを更新しました。利用者から見た機能変化はありません。

= 1.2.1 =
バージョン通知が消えないバグの修正です。既存の「確認済みにする」状態はリセットされるため、通知が出た場合は再度クリックしてください。

= 1.2.0 =
GitHub Releases からの自動アップデートに対応し、WordPress 7.1 で動作確認しました。

= 1.1.0 =
受信メッセージ CSV の Shift_JIS 変換が正しく適用されるよう、フック名と出力バッファの扱いを修正しました。

= 1.0.0 =
初版です。
