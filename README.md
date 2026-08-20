# Flamingo CSV Shift_JIS Exporter

WordPress プラグインです。[Flamingo](https://wordpress.org/plugins/flamingo/) の受信メッセージ CSV エクスポートを **Shift_JIS（CP932 / SJIS-win）** に変換し、Excel など Windows 環境での文字化けを防ぎます。

- **Requires WordPress:** 6.0+（7.1 で動作確認済み）
- **Requires PHP:** 7.4+
- **Stable tag:** 1.2.0
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

Flamingo がアップデートされると、管理画面に **動作確認を促す警告** が出ることがあります。問題なければ「確認済みにする」で通知を消せます。

コード内では、このプラグインの動作確認済み Flamingo バージョンとして **2.6.4** が宣言されています（`TESTED_VERSION`）。Flamingo を大きく更新したあとは、CSV が期待どおりか確認することをおすすめします。

## アンインストール

プラグインを「削除」すると `uninstall.php` が実行され、`wp_options` の `flamingo_sjis_known_version` が削除されます。無効化だけでは削除されません。

## リンク

- **Plugin URI:** <https://github.com/lunaluna/flamingo-csv-sjis-exporter/>
- **Author:** [lunaluna_dev](https://profiles.wordpress.org/lunaluna_dev/)

## 変更履歴

### 1.2.0

- GitHub Releases からの自動アップデート機構（`lunaluna/l2d-wp-github-update-lib`）を同梱し、管理画面から通常の更新フロー（通知 → ワンクリック更新）が使えるようにした。
- WordPress 7.1 で動作確認し、`Tested up to: 7.1` を宣言した。
- 動作確認済み Flamingo バージョンを **2.6.4** に更新した。

### 1.1.0

- 受信メッセージ一覧用の管理画面フックを **`load-flamingo_page_flamingo_inbound`** に修正した（以前は `load-flamingo_page_flamingo-inbound` と誤っており、変換処理が実行されなかった）。
- Flamingo が CSV 出力後に `exit()` するため、後段の優先度でバッファを閉じる方式では変換処理に到達しなかった問題を、`ob_start()` の出力ハンドラで変換することで修正した。

### 1.0.0

- 初版。
