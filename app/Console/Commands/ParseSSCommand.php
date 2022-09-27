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
        for ($pageNum = 1; $pageNum <= 5; $pageNum++) {
            $this->info('Загружаем страницу: ' . $pageNum);
            $this->getPage($pageNum);
        }
    }

    private function getPage(int $page) {
        $raw = Http::get('https://ss.ge/ru/%D0%BD%D0%B5%D0%B4%D0%B2%D0%B8%D0%B6%D0%B8%D0%BC%D0%BE%D1%81%D1%82%D1%8C/l/%D0%9A%D0%B2%D0%B0%D1%80%D1%82%D0%B8%D1%80%D0%B0/%D0%90%D1%80%D0%B5%D0%BD%D0%B4%D0%B0?MunicipalityId=95&CityIdList=95&subdistr=47&PriceType=false&CurrencyId=1&Page=' . $page)
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
