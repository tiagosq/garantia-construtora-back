<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

use function PHPUnit\Framework\isEmpty;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'fullname',
        'phone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public static function roleCanBeAssociatedToUser(string $role, string $business = null) : bool
    {
        if (!auth()->user())
        {
            return false;
        }

        $userRoleWhereParams = [
            ['user', '=', auth()->user()->id],
            ['business', '=', $business]
        ];

        $userRole = UserRole::where($userRoleWhereParams)->first();

        if (empty($userRole))
        {
            return ($business != null ? User::roleCanBeAssociatedToUser($role) : false);
        }

        $roleUsed = Role::find($userRole->role);
        $associatedRole = Role::find($role);
        $roleResult = (!empty($role) && !empty($associatedRole) && $roleUsed->order <= $associatedRole->order);

        // If result is false, check if user has a management role
        $roleResult = ((!$roleResult && $business != null) ? User::roleCanBeAssociatedToUser($role) : $roleResult);

        return $roleResult;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get all of the userRoles for the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user', 'id');
    }
}
