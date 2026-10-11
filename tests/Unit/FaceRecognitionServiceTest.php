<?php

namespace Tests\Unit;

use App\Services\FaceRecognitionService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FaceRecognitionServiceTest extends TestCase
{
    private FaceRecognitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FaceRecognitionService();
    }

    public function test_compare_returns_zero_for_identical_embeddings(): void
    {
        $embedding = [0.1, 0.2, 0.3, 0.4, 0.5];
        $distance = $this->service->compare($embedding, $embedding);

        $this->assertEqualsWithDelta(0.0, $distance, 0.00001);
    }

    public function test_compare_calculates_euclidean_distance_correctly(): void
    {
        // 3D vector: [0, 0, 0] and [3, 4, 0] -> distance should be 5.0
        $vectorA = [0.0, 0.0, 0.0];
        $vectorB = [3.0, 4.0, 0.0];

        $distance = $this->service->compare($vectorA, $vectorB);
        $this->assertEqualsWithDelta(5.0, $distance, 0.00001);
    }

    public function test_compare_throws_exception_when_dimensions_mismatch(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Biometric embeddings do not share the same dimensionality.');

        $this->service->compare([0.1, 0.2], [0.1, 0.2, 0.3]);
    }

    public function test_compare_throws_exception_when_embeddings_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Biometric embeddings are missing.');

        $this->service->compare([], []);
    }

    public function test_verification_result_passes_when_distance_is_within_threshold(): void
    {
        $vectorA = [0.1, 0.2, 0.3];
        $vectorB = [0.11, 0.21, 0.31];

        $result = $this->service->verificationResult($vectorA, $vectorB, 0.60);

        $this->assertTrue($result['verified']);
        $this->assertLessThanOrEqual(0.60, $result['distance']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_verification_result_fails_when_distance_exceeds_threshold(): void
    {
        $vectorA = [0.0, 0.0, 0.0];
        $vectorB = [0.8, 0.8, 0.8];

        $result = $this->service->verificationResult($vectorA, $vectorB, 0.60);

        $this->assertFalse($result['verified']);
        $this->assertGreaterThan(0.60, $result['distance']);
    }

    public function test_validate_image_rejects_non_image(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The submitted face image is not a valid image file.');

        $this->service->validateImage($file);
    }

    public function test_validate_image_rejects_image_smaller_than_minimum_dimensions(): void
    {
        $file = UploadedFile::fake()->image('tiny.jpg', 32, 32);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The face image dimensions must be between 64x64 and 6000x6000 pixels.');

        $this->service->validateImage($file);
    }

    public function test_validate_image_accepts_valid_dimensions_and_mime(): void
    {
        $file = UploadedFile::fake()->image('valid.jpg', 200, 200);

        // Should not throw any exception
        $this->service->validateImage($file);
        $this->assertTrue(true);
    }
}
