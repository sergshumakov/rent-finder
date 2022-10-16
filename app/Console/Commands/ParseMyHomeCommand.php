<?php

namespace App\Console\Commands;

use App\Jobs\ParseMyHomeJob;
use App\Models\District;
use Illuminate\Console\Command;

class ParseMyHomeCommand extends Command
{
    protected $signature = 'parse:myhome';
    protected $description = 'Парсер квартир с myhome.ge';

    public function handle()
    {
        foreach(District::all() as $district) {
            for($page = 1; $page <= 5; $page++) {
                ParseMyHomeJob::dispatch(
                    district: $district,
                    page: $page
                );
            }
        }
    }
}
