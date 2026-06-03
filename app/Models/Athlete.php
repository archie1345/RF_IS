<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Athlete extends Model{
    use SoftDeletes, HasFactory;
    protected $table = 'athletes';

    protected $primaryKey = 'athlete_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'height_cm',
        'weight_kg',
        'nik_hash',
        'nik_ciphertext',
        'bpjs_hash',
        'bpjs_ciphertext',
        'alamat',
        'geup',
        'id',
        'group_id',
        'parent_id',
        'branch_id',
    ];

    protected $hidden = [
        'nik_hash',
        'bpjs_hash',
    ];

    protected $dates = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'nik_ciphertext' => 'encrypted',
            'bpjs_ciphertext' => 'encrypted',
        ];
    }

    public function displayValue(string $column): string
    {
        return $this->sensitiveIdentifier($column, $column . '_hash');
    }

    private function sensitiveIdentifier(string $ciphertextColumn, string $hashColumn): string
    {
        if (blank($this->getRawOriginal($ciphertextColumn))) {
            return filled($this->getRawOriginal($hashColumn)) ? 'Stored as hash only' : 'Not stored';
        }

        try {
            return (string) $this->getAttribute($ciphertextColumn);
        } catch (DecryptException) {
            return 'Stored, cannot decrypt';
        }
    }

    public function group()
    {
        return $this->belongsTo(Group::class,'group_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class,'branch_id');
    }

    public function parent()
    {
        return $this->belongsTo(Parents::class,'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'id');
    }
}
