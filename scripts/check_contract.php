<?php

use Domain\Transcription\DataTransferObjects\TranscriptData;

// Dependency-free red/green check while Composer is installing.
spl_autoload_register(function (string $class): void {
    $path = dirname(__DIR__).'/src/'.str_replace('\\', '/', $class).'.php';
    if (is_file($path)) {
        require $path;
    }
});
if (! class_exists(TranscriptData::class)) {
    fwrite(STDERR, "FAIL: transcript data contract is not implemented\n");
    exit(1);
}
$data = TranscriptData::fromArray([
    'text' => 'Hello', 'driver' => 'mock', 'language' => null, 'duration' => 2,
    'segments' => [['text' => 'Hello', 'start' => 0, 'end' => 2]],
]);
if ($data->segments[0]->end !== 2.0) {
    exit(1);
}
try {
    TranscriptData::fromArray([
        'text' => 'Hello', 'driver' => 'mock', 'language' => null, 'duration' => 2,
        'segments' => [['text' => 'Hello', 'start' => 2, 'end' => 1]],
    ]);
    exit(1);
} catch (InvalidArgumentException) {
    echo "PASS: typed timestamps reject invalid segment bounds\n";
}
