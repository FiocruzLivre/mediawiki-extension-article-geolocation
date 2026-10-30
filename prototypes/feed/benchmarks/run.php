<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use Mapa\GeoFeed\{Article,Point,Serializer};
foreach ([82,1000,10000] as $n) {
    $rows = [];
    $start = hrtime(true);
    for ($i=1; $i<=$n; $i++) {
        $rows[$i]=new Article($i,'Coletivo '.$i,1,true,false,true,true,
            Point::fromNumbers(-3.68274, -40.3512), null);
    }
    $make = (hrtime(true)-$start)/1e6;
    $start = hrtime(true);
    $json=(new Serializer())->encode($rows);
    $serial=(hrtime(true)-$start)/1e6;
    $read=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if(count($read['features'])!==$n)throw new RuntimeException('Count mismatch');
    printf("%d points: fixtures %.2fms; serialize %.2fms; bytes %d; peak %d MiB\n",
        $n,$make,$serial,strlen($json),(int)ceil(memory_get_peak_usage(true)/1048576));
}
