<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ParseSSCommand extends Command
{
    protected $signature = 'parse:ss';
    protected $description = 'Parser for ss.ge';

    public function handle()
    {
        $raw = Http::get('https://ss.ge/ru/недвижимость/l/Квартира/Аренда?RealEstateTypeId=5&RealEstateDealTypeId=1&BaseUrl=/ru/недвижимость/l&CurrentUserId=&Query=&MunicipalityId=95&CityIdList=95&IsMap=false&subdistr=44,45,46,47,48,49,50,27,26,2,3,4,5&stId=&PrcSource=1&CurrencyId=&PageSize=100&Sort.SortExpression=%22OrderDate%22%20DESC')
            ->body();

        $html = new Crawler($raw);
        $html->filter('.latest_article_each')->each(function (Crawler $item) use (&$flats) {
            $id = 'ss-' . $item->attr('data-id');

            $existFlat = DB::table('flats')->where('uuid', $id)
                ->exists();
            if($existFlat) return;

            $photos = $item->filter('.DesktopArticleLayout .owl-lazy')->each(function (Crawler $pic) use (&$data) {
                return Str::remove(
                    '_Thumb',
                    $pic->attr('data-src')
                );
            });

            $data = [
                'source' => 'ss.ge',
                'uuid' => 'ss-' . $item->attr('data-id'),
                'link' => $item->filter('.latest_desc a')->attr('href'),
                'title' => $item->filter('.TiTleSpanList')->text(),
                'description' => $item->filter('.DescripTionListB')->text(),
                'price' => $item->filter('.latest_price')->last()->text(),
                'address' => $item->filter('.StreeTaddressList')->text(),
                'flat_area' => $item->filter('.latest_flat_km')->text(),
                'flat_type' => $item->filter('.latest_flat_type')->count() ? $item->filter('.latest_flat_type')->text() : null,
                'flat_floor' => $item->filter('.latest_stair_count')->text(),
                'photos' => json_encode($photos),
                'time' => $item->filter('.add_time')->text(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('flats')->insert($data);
            $this->info('Добавлена новая квартира: ' . $data['title']);
        });
    }
}
