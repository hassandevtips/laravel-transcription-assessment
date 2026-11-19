# Laravel Transcription Assessment

A small Laravel 13 API that accepts audio, processes it in a queue, and returns text with timestamps. It uses a focused, Laravel Beyond CRUD style DDD layout. Speech recognition runs locally with faster-whisper; no paid API key is needed.

## Requirements

- PHP 8.4.1+ with SQLite, fileinfo and pcntl (for queue worker timeouts), Composer
- Python 3.11 or 3.12 and FFmpeg (`ffmpeg` and `ffprobe` on PATH)
- Internet access on the first real run to download the selected Whisper model

The application is API-only. Node.js and a frontend build are not needed.

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Set a strong private token in `.env`:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Copy that value into `TRANSCRIPTION_API_TOKEN`. Keep `.env` private.

For a quick demo, set `TRANSCRIPTION_DRIVER=mock`. Mock mode still validates, converts and chunks real audio, but returns clearly labeled sample text. **It does not recognize speech.** It needs FFmpeg but no Python packages.

Run these in two terminals:

```bash
php artisan serve
php artisan queue:work --queue=transcriptions --tries=3 --timeout=3600 --sleep=1
```

For real speech recognition:

```bash
python3.12 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

Set these values in `.env`, then restart the queue worker:

```dotenv
TRANSCRIPTION_DRIVER=whisper
TRANSCRIPTION_PYTHON=/absolute/path/to/project/.venv/bin/python
TRANSCRIPTION_MODEL=base
```

`tiny` is faster for a first check. `base` is the default accuracy/speed compromise. The model runs on CPU with int8 precision. Model downloads and CPU processing can take time. Install FFmpeg using your OS package manager, for example `brew install ffmpeg` or `sudo apt-get install ffmpeg`.

## API examples

Replace `YOUR_TOKEN` with your configured token. The same token is used for every endpoint.

### Upload audio

```bash
curl -X POST http://127.0.0.1:8000/api/transcriptions \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json' \
  -F 'audio=@/absolute/path/to/voice.mp3'
```

Returns HTTP 202 with an ID and `queued` status. Store that ID to poll the result.

### Get status and transcript

```bash
curl http://127.0.0.1:8000/api/transcriptions/TRANSCRIPTION_ID \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

Example completed response (mock mode):

```json
{
  "id": "01900000-0000-7000-8000-000000000001",
  "status": "completed",
  "driver": "mock",
  "text": "Mock transcript for audio chunk 1.",
  "language": null,
  "duration": 2,
  "segments": [
    {"start": 0, "end": 2, "text": "Mock transcript for audio chunk 1."}
  ],
  "error": null
}
```

Start and end values are seconds from the beginning of the original recording. Empty audio/silence can produce no speech segments in real mode.

### Retry a failed transcription

```bash
curl -X POST http://127.0.0.1:8000/api/transcriptions/TRANSCRIPTION_ID/retry \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json'
```

Only a `failed` transcription may be manually retried. Other states return HTTP 409. Automatic retries happen first, stopping after three processing exceptions with increasing delays. A hard worker timeout is a final failure and can be manually retried.

| Response | Meaning |
| --- | --- |
| 202 | Upload or retry accepted |
| 200 | Current status and result |
| 401 | Missing or incorrect token |
| 404 | Unknown transcription |
| 409 | Retry is not allowed in this state |
| 422 | Invalid upload |
| 429 | Request limit reached |
| 503 | Service token has not been configured |

## Architecture and DDD

```text
src/
  Domain/Transcription/
    Actions/             Create, process, retry and fail a transcription
    Contracts/           AudioTranscriber port
    DataTransferObjects/ Typed transcript and segment data
    Enums/               TranscriptionStatus
    Events/              TranscriptionRequested, TranscriptionCompleted
    Exceptions/          RetryNotAllowed
    Models/              Transcription persistence and casts
  App/
    Http/                Controllers, Requests, Resources, Middlewares
    Jobs/                Thin queued job
    Listeners/           Queue work when the domain requests transcription
    Providers/           Container bindings and rate limits
  Support/Transcription/ Python process adapter
scripts/
  transcribe.py          Normalization, chunking and local Whisper bridge
```

This is pragmatic DDD using Eloquent, rather than an entirely framework-free domain. Actions own business workflows and state changes. Controllers handle HTTP; jobs handle delivery and retries; support classes handle external tools. Typed `DataTransferObjects` validate the bridge output. The domain depends on an `AudioTranscriber` interface, not a concrete Python implementation. No generic repository is added over Eloquent.

