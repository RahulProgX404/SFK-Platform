<?php

declare(strict_types=1);

namespace Modules\Orders\Application\FaxImport;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Domain\FaxImport\FaxImportRules;
use Modules\Orders\Domain\FaxImport\FaxOrderMapper;

/**
 * 受注書を読み込む (legacy 「ハート封筒受注書を読み込む」 on the 04. 受注入力 create screen): reads the uploaded purchase
 * order, accepts only those of the configured issuer, finds the stamp (検印欄; else the one surname seen in another
 * field; else a second reading of 検印欄) and maps the reading (FaxOrderMapper). The issuer and 仕入先(製造場所) are
 * then looked up in 01. 取引先マスタ (customers / その他, resp. suppliers) by name, as the legacy lookup did.
 */
final class FaxOrderImport
{
    public function __construct(private readonly FaxOrderReader $reader) {}

    /** @throws FaxImportFailed */
    public function import(FaxDocument $document, FaxImportRules $rules, DateTimeImmutable $today): FaxImportResult
    {
        $reading = $this->reader->read($document);

        $issuer = FaxOrderMapper::issuer($reading);
        if (! FaxOrderMapper::isFromIssuer($issuer, $rules)) {
            throw new FaxImportFailed("このツールは発行元が「{$rules->issuer}」の発注書の読み込み専用です。"
                .($issuer !== '' ? "読み取った発行元は「{$issuer}」でした。" : '発行元を読み取れませんでした。')
                .'対象の発注書か確認のうえ、再度お試しください。');
        }

        $stamp = FaxOrderMapper::inspectionStamp($reading, $rules);
        $source = '検印欄';

        if ($stamp === null) {
            $candidates = FaxOrderMapper::stampCandidates($reading, $rules);
            if (count($candidates) === 1) {
                $stamp = (string) array_key_first($candidates);
                $source = $candidates[$stamp];
            } else {
                $reading = $this->reader->rereadInspectionStamp($document, $reading, array_keys($candidates));
                $stamp = FaxOrderMapper::inspectionStamp($reading, $rules);
                $source = '検印欄（再解析）';
            }
        }

        if ($stamp === null) {
            throw new FaxImportFailed('検印欄から得意先（'.implode('・', $rules->stampNames()).'）を特定できませんでした。'
                .'画像が鮮明かどうか確認のうえ、再度お試しください。');
        }

        $mapping = FaxOrderMapper::map($reading, $stamp, $rules, $today);
        $values = $mapping->values;
        $fields = $mapping->fields;

        $customer = $this->customer($values['customer_name']);
        $manufacturer = $this->supplier($values['manufacturer_name']);
        unset($values['customer_name'], $values['manufacturer_name']);
        $values['customer_id'] = $customer?->id;
        $values['manufacturer_id'] = $manufacturer?->id;

        foreach ($fields as $index => $field) {
            if ($field['field'] === 'customer_name' && $values['customer_id'] === null && $field['value'] !== '') {
                $fields[$index]['warning'] = '取引先マスタ（得意先・その他）に見つかりません';
            }
            if ($field['field'] === 'manufacturer_name' && $values['manufacturer_id'] === null) {
                $fields[$index]['warning'] = '取引先マスタ（仕入先）に見つかりません';
            }
        }

        $uncertain = array_values(array_filter((array) ($reading['判読が不確実な箇所'] ?? []), 'is_string'));

        return new FaxImportResult(
            $document->fileName,
            $stamp,
            $source,
            array_filter($values, fn (mixed $value): bool => $value !== null && $value !== ''),
            $fields,
            $mapping->errors,
            $uncertain,
            $reading,
        );
    }

    /** 得意先(受注先) by name: the same name, else the longest name the read one starts with. */
    private function customer(string $name): ?Partner
    {
        return $this->partner($name, fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('is_customer', true)->orWhere('is_other', true)));
    }

    private function supplier(string $name): ?Partner
    {
        return $this->partner($name, fn (Builder $query) => $query->where('is_supplier', true));
    }

    /** @param  callable(Builder<Partner>): mixed  $scope */
    private function partner(string $name, callable $scope): ?Partner
    {
        $key = FaxOrderMapper::companyKey($name);
        if ($key === '') {
            return null;
        }

        $query = Partner::query();
        $scope($query);

        $best = null;
        $bestLength = 0;
        foreach ($query->get(['id', 'name']) as $partner) {
            $partnerKey = FaxOrderMapper::companyKey($partner->name);
            if ($partnerKey === '' || ! str_starts_with($key, $partnerKey)) {
                continue;
            }
            if ($partnerKey === $key) {
                return $partner;
            }
            if (mb_strlen($partnerKey) > $bestLength) {
                [$best, $bestLength] = [$partner, mb_strlen($partnerKey)];
            }
        }

        return $best;
    }
}
