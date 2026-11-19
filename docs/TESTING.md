# Test strategy

API feature tests use SQLite in memory, Laravel's private storage fake, and a queue fake only when checking dispatch. The completion test runs the real job, actions, subprocess bridge and FFmpeg against a generated PCM WAV. Speech recognition is explicitly mocked, so no model download is needed.

Python tests generate stereo audio, encode it as MP3 with real FFmpeg, then normalize and split it through the bridge. They check absolute timestamps and corrupted input failure. Domain DTO tests check invalid timestamps. Recovery tests inject a failing transcriber through the domain port and verify the retriable state.

Run `php artisan test`, `vendor/bin/pint --test`, `php scripts/check_contract.php`, and `python3 -m unittest discover -s scripts -p 'test_*.py'`.

A manual HTTP smoke test should use a real multipart upload, the database queue worker, and a subsequent GET. In real mode, use a short speech recording and check the returned words by listening. Do not mistake mock output for evidence of speech recognition accuracy.
