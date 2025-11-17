<?php




DB::connection('alternate')->select("select sum(revenue) from ssp_ebda where date='2025-09-25'");
DB::connection('maindash')->select("select sum(Entrate) from ebda where Data='2025-09-25'");

DB::connection('alternate')->select("select sum(impressions) from ssp_ebda where date='2025-09-25'");
DB::connection('maindash')->select("select sum(Impressioni) from ebda where Data='2025-09-25'");

DB::connection('alternate')->select("select sum(reuqests) from ssp_ebda where date='2025-09-25'");
DB::connection('maindash')->select("select sum(Richieste) from ebda where Data='2025-09-25'");


