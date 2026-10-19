<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

use Modules\Orders\Domain\FaxImport\FaxImportRules;

/**
 * The instructions of the legacy 受注書 import, unchanged in content: read every field of the faxed purchase order as
 * JSON of a fixed structure (only the fields that could be read), with the marks to look at closely (検印欄, the red
 * numbered fields, the handwritten date by フタ仕様, 手配数, the issuer), and the second request when 検印欄 gave no
 * stamp. The company names and stamp surnames come from FaxImportRules.
 */
final class FaxOrderPrompt
{
    public static function extraction(FaxImportRules $rules, string $readerCompany): string
    {
        $stamps = implode('・', $rules->stampNames());
        $issuer = $rules->issuer;

        return <<<PROMPT
            添付は日本の封筒製造会社（{$readerCompany}）が得意先から受け取った発注書のFAX画像です。
            この発注書に記載されている情報を**漏れなく**読み取り、下記スキーマのJSONとして返してください。

            ## 出力ルール
            - **JSONのみ**を返すこと。前置き・説明文・マークダウンのコードフェンスは一切不要。
            - 値はすべて文字列（配列項目を除く）。原則としてFAX上の表記をそのまま入れ、勝手に整形しない。
              例: 手配数は「12,000 枚」、納期は「2026/09/03(木) 直送・午前」のように単位や補足ごと入れる。
            - **読み取れない・欄が存在しない項目は、キーごと省略すること**（null と書かない）。
              配列項目も該当なしなら省略してよい。値のある項目だけを出力すること
              （出力量を減らすため。省略されたキーは受け取り側で「値なし」として扱う）。
            - 手書き・押印・朱記（赤ペンの丸囲みや下線）は印字と区別して該当欄に入れる。

            ## 特に注意して読む欄
            - **検印欄**: 発注担当者の姓が押印されている。この値から社内担当を特定するため、
              にじみ・かすれがあっても可能な限り姓を読み取ること。想定される姓は {$stamps} のいずれか。
              日付と姓が併記されている場合（例「26.8.18 古井（丸印）」）はそのまま両方入れる。
            - **赤丸番号付きデータ**: 発注書に朱記された丸数字①〜⑥に対応する欄。
              契印 / 印刷名 / 手配数 / 納期 / 加工伝票番号 / フタ仕様・折り返し寸法。
            - **フタ仕様_折り返し寸法**: フタの仕様と折り返し寸法に加えて、
              近傍の手書き文字と押印を必ず含めること（例「フタ開き／カド角、折り返し寸法 105mm、手書「8/6」「アバン便」」）。
              手書きの日付（例「8/6」「7/8」「7/2」）は持込日として使うため、鉤括弧つきで残すこと。
            - **手配数**: 受注数と予備数の合計が別記されている場合は合計値を先頭に置く
              （例「10,100 枚（受注 10,000 ＋ 予備 100）」）。
            - **発注情報.発行元**: 「発行元」という見出しの欄があるとは限らない。次の場所を必ず確認すること。
              - 右下または左下に**縦書き・小さめの文字**で印字された発行会社名（例「{$issuer}」
                「ハート封筒（株）大阪工場」）。工場名が併記されていても社名部分ごとそのまま入れる。
              - ページ最上部の、FAX受信時に機械が自動で付与した送信ヘッダー行
                （日時・FAX番号のあとに続く送信元名。例「FAX 072-994-8939　大阪工場」）。
              - どちらも読み取れる場合は、より正式名称に近い方（「株式会社」表記がある方）を優先する。
              かすれ・傾き・縦書きでも社名の文字自体が読めれば省略せずに入れること。

            ## スキーマ
            下記は**取りうる項目の一覧**であり、全項目を出力するという意味ではない。
            値が読み取れた項目だけを、この構造・この名前で出力すること。

            {
              "加工伝票番号": "string|null",
              "件名": "string|null",
              "赤丸番号付きデータ": {
                "契印": "string|null",
                "印刷名": "string|null",
                "手配数": "string|null",
                "納期": "string|null",
                "加工伝票番号": "string|null",
                "フタ仕様_折り返し寸法": "string|null"
              },
              "赤マーク": {
                "手書き枠囲み": "string|null",
                "大型押印": "string|null",
                "検印欄": "string|null",
                "発注担当者欄": "string|null",
                "発注日欄": "string|null",
                "単価欄": "string|null",
                "押印": "string|null",
                "手書き": "string|null",
                "備考欄": "string|null"
              },
              "ヘッダー情報": {
                "納品指定": "string|null", "帳票種別": "string|null", "宛先": "string|null",
                "注文区分": "string|null", "依頼日": "string|null", "所属": "string|null",
                "発行担当者": "string|null", "お得意様名": "string|null", "見積番号": "string|null",
                "業務見積番号": "string|null", "業務見積NO": "string|null", "上部欄外": "string|null"
              },
              "製品仕様": {
                "製品No": "string|null", "製品名": "string|null", "封筒区分": "string|null",
                "印刷名": "string|null", "製品コード": "string|null", "受注数": "string|null",
                "予備数": "string|null", "加工予備": "string|null", "手配数": "string|null",
                "使用用紙": "string|null", "色": "string|null", "連量": "string|null",
                "印刷機械": "string|null", "仕上寸法": "string|null", "マチ": "string|null",
                "型": "string|null", "貼方": "string|null"
              },
              "窓仕様": {
                "窓": [{ "番号": "窓①", "ヨコ": "string|null", "タテ": "string|null",
                         "左より": "string|null", "右より": "string|null", "底より": "string|null" }],
                "窓材料": "string|null", "窓種寸法": "string|null", "折り返し寸法": "string|null"
              },
              "製造版関連": {
                "ケース": "string|null", "色校正": "string|null", "形状原稿": "string|null",
                "色原稿": "string|null", "位置内容原稿": "string|null", "印刷版": "string|null",
                "版支給": "string|null", "版下番号": "string|null", "旧版番号": "string|null",
                "抜き方法": "string|null", "裏地紋": "string|null", "色数": "string|null"
              },
              "刷色": { "平判表": "string|null", "平判裏": "string|null" },
              "口糊加工": {
                "加工方法": "string|null", "フタ仕様": "string|null",
                "詳細": "string|null", "加工依頼数": "string|null"
              },
              "注意事項": ["string"],
              "手書き注記": {
                "枠囲み左": "string|null", "枠囲み右": "string|null",
                "押印": "string|null", "手書き": "string|null"
              },
              "クレーム履歴": "string|null",
              "配送先検印": {
                "検印": "string|null", "宛名ラベル貼方": "string|null", "郵便番号": "string|null",
                "住所": "string|null", "会社名": "string|null", "TEL": "string|null",
                "FAX": "string|null", "配送区分": "string|null"
              },
              "発注情報": {
                "発注日": "string|null", "発注担当者": "string|null", "納期": "string|null",
                "単価": "string|null", "納入場所": "string|null", "発行元": "string|null"
              },
              "支払条件": ["string"],
              "FAX送信情報": {
                "受信日時": "string|null", "FAX番号": "string|null",
                "送信元": "string|null", "宛先": "string|null", "ページ表記": "string|null"
              },
              "判読が不確実な箇所": ["string"]
            }
            PROMPT;
    }

