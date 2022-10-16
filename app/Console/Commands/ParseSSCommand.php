<?php

namespace App\Console\Commands;

use App\Jobs\ParseSSJob;
use App\Models\District;
use Illuminate\Console\Command;

class ParseSSCommand extends Command
{
    protected $signature = 'parse:ss';
    protected $description = 'Парсер квартир с ss.ge';

    public function handle()
    {
        foreach(District::all() as $district) {
            ParseSSJob::dispatch($district);
        }
    }
}