Flow: upload → private storage → create action → domain event → database queue → job → process action → transcriber port → Python/FFmpeg/Whisper → saved text and segments → completed event.

### Engineering decisions

- **Audio storage:** private local Laravel Storage, with generated filenames. Neither storage paths nor raw tool errors appear in API responses. Transcripts, timestamps, driver and status live in SQLite. MySQL/PostgreSQL can replace SQLite using normal Laravel configuration.
- **Concurrent uploads:** requests only store files and enqueue work. Database queues separate requests from expensive processing. Multiple workers can process different recordings; a shared cache lock prevents overlapping work for the same record. Start with one CPU worker and increase based on memory/CPU capacity.
- **Formats:** validate MIME and extension for WAV, MP3, M4A, FLAC and OGG. FFprobe checks duration; FFmpeg verifies decoding and converts to mono, 16 kHz PCM WAV. A renamed or corrupt file may pass MIME checks but will fail during processing rather than be treated as valid speech.
- **Long recordings:** limit uploads to 100 MB and recordings to one hour. Normalize once, then stream five-minute WAV chunks into one reused model instance. Add each chunk's actual sample offset to segment times. Temporary files are removed on normal success or exceptions.
- **Recovery:** statuses are `queued`, `processing`, `retrying`, `completed`, `failed`. Retry transient failures up to three processing exceptions; final failure exposes a safe message. Completed work is idempotent. Manual retry uses an atomic state change, so two retry requests cannot both enqueue new work. Lock contention releases a job for later instead of deleting it; these releases do not consume the exception limit. A stored four-hour retry deadline bounds recovery. After a hard crash, a new attempt may wait for the old 3660-second lock to expire.
- **Queue timing:** the subprocess has a 3500-second limit, the job a 3600-second limit, and database queue retry-after is 3660 seconds. This avoids the queue making a timed-out job available before the worker stops it.
- **API protection:** one configured bearer token and rate limits. This is a single-client assessment service; it does not implement user accounts or tenant isolation. Anyone holding the token can read its transcripts. Use HTTPS outside localhost.
- **Downstream use:** the saved JSON can be consumed through the status API; `TranscriptionCompleted` is the extension point for later indexing, summarization or notifications.

### Deliberate limits

Chunk boundaries can cut a word; overlapping chunks plus text deduplication would improve this. The model reloads per recording to keep the demo simple; a persistent inference service would improve throughput. SQLite is convenient for review but MySQL/PostgreSQL plus Redis/Horizon and object storage would suit larger deployments. Moving the database queue to an external broker needs an outbox to preserve atomic creation and enqueueing. Audio retention/deletion, per-user ownership and full observability are further production work.

An OS kill may leave a temporary directory; operational cleanup should remove old `transcription-*` temporary directories. The worker retries crashed jobs after the visibility timeout. A deployment supervisor should restart workers and should also restart them after configuration changes. Infrastructure errors are logged privately; do not publish logs or uploaded audio.

If uploads over your PHP defaults fail, increase `upload_max_filesize` to `100M`, `post_max_size` to `110M`, and your web server body limit accordingly. Duration is validated asynchronously, so an oversized-duration recording is accepted then eventually marked failed.

## Verification

The included verification report records automated checks and a real HTTP/database-queue smoke test in mock speech mode. Real Whisper inference was not executed during preparation because Python/model downloads were interrupted; it must be checked with a short speech recording after installing the dependencies. Mock mode is not an accuracy test.


```bash
php artisan test
vendor/bin/pint --test
python3 -m unittest discover -s scripts -p 'test_*.py'
```

The feature suite exercises authentication, MIME validation, private upload and dispatch, status lookup, retry conflicts, completion, idempotency and safe failure responses. Python tests run real FFmpeg MP3 conversion/chunking and verify timestamp offsets and corrupt input handling. Speech recognition is mocked in automated tests to avoid a model download and nondeterministic output.

## Short video walkthrough

1. Explain the upload → queue → transcription → result flow.
2. Show `src/Domain/Transcription/Actions` and the transcriber contract.
3. Show the thin controller/job and Python adapter.
4. Upload audio with curl, run a worker, and poll the completed result.
5. Point out timestamps, retry behavior, mock versus real mode, and the limits above.

## References

- [Laravel queues](https://laravel.com/docs/13.x/queues)
- [Laravel private file storage](https://laravel.com/docs/13.x/filesystem)
- [faster-whisper](https://github.com/SYSTRAN/faster-whisper)

## AI assistance

This implementation and documentation were prepared with AI assistance. Review and understand the code before presenting it; the video should explain your own understanding and decisions.
