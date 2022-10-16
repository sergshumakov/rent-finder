<?php

namespace App\Jobs;

use App\Models\District;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ParseMyHomeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public District $district;
    public int $page;

    public function __construct(District $district, int $page)
    {
        $this->district = $district;
        $this->page = $page;
    }

    public function handle()
    {
        $raw = Http::get($this->district->myhome_url . '&Page=' . $this->page)
            ->body();

        $html = new Crawler($raw);
        $html->filter('.statement-row-search .statement-card')
            ->each(closure: function (Crawler $item) {
                $id = $item->attr('data-product-id');
                if (!$id) return;

                $photos = [];
                $photoContainer = $item->filter('img.swiper-lazy');
                $countPhotos = $photoContainer->attr('data-photos-cnt');
                $maxPhotos = $countPhotos >= 4 ? 4 : $countPhotos;
                [$photoBaseUrl] = explode('thumbs/', $photoContainer->attr('data-src'));
                for ($i = 1; $i <= $maxPhotos; $i++) {
                    $photos[] = $photoBaseUrl . 'large/' . $id . '_' . $i . '.jpg';
                }

                ParseMyHomePageJob::dispatch(
                    district: $this->district,
                    data: [
                        'id' => $id,
                        'title' => $item->filter('h5.card-title')->text(),
                        'link' => $item->filter('a.card-container')->attr('href'),
                        'price' => $item->filter('.item-price-usd')->text(),
                        'address' => $item->filter('.address')->text(),
                        'flatArea' => $item->filter('.item-size')->text(),
                        'flatFloor' => Str::replace('Этаж ', '', $item->filter('.options-texts')->text()),
                        'photos' => $photos,
                        'time' => $item->filter('.statement-date')->text(),
                    ]
                );
            });
    }
}
