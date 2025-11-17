<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Illuminate\Support\Facades\DB;

class AppnexuscuratedlistManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?string $deal_name = null,
        public ?int $is_curated = null,
        public ?string $broker_member_name = null,
    ) {}

    public static $mapping = [
        'deal_name' => 'DealName',
        'is_curated' => 'IsCurated',
        'broker_member_name' => 'BrokerMemberName',
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result = collect();
        foreach ($rows as $riga) {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            if(intval(trim($assoc['deal_id']))!=0){
                $result->push(new self(
                    deal_name: ((((int) $assoc['curator_cleared'])>0)?"(CURATED)":"").trim($assoc['deal_name']) . " (".trim($assoc['deal_id']).")",
                    is_curated: ((int) $assoc['curator_cleared'])>0,
                    broker_member_name: str_replace("(CURATOR)", "", $assoc['broker_member_name']),
                ));
            }

        }

        return $result;
    }

    public function writeToDB($datas, $dtoClass, $report_code='')
    {

        foreach($datas as $r){
            $params=[
                    'deal_name' => $r->deal_name,
                    'is_curated' => $r->is_curated,
                    'broker_member_name' => $r->broker_member_name,
                    'update_is_curated' => $r->is_curated,

                ];
            $sql="INSERT INTO ssp_appnexus_curated_list (deal_name, is_curated, broker_member_name)
                            VALUES (:deal_name, :is_curated, :broker_member_name)
                            ON DUPLICATE KEY UPDATE is_curated = :update_is_curated"; //, dati,transaction: t);
            DB::connection('alternate')->select($sql, $params);

        }
        return count($datas);
    }

    public function getDelimiter(): string
    {
        return "broker_member_name"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_appnexus_curated_list";
    }

    public function getCsvDelimiter(): string
    {
        return ","; // Placeholder
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
