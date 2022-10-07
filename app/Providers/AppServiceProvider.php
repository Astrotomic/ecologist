<?php

namespace App\Providers;

use Astrotomic\Ecologi\Ecologi;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use NumberFormatter;
use Spatie\Packagist\PackagistClient;
use Spatie\Packagist\PackagistUrlGenerator;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        $this->app->singleton(PackagistClient::class, function (): PackagistClient {
            return new PackagistClient(new Client(), new PackagistUrlGenerator());
        });

        $this->app->singleton(Ecologi::class, function (): Ecologi {
            return new Ecologi(env('ECOLOGI_SECRET'));
        });

        $this->app->singleton(IntlMoneyFormatter::class, function (): IntlMoneyFormatter {
            return new IntlMoneyFormatter(
                new NumberFormatter('en_US', NumberFormatter::CURRENCY),
                new ISOCurrencies()
            );
        });
    }
}
