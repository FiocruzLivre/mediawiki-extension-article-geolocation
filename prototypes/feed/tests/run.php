<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use Mapa\GeoFeed\{Article,Point,Selector,Serializer,Consolidator,TemplateFieldsReader,ApprovalService};
use Mapa\GeoFeed\Contracts\{ArticleSource,Approvals,ChangeEpoch,Lock,Publisher};
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function rejects(callable $fn): void { try { $fn(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Invalid input accepted'); }
final class Source implements ArticleSource {
    public array $items = [];
    public function byIds(array $pageIds): iterable { foreach ($pageIds as $id) { if(isset($this->items[$id])) { yield $this->items[$id]; } } }
}
final class Grants implements Approvals {
    public array $rows = [];
    public function all(): iterable { foreach ($this->rows as $id=>$hash) { yield ['pageId'=>$id,'fingerprint'=>$hash]; } }
    public function grant(int $id,string $fingerprint,int $revisionId,int $actorId):void {$this->rows[$id]=$fingerprint;}
    public function revoke(int $id,int $actorId,string $reason):void {unset($this->rows[$id]);}
}
final class Clock implements ChangeEpoch {public int $n=0; public function current():int{return $this->n;}}
final class Mutex implements Lock {public bool $busy=false;public function acquire():bool {if($this->busy)return false;$this->busy=true;return true;}public function release():void {$this->busy=false;}}
final class Output implements Publisher {
    public ?string $json=null;
    public bool $fail=false;
    public int $writes=0;
    public function hasContentHash(string $hash):bool{return $this->json!==null && hash('sha256',$this->json)===$hash;}
    public function publish(string $json,string $hash):void {if($this->fail)throw new RuntimeException('db offline');$this->json=$json;$this->writes++;}
}
foreach ([['5','6'], [NAN,1], [INF,1], [1,-INF], [91,0],[-91,0],[0,181],[0,-181],[[],1]] as $coordinates) {
    rejects(fn()=>Point::fromNumbers(...$coordinates));
}
check(Point::fromNumbers(-3.68274,-40.3512)->coordinates()===[-40.3512,-3.68274],'coordinate order');
rejects(fn()=>Point::fromDecimals('NaN','1'));
rejects(fn()=>Point::fromDecimals('1e2','1'));
$t=(new TemplateFieldsReader())->parse('{{Geolocalização do artigo|latitude=-3.68274|longitude=-40.3512|exibir_no_mapa=sim|precisao=municipio|tipo_local=coletivo}}');
check($t->requested && $t->point?->longitude===-40.3512,'template reader');
check((new TemplateFieldsReader())->parse('{{Geolocalização do artigo|latitude=100|longitude=0|exibir_no_mapa=sim}}')->invalid,'invalid template');
$s=new Source();$g=new Grants();$clock=new Clock();$m=new Mutex();$o=new Output();
$a=new Article(10,'Loucure-se',2,true,false,true,true,Point::fromNumbers(-3.68274,-40.3512),null);
$s->items[10]=$a;$g->grant(10,$a->fingerprint(),2,5);
$engine=new Consolidator(new Selector($s,$g),new Serializer(),$clock,$m,$o);
check($engine->run()['status']==='published','first publish');
check($engine->run()['status']==='unchanged' && $o->writes===1,'idempotence');
$data=json_decode($o->json,true,512,JSON_THROW_ON_ERROR);
check($data['features'][0]['geometry']['coordinates']===[-40.3512,-3.68274],'GeoJSON coordinates');
check($data['features'][0]['properties']['marker-size']==='large','marker size');
check($data['features'][0]['properties']['title']==='[[Loucure-se]]','title');
$m->busy=true;check($engine->run()['status']==='busy','mutex');$m->busy=false;
$s->items[10]=new Article(10,'Loucure-se',3,true,false,true,true,Point::fromNumbers(-3.68,-40.35),null);
check($engine->run()['status']==='empty_preserved' && $o->writes===1,'approval invalidated');
$s->items=[];check($engine->run()['status']==='empty_preserved' && $o->writes===1,'empty preserved');
$s->items[10]=$a;$o->fail=true;$s->items[11]=new Article(11,'Good',3,true,false,true,true,Point::fromNumbers(0,0),null);
$g->grant(11,$s->items[11]->fingerprint(),3,5);
try {$engine->run();throw new RuntimeException('expected publisher failure');} catch(RuntimeException $ex) {check($ex->getMessage()==='db offline','failed safe');}
check($o->writes===1 && !$m->busy,'failure preserves and unlocks');
$o->fail=false;check($engine->run()['status']==='published','recovery');
$malicious=new Article(12,'<script>alert(1)</script>',2,true,false,true,true,Point::fromNumbers(0,0),null);
check(!$malicious->eligible(),'malicious title');
foreach (['public'=>false,'requested'=>false,'exists'=>false] as $key=>$flag) {
    $args=['id'=>100,'title'=>'Example','revisionId'=>1,'exists'=>true,'redirect'=>false,'public'=>true,'requested'=>true,'primary'=>Point::fromNumbers(0,0),'template'=>null];
    $args[$key]=$flag;
    check(!(new Article(...$args))->eligible(),$key.' exclusion');
}
$service=new ApprovalService($s,$g,static fn(int $id):bool=>$id===5);
try {$service->approve(10,6);throw new RuntimeException('unauthorized');}catch(DomainException $ignored){}
echo "PASS: geometry, invalid values, template, approvals, concurrency, safe publication, empty recovery\n";