    /** @param  list<string>  $candidates  configured surnames seen in other fields */
    public static function inspectionStampRetry(FaxImportRules $rules, array $candidates): string
    {
        $stamps = implode('・', $rules->stampNames());
        $seen = $candidates === [] ? '' : "\n- 他の欄（発注担当者欄・配送先検印など）には ".implode('・', $candidates).' が見えています。検印欄の印影がこれと同一かどうかを確かめてください。';

        return <<<PROMPT
            先ほどの抽出結果では、**検印欄**から発注担当者の姓を特定できませんでした。
            同じ画像をもう一度、検印欄を中心に精査してください。

            - 検印欄は発注書の右側にある押印枠です。日付印と姓の丸印が重なっていることがあります。
            - 期待される姓は {$stamps} のいずれかです。かすれ・にじみ・傾き・重ね押しを考慮して読み取ってください。
            - 検印欄が本当に空欄の場合は、発注担当者欄・発注日欄・配送先検印欄の印影も確認し、
              そこに姓があれば「検印欄」にその値を入れ、どの欄から読んだかを「判読が不確実な箇所」に明記してください。{$seen}

            前回と同じスキーマで、**JSONのみ**を返してください。
            値が読み取れた項目だけでよく、読み取れない項目はキーごと省略してください。
            PROMPT;
    }
}
