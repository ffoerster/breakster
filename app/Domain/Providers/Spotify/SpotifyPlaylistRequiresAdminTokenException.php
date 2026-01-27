<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Spotify;

use RuntimeException;

class SpotifyPlaylistRequiresAdminTokenException extends RuntimeException {}
