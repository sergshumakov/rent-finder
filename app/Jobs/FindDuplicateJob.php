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
        $isUnique = true;

        try {
            foreach($this->flat->photos as $key => $photo) {
                $flatPhoto = 'photos/' . $this->flat->id . '_' . $key .'.jpg';
                $photoBin = Http::get($photo);
                if ($photoBin->status() == 404) {
                    throw new Exception($photo . ' – photo not found', 404);
                }
                Storage::put($flatPhoto, $photoBin->body());

                $photoIsUnique = $this->isUniquePhoto($flatPhoto);
                if (!$photoIsUnique) {
                    Storage::delete($flatPhoto);
                    $isUnique = false;
                    break;
                }
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->flat->compared_at = now();
            $this->flat->save();
            return;
        }

        if (!$isUnique) {
            $this->flat->duplicate_at = now();
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

        foreach ($bankPhotos as $photo) {
            if (!Storage::exists($photo)) {
                continue;
            }
            $item = Storage::path($photo);
            $duplicates = $this->comparer->findDuplicates([$input, $item], 0.04);
            if (count($duplicates)) {
                // проверяем не дублируются ли фото внутри одного объявления
                [$left, $right] = $duplicates;
                $remove = [Storage::path('photos') . '/', '_0.jpg', '_2.jpg', '_3.jpg', '_4.jpg'];
                $leftId = (int) Str::remove($remove, $left);
                $rightId = (int) Str::remove($remove, $right);
                if ($leftId === $rightId) {
                    continue;
                } else {
                    // найден дубликат с другим объявлением
                    return false;
                }
            }
        }

        return true;
    }
}
