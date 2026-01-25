<?php

namespace App\Providers;

use App\Domain\Game\GameService;
use App\Domain\Game\PlacementValidator;
use App\Domain\Game\RoomCodeGenerator;
use App\Domain\Game\ScoreCalculator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RoomCodeGenerator::class);
        $this->app->singleton(PlacementValidator::class);
        $this->app->singleton(ScoreCalculator::class);

        $this->app->singleton(GameService::class, function ($app) {
            return new GameService(
                $app->make(RoomCodeGenerator::class),
                $app->make(PlacementValidator::class),
                $app->make(ScoreCalculator::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
