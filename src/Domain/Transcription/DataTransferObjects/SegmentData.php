<?php

namespace Domain\Transcription\DataTransferObjects;

use InvalidArgumentException;

final readonly class SegmentData
{
    public function __construct(public float $start, public float $end, public string $text)
    {
        if (! is_finite($start) || ! is_finite($end) || $start < 0 || $end < $start) {
            throw new InvalidArgumentException('Invalid segment timestamps.');
        }
    }

    public function toArray(): array
    {
        return ['start' => $this->start, 'end' => $this->end, 'text' => $this->text];
    }
}
