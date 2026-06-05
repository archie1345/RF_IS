<?php

namespace App\Http\Requests\Profiles;

use App\Support\Profile\ProfileFormRules;
use Illuminate\Foundation\Http\FormRequest;

class SaveUserAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(ProfileFormRules::class)->achievement();
    }
}
