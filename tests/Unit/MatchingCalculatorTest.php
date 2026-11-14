<?php
namespace Tests\Unit;
use App\Services\MatchingCalculator;
use PHPUnit\Framework\TestCase;
class MatchingCalculatorTest extends TestCase {
 private function requirements():array {return [
  ['skill_id'=>1,'skill'=>'Pencatatan Stok','target'=>2,'importance'=>'required','weight'=>3,'is_override'=>false],
  ['skill_id'=>2,'skill'=>'Forklift','target'=>2,'importance'=>'required','weight'=>3,'is_override'=>false],
  ['skill_id'=>3,'skill'=>'Spreadsheet','target'=>2,'importance'=>'required','weight'=>3,'is_override'=>false],
  ['skill_id'=>4,'skill'=>'K3','target'=>1,'importance'=>'preferred','weight'=>1,'is_override'=>false],
 ];}
 public function test_score_55_85_100():void {
  $config=['required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5];
  $original=[1=>3,3=>1,4=>2];
  $this->assertSame(55.0,MatchingCalculator::calculate($this->requirements(),$original,[],$config)['score']);
  $this->assertSame(85.0,MatchingCalculator::calculate($this->requirements(),$original+[2=>2],[],$config)['score']);
  $this->assertSame(100.0,MatchingCalculator::calculate($this->requirements(),[1=>3,2=>2,3=>2,4=>2],[],$config)['score']);
 }
 public function test_related_skill_is_partial_and_does_not_replace_missing_skill():void {
  $conf=['required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5];
  $r=MatchingCalculator::calculate($this->requirements(),[1=>3,3=>1,4=>2],[2=>[2]],$conf);
  $this->assertSame(70.0,$r['score']);
  $this->assertTrue($r['items'][1]['related']);$this->assertSame(2,$r['items'][1]['gap']);
 }
}
