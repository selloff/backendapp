<?php

namespace App\Modules\Selloff\Admin\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class AdminReportPeriod
{
    /**
     * @return array{
     *     from: CarbonInterface,
     *     to: CarbonInterface,
     *     previous_from: CarbonInterface,
     *     previous_to: CarbonInterface,
     *     days: int
     * }
     */
    public function resolve(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $days = (int) ($from->diffInDays($to) + 1);
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'previous_from' => $previousFrom,
            'previous_to' => $previousTo,
            'days' => $days,
        ];
    }

    /**
     * @param  array{from: CarbonInterface, to: CarbonInterface, previous_from: CarbonInterface, previous_to: CarbonInterface, days: int}  $period
     * @return array{from: string, to: string, previous_from: string, previous_to: string}
     */
    public function payload(array $period): array
    {
        return [
            'from' => $period['from']->toDateString(),
            'to' => $period['to']->toDateString(),
            'previous_from' => $period['previous_from']->toDateString(),
            'previous_to' => $period['previous_to']->toDateString(),
        ];
    }
}
