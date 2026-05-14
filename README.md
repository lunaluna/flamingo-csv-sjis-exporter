# Flamingo CSV Shift_JIS Exporter

WordPress プラグインです。[Flamingo](https://wordpress.org/plugins/flamingo/) の受信メッセージ CSV エクスポートを **Shift_JIS（CP932 / SJIS-win）** に変換し、Excel など Windows 環境での文字化けを防ぎます。

- **Requires WordPress:** 6.0+
- **Requires PHP:** 7.4+
- **License:** GPLv2 or later

## 動作条件

- [Flamingo](https://wordpress.org/plugins/flamingo/) が **有効**であること（依存プラグイン）。
- Flamingo が無効のときは、このプラグインを有効化できません。また管理画面にエラー通知が表示されます。

## インストール

1. Flamingo をインストールして有効化する。
2. このプラグインを `wp-content/plugins/` に配置する。
3. 管理画面の「プラグイン」から有効化する。

## 使い方

Flamingo の **受信メッセージ** 画面から従来どおり CSV をエクスポートします。プラグインが有効な場合、ダウンロードされる CSV は UTF-8 ではなく **SJIS-win に変換されたバイト列** になります。HTTP ヘッダーの `charset` も `Shift_JIS` に合わせて設定されます。

技術的には、Flamingo が `export` リクエストで出力する CSV を出力バッファで捕捉し、`mb_convert_encoding()` で UTF-8 → SJIS-win に変換してから返しています。

## Flamingo のバージョンについて

Flamingo がアップデートされると、管理画面に **動作確認を促す警告** が出ることがあります。問題なければ「確認済みにする」で通知を消せます。

コード内では、このプラグインの動作確認済み Flamingo バージョンとして **2.6.2** が宣言されています（`TESTED_VERSION`）。Flamingo を大きく更新したあとは、CSV が期待どおりか確認することをおすすめします。

## アンインストール

プラグインを「削除」すると `uninstall.php` が実行され、`wp_options` の `flamingo_sjis_known_version` が削除されます。無効化だけでは削除されません。

## リンク

- **Plugin URI:** <https://github.com/lunaluna/flamingo-csv-sjis-exporter/>
- **Author:** [lunaluna_dev](https://profiles.wordpress.org/lunaluna_dev/)

## 変更履歴

### 1.0.0

- 初版。
