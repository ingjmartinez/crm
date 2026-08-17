<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavorito extends Model
{
    /** @use HasFactory<\Database\Factories\UserFavoritoFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'favorito_key', 'orden'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
