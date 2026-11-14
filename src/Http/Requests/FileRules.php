<?php

namespace Coding1688\Files\Http\Requests;

use Coding1688\Files\Constants\FileType;
use Coding1688\Files\UploadConfiguration;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait FileRules
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if ($action === 'list') {
            return [
                'q' => ['nullable', 'string', 'max:128'],
                'disk' => ['nullable', 'string', Rule::in(array_keys(app(UploadConfiguration::class)->availableProviders()))],
                'type' => ['nullable', 'string', Rule::in(FileType::values())],
                'mime' => ['nullable', 'string', 'max:128'],
                'page' => ['nullable', 'integer', 'min:1'],
            ];
        }
        if ($action !== 'upload') {
            return [];
        }
        $uploads = app(UploadConfiguration::class);

        return [
            'files' => ['required', 'array', 'min:1', 'max:16'],
            'files.*' => $uploads->fileRules(),
            'disk' => ['required', 'string', Rule::in($uploads->enabledDisks())],
            'type' => ['required', 'string', Rule::in(FileType::values())],
            'visibility' => ['required', Rule::in(['public', 'private'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->route()->getActionMethod() !== 'upload') {
            return;
        }
        $validator->after(function (Validator $validator): void {
            $disk = $this->input('disk');
            $visibilities = is_string($disk) ? (app(UploadConfiguration::class)->providers()[$disk]['visibilities'] ?? []) : [];
            if (! in_array($this->input('visibility'), $visibilities, true)) {
                $validator->errors()->add('visibility', __('validation.in', ['attribute' => 'visibility']));
            }
        });
    }
}
