<?php

namespace App\Http\Requests\TaskManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitTaskRequest extends FormRequest
{
    /**
     * Allowed extensions and MIME types for regular submission attachments.
     */
    private const ALLOWED_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,zip';

    private const ALLOWED_MIMES = 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,image/jpeg,image/png,application/zip,application/x-zip-compressed';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $method = $this->input('method');

        $fileRules = ['file', 'max:20480'];

        if ($method === 'folder') {
            // A "folder" submission is either a ZIP archive or a shared URL.
            $fileRules = [
                'file',
                'max:51200',
                'mimes:zip',
                'mimetypes:application/zip,application/x-zip-compressed',
            ];
        } elseif ($method === 'upload') {
            $fileRules = [
                'file',
                'max:20480',
                'mimes:'.self::ALLOWED_EXTENSIONS,
                'mimetypes:'.self::ALLOWED_MIMES,
            ];
        }

        return [
            'method' => ['required', Rule::in(['upload', 'link', 'folder', 'written'])],

            'files' => ['exclude_unless:method,upload,folder', 'array', 'max:10'],
            'files.*' => $fileRules,

            'url' => ['exclude_unless:method,link,folder', 'nullable', 'string', 'max:2048', 'url:https'],
            'written_response' => ['exclude_unless:method,written', 'nullable', 'string', 'max:20000'],

            'notes' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Require the content that matches the selected submission method.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $method = $this->input('method');

                $missing = match ($method) {
                    'upload' => ! $this->hasFile('files'),
                    'link' => ! $this->filled('url'),
                    'folder' => ! $this->hasFile('files') && ! $this->filled('url'),
                    'written' => ! $this->filled('written_response'),
                    default => true,
                };

                if ($missing) {
                    $validator->errors()->add(
                        'method',
                        match ($method) {
                            'upload' => 'Upload at least one file before submitting.',
                            'link' => 'A valid HTTPS link is required for a link submission.',
                            'folder' => 'Upload a ZIP archive or provide a shared folder URL.',
                            'written' => 'A written response is required for a written submission.',
                            default => 'Select a valid submission method.',
                        },
                    );
                }
            },
        ];
    }
}
