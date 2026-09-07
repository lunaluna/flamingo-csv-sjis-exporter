# Flamingo CSV Shift_JIS Exporter

WordPress プラグインです。[Flamingo](https://wordpress.org/plugins/flamingo/) の受信メッセージ CSV エクスポートを **Shift_JIS（CP932 / SJIS-win）** に変換し、Excel など Windows 環境での文字化けを防ぎます。

- **Requires WordPress:** 6.0+（7.1 で動作確認済み）
- **Requires PHP:** 7.4+
- **Stable tag:** 1.2.1
- **License:** GPLv2 or later

## 動作条件

- [Flamingo](https://wordpress.org/plugins/flamingo/) が **有効**であること（依存プラグイン）。
- Flamingo が無効のときは、このプラグインを有効化できません。また管理画面にエラー通知が表示されます。

## インストール

1. Flamingo をインストールして有効化する。
2. このプラグインを `wp-content/plugins/` に配置する。
3. 管理画面の「プラグイン」から有効化する。

初回リリース（1.2.0）以降は GitHub Releases から自動アップデート機構が有効になり、通常のプラグイン更新フロー（更新通知 → ワンクリック更新）でこのプラグイン自身を更新できます。

## 使い方

Flamingo の **受信メッセージ** 画面から従来どおり CSV をエクスポートします。プラグインが有効な場合、ダウンロードされる CSV は UTF-8 ではなく **SJIS-win に変換されたバイト列** になります。HTTP ヘッダーの `charset` も `Shift_JIS` に合わせて設定されます。

技術的には、受信メッセージ画面の読み込みフック `load-flamingo_page_flamingo_inbound` で `$_GET['export']` を検知したときに `ob_start()` を掛け、Flamingo が出力する CSV をバッファに溜めます。Flamingo は出力直後に `exit()` するため、同一フック上で優先度の遅いコールバックは実行されません。バッファの flush 時に動く **`ob_start()` のコールバック** で `mb_convert_encoding()` により UTF-8 → SJIS-win に変換してから送信します。

## Flamingo のバージョンについて

このプラグインはコード内で「動作確認済みの Flamingo バージョン」（`TESTED_VERSION`、現在 **2.6.4**）を宣言しています。管理画面には **`activate_plugins` 権限を持つユーザー**にのみ、インストール済みの Flamingo がこの動作確認済みバージョンより新しいときに警告が表示されます。

- プラグイン側が `TESTED_VERSION` を上げて対応を宣言すると、**次のページ読み込みで警告は自動的に消えます**（「確認済みにする」を押す必要はありません）。
- 対応が宣言される前でも、サイト管理者が自己判断で個別に警告を黙らせたい場合は「確認済みにする」を押してください。押した時点のバージョンまでは再表示されず、さらに新しい Flamingo が入れば再び警告が出ます。

## アンインストール

プラグインを「削除」すると `uninstall.php` が実行され、`wp_options` の `flamingo_sjis_acked_version`（「確認済みにする」操作で保存される値。未 ack のサイトでは作成されない）が削除されます。1.2.1 より前に使用していた旧キー `flamingo_sjis_known_version` も、既存サイトの残留を防ぐためあわせて削除されます。無効化だけでは削除されません。

## リンク

- **Plugin URI:** <https://github.com/lunaluna/flamingo-csv-sjis-exporter/>
- **Author:** [lunaluna_dev](https://profiles.wordpress.org/lunaluna_dev/)

## 変更履歴

### 1.2.1

- バージョン通知が「動作確認済みバージョン（`TESTED_VERSION`）を宣言し直しても消えない」バグを修正した。通知条件を DB の旧値ではなく `TESTED_VERSION` との対比に変更し、対応宣言だけで次のページ読み込みから通知が自動的に消えるようにした。
- 通知の表示と「確認済みにする」操作を `activate_plugins` 権限を持つユーザーに限定した。
- オプションキーを `flamingo_sjis_known_version` から `flamingo_sjis_acked_version` に改名した（値の移行はしない。既存の ack 状態は失われるため再度「確認済みにする」を押す必要がある）。
- プラグイン有効化時の既知バージョン自動保存を廃止し、`wp_options` への書き込みを「確認済みにする」操作のみに限定した。

### 1.2.0

- GitHub Releases からの自動アップデート機構（`lunaluna/l2d-wp-github-update-lib`）を同梱し、管理画面から通常の更新フロー（通知 → ワンクリック更新）が使えるようにした。
- WordPress 7.1 で動作確認し、`Tested up to: 7.1` を宣言した。
- 動作確認済み Flamingo バージョンを **2.6.4** に更新した。

### 1.1.0

- 受信メッセージ一覧用の管理画面フックを **`load-flamingo_page_flamingo_inbound`** に修正した（以前は `load-flamingo_page_flamingo-inbound` と誤っており、変換処理が実行されなかった）。
- Flamingo が CSV 出力後に `exit()` するため、後段の優先度でバッファを閉じる方式では変換処理に到達しなかった問題を、`ob_start()` の出力ハンドラで変換することで修正した。

### 1.0.0

- 初版。
