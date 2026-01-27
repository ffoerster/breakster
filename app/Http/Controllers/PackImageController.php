<?php

declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Models\Pack;
use Illuminate\Http\Response;

class PackImageController extends Controller
{
    public function placeholder(Pack $pack): Response
    {
        $name = $pack->name ?? 'Pack';

        // Deterministic color based on pack name
        $hash = crc32($name);
        $hue = $hash % 360; // 0-359
        $bgColor = "hsl($hue, 60%, 55%)";
        $textColor = 'rgba(255,255,255,0.9)';

        // Initials (first letters of words)
        $initials = collect(preg_split('/\s+/', trim($name)) ?: ['P'])
            ->map(static fn(string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->slice(0, 3)
            ->implode('');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
              <defs>
                <linearGradient id="grad" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="$bgColor" />
                  <stop offset="100%" stop-color="hsl($hue, 70%, 35%)" />
                </linearGradient>
              </defs>
              <rect width="600" height="600" fill="url(#grad)"/>
              <circle cx="300" cy="300" r="260" fill="rgba(255,255,255,0.06)"/>
              <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
                    font-family="system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial, sans-serif"
                    font-size="220" font-weight="800" fill="$textColor" letter-spacing="8">
                $initials
              </text>
            </svg>
            SVG;

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
