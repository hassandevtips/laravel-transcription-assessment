import json
import subprocess
import sys
import tempfile
import unittest
import wave
from pathlib import Path


class PipelineTest(unittest.TestCase):
    def test_normalizes_mp3_chunks_and_offsets_timestamps(self):
        with tempfile.TemporaryDirectory() as directory:
            wav = Path(directory) / 'source.wav'
            with wave.open(str(wav), 'wb') as audio:
                audio.setnchannels(2)
                audio.setsampwidth(2)
                audio.setframerate(44100)
                audio.writeframes(b'\0' * 44100 * 4 * 3)
            mp3 = Path(directory) / 'source.mp3'
            subprocess.run(['ffmpeg', '-v', 'error', '-i', str(wav), str(mp3)], check=True)
            result = subprocess.run([sys.executable, str(Path(__file__).with_name('transcribe.py')),
                                     '--input', str(mp3), '--driver', 'mock', '--chunk-seconds', '1'],
                                    capture_output=True, text=True, check=True)
            data = json.loads(result.stdout)
            self.assertEqual(data['driver'], 'mock')
            self.assertEqual(len(data['segments']), 3)
            self.assertEqual([s['start'] for s in data['segments']], [0, 1, 2])
            self.assertEqual(data['segments'][-1]['end'], 3)
            self.assertIn('Mock', data['text'])

    def test_corrupt_audio_fails_without_json_success(self):
        with tempfile.NamedTemporaryFile(suffix='.wav') as audio:
            audio.write(b'invalid audio')
            audio.flush()
            result = subprocess.run([sys.executable, str(Path(__file__).with_name('transcribe.py')),
                                     '--input', audio.name, '--driver', 'mock'],
                                    capture_output=True, text=True)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(result.stdout, '')


if __name__ == '__main__':
    unittest.main()
