<?php

namespace App\Jobs;

use App\Models\District;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ParseSSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public District $district;

    public function __construct(District $district)
    {
        $this->district = $district;
    }

    public function handle()
    {
        $raw = Http::retry(3, 3000)
            ->get($this->district->ss_url)
            ->body();

        $html = new Crawler($raw);

        $html->filter('.latest_article_each')->each(function (Crawler $item) {
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
                'district_id' => $this->district->id,
                'source' => 'ss.ge',
                'uuid' => 'ss-' . $item->attr('data-id'),
                'link' => 'https://ss.ge' . $item->filter('.latest_desc a')->attr('href'),
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
        });
    }
}
