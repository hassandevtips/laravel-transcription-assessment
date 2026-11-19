<?php

namespace Domain\Transcription\DataTransferObjects;

use InvalidArgumentException;

final readonly class TranscriptData
{
    /** @param list<SegmentData> $segments */
    public function __construct(
        public string $text,
        public string $driver,
        public ?string $language,
        public float $duration,
        public array $segments,
    ) {
        if (! in_array($driver, ['whisper', 'mock'], true) || ! is_finite($duration) || $duration <= 0) {
            throw new InvalidArgumentException('Invalid transcript metadata.');
        }
        $previousEnd = 0.0;
        foreach ($segments as $segment) {
            if (! $segment instanceof SegmentData || $segment->end > $duration + 0.01 || $segment->start < $previousEnd) {
                throw new InvalidArgumentException('Invalid transcript segment sequence.');
            }
            $previousEnd = $segment->end;
        }
    }

    public static function fromArray(array $data): self
    {
        if (! is_string($data['text'] ?? null) || ! is_string($data['driver'] ?? null)
            || ! is_numeric($data['duration'] ?? null) || ! is_array($data['segments'] ?? null)
            || (isset($data['language']) && ! is_string($data['language']))) {
            throw new InvalidArgumentException('Invalid transcript response.');
        }
        $segments = array_map(function ($segment): SegmentData {
            if (! is_array($segment) || ! is_numeric($segment['start'] ?? null)
                || ! is_numeric($segment['end'] ?? null) || ! is_string($segment['text'] ?? null)) {
                throw new InvalidArgumentException('Invalid segment response.');
            }

            return new SegmentData((float) $segment['start'], (float) $segment['end'], $segment['text']);
        }, array_values($data['segments']));

        return new self($data['text'], $data['driver'], $data['language'] ?? null, (float) $data['duration'], $segments);
    }
}
