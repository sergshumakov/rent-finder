<?php

namespace App\Console\Commands;

use App\Models\District;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ParseMyHomeCommand extends Command
{
    protected $signature = 'parse:myhome';
    protected $description = 'Parser for myhome.ge';

    public function handle()
    {
        foreach(District::all() as $district) {
            for($page = 1; $page <= 5; $page++) {
                $this->parseDistrict($district, $page);
            }
            sleep(1);
        }
    }

    private function parseDistrict(District $district, int $page)
    {
        $raw = Http::get($district->myhome_url . '&Page=' . $page)
            ->body();

        $html = new Crawler($raw);
        $html->filter('.statement-row-search .statement-card')
            ->each(function (Crawler $item) use ($district) {
                $id = $item->attr('data-product-id');
                if (!$id) return;

                $existFlat = DB::table('flats')->where('uuid', 'mh-' . $id)
                    ->exists();
                if($existFlat) return;

                $link = $item->filter('a.card-container')->attr('href');

                $rawFlatPage = Http::retry(3, 1000)
                    ->get($link)
                    ->body();

                $flatPage = new Crawler($rawFlatPage);

                $cadNumberRaw = $flatPage->filter('.cadastral');
                $cadNumber = $cadNumberRaw->count() ? Str::replace('Кадастровый код: ', '', $cadNumberRaw->text()) : null;
                if ($cadNumber) {
                    $existFlatByCad = DB::table('flats')->where('cad_number', $cadNumber)->exists();
                    if($existFlatByCad) return;
                }

                $photos = [];
                $photoContainer = $item->filter('img.swiper-lazy');
                $countPhotos = $photoContainer->attr('data-photos-cnt');
                $maxPhotos = $countPhotos >= 4 ? 4 : $countPhotos;
                [$photoBaseUrl] = explode('thumbs/', $photoContainer->attr('data-src'));
                for ($i = 1; $i <= $maxPhotos; $i++) {
                    $photos[] = $photoBaseUrl . 'large/' . $id . '_' . $i . '.jpg';
                }

                $description = $flatPage->filter('.pr-comment.translated');
                $map = $flatPage->filter('#map');

                $data = [
                    'district_id' => $district->id,
                    'source' => 'myhome.ge',
                    'uuid' => 'mh-' . $id,
                    'cad_number' => $cadNumber,
                    'link' => $link,
                    'title' => $item->filter('h5.card-title')->text(),
                    'description' => $description->count() ? $description->text() : '',
                    'price' => $item->filter('.item-price-usd')->text() . '$',
                    'address' => $item->filter('.address')->text(),
                    'flat_area' => $item->filter('.item-size')->text(),
                    'flat_floor' => Str::replace('Этаж ', '', $item->filter('.options-texts')->text()),
                    'photos' => json_encode($photos),
                    'lat' => $map->count() ? $map->attr('data-lat') : null,
                    'lng' => $map->count() ? $map->attr('data-lng') : null,
                    'time' => $item->filter('.statement-date')->text(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('flats')->insert($data);
                $this->info('Добавлена новая квартира: ' . $data['title']);
        });
    }
}
