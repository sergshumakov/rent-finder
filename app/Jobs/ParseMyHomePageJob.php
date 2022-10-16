<?php

namespace App\Jobs;

use App\Models\District;
use App\Models\Flat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ParseMyHomePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public District $district;
    public array $item;

    public function __construct(District $district, array $item)
    {
        $this->district = $district;
        $this->item = $item;
    }

    public function handle()
    {
        $rawFlatPage = Http::retry(3, 1000)
            ->get($this->item['link'])
            ->body();

        $flatPage = new Crawler($rawFlatPage);

        $cadNumberRaw = $flatPage->filter('.cadastral');
        $cadNumber = $cadNumberRaw->count() ? Str::replace('Кадастровый код: ', '', $cadNumberRaw->text()) : null;
        if ($cadNumber) {
            $existFlatByCad = DB::table('flats')->where('cad_number', $cadNumber)->exists();
            if($existFlatByCad) return;
        }

        $description = $flatPage->filter('.pr-comment.translated');
        $map = $flatPage->filter('#map');

        $data = [
            'district_id' => $this->district->id,
            'source' => 'myhome.ge',
            'uuid' => 'mh-' . $this->item['id'],
            'cad_number' => empty($cadNumber) ? null : $cadNumber,
            'link' => $this->item['link'],
            'title' => $this->item['title'],
            'description' => $description->count() ? $description->text() : '',
            'price' => $this->item['price'] . '$',
            'address' => $this->item['address'],
            'flat_area' => $this->item['flatArea'],
            'flat_floor' => $this->item['flatFloor'],
            'photos' => json_encode($this->item['photos']),
            'lat' => $map->count() ? $map->attr('data-lat') : null,
            'lng' => $map->count() ? $map->attr('data-lng') : null,
            'time' => $this->item['time'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existFlat = DB::table('flats')->where('uuid', 'mh-' . $this->item['id'])
            ->exists();
        if($existFlat) return;

        Flat::create($data);
    }
}
