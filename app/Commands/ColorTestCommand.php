<?php

namespace App\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use function Termwind\render;

class ColorTestCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'colors:show';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show all Termwind colors';

    /** @var array|string[] */
    private array $terminalColors = [
        'black',
        'white',
        'brightwhite',
        'gray',
        'red',
        'brightred',
        'orange',
        'yellow',
        'brightyellow',
        'green',
        'brightgreen',
        'cyan',
        'brightcyan',
        'blue',
        'brightblue',
        'magenta',
        'brightmagenta',
    ];

    /** @var array|string[] */
    private array $termwindColors = [
        'slate',
        'gray',
        'zinc',
        'neutral',
        'stone',
        'red',
        'orange',
        'amber',
        'yellow',
        'lime',
        'green',
        'emerald',
        'teal',
        'cyan',
        'sky',
        'blue',
        'indigo',
        'violet',
        'purple',
        'fuchsia',
        'pink',
        'rose',
    ];

    /** @var array|int[] */
    private array $weights = [
        50,
        100,
        200,
        300,
        400,
        500,
        600,
        700,
        800,
        900,
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getAllColors()->each(function ($color) {
            $textClass = 'text-black';
            if (preg_match('/(\d+)$/', $color, $match) && $match[1] > 500) {
                $textClass = 'text-white';
            }
            render(<<<HTML
                <div>
                    <span class='w-15 px-1 text-center bg-$color $textClass'>$color</span>
                    <span class='ml-3 text-$color'>$color</span>
                </div>
            HTML
            );
        });

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function getAllColors(): Collection
    {
        return collect($this->termwindColors)
            ->flatMap(fn ($color) => collect($this->weights)->map(fn ($weight) => "$color-$weight"))
            ->merge($this->terminalColors)
            ->sortBy(fn ($color) => Str::after($color, 'bright'), SORT_NATURAL);
    }
}
