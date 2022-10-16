<?php

namespace App\Jobs;

use App\Models\Flat;
use devoleg\FastImageCompare\FastImageCompare;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FindDuplicateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Flat $flat;
    private FastImageCompare $comparer;

    /**
     * @throws Exception
     */
    public function __construct(Flat $flat)
    {
        $this->flat = $flat;

        $this->comparer = new FastImageCompare();
        $this->comparer->setTemporaryDirectory(Storage::path('temp'));
    }

    public function handle()
    {
        Log::info($this->flat->id . ' – started');
        $isUnique = true;

        try {
            foreach($this->flat->photos as $key => $photo) {
                $flatPhoto = 'photos/' . $this->flat->id . '_' . $key .'.jpg';
                Log::info($photo);
                $photoBin = Http::get($photo);
                if ($photoBin->status() == 404) {
                    throw new Exception($photo . ' – photo not found', 404);
                }
                Storage::put($flatPhoto, $photoBin->body());

                $tStart = microtime(true);
                $photoIsUnique = $this->isUniquePhoto($flatPhoto);
                Log::info('Speed: ' . (microtime(true) - $tStart));
                if (!$photoIsUnique) {
                    Storage::delete($flatPhoto);
                    $isUnique = false;
                    break;
                }
            }
        } catch (Exception) {
            Log::error($this->flat->id . ' – photo is not found');
            $this->flat->compared_at = now();
            $this->flat->save();
            return;
        }

        if (!$isUnique) {
            $this->flat->duplicate_at = now();
            Log::warning($this->flat->id . ' – find duplicate!');
        } else {
            Log::info($this->flat->id . ' – is unique!');
        }

        $this->flat->compared_at = now();
        $this->flat->save();
    }

    /**
     * @throws Exception
     */
    private function isUniquePhoto(string $flatPhoto): bool
    {
        $input = Storage::path($flatPhoto);

        $bankPhotos = Storage::files('photos');
        Log::info('Count photos in bank: ' . count($bankPhotos));

        $items = array_map(
            fn ($file) => Storage::path($file),
            $bankPhotos
        );

        foreach ($items as $item) {
            $duplicates = $this->comparer->findDuplicates([$input, $item], 0.04);
            if (count($duplicates)) {
                // проверяем не дублируются ли фото внутри одного объявления
                [$left, $right] = $duplicates;
                $remove = [Storage::path('photos') . '/', '_0.jpg', '_2.jpg', '_3.jpg', '_4.jpg'];
                $leftId = (int) Str::remove($remove, $left);
                $rightId = (int) Str::remove($remove, $right);
                if ($leftId === $rightId) {
                    // удаляем лишнюю фотографию
                    Storage::delete($flatPhoto);
                    break;
                } else {
                    // найден дубликат с другим объявлением
                    return false;
                }
            }
        }

        return true;
    }
}
