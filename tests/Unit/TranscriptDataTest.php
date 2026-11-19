<?php

namespace Tests\Unit;

use Domain\Transcription\DataTransferObjects\TranscriptData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TranscriptDataTest extends TestCase
{
    public function test_rejects_reversed_timestamps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TranscriptData::fromArray(['text' => 'Hello', 'driver' => 'mock', 'duration' => 2,
            'segments' => [['start' => 2, 'end' => 1, 'text' => 'Hello']]]);
    }

    public function test_rejects_segments_outside_the_audio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TranscriptData::fromArray(['text' => 'Hello', 'driver' => 'mock', 'duration' => 2,
            'segments' => [['start' => 0, 'end' => 3, 'text' => 'Hello']]]);
    }
}
