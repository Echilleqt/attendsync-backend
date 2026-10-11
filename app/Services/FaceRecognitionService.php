<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FaceRecognitionService
{
    public function isConfigured(): bool
    {
        return (bool) config('face-recognition.enabled', true);
    }

    public function getModelVersion(): string
    {
        return config('face-recognition.model_version', 'dlib_face_recognition_v1');
    }

    public function generateEmbedding(string $imagePath): array
    {
        $this->ensureWorkerIsAvailable();

        $pythonBinary = config('face-recognition.python_binary', 'python');
        $workerPath = base_path('scripts/face_recognition_worker.py');

        if (!is_file($workerPath)) {
            throw new RuntimeException('Face recognition worker script is missing.');
        }

        $command = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg($pythonBinary),
            escapeshellarg($workerPath),
            escapeshellarg($imagePath)
        );

        $output = [];
        exec($command, $output, $exitCode);
        $response = trim(implode("\n", $output));

        // Locate JSON response in case libraries or Python printed warning lines
        $decoded = null;
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                $candidate = json_decode($line, true);
                if (is_array($candidate)) {
                    $decoded = $candidate;
                    break;
                }
            }
        }

        if (!is_array($decoded)) {
            $decoded = json_decode($response, true);
        }

        if (!is_array($decoded)) {
            if ($exitCode !== 0 || $response === '') {
                throw new RuntimeException('Face recognition backend failed to process the image.');
            }
            throw new RuntimeException('Face recognition backend returned an invalid response.');
        }

        if (($decoded['ok'] ?? false) !== true) {
            $message = $decoded['error'] ?? 'Face recognition could not process the submitted image.';

            if (str_contains(strtolower($message), 'no face')) {
                throw new RuntimeException('No face was detected in the submitted image.');
            }

            if (str_contains(strtolower($message), 'multiple')) {
                throw new RuntimeException('Multiple faces were detected. Please submit an image with exactly one face.');
            }

            if (str_contains(strtolower($message), 'quality') || str_contains(strtolower($message), 'low')) {
                throw new RuntimeException('The submitted face image is invalid or too low-quality to verify.');
            }

            throw new RuntimeException($message);
        }

        $embedding = $decoded['embedding'] ?? null;

        if (!is_array($embedding) || count($embedding) === 0) {
            throw new RuntimeException('The requested face image did not produce a valid biometric embedding.');
        }

        return array_map('floatval', $embedding);
    }

    public function compare(array $storedEmbedding, array $candidateEmbedding): float
    {
        if ($storedEmbedding === [] || $candidateEmbedding === []) {
            throw new RuntimeException('Biometric embeddings are missing.');
        }

        if (count($storedEmbedding) !== count($candidateEmbedding)) {
            throw new RuntimeException('Biometric embeddings do not share the same dimensionality.');
        }

        $sum = 0.0;

        foreach ($storedEmbedding as $index => $value) {
            $delta = (float) $value - (float) ($candidateEmbedding[$index] ?? 0.0);
            $sum += $delta * $delta;
        }

        return sqrt($sum);
    }

    public function verificationResult(array $storedEmbedding, array $candidateEmbedding, ?float $threshold = null): array
    {
        $threshold = $threshold ?? (float) config('face-recognition.threshold', 0.60);
        $distance = $this->compare($storedEmbedding, $candidateEmbedding);
        $confidence = max(0.0, min(1.0, 1.0 - $distance));

        return [
            'distance' => round($distance, 6),
            'confidence' => round($confidence, 6),
            'verified' => $distance <= $threshold,
            'threshold' => $threshold,
        ];
    }

    public function validateImage(UploadedFile $file): void
    {
        $pathname = $file->getPathname();

        if (!is_file($pathname) || filesize($pathname) < 8) {
            throw new RuntimeException('The submitted face image is not a valid image file.');
        }

        $handle = @fopen($pathname, 'rb');
        $header = $handle ? fread($handle, 8) : '';
        if ($handle) {
            fclose($handle);
        }

        $isJpeg = str_starts_with($header, "\xFF\xD8\xFF");
        $isPng = str_starts_with($header, "\x89PNG\r\n\x1a\n");

        if (!$isJpeg && !$isPng) {
            throw new RuntimeException('The submitted face image is not a valid image file.');
        }

        $imageInfo = @getimagesize($pathname);

        if (!$imageInfo) {
            throw new RuntimeException('The submitted face image is not a valid image file.');
        }

        $mime = $file->getMimeType();
        $expected = $imageInfo['mime'] ?? null;

        if (!in_array($expected, ['image/jpeg', 'image/png'], true) || $mime !== $expected) {
            throw new RuntimeException('The face image must be a valid JPEG or PNG file.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width < 64 || $height < 64 || $width > 6000 || $height > 6000) {
            throw new RuntimeException('The face image dimensions must be between 64x64 and 6000x6000 pixels.');
        }
    }

    private function ensureWorkerIsAvailable(): void
    {
        if (!is_file(base_path('scripts/face_recognition_worker.py'))) {
            throw new RuntimeException('The face recognition worker script is missing.');
        }

        $pythonBinary = config('face-recognition.python_binary', 'python');

        $command = sprintf('%s --version 2>&1', escapeshellarg($pythonBinary));
        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('The configured Python runtime for face recognition is not available.');
        }

        if (!is_array($output) || $output === []) {
            Log::warning('Face recognition Python runtime returned no output while checking availability.');
        }
    }
}
