<?php

namespace App\Commands;

use App\Data\Package;
use Astrotomic\Ecologi\Ecologi;
use Astrotomic\Ecologi\Enums\CarbonUnit;
use LaravelZero\Framework\Commands\Command;
use Money\Formatter\IntlMoneyFormatter;
use Spatie\Packagist\PackagistClient;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;

class OffsetPackagistDownloadsCommand extends Command
{
    protected $signature = 'packagist:offset
        {vendor : The name of the packagist vendor}
        {--downloads-per-tree=1000 : The number of donwloads needed to buy one tree}
        {--downloads-per-carbon=100 : The number of downloads needed to offset one KG of carbon}
        {--test : Use the ecologi testing API}';

    protected $description = 'Purchase trees and offset carbon based on the monthly downloads of vendor packages.';

    public function handle(PackagistClient $packagist, Ecologi $ecologi, IntlMoneyFormatter $moneyFormatter): int
    {
        $this->line("Get all package names by vendor [{$this->argument('vendor')}]");
        $packages = collect(data_get($packagist->getPackagesNamesByVendor($this->argument('vendor')), 'packageNames'))
            ->map(fn (string $name) => new Package($name));

        $this->line('Get package downloads for each package');
        $this->output->progressStart($packages->count());
        $packages = $packages
            ->each(function (Package $package) use ($packagist): void {
                $package->downloads = data_get($packagist->getPackage($package->name), 'package.downloads.monthly');
                $package->trees = round($package->downloads / intval($this->option('downloads-per-tree')));
                $package->carbon = round($package->downloads / intval($this->option('downloads-per-carbon')));

                $this->output->progressAdvance();
            })
            ->reject(fn (Package $package) => $package->trees < 1 && $package->carbon < 1)
            ->sortByDesc(fn (Package $package) => $package->downloads)
            ->values();
        $this->output->progressFinish();

        $this->line('Plant trees and offset carbon');
        $this->output->progressStart($packages->count());
        $packages = $packages
            ->each(function (Package $package) use ($ecologi): void {
                $package->treePrice = $package->trees > 0
                    ? retry(3, fn () => $ecologi->purchasing((bool) $this->option('test'))->buyTrees($package->trees, $package->name)->costs(), 250)
                    : null;
                $package->carbonPrice = $package->carbon > 0
                    ? retry(3, fn () => $ecologi->purchasing((bool) $this->option('test'))->buyCarbonOffset($package->carbon, CarbonUnit::KG)->costs(), 250)
                    : null;

                $this->output->progressAdvance();
            });
        $this->output->progressFinish();

        $this->table(
            [
                'Package',
                'Downloads',
                'Trees',
                'Carbon-Offset',
                'Total-Price',
            ],
            $packages->map(fn (Package $package) => [
                $package->name,
                new TableCell($package->downloads, [
                    'style' => new TableCellStyle([
                        'align' => 'right',
                    ]),
                ]),
                new TableCell($package->trees, [
                    'style' => new TableCellStyle([
                        'align' => 'right',
                    ]),
                ]),
                new TableCell($package->carbon.' kg', [
                    'style' => new TableCellStyle([
                        'align' => 'right',
                    ]),
                ]),
                new TableCell($moneyFormatter->format($package->price()), [
                    'style' => new TableCellStyle([
                        'align' => 'right',
                    ]),
                ]),
            ])
        );

        return self::SUCCESS;
    }
}
