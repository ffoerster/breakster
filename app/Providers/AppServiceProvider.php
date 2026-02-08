<?php

namespace App\Providers;

use App\Domain\Analytics\GameSessionRecorder;
use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\GameService;
use App\Domain\Game\PlacementValidator;
use App\Domain\Game\RoomCodeGenerator;
use App\Domain\Game\ScoreCalculator;
use App\Events\RoomStateChanged;
use App\Models\Room;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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

        $this->app->singleton(GameService::class, static function ($app) {
            return new GameService(
                $app->make(RoomCodeGenerator::class),
                $app->make(PlacementValidator::class),
                $app->make(ScoreCalculator::class),
            );
        });

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('authentik', \SocialiteProviders\Authentik\Provider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Event::listen(function (RoomStateChanged $event): void {
            if ($event->state !== RoomState::Finished) {
                return;
            }

            $room = Room::query()->where('code', $event->roomCode)->first();

            if (! $room) {
                return;
            }

            app(GameSessionRecorder::class)->record($room);
        });
    }
}
