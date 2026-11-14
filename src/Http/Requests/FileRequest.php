<?php

namespace Coding1688\Files\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileRequest extends FormRequest
{
    use FileRules;
}
