<?php

namespace App\Console\Commands;

use App\Services\DataCache\CreateDealsCache;
use App\Services\DataCache\CreateDiretteCache;
use App\Services\Importers\Ssp\AdformadunitsManager;
use App\Services\Importers\Ssp\AmazonManager;
use App\Services\Importers\Ssp\AniviewmarketplaceManager;
use App\Services\Importers\Ssp\BridManager;
use App\Services\Importers\Ssp\ConfiantManager;
use App\Services\Importers\Ssp\ConnectadManager;
use App\Services\Importers\Ssp\CriteoManager;
use App\Services\Importers\Ssp\EbdaManager;
use App\Services\Importers\Ssp\EvolutionManager;
use App\Services\Importers\Ssp\GoogledealsManager;
use App\Services\Importers\Ssp\GooglehistoricalManager;
use App\Services\Importers\Ssp\GoogleimpressionsdetailsManager;
use App\Services\Importers\Ssp\MsnManager;
use App\Services\Importers\Ssp\OguryManager;
use App\Services\Importers\Ssp\OutbrainManager;
use App\Services\Importers\Ssp\RichaudienceManager;
use App\Services\Importers\Ssp\SeedtagManager;
use App\Services\Importers\Ssp\SmartclipManager;
use App\Services\Importers\Ssp\SparteoManager;
use App\Services\Importers\Ssp\TeadsManager;
use App\Services\Importers\Ssp\TtdManager;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateCacheTablesCommand extends Command
{
    protected $signature = 'dash:create-cache-tables';

    protected $description = 'Command description';

    public function handle(): void
    {

        $current_date = CarbonImmutable::parse('2025-10-30');
        echo "Amazon".PHP_EOL;

        $listaDati=(new AmazonManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='AMAZON' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        $dealRev=$deals
            ->reduce(function($carry, $item) {
                return $carry->plus($item['value']);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo $dealRev.PHP_EOL;
        $old_deals=DB::connection('maindash')->select("select sum(Valore) as Valore from deal_dn where ssp='AMAZON' and Data='".$current_date->toDateString()."'");
        echo $old_deals[0]->Valore.PHP_EOL;
$a=0;
        echo "Googole historical".PHP_EOL;

        $listaDati=(new GooglehistoricalManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where IdDeal='OPEN MARKET' and  ssp='ADX' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        //---------------------------------------------------
echo "Googole deals".PHP_EOL;
        $listaDati=(new GoogledealsManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where IdDeal<>'OPEN MARKET' and  ssp='ADX' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        $dealRev=$deals
            ->reduce(function($carry, $item) {
                return $carry->plus($item['value']);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo $dealRev.PHP_EOL;
        $old_deals=DB::connection('maindash')->select("select sum(Valore) as Valore from deal_dn where IdDeal<>'OPEN MARKET' and ssp='ADX' and Data='".$current_date->toDateString()."'");
        echo $old_deals[0]->Valore.PHP_EOL;

        //---------------------------------------------------
        echo "Connectad".PHP_EOL;
        $listaDati=(new ConnectadManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='CONNECTAD' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        //---------------------------------------------------
        echo "Ebda".PHP_EOL;
        $listaDati=(new EbdaManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='EBDA' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        //---------------------------------------------------
        echo "Ogury".PHP_EOL;
        $listaDati=(new OguryManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='OGURY' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        //---------------------------------------------------
        echo "Outbrain".PHP_EOL;
        $listaDati=(new OutbrainManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='OUTBRAIN' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        //---------------------------------------------------
        echo "Richaudience".PHP_EOL;
        $listaDati=(new RichaudienceManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='RICHAUDIENCE' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;
        //---------------------------------------------------
        echo "Admanager".PHP_EOL;
        $listaDati=(new GoogleimpressionsdetailsManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='ADMANAGER' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;
        //---------------------------------------------------
        echo "Smartclip".PHP_EOL;
        $listaDati=(new SmartclipManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='SMARTCLIP' and Data='".$current_date->toDateString()."'");
        echo $old_dash[0]->Entrate.PHP_EOL;

        echo "Sparteo".PHP_EOL;
        $listaDati=(new SparteoManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='SPARTEO' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;




        echo "AdForm Adunits".PHP_EOL;
        $listaDati=(new AdformadunitsManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);

        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='ADFORM' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='ADFORM' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        $dealRev=$deals
            ->reduce(function($carry, $item) {
                return $carry->plus($item['value']);
            }, BigDecimal::zero());
        echo "Deal".PHP_EOL;
        echo "New-".$dealRev.PHP_EOL;
        $old_deals=DB::connection('maindash')->select("select sum(Valore) as Valore from deal_dn where ssp='ADFORM' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_deals[0]->Valore.PHP_EOL;


        echo "Aniview MArketplace".PHP_EOL;
        $listaDati=(new AniviewmarketplaceManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='ANIVIEW_MARKETPLACE' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='ANIVIEW_MARKETPLACE' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        echo "Criteo".PHP_EOL;
        $listaDati=(new CriteoManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='CRITEO' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='CRITEO' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "Evolution".PHP_EOL;
        $listaDati=(new EvolutionManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='E_VOLUTION' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='E_VOLUTION' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "Msn".PHP_EOL;
        $listaDati=(new MsnManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='MSN' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='MSN' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "Seedtag".PHP_EOL;
        $listaDati=(new SeedtagManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='SEEDTAG' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='SEEDTAG' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "Teads".PHP_EOL;
        $listaDati=(new TeadsManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='TEADS' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='TEADS' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "TTD".PHP_EOL;
        $listaDati=(new TtdManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='TTD' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='TTD' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "Brid".PHP_EOL;
        $listaDati=(new BridManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='BRID' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='BRID' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;

        echo "Confiant".PHP_EOL;
        $listaDati=(new ConfiantManager())->getDomainCache($current_date);

        $domains=collect($listaDati['domains']);
        $deals=collect($listaDati['deals']);
//dd($domains);
        $domRev=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->revenue);
            }, BigDecimal::zero());
        echo "Revenues".PHP_EOL;
        echo "New-". $domRev.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Entrate) as Entrate from data_summary where ssp='CONFIANT' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


        $domImp=$domains
            ->reduce(function($carry, $item) {
                return $carry->plus($item->impressions);
            }, BigDecimal::zero());
        echo "Impressions".PHP_EOL;
        echo "New-". $domImp.PHP_EOL;
        $old_dash=DB::connection('maindash')->select("select sum(Impressioni) as Entrate from data_summary where ssp='CONFIANT' and Data='".$current_date->toDateString()."'");
        echo "Old-".$old_dash[0]->Entrate.PHP_EOL;


    }
}
