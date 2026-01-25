<?php

declare(strict_types=1);

namespace App\Domain\Providers\Tidal;

use RuntimeException;

class TidalPlaylistRequiresAdminTokenException extends RuntimeException {}
