"""Local speech-to-text bridge. Only the final result is written to stdout."""
import argparse
import json
import subprocess
import sys
import tempfile
import wave
from pathlib import Path


def run(args):
    # Argument arrays avoid shell injection from uploaded paths.
    probe = subprocess.run(
        [args.ffprobe, '-v', 'error', '-show_entries', 'format=duration',
         '-of', 'default=noprint_wrappers=1:nokey=1', args.input],
        check=True, capture_output=True, text=True, timeout=30,
    )
    duration = float(probe.stdout.strip())
    if not 0 < duration <= 3600:
        raise ValueError('Audio must be between zero and 3600 seconds.')
    model = None
    if args.driver == 'whisper':
        from faster_whisper import WhisperModel
        model = WhisperModel(args.model, device='cpu', compute_type='int8')

    segments = []
    language = None
    with tempfile.TemporaryDirectory(prefix='transcription-') as directory:
        normalized = Path(directory) / 'normalized.wav'
        subprocess.run(
            [args.ffmpeg, '-nostdin', '-v', 'error', '-i', args.input, '-vn', '-ac', '1',
             '-ar', '16000', '-t', '3600.001', '-c:a', 'pcm_s16le', str(normalized)],
            check=True, capture_output=True, timeout=180,
        )
        with wave.open(str(normalized), 'rb') as source:
            rate = source.getframerate()
            if source.getnframes() / rate > 3600:
                raise ValueError('Decoded audio exceeds 3600 seconds.')
            offset_frames = 0
            chunk_number = 0
            while frames := source.readframes(rate * args.chunk_seconds):
                chunk_number += 1
                offset = offset_frames / rate
                chunk_duration = len(frames) / (source.getsampwidth() * rate)
                chunk = Path(directory) / 'chunk.wav'
                with wave.open(str(chunk), 'wb') as target:
                    target.setparams(source.getparams())
                    target.writeframes(frames)
                if model is None:
                    found = [{'start': 0, 'end': chunk_duration,
                              'text': f'Mock transcript for audio chunk {chunk_number}.'}]
                else:
                    found_segments, info = model.transcribe(str(chunk), beam_size=5, vad_filter=True)
                    language = language or info.language
                    found = [{'start': s.start, 'end': s.end, 'text': s.text.strip()}
                             for s in found_segments]
                for item in found:
                    if item['text']:
                        start = min(chunk_duration, max(0, float(item['start'])))
                        end = min(chunk_duration, max(start, float(item['end'])))
                        segments.append({'start': round(offset + start, 3),
                                         'end': round(offset + end, 3), 'text': item['text']})
                offset_frames += len(frames) // source.getsampwidth()
    return {'driver': args.driver, 'language': language,
            'duration': round(offset_frames / rate, 3),
            'text': ' '.join(s['text'] for s in segments), 'segments': segments}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', required=True)
    parser.add_argument('--driver', choices=['whisper', 'mock'], default='whisper')
    parser.add_argument('--model', default='base')
    parser.add_argument('--ffmpeg', default='ffmpeg')
    parser.add_argument('--ffprobe', default='ffprobe')
    parser.add_argument('--chunk-seconds', type=int, default=300)
    args = parser.parse_args()
    if not 1 <= args.chunk_seconds <= 300:
        parser.error('chunk-seconds must be between 1 and 300')
    try:
        print(json.dumps(run(args)))
    except Exception as error:
        print(f'Transcription failed: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
