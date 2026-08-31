<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Validates and persists attachment files with their metadata.
 *
 * Files are always written to the named private disk. Metadata is only
 * persisted after the file write succeeds; a failed metadata write removes
 * the newly stored file to avoid orphaned private files.
 */
class AttachmentService
{
    public const PRIVATE_DISK = 'private';

    public const DEFAULT_MAX_SIZE_BYTES = 10 * 1024 * 1024;

    /** @var array<int, string> */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/csv',
        'text/plain',
    ];

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/csv' => 'csv',
        'text/plain' => 'txt',
    ];

    /**
     * @param  (Closure(Model, User): void)|null  $authorizationHook
     */
    public function __construct(private readonly ?Closure $authorizationHook = null) {}

    /**
     * Return the request-facing rules for the upload boundary.
     *
     * File::types() inspects file contents to determine MIME type; it does
     * not trust the client-provided filename extension. validateFile() also
     * confirms the detected bytes with finfo before any storage write.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types($this->allowedMimeTypes())->max($this->maxSizeKilobytes()),
            ],
        ];
    }

    /**
     * Store a validated upload and persist its private metadata.
     *
     * @throws ValidationException when the upload or collection is invalid
     */
    public function store(
        UploadedFile $file,
        Model $attachable,
        User $uploader,
        string $collection = 'default',
        array $metadata = [],
        ?array $allowedMimeTypes = null,
    ): Attachment {
        $this->assertPersisted($attachable, 'attachable');
        $this->assertPersisted($uploader, 'uploader');
        if ($this->authorizationHook !== null) {
            ($this->authorizationHook)($attachable, $uploader);
        }
        $mimeType = $this->validateFile($file, $allowedMimeTypes);
        $collection = $this->validateCollection($collection);
        $metadata = $this->validateMetadata($metadata);
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;
        $filename = Str::uuid()->toString().($extension === null ? '' : '.'.$extension);
        $directory = 'attachments/'.$collection.'/'.now()->format('Y/m');
        $disk = $this->disk();
        $storedPath = null;

        try {
            $storedPath = Storage::disk($disk)->putFileAs(
                $directory,
                $file,
                $filename,
                ['visibility' => 'private'],
            );

            if ($storedPath === false) {
                throw new RuntimeException('Unable to store attachment file.');
            }

            return Attachment::create([
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
                'uploader_id' => $uploader->getKey(),
                'path' => $storedPath,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'collection' => $collection,
                'disk' => $disk,
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                try {
                    Storage::disk($disk)->delete($storedPath);
                } catch (Throwable) {
                    // Preserve the metadata/storage exception as the primary error.
                }
            }

            throw $exception;
        }
    }

    /**
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    private function validateFile(UploadedFile $file, ?array $allowedMimeTypes = null): string
    {
        $allowedMimeTypes ??= $this->allowedMimeTypes();
        Validator::make(['file' => $file], [
            'file' => [
                'required',
                File::types($allowedMimeTypes)->max($this->maxSizeKilobytes()),
            ],
        ])->validate();

        $size = $file->getSize();
        if (! is_int($size) || $size > $this->maxSizeBytes()) {
            throw ValidationException::withMessages([
                'file' => 'The file exceeds the configured attachment size limit.',
            ]);
        }

        $mimeType = $this->detectMimeType($file);
        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file type is not allowed.',
            ]);
        }

        return $mimeType;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function validateMetadata(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || mb_strlen((string) $value) > 255) {
                throw ValidationException::withMessages([
                    'metadata' => 'Attachment metadata must contain short scalar values.',
                ]);
            }
        }

        return $metadata;
    }

    private function detectMimeType(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_readable($realPath)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file cannot be inspected.',
            ]);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        if (! is_string($mimeType) || $mimeType === '') {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file type cannot be determined.',
            ]);
        }

        return $mimeType;
    }

    private function validateCollection(string $collection): string
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,99}\z/', $collection) !== 1) {
            throw ValidationException::withMessages([
                'collection' => 'The attachment collection contains invalid characters.',
            ]);
        }

        return $collection;
    }

    private function maxSizeBytes(): int
    {
        return max(1, (int) config(
            'filesystems.attachments.max_size_bytes',
            self::DEFAULT_MAX_SIZE_BYTES,
        ));
    }

    private function maxSizeKilobytes(): int
    {
        return (int) ceil($this->maxSizeBytes() / 1024);
    }

    private function disk(): string
    {
        $disk = (string) config('filesystems.attachments.disk', self::PRIVATE_DISK);
        if ($disk !== self::PRIVATE_DISK) {
            throw new RuntimeException('Attachments must use the private disk.');
        }

        return $disk;
    }

    private function assertPersisted(Model $model, string $name): void
    {
        if ($model->getKey() === null) {
            throw new RuntimeException("The {$name} must be persisted before storing an attachment.");
        }
    }
}
