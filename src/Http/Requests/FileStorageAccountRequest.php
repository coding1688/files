<?php

namespace Coding1688\Files\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileStorageAccountRequest extends FormRequest
{
    use FileStorageAccountRules;
}
