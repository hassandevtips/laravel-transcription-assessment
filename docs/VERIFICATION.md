# Verification report

Prepared on 19 November 2026.

- PHP feature/domain suite: 15 tests, 49 assertions passed.
- Python pipeline suite: 2 tests passed using actual FFmpeg normalization, MP3 decoding, chunking and timestamp offsets.
- Typed transcript contract check: passed.
- Laravel Pint: passed.
- Composer strict validation and locked dependency installation check: passed.
- Running HTTP API smoke check: multipart WAV upload returned 202/queued; a database queue worker completed it; GET returned completed/mock with a two-second timestamped segment.
- Independent review identified a silent job-loss risk with overlap locks. A regression test reproduced it, then passed after delayed release, a processing-exception limit and a stored retry deadline were added.
- Additional tests verify transaction rollback and stored-file cleanup, the real queue's three-exception failure path, duplicate retry rejection, oversized uploads and authentication before route binding.

PHP 8.5.12, Laravel 13.25.0 and Python 3.14 were used for local mock-mode checks. Runtime dependencies are pinned in composer.lock; PHP 8.4.1+ is required. The workflow checks PHP 8.4 and Python 3.12.

Real Whisper recognition was not executed: Python dependency/model downloads were interrupted by network timeouts. The real adapter is implemented, but speech accuracy and real model execution must be verified after installing requirements.txt. Automated results exercise mock recognition and must not be represented as a real speech-recognition benchmark.
