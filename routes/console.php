<?php

use App\Jobs\StartParserJob;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('parser:run {type : Parser növü: wolt və ya bina}', function (): int {
    $type = strtolower((string) $this->argument('type'));
    if (! in_array($type, ['wolt', 'bina'], true)) {
        $this->error('type yalnız wolt və ya bina ola bilər.');

        return 1;
    }

    StartParserJob::dispatch($type, 'manual');
    $this->info("StartParserJob növbəyə göndərildi: {$type} (trigger: manual)");
    $this->newLine();
    $this->comment('Worker (ayrı terminal): php artisan horizon');
    $this->comment('Növbə sırası config/horizon.php: əvvəl orchestration+bina, sonra wolt (wolt backlog bina bloklamasın).');
    $this->comment('Konfiq dəyişəndən sonra: php artisan horizon:terminate');
    $this->comment('və ya: php artisan queue:work redis --queue=parser-orchestration,parser-bina,parser-wolt,default');
    $this->comment('.env: QUEUE_CONNECTION=redis (parser job-ları redis növbəsindədir).');
    $this->comment('Redis xətası: REDIS_CLIENT=predis; php artisan config:clear');
    $this->comment('Redis olmadan sınaq: .env-də QUEUE_CONNECTION=sync');
    $this->comment('Nəticə JSON (işlər bitəndən sonra): php artisan parser:json-report');

    return 0;
})->purpose('Parser testi — Wolt/Bina pipeline-ını başladır');

Artisan::command('parser:ensure-basic-user', function (): int {
    $email = (string) config('parser_api.basic_username', '');
    $password = (string) env('PARSER_BASIC_PASSWORD', '');
    if ($email === '' || $password === '') {
        $this->error('.env-də PARSER_BASIC_USERNAME və PARSER_BASIC_PASSWORD təyin edin (HTTP Basic /api/v1/control üçün).');

        return 1;
    }

    User::updateOrCreate(
        ['email' => $email],
        [
            'name' => 'Parser Admin',
            'password' => $password,
        ],
    );

    $this->info("users cədvəli: {$email} (HTTP Basic ilə eyni e-poçt və parol).");

    return 0;
})->purpose('API parser control üçün HTTP Basic istifadəçisi (users.email)');

Artisan::command('parser:json-report {run_id? : parser_runs.id, boşdursa son run}', function (): int {
    $runId = $this->argument('run_id');
    $id = ($runId !== null && $runId !== '') ? (int) $runId : (int) (DB::table('parser_runs')->max('id') ?? 0);
    if ($id < 1) {
        $this->error('parser_runs boşdur.');

        return 1;
    }

    $run = DB::table('parser_runs')->where('id', $id)->first();
    if ($run === null) {
        $this->error("parser_run tapılmadı: {$id}");

        return 1;
    }

    $sprCount = (int) DB::table('source_price_results')->where('parser_run_id', $id)->count();
    $snapCount = (int) DB::table('price_snapshots')->where('parser_run_id', $id)->count();

    $sampleSpr = DB::table('source_price_results as spr')
        ->join('price_positions as pp', 'pp.id', '=', 'spr.position_id')
        ->join('price_categories as pc', 'pc.id', '=', 'pp.category_id')
        ->where('spr.parser_run_id', $id)
        ->orderByDesc('spr.id')
        ->limit(15)
        ->get([
            'spr.id',
            'spr.position_id',
            'spr.normalized_price',
            'spr.currency',
            'spr.title',
            'pp.slug as position_slug',
            'pc.slug as category_slug',
        ]);

    $payload = [
        'parser_run_id' => $id,
        'parser_run' => $run,
        'counts' => [
            'source_price_results' => $sprCount,
            'price_snapshots' => $snapCount,
        ],
        'sample_source_price_results' => $sampleSpr->map(fn ($r) => (array) $r)->values()->all(),
        'http_json' => [
            'city_price_averages' => '/api/v1/parser/city-price-averages?token=GUN_AZ_API_TOKEN',
            'source_price_results' => '/api/v1/parser/source-price-results?token=GUN_AZ_API_TOKEN&parser_run_id='.$id,
            'price_snapshots_pending' => '/api/v1/parser/price-snapshots?token=GUN_AZ_API_TOKEN&parser_run_id='.$id,
        ],
    ];

    $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Son (və ya verilmiş) parser run üçün statistika və nümunə JSON');
