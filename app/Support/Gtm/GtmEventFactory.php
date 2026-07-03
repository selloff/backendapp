<?php

namespace App\Support\Gtm;

class GtmEventFactory
{
    /**
     * @param  array<string, mixed>  $eventData
     * @return array{event: string, eventData: array<string, mixed>, timestamp: int}
     */
    public function make(string $event, array $eventData, ?int $timestamp = null): array
    {
        return [
            'event' => $event,
            'eventData' => $eventData,
            'timestamp' => $timestamp ?? time(),
        ];
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function list(string $event, array $eventData, ?int $timestamp = null): array
    {
        return [$this->make($event, $eventData, $timestamp)];
    }
}
