# Laravel transcription assessment

Approved scope: implement the pipeline described in the form, with Laravel uploads, private storage, queued transcription, segment timestamps, format conversion, chunking and retry support. Deliver a Git repository and README; leave video and final submission to Hassan.

Use Laravel 13 / PHP 8.4+ for reviewer compatibility, SQLite and a database queue. A Python faster-whisper bridge normalizes audio to mono 16 kHz WAV using FFmpeg, splits into five-minute chunks and adds offsets to timestamps. A clearly labeled mock mode supports a quick demo. A single configured bearer token protects this small service; document that this is not multi-user authorization.

1. Scaffold an isolated Laravel project. Write API feature tests for validation, authentication, queue dispatch, status, completion and retry conflicts; run and observe missing endpoints fail.
2. Add migration/model, bearer middleware, API routes/controller, queued job and Python bridge. Run tests and address failures. Do not store uploaded client filenames in returned metadata.
3. Add Python tests for timestamp offsets and actual FFmpeg normalization/chunking. Run an HTTP upload-to-completion smoke test and lint PHP/Python.
4. Write README setup, API examples, design decisions, limits and verification evidence. Package code excluding .env, vendor, uploaded audio, databases and logs. Review generated source before publishing.
5. Publish the new assessment repository, enter its URL in the form and upload README. Confirm both are visible and leave the video and final Submit untouched.

## DDD refinement requested by Hassan

- `src/Domain/Transcription`: Eloquent model, status enum, typed DataTransferObjects, action classes, domain events and transcriber contract. Actions own transitions and workflows.
- `src/App`: HTTP controller/request/resource, bearer middleware, queued job and event listener. Controllers and jobs delegate to actions.
- `src/Support/Transcription`: process adapter implementing the domain contract; service provider binds it.
- Avoid generic repositories over Eloquent and empty architectural folders. The API uses one service token; it is a single-client assessment service.
- Tests run through real HTTP routes and action/job orchestration; Python tests run real FFmpeg conversion with clearly mocked speech recognition.

Runtime adjustment: use the locally available Laravel 13 dependency versions after repeated network timeouts. Only public dependency packages are reused; no application source from another project is copied. The committed lock file pins the complete dependency graph.
