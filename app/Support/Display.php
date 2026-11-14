<?php
namespace App\Support;

use Carbon\Carbon;

final class Display
{
    public static function date(mixed $value,bool $time=false): string
    {
        if(!$value)return '—';
        try{return Carbon::parse($value)->locale('id')->translatedFormat($time?'d F Y, H.i':'d F Y');}
        catch(\Throwable){return 'Tanggal tidak tersedia';}
    }
    public static function mode(?string $value): string
    {
        return ['online'=>'Daring','offline'=>'Tatap muka','hybrid'=>'Gabungan daring dan tatap muka'][$value]??'Tidak dicantumkan';
    }
}
