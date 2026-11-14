<?php

namespace Coding1688\Files\Http\Requests;

use Coding1688\Files\ModelResolver;
use Coding1688\Files\UploadConfiguration;
use Illuminate\Validation\Rule;

trait FileStorageAccountRules
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if ($action === 'saveSettings') {
            return [
                'default_disk' => ['nullable', 'string', Rule::in(app(UploadConfiguration::class)->enabledDisks())],
                'max_upload_mb' => ['required', 'integer', 'min:0', 'max:'.app(UploadConfiguration::class)->defaults()['limits']['hard_max_upload_mb']],
                'allowed_extensions' => ['present', 'array', 'max:128'],
                'allowed_extensions.*' => ['required', 'string', 'regex:/^[a-z0-9]{1,16}$/', 'distinct', Rule::notIn(app(UploadConfiguration::class)->defaults()['limits']['forbidden_extensions'])],
            ];
        }
        if ($action === 'sync') {
            return [
                'source_disk' => ['required', 'string', Rule::in(app(UploadConfiguration::class)->enabledDisks())],
                'target_disk' => ['required', 'string', 'different:source_disk', Rule::in(app(UploadConfiguration::class)->enabledDisks())],
            ];
        }
        if (! in_array($action, ['create', 'update'], true)) {
            return [];
        }

        return [
            'disk' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(ModelResolver::resolve('storage_account'), 'disk')->ignore($this->route('id'))],
            'name_i18n' => ['required', 'array:'.implode(',', config('files.management.locales', ['en', 'zh_CN', 'zh_TW', 'ja', 'ko']))],
            'name_i18n.en' => ['required', 'string', 'max:128'],
            'name_i18n.*' => ['nullable', 'string', 'max:128'],
            'provider' => ['required', Rule::in(['local', 'local_private', 'aws', 'cloudflare', 'aliyun', 'google', 'tencent', 's3_compatible'])],
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'enabled' => ['required', 'boolean'],
            'configuration' => ['required', 'array:directory,bucket,region,endpoint,url,use_path_style_endpoint'],
            'configuration.directory' => ['nullable', 'string', 'max:128', 'regex:#^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$#'],
            'configuration.bucket' => ['nullable', 'string', 'max:128', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'configuration.region' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'configuration.endpoint' => ['nullable', 'url:http,https', 'max:512'],
            'configuration.url' => ['nullable', 'url:http,https', 'max:512'],
            'configuration.use_path_style_endpoint' => ['sometimes', 'boolean'],
            'access_key' => ['nullable', 'string', 'max:512'],
            'secret_key' => ['nullable', 'string', 'max:512'],
        ];
    }
}
